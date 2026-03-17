<?php

namespace Database\Seeders;

use App\Models\BorrowingItem;
use App\Models\Borrowing;
use App\Models\Course;
use App\Models\Device;
use App\Models\Section;
use App\Models\Students;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BorrowSeeder extends Seeder
{
  public function run(): void
  {
    // Keep reference tables, reset transactional rows.
    BorrowingItem::query()->delete();
    Borrowing::query()->delete();

    // Courses (from Borrow.vue registeredStudents)
    $bsit = Course::updateOrCreate(
      ['course_code' => 'BSIT'],
      [
        'course_name' => 'Bachelor of Science in Information Technology',
        'department' => 'College of Computing',
        'status' => 'active',
      ]
    );

    $bscs = Course::updateOrCreate(
      ['course_code' => 'BSCS'],
      [
        'course_name' => 'Bachelor of Science in Computer Science',
        'department' => 'College of Computing',
        'status' => 'active',
      ]
    );

    // Sections (from Borrow.vue registeredStudents)
    $section3A = Section::updateOrCreate(
      ['course_id' => $bsit->course_id, 'section_name' => '3A'],
      [
        'year_level' => 3,
        'semester' => '2nd',
        'school_year' => '2025-2026',
        'status' => 'active',
      ]
    );

    $section2B = Section::updateOrCreate(
      ['course_id' => $bscs->course_id, 'section_name' => '2B'],
      [
        'year_level' => 2,
        'semester' => '2nd',
        'school_year' => '2025-2026',
        'status' => 'active',
      ]
    );

    $section1A = Section::updateOrCreate(
      ['course_id' => $bsit->course_id, 'section_name' => '1A'],
      [
        'year_level' => 1,
        'semester' => '2nd',
        'school_year' => '2025-2026',
        'status' => 'active',
      ]
    );

    // Students (exact RFID/student constants from Borrow.vue)
    $juan = Students::updateOrCreate(
      ['student_number' => '2023001'],
      [
        'section_id' => $section3A->section_id,
        'course_id' => $bsit->course_id,
        'rfid_tag' => '0000002049',
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
        'middle_name' => null,
        'gender' => 'male',
        'email' => 'juan.cruz@school.edu',
        'year_level' => 3,
        'semester' => '2nd',
        'school_year' => '2025-2026',
        'status' => 'active',
      ]
    );

    Students::updateOrCreate(
      ['student_number' => '2023002'],
      [
        'section_id' => $section2B->section_id,
        'course_id' => $bscs->course_id,
        'rfid_tag' => '000679A641',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => null,
        'gender' => 'female',
        'email' => 'maria.santos@school.edu',
        'year_level' => 2,
        'semester' => '2nd',
        'school_year' => '2025-2026',
        'status' => 'active',
      ]
    );

    Students::updateOrCreate(
      ['student_number' => '2023003'],
      [
        'section_id' => $section1A->section_id,
        'course_id' => $bsit->course_id,
        'rfid_tag' => '00DA15A641',
        'first_name' => 'Pedro',
        'last_name' => 'Reyes',
        'middle_name' => null,
        'gender' => 'male',
        'email' => 'pedro.reyes@school.edu',
        'year_level' => 1,
        'semester' => '2nd',
        'school_year' => '2025-2026',
        'status' => 'active',
      ]
    );

    // Instructor user with RFID
    $instructor = User::updateOrCreate(
      ['email' => 'instructor@school.edu'],
      [
        'name' => 'Prof. Dela Cruz',
        'password' => Hash::make('password'),
        'role' => 'instructor',
        'rfid_tag' => 'INSTR001',
      ]
    );

    // Devices (exact constants from BORROW_ITEMS_EXAMPLE in Borrow.vue)
    $monitor = Device::updateOrCreate(
      ['device_code' => 'DEV-00421'],
      [
        'device_name' => 'Samsung Monitor 24 Inch IPS',
        'device_type' => 'Monitor',
        'barcode' => '971844908878',
        'brand' => 'Samsung',
        'model' => '24" IPS',
        'status' => 'available',
      ]
    );

    $keyboard = Device::updateOrCreate(
      ['device_code' => 'DEV-00981'],
      [
        'device_name' => 'Logitech Wireless Keyboard',
        'device_type' => 'Keyboard',
        'barcode' => '5591590719996302436',
        'brand' => 'Logitech',
        'model' => 'MK270',
        'status' => 'available',
      ]
    );

    $mouse = Device::updateOrCreate(
      ['device_code' => 'DEV-00271'],
      [
        'device_name' => 'A4Tech Optical Mouse',
        'device_type' => 'Mouse',
        'barcode' => '352479230923229',
        'brand' => 'A4Tech',
        'model' => 'OP-620D',
        'status' => 'available',
      ]
    );

    // Sample borrowing: student borrows monitor + keyboard
    $borrowing = Borrowing::create([
      'student_id' => $juan->student_id,
      'user_id' => null,
      'borrower_type' => 'student',
      'borrowed_at' => now()->subHours(3),
      'returned_at' => null,
      'status' => 'active',
    ]);

    BorrowingItem::create([
      'borrowing_id' => $borrowing->borrowing_id,
      'device_id' => $monitor->device_id,
      'quantity' => 1,
      'status' => 'borrowed',
    ]);

    BorrowingItem::create([
      'borrowing_id' => $borrowing->borrowing_id,
      'device_id' => $keyboard->device_id,
      'quantity' => 1,
      'status' => 'borrowed',
    ]);

    $monitor->update(['status' => 'borrowed']);
    $keyboard->update(['status' => 'borrowed']);

    // Sample borrowing: instructor borrows mouse (returned)
    $borrowing2 = Borrowing::create([
      'student_id' => null,
      'user_id' => $instructor->user_id,
      'borrower_type' => 'instructor',
      'borrowed_at' => now()->subDay(),
      'returned_at' => now()->subHours(2),
      'status' => 'returned',
    ]);

    BorrowingItem::create([
      'borrowing_id' => $borrowing2->borrowing_id,
      'device_id' => $mouse->device_id,
      'quantity' => 1,
      'status' => 'returned',
    ]);
  }
}
