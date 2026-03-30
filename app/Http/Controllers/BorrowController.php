<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Item;
use App\Models\Students;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

use Illuminate\Support\Facades\Log;
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

    // Load all active borrowings for this person
    $borrowings = Borrowing::query()
      ->with(['items.item'])
      ->whereIn('status', ['active', 'overdue'])
      ->where('borrower_type', $borrowerType)
      ->when($borrowerType === 'student', fn($q) => $q->where('student_id', $borrowerStudentId))
      ->when($borrowerType === 'instructor', fn($q) => $q->where('user_id', $borrowerUserId))
      ->get();

    // Map barcode → borrowing item record with its current status
    $borrowItemByBarcode = [];
    foreach ($borrowings as $borrowing) {
      foreach ($borrowing->items as $borrowItem) {
        $barcode = trim((string) ($borrowItem->item?->barcode ?? ''));
        if ($barcode === '') {
          continue;
        }
        // Only track items that are still in-progress (borrow or borrowed)
        $itemStatus = (string) $borrowItem->status;
        if (in_array($itemStatus, ['borrow', 'borrowed'], true)) {
          $borrowItemByBarcode[$barcode] = [
            'borrowing_id' => $borrowing->borrowing_id,
            'borrowing_item_id' => $borrowItem->id,
            'item_id' => $borrowItem->item_id,
            'current_status' => $itemStatus,
          ];
        }
      }
    }

    $advancedBarcodes = []; // successfully advanced to next state
    $missingBarcodes = []; // barcode not in inventory
    $unavailableBarcodes = []; // available item but already at final state / taken by someone else
    $touchedBorrowingIds = [];

    DB::transaction(function () use ($requestedBarcodes, $borrowItemByBarcode, $borrowerType, $borrowerStudentId, $borrowerUserId, &$advancedBarcodes, &$missingBarcodes, &$unavailableBarcodes, &$touchedBorrowingIds, ) {
      $activeBorrowing = null;

      foreach ($requestedBarcodes as $barcode) {

        // ── EXISTING ITEM IN PIPELINE ────────────────────────────
        if (isset($borrowItemByBarcode[$barcode])) {
          $record = $borrowItemByBarcode[$barcode];
          $currentStatus = $record['current_status'];

          // borrow → borrowed (permit the borrow)
          if ($currentStatus === 'borrow') {
            BorrowingItem::query()
              ->where('id', $record['borrowing_item_id'])
              ->update(['status' => 'borrowed']);

            Item::query()
              ->where('item_id', $record['item_id'])
              ->update(['status' => 'Borrowed']);

            Log::info('[BorrowFlow] borrow → borrowed', [
              'barcode' => $barcode,
              'borrowing_item_id' => $record['borrowing_item_id'],
            ]);
          }

          // borrowed → returned (return the item)
          elseif ($currentStatus === 'borrowed') {
            BorrowingItem::query()
              ->where('id', $record['borrowing_item_id'])
              ->update(['status' => 'returned']);

            Item::query()
              ->where('item_id', $record['item_id'])
              ->update(['status' => 'Available']);

            Log::info('[BorrowFlow] borrowed → returned', [
              'barcode' => $barcode,
              'borrowing_item_id' => $record['borrowing_item_id'],
            ]);
          }

          $advancedBarcodes[] = $barcode;
          $touchedBorrowingIds[$record['borrowing_id']] = $record['borrowing_id'];
          continue;
        }

        // ── NEW ITEM — start at 'borrow' ─────────────────────────
        $inventoryItem = Item::query()->where('barcode', $barcode)->first();
        if (!$inventoryItem) {
          Log::warning('[BorrowFlow] Barcode not found', ['barcode' => $barcode]);
          $missingBarcodes[] = $barcode;
          continue;
        }

        if (strtolower((string) ($inventoryItem->status ?? '')) !== 'available') {
          Log::warning('[BorrowFlow] Item not available', [
            'barcode' => $barcode,
            'item_status' => $inventoryItem->status,
          ]);
          $unavailableBarcodes[] = $barcode;
          continue;
        }

        // Find or create an active borrowing record
        if (!$activeBorrowing) {
          $activeBorrowing = Borrowing::query()
            ->where('borrower_type', $borrowerType)
            ->when($borrowerType === 'student', fn($q) => $q->where('student_id', $borrowerStudentId))
            ->when($borrowerType === 'instructor', fn($q) => $q->where('user_id', $borrowerUserId))
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

            Log::info('[BorrowFlow] New borrowing record created', [
              'borrowing_id' => $activeBorrowing->borrowing_id,
            ]);
          } elseif ($activeBorrowing->status !== 'active') {
            Borrowing::query()
              ->where('borrowing_id', $activeBorrowing->borrowing_id)
              ->update(['status' => 'active', 'returned_at' => null]);
          }
        }

        // Create the item at the first state: 'borrow' (asking to borrow)
        BorrowingItem::query()->create([
          'borrowing_id' => $activeBorrowing->borrowing_id,
          'item_id' => $inventoryItem->item_id,
          'quantity' => 1,
          'status' => 'borrow',
        ]);

        Log::info('[BorrowFlow] New item → borrow (requesting)', [
          'barcode' => $barcode,
          'borrowing_id' => $activeBorrowing->borrowing_id,
        ]);

        $advancedBarcodes[] = $barcode;
        $touchedBorrowingIds[$activeBorrowing->borrowing_id] = $activeBorrowing->borrowing_id;
      }

      // ── SYNC PARENT BORROWING STATUS ────────────────────────────
      foreach ($touchedBorrowingIds as $borrowingId) {
        $hasPending = BorrowingItem::query()
          ->where('borrowing_id', $borrowingId)
          ->whereIn('status', ['borrow', 'borrowed'])
          ->exists();

        Borrowing::query()
          ->where('borrowing_id', $borrowingId)
          ->update(
            $hasPending
            ? ['status' => 'active', 'returned_at' => null]
            : ['status' => 'returned', 'returned_at' => now()]
          );

        Log::info('[BorrowFlow] Borrowing status synced', [
          'borrowing_id' => $borrowingId,
          'new_status' => $hasPending ? 'active' : 'returned',
        ]);
      }
    });

    $advancedUnique = collect($advancedBarcodes)->unique()->values()->all();
    $missingUnique = collect($missingBarcodes)->unique()->values()->all();
    $unavailableUnique = collect($unavailableBarcodes)->unique()->values()->all();

    if (count($advancedUnique) === 0) {
      return response()->json([
        'ok' => false,
        'message' => 'Item Already Borrowed or Not listed as borrowable.',
        'missing_barcodes' => $missingUnique,
        'unavailable_barcodes' => $unavailableUnique,
      ], 422);
    }

    return response()->json([
      'ok' => true,
      'message' => count($advancedUnique) . ' item(s) advanced in workflow.',
      'advanced_barcodes' => $advancedUnique,
      'missing_barcodes' => $missingUnique,
      'unavailable_barcodes' => $unavailableUnique,
    ]);
  }
  public function borrowItemsOnly(Request $request): JsonResponse
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

    $borrowedBarcodes = [];
    $missingBarcodes = [];
    $unavailableBarcodes = [];
    $touchedBorrowingIds = [];

    DB::transaction(function () use ($requestedBarcodes, $borrowerType, $borrowerStudentId, $borrowerUserId, &$borrowedBarcodes, &$missingBarcodes, &$unavailableBarcodes, &$touchedBorrowingIds) {
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
      }

      foreach ($requestedBarcodes as $barcode) {
        $inventoryItem = Item::query()->where('barcode', $barcode)->first();
        if (!$inventoryItem) {
          $missingBarcodes[] = $barcode;
          continue;
        }

        if (strtolower((string) ($inventoryItem->status ?? '')) !== 'available') {
          $unavailableBarcodes[] = $barcode;
          continue;
        }

        BorrowingItem::query()->create([
          'borrowing_id' => $activeBorrowing->borrowing_id,
          'item_id' => $inventoryItem->item_id,
          'quantity' => 1,
          'status' => 'borrow',
        ]);

        $inventoryItem->update(['status' => 'Borrowed']);

        $borrowedBarcodes[] = $barcode;
        $touchedBorrowingIds[$activeBorrowing->borrowing_id] = $activeBorrowing->borrowing_id;
      }

      foreach ($touchedBorrowingIds as $borrowingId) {
        Borrowing::query()
          ->where('borrowing_id', $borrowingId)
          ->update([
            'status' => 'active',
            'returned_at' => null,
          ]);
      }
    });

    $borrowedUnique = collect($borrowedBarcodes)->unique()->values()->all();
    $missingUnique = collect($missingBarcodes)->unique()->values()->all();
    $unavailableUnique = collect($unavailableBarcodes)->unique()->values()->all();

    if (count($borrowedUnique) === 0) {
      return response()->json([
        'ok' => false,
        'message' => 'No items were borrowed. Check barcode validity or availability.',
        'missing_barcodes' => $missingUnique,
        'unavailable_barcodes' => $unavailableUnique,
      ], 422);
    }

    return response()->json([
      'ok' => true,
      'message' => count($borrowedUnique) . ' item(s) borrowed successfully.',
      'borrowed_barcodes' => $borrowedUnique,
      'missing_barcodes' => $missingUnique,
      'unavailable_barcodes' => $unavailableUnique,
    ]);
  }

  private function buildBorrowRows(array $filters)
  {
    $statusMap = $this->statusMap();

    $query = Borrowing::with(['student.strand', 'student.section', 'instructor', 'items.item'])
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
                ->where('barcode', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
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
          ->map(fn($item) => $item->item->name ?? null)
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
            'name' => $bi->item->name ?? 'Unknown Item',
            'code' => $bi->item->sku ?? 'N/A',
            'type' => $bi->item->description ?? 'N/A',
            'barcode' => $bi->item->barcode ?? 'N/A',
            'quantity' => (int) $bi->quantity,
            'status' => (strtolower((string) ($bi->status ?? 'borrowed')) === 'borrowed') ? 'Borrowed' : ucfirst((string) $bi->status),
            'borrowedAt' => $bi->created_at ? $bi->created_at->format('Y-m-d H:i:s') : null,
            'returnedAt' => ($bi->status === 'returned') ? ($bi->updated_at ? $bi->updated_at->format('Y-m-d H:i:s') : null) : null,
          ];
        })->values()->toArray();

        return [
          'name' => $borrowerName !== '' ? $borrowerName : 'Unknown Borrower',
          'id' => $borrowerId,
          'role' => $isStudent ? 'Student' : 'Teacher',
          'course' => $isStudent ? ($borrowing->student->strand->strand_code ?? 'N/A') : 'Faculty',
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
      ->leftJoin('strands', 'students.strand_id', '=', 'strands.strand_id')
      ->leftJoin('sections', 'students.section_id', '=', 'sections.section_id')
      ->whereNotNull('students.rfid_tag')
      ->select([
        'students.rfid_tag as rfid',
        'students.student_number as studentId',
        'students.first_name',
        'students.last_name',
        'students.year_level',
        'strands.strand_code as strand',
        'sections.section_name as section',
      ])
      ->get()
      ->map(function ($student) {
        return [
          'rfid' => (string) $student->rfid,
          'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
          'studentId' => $student->studentId ?? 'N/A',
          'strand' => $student->strand ?? 'N/A',
          'section' => $student->section ?? 'N/A',
          'year' => $student->year_level ?? 'N/A',
          'role' => 'Student',
        ];
      });

    $userBorrowers = User::query()
      ->whereNotNull('rfid_tag')
      ->get()
      ->map(function ($user) {
        return [
          'rfid' => (string) $user->rfid_tag,
          'name' => $user->name ?? 'Unknown User',
          'studentId' => 'INS-' . ($user->user_id ?? 'N/A'),
          'strand' => 'Faculty',
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
    return Item::query()
      ->where(function ($query) {
        $query->whereNotNull('barcode')->orWhereNotNull('barcode');
      })
      ->select(['name', 'sku', 'barcode', 'description', 'status'])
      ->orderBy('name')
      ->get()
      ->map(function ($device) {
        return [
          'name' => $device->name,
          'id' => $device->sku,
          'type' => $device->description,
          'barcode' => (string) $device->barcode,
          'status' => $device->status,
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
        $barcode = $borrowedItem?->barcode;

        if (!$borrowedItem || empty($barcode)) {
          continue;
        }

        $barcode = (string) $barcode;

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
          'name' => $borrowedItem->name,
          'id' => $borrowedItem->sku ?: ('ITEM-' . $borrowedItem->item_id),
          'type' => $borrowedItem->description,
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
