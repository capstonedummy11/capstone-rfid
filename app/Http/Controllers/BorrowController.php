<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Device;
use App\Models\Students;
use App\Models\User;
use Illuminate\Http\Request;
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
      'borrowRows' => $this->buildBorrowRows($filters),
      'borrowerProfiles' => $this->buildBorrowerProfiles(),
      'borrowItemsCatalog' => $this->buildBorrowItemsCatalog(),
      'borrowItemsByRfid' => $this->buildBorrowItemsByRfid(),
      'dashboardStats' => $this->countDashboardBorrowing(),
      'filters' => $filters,
    ]);
  }

  private function buildBorrowRows(array $filters)
  {
    $statusMap = $this->statusMap();

    $query = Borrowing::with(['student', 'instructor', 'items.item'])
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
                ->orWhere('item_sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")
                ->orWhere('item_barcode', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('item_description', 'like', "%{$term}%");
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

        return [
          'name' => $borrowerName !== '' ? $borrowerName : 'Unknown Borrower',
          'id' => $borrowerId,
          'role' => $isStudent ? 'Student' : 'Teacher',
          'item' => $itemPreview,
          'number' => (int) $borrowing->items->sum('quantity'),
          'date' => $timeIn ? $timeIn->format('d F Y') : 'N/A',
          'status' => $statusMap[$borrowing->status] ?? 'Under Maintenance',
          'timeIn' => $timeIn ? $timeIn->format('H:i') : '00:00',
          'timeOut' => $timeOut ? $timeOut->format('H:i') : '00:00',
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
    return Device::query()
      ->where(function ($query) {
        $query->whereNotNull('barcode')->orWhereNotNull('item_barcode');
      })
      ->select(['item_name', 'item_code', 'item_type', 'barcode', 'item_barcode', 'item_sku'])
      ->orderBy('item_code')
      ->get()
      ->map(function ($device) {
        $barcode = $device->item_barcode ?: $device->barcode;

        return [
          'name' => $device->item_name,
          'id' => $device->item_sku ?: $device->item_code,
          'type' => $device->item_type,
          'barcode' => (string) $barcode,
        ];
      })
      ->values();
  }

  private function buildBorrowItemsByRfid(): array
  {
    $map = [];

    $borrowings = Borrowing::with(['student', 'instructor', 'items.item'])
      ->whereIn('status', ['active', 'overdue'])
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
        $map[$rfidKey] = [];
      }

      foreach ($borrowing->items as $item) {
        $borrowedItem = $item->item;
        $barcode = $borrowedItem?->item_barcode ?: $borrowedItem?->barcode;

        if (!$borrowedItem || empty($barcode)) {
          continue;
        }

        $map[$rfidKey][] = [
          'name' => $borrowedItem->item_name,
          'id' => $borrowedItem->item_sku ?: $borrowedItem->item_code,
          'type' => $borrowedItem->item_type,
          'barcode' => (string) $barcode,
        ];
      }
    }

    foreach ($map as $key => $items) {
      $map[$key] = collect($items)
        ->unique('barcode')
        ->values()
        ->all();
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
