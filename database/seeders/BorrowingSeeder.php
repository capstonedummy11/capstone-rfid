<?php

namespace Database\Seeders;

use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Item;
use App\Models\Students;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BorrowingSeeder extends Seeder
{
  public function run(): void
  {
    $students = Students::limit(5)->get();
    $instructor = User::where('role', '!=', 'admin')->first()
      ?? User::first();
    $items = Item::limit(10)->get();

    if ($students->isEmpty() || $items->isEmpty()) {
      $this->command->warn('BorrowingSeeder skipped: no students or items found.');
      return;
    }

    $records = [
      // Active student borrow
      [
        'borrowing' => [
          'student_id' => $students[0]->student_id,
          'user_id' => null,
          'borrower_type' => 'student',
          'borrowed_at' => Carbon::now()->subDays(3),
          'returned_at' => null,
          'status' => 'active',
          'due_date' => Carbon::now()->addDays(4)->toDateString(),
          'remarks' => null,
        ],
        'item_indexes' => [0, 1],
      ],
      // Returned student borrow
      [
        'borrowing' => [
          'student_id' => isset($students[1]) ? $students[1]->student_id : $students[0]->student_id,
          'user_id' => null,
          'borrower_type' => 'student',
          'borrowed_at' => Carbon::now()->subDays(10),
          'returned_at' => Carbon::now()->subDays(2),
          'status' => 'returned',
          'due_date' => Carbon::now()->subDays(3)->toDateString(),
          'remarks' => 'Returned on time.',
        ],
        'item_indexes' => [2],
        'item_status' => 'returned',
      ],
      // Overdue student borrow
      [
        'borrowing' => [
          'student_id' => isset($students[2]) ? $students[2]->student_id : $students[0]->student_id,
          'user_id' => null,
          'borrower_type' => 'student',
          'borrowed_at' => Carbon::now()->subDays(15),
          'returned_at' => null,
          'status' => 'overdue',
          'due_date' => Carbon::now()->subDays(5)->toDateString(),
          'remarks' => 'Past due date.',
        ],
        'item_indexes' => [3],
      ],
      // Active instructor borrow
      [
        'borrowing' => [
          'student_id' => null,
          'user_id' => $instructor?->user_id,
          'borrower_type' => 'instructor',
          'borrowed_at' => Carbon::now()->subDay(),
          'returned_at' => null,
          'status' => 'active',
          'due_date' => Carbon::now()->addWeek()->toDateString(),
          'remarks' => null,
        ],
        'item_indexes' => [4, 5],
      ],
      // Damaged student borrow
      [
        'borrowing' => [
          'student_id' => isset($students[3]) ? $students[3]->student_id : $students[0]->student_id,
          'user_id' => null,
          'borrower_type' => 'student',
          'borrowed_at' => Carbon::now()->subDays(7),
          'returned_at' => Carbon::now()->subDay(),
          'status' => 'damaged',
          'due_date' => Carbon::today()->toDateString(),
          'remarks' => 'Item returned with visible damage.',
        ],
        'item_indexes' => [6],
        'item_status' => 'damaged',
      ],
    ];

    foreach ($records as $record) {
      if ($record['borrowing']['user_id'] === null && $record['borrowing']['borrower_type'] === 'instructor') {
        // Skip instructor record if no non-admin user exists but we still have admin
        if (!$instructor) {
          continue;
        }
      }

      $borrowing = Borrowing::create($record['borrowing']);

      $itemStatus = $record['item_status'] ?? 'borrowed';

      foreach ($record['item_indexes'] as $idx) {
        $item = $items->get($idx);
        if (!$item) {
          continue;
        }

        BorrowingItem::create([
          'borrowing_id' => $borrowing->borrowing_id,
          'item_id' => $item->item_id,
          'quantity' => 1,
          'status' => $itemStatus,
        ]);
      }
    }

    $this->command->info('BorrowingSeeder: seeded ' . count($records) . ' borrowing records.');
  }
}
