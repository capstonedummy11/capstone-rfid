<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Device;
use App\Models\Students;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BorrowController
{
  /**
   * Display a listing of the resource.
   */
  public function index(Request $request)
  {
    $filters = [
      'search' => trim((string) $request->input('search', '')),
      'status' => trim((string) $request->input('status', '')),
      'perPage' => (int) $request->input('perPage', 10),
    ];

    if (!in_array($filters['perPage'], [5, 10, 20, 50], true)) {
      $filters['perPage'] = 10;
    }

    return Inertia::render('Borrow', [
      'title' => 'Borrowing',
      'borrowRows' => $this->buildBorrowRows($filters),
      'borrowerProfiles' => $this->buildBorrowerProfiles(),
      'borrowItemsCatalog' => $this->buildBorrowItemsCatalog(),
      'borrowItemsByRfid' => $this->buildBorrowItemsByRfid(),
      'dashboardStats' => $this->countDashboardBorrowing(),
      'filters' => $filters,
    ]);
  }

  public function returnItems(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'rfid' => ['required', 'string', 'max:255'],
      'barcodes' => ['required', 'array', 'min:1'],
      'barcodes.*' => ['required', 'string', 'max:255'],
    ]);

    $rfid = strtolower(trim((string) $validated['rfid']));
    $requestedBarcodes = collect($validated['barcodes'])
      ->map(fn($barcode) => trim((string) $barcode))
      ->filter(fn($barcode) => $barcode !== '')
      ->unique()
      ->values();

    if ($requestedBarcodes->isEmpty()) {
      return response()->json([
        'ok' => false,
        'message' => 'No valid barcodes were provided.',
      ], 422);
    }

    $student = Students::query()
      ->whereRaw('LOWER(rfid_tag) = ?', [$rfid])
      ->first();

    $instructor = null;
    if (!$student) {
      $instructor = User::query()
        ->whereRaw('LOWER(rfid_tag) = ?', [$rfid])
        ->first();
    }

    if (!$student && !$instructor) {
      return response()->json([
        'ok' => false,
        'message' => 'No borrower profile was found for this RFID.',
      ], 404);
    }

    $borrowerType = $student ? 'student' : 'instructor';
    $borrowerStudentId = $student?->student_id;
    $borrowerUserId = $instructor?->user_id;

    $borrowings = Borrowing::query()
      ->with(['items.item'])
      ->whereIn('status', ['active', 'overdue'])
      ->where('borrower_type', $borrowerType)
      ->when($borrowerType === 'student', function ($query) use ($borrowerStudentId) {
        $query->where('student_id', $borrowerStudentId);
      })
      ->when($borrowerType === 'instructor', function ($query) use ($borrowerUserId) {
        $query->where('user_id', $borrowerUserId);
      })
      ->get();

    $activeBorrowedByBarcode = [];
    foreach ($borrowings as $borrowing) {
      foreach ($borrowing->items as $borrowItem) {
        $barcode = trim((string) ($borrowItem->item?->barcode ?? ''));
        if ($barcode === '') {
          continue;
        }

        if ((string) $borrowItem->status === 'borrowed') {
          $activeBorrowedByBarcode[$barcode] = [
            'borrowing_id' => $borrowing->borrowing_id,
            'borrowing_item_id' => $borrowItem->id,
            'item_id' => $borrowItem->item_id,
          ];
        }
      }
    }

    $returnedBarcodes = [];
    $borrowedBarcodes = [];
    $missingBarcodes = [];
    $unavailableBarcodes = [];
    $touchedBorrowingIds = [];

    DB::transaction(function () use ($requestedBarcodes, $activeBorrowedByBarcode, $borrowerType, $borrowerStudentId, $borrowerUserId, &$returnedBarcodes, &$borrowedBarcodes, &$missingBarcodes, &$unavailableBarcodes, &$touchedBorrowingIds) {
      $activeBorrowing = null;

      foreach ($requestedBarcodes as $barcode) {
        if (isset($activeBorrowedByBarcode[$barcode])) {
          $record = $activeBorrowedByBarcode[$barcode];

          BorrowingItem::query()
            ->where('id', $record['borrowing_item_id'])
            ->update(['status' => 'returned']);

          Device::query()
            ->where('item_id', $record['item_id'])
            ->update(['status' => 'available']);

          $returnedBarcodes[] = $barcode;
          $touchedBorrowingIds[$record['borrowing_id']] = $record['borrowing_id'];
          continue;
        }

        $device = Device::query()->where('barcode', $barcode)->first();
        if (!$device) {
          $missingBarcodes[] = $barcode;
          continue;
        }

        if (strtolower((string) ($device->status ?? '')) !== 'available') {
          $unavailableBarcodes[] = $barcode;
          continue;
        }

        if (!$activeBorrowing) {
          $activeBorrowing = Borrowing::query()
            ->where('borrower_type', $borrowerType)
            ->when($borrowerType === 'student', function ($query) use ($borrowerStudentId) {
              $query->where('student_id', $borrowerStudentId);
            })
            ->when($borrowerType === 'instructor', function ($query) use ($borrowerUserId) {
              $query->where('user_id', $borrowerUserId);
            })
            ->whereIn('status', ['active', 'overdue'])
            ->latest('borrowing_id')
            ->first();

          if (!$activeBorrowing) {
            $activeBorrowing = Borrowing::query()->create([
              'student_id' => $borrowerType === 'student' ? $borrowerStudentId : null,
              'user_id' => $borrowerType === 'instructor' ? $borrowerUserId : null,
              'borrower_type' => $borrowerType,
              'borrowed_at' => now(),
              'returned_at' => null,
              'status' => 'active',
            ]);
          } elseif ($activeBorrowing->status !== 'active') {
            Borrowing::query()
              ->where('borrowing_id', $activeBorrowing->borrowing_id)
              ->update([
                'status' => 'active',
                'returned_at' => null,
              ]);
          }
        }

        BorrowingItem::query()->create([
          'borrowing_id' => $activeBorrowing->borrowing_id,
          'item_id' => $device->item_id,
          'quantity' => 1,
          'status' => 'borrowed',
        ]);

        $device->update(['status' => 'borrowed']);

        $borrowedBarcodes[] = $barcode;
        $touchedBorrowingIds[$activeBorrowing->borrowing_id] = $activeBorrowing->borrowing_id;
      }

      foreach ($touchedBorrowingIds as $borrowingId) {
        $hasBorrowedItems = BorrowingItem::query()
          ->where('borrowing_id', $borrowingId)
          ->where('status', 'borrowed')
          ->exists();

        if ($hasBorrowedItems) {
          Borrowing::query()
            ->where('borrowing_id', $borrowingId)
            ->update([
              'status' => 'active',
              'returned_at' => null,
            ]);
        } else {
          Borrowing::query()
            ->where('borrowing_id', $borrowingId)
            ->update([
              'status' => 'returned',
              'returned_at' => now(),
            ]);
        }
      }
    });

    $returnedUnique = collect($returnedBarcodes)->unique()->values()->all();
    $borrowedUnique = collect($borrowedBarcodes)->unique()->values()->all();
    $missingUnique = collect($missingBarcodes)->unique()->values()->all();
    $unavailableUnique = collect($unavailableBarcodes)->unique()->values()->all();

    if (count($returnedUnique) === 0 && count($borrowedUnique) === 0) {
      return response()->json([
        'ok' => false,
        'message' => 'No items were updated. Check barcode validity or item availability.',
        'missing_barcodes' => $missingUnique,
        'unavailable_barcodes' => $unavailableUnique,
      ], 422);
    }

    $summary = [];
    if (count($returnedUnique) > 0) {
      $summary[] = count($returnedUnique) . ' returned';
    }
    if (count($borrowedUnique) > 0) {
      $summary[] = count($borrowedUnique) . ' borrowed again';
    }

    return response()->json([
      'ok' => true,
      'message' => 'Items updated: ' . implode(', ', $summary) . '.',
      'returned_barcodes' => $returnedUnique,
      'borrowed_barcodes' => $borrowedUnique,
      'missing_barcodes' => $missingUnique,
      'unavailable_barcodes' => $unavailableUnique,
    ]);
  }

  private function buildBorrowRows(array $filters)
  {
    $statusMap = $this->statusMap();

    $query = Borrowing::with(['student.course', 'student.section', 'instructor', 'items.item'])
      ->when($filters['search'] !== '', function ($query) use ($filters) {
        $term = $filters['search'];
        $query->where(function ($subQuery) use ($term) {
          $subQuery
            ->whereHas('student', function ($studentQuery) use ($term) {
              $studentQuery
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('student_number', 'like', "%{$term}%")
                ->orWhere('rfid_tag', 'like', "%{$term}%");
            })
            ->orWhereHas('instructor', function ($userQuery) use ($term) {
              $userQuery
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('rfid_tag', 'like', "%{$term}%");
            })
            ->orWhereHas('items.item', function ($itemQuery) use ($term) {
              $itemQuery
                ->where('item_name', 'like', "%{$term}%")
                ->orWhere('item_code', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
            });
        });
      })
      ->when($filters['status'] !== '', function ($query) use ($filters) {
        $query->where('status', $filters['status']);
      })
      ->orderByDesc('borrowed_at')
      ->paginate($filters['perPage'])
      ->withQueryString()
      ->through(function (Borrowing $borrowing) use ($statusMap) {
        $isStudent = $borrowing->borrower_type === 'student';

        $borrowerName = $isStudent
          ? trim(($borrowing->student->first_name ?? '') . ' ' . ($borrowing->student->last_name ?? ''))
          : ($borrowing->instructor->name ?? 'Unknown Instructor');

        $borrowerId = $isStudent
          ? ($borrowing->student->student_number ?? 'N/A')
          : ('INS-' . ($borrowing->instructor->user_id ?? 'N/A'));

        $itemNames = $borrowing->items
          ->map(fn($item) => $item->item->item_name ?? null)
          ->filter()
          ->values();

        $itemPreview = $itemNames->first() ?? 'No item';
        if ($itemNames->count() > 1) {
          $itemPreview .= ' +' . ($itemNames->count() - 1);
        }

        $timeIn = $borrowing->borrowed_at;
        $timeOut = $borrowing->returned_at;
        $endTime = $timeOut ?? now();
        $totalMinutes = $timeIn ? $timeIn->diffInMinutes($endTime) : 0;
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";

        $allItems = $borrowing->items->map(function ($bi) {
          return [
            'name' => $bi->item->item_name ?? 'Unknown Item',
            'code' => $bi->item->item_code ?? 'N/A',
            'type' => $bi->item->item_type ?? 'N/A',
            'barcode' => $bi->item->barcode ?? 'N/A',
            'quantity' => (int) $bi->quantity,
            'status' => ucfirst((string) ($bi->status ?? 'borrowed')),
            'borrowedAt' => $bi->created_at ? $bi->created_at->format('Y-m-d H:i:s') : null,
            'returnedAt' => ($bi->status === 'returned') ? ($bi->updated_at ? $bi->updated_at->format('Y-m-d H:i:s') : null) : null,
          ];
        })->values()->toArray();

        return [
          'name' => $borrowerName !== '' ? $borrowerName : 'Unknown Borrower',
          'id' => $borrowerId,
          'role' => $isStudent ? 'Student' : 'Teacher',
          'course' => $isStudent ? ($borrowing->student->course->course_code ?? 'N/A') : 'Faculty',
          'section' => $isStudent ? ($borrowing->student->section->section_name ?? 'N/A') : 'N/A',
          'year' => $isStudent ? $this->formatYearLabel((int) ($borrowing->student->year_level ?? 0)) : 'N/A',
          'rfid' => $isStudent ? ($borrowing->student->rfid_tag ?? 'N/A') : ($borrowing->instructor->rfid_tag ?? 'N/A'),
          'remarks' => $borrowing->remarks ?? '',
          'item' => $itemPreview,
          'items' => $allItems,
          'number' => (int) $borrowing->items->sum('quantity'),
          'date' => $timeIn ? $timeIn->format('d F Y') : 'N/A',
          'status' => $statusMap[$borrowing->status] ?? 'Under Maintenance',
          'timeIn' => $timeIn ? $timeIn->format('H:i') : '00:00',
          'timeOut' => $timeOut ? $timeOut->format('H:i') : '00:00',
          'borrowedAt' => $timeIn ? $timeIn->format('Y-m-d H:i:s') : null,
          'returnedAt' => $timeOut ? $timeOut->format('Y-m-d H:i:s') : null,
          'hours' => $duration,
        ];
      })
      ->toArray();

    return $query;
  }

  private function buildBorrowerProfiles()
  {
    $studentBorrowers = Students::query()
      ->leftJoin('courses', 'students.course_id', '=', 'courses.course_id')
      ->leftJoin('sections', 'students.section_id', '=', 'sections.section_id')
      ->whereNotNull('students.rfid_tag')
      ->select([
        'students.rfid_tag as rfid',
        'students.student_number as studentId',
        'students.first_name',
        'students.last_name',
        'students.year_level',
        'courses.course_code as course',
        'sections.section_name as section',
      ])
      ->get()
      ->map(function ($student) {
        return [
          'rfid' => (string) $student->rfid,
          'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
          'studentId' => $student->studentId ?? 'N/A',
          'course' => $student->course ?? 'N/A',
          'year' => $this->formatYearLabel((int) ($student->year_level ?? 0)),
          'section' => $student->section ?? 'N/A',
          'role' => 'Student',
        ];
      });

    $userBorrowers = User::query()
      ->whereNotNull('rfid_tag')
      ->select(['user_id', 'name', 'role', 'rfid_tag'])
      ->get()
      ->map(function ($user) {
        return [
          'rfid' => (string) $user->rfid_tag,
          'name' => $user->name ?? 'Unknown User',
          'studentId' => 'INS-' . ($user->user_id ?? 'N/A'),
          'course' => 'Faculty',
          'year' => 'N/A',
          'section' => 'N/A',
          'role' => ucfirst((string) ($user->role ?? 'Instructor')),
        ];
      });

    return $studentBorrowers
      ->concat($userBorrowers)
      ->unique('rfid')
      ->values();
  }

  private function buildBorrowItemsCatalog()
  {
    return Device::query()
      ->whereNotNull('barcode')
      ->select(['item_name', 'item_code', 'item_type', 'barcode'])
      ->orderBy('item_code')
      ->get()
      ->map(function ($device) {
        return [
          'name' => $device->item_name,
          'id' => $device->item_code,
          'type' => $device->item_type,
          'barcode' => (string) $device->barcode,
        ];
      })
      ->values();
  }

  private function buildBorrowItemsByRfid(): array
  {
    $map = [];

    // Load ALL borrowings so that returned/closed ones can be shown as read-only history.
    $borrowings = Borrowing::with(['student', 'instructor', 'items.item'])
      ->orderByDesc('borrowed_at')
      ->orderByDesc('borrowing_id')
      ->get();

    foreach ($borrowings as $borrowing) {
      $rfid = null;

      if ($borrowing->borrower_type === 'student') {
        $rfid = $borrowing->student->rfid_tag ?? null;
      } else {
        $rfid = $borrowing->instructor->rfid_tag ?? null;
      }

      $rfidKey = strtolower(trim((string) $rfid));
      if ($rfidKey === '') {
        continue;
      }

      if (!isset($map[$rfidKey])) {
        $map[$rfidKey] = [
          'hasActiveBorrowing' => false,
          'items' => [],
        ];
      }

      $borrowingStatus = strtolower((string) ($borrowing->status ?? 'active'));
      $isActiveBorrowing = in_array($borrowingStatus, ['active', 'overdue'], true);

      if ($isActiveBorrowing) {
        $map[$rfidKey]['hasActiveBorrowing'] = true;
      }

      $borrowedAt = $borrowing->borrowed_at?->format('Y-m-d H:i:s');
      $borrowingReturnedAt = $borrowing->returned_at?->format('Y-m-d H:i:s');

      foreach ($borrowing->items as $item) {
        $borrowedItem = $item->item;
        if (!$borrowedItem || empty($borrowedItem->barcode)) {
          continue;
        }

        $barcode = (string) $borrowedItem->barcode;

        // If an active-borrowing entry already exists for this barcode, keep it.
        if (isset($map[$rfidKey]['items'][$barcode])) {
          $existingIsActive = in_array(
            strtolower($map[$rfidKey]['items'][$barcode]['borrowingStatus'] ?? ''),
            ['active', 'overdue'],
            true,
          );
          if ($existingIsActive) {
            continue;
          }
        }

        $itemStatus = strtolower((string) ($item->status ?? 'borrowed'));
        $statusLabel = match ($itemStatus) {
          'returned' => 'Returned',
          'borrowed' => 'Borrowed',
          'damaged' => 'Damaged',
          'lost' => 'Lost',
          default => ucfirst($itemStatus),
        };

        // Use item's updated_at as returnedAt when the item was returned.
        $itemReturnedAt = null;
        if ($itemStatus === 'returned') {
          $itemReturnedAt = $item->updated_at?->format('Y-m-d H:i:s') ?? $borrowingReturnedAt;
        }

        $map[$rfidKey]['items'][$barcode] = [
          'name' => $borrowedItem->item_name,
          'id' => $borrowedItem->item_code,
          'type' => $borrowedItem->item_type,
          'barcode' => $barcode,
          'status' => $statusLabel,
          'borrowingStatus' => $borrowingStatus,
          'borrowedAt' => $borrowedAt,
          'returnedAt' => $itemReturnedAt,
        ];
      }
    }

    foreach ($map as $key => $data) {
      $map[$key]['items'] = array_values($data['items']);
    }

    return $map;
  }

  private function countDashboardBorrowing(): array
  {
    // count dashboard borrowing
    $counts = Borrowing::query()
      ->selectRaw('status, COUNT(*) as total')
      ->groupBy('status')
      ->pluck('total', 'status');

    $borrowed = (int) ($counts['active'] ?? 0);
    $overdue = (int) ($counts['overdue'] ?? 0);
    $returned = (int) ($counts['returned'] ?? 0);
    $totalRecords = (int) Borrowing::query()->count();

    return [
      [
        'label' => 'Borrowed',
        'value' => $borrowed,
        'color' => 'text-emerald-500',
        'bg' => 'bg-emerald-50',
        'icon' => '📦',
      ],
      [
        'label' => 'Overdue',
        'value' => $overdue,
        'color' => 'text-red-500',
        'bg' => 'bg-red-50',
        'icon' => '⏰',
      ],
      [
        'label' => 'Returned',
        'value' => $returned,
        'color' => 'text-sky-500',
        'bg' => 'bg-sky-50',
        'icon' => '↩️',
      ],
      [
        'label' => 'Total Records',
        'value' => $totalRecords,
        'color' => 'text-slate-500',
        'bg' => 'bg-slate-100',
        'icon' => '🧾',
      ],
    ];
  }

  private function statusMap(): array
  {
    return [
      'active' => 'Borrowed',
      'returned' => 'Returned',
      'overdue' => 'Overdue',
      'damaged' => 'Damaged',
      'lost' => 'Under Maintenance',
    ];
  }

  private function formatYearLabel(int $yearLevel): string
  {
    if ($yearLevel <= 0) {
      return 'N/A';
    }

    $suffix = match ($yearLevel % 100) {
      11, 12, 13 => 'th',
      default => match ($yearLevel % 10) {
          1 => 'st',
          2 => 'nd',
          3 => 'rd',
          default => 'th',
        },
    };

    return $yearLevel . $suffix . ' Year';
  }

  /**
   * Show the form for creating a new resource.
   */
  public function create()
  {

  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {

  }

  /**
   * Display the specified resource.
   */
  public function show(string $id)
  {

  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(string $id)
  {

  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, string $id)
  {

  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(string $id)
  {

  }
}
