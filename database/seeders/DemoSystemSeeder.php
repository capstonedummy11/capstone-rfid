<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use App\Models\EmergencyType;
use App\Models\Instructor;
use App\Models\Item;
use App\Models\Laboratory;
use App\Models\PatientHistory;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSystemSeeder extends Seeder
{
    private string $schoolYear = '2026-2027';

    public function run(): void
    {
        $users = $this->seedUsers();
        $academic = $this->seedAcademicSetup($users['instructor']);
        $students = $this->seedStudents($academic['strand'], $academic['sections']);
        $items = $this->seedInventoryItems();

        $this->seedBorrowing($students, $users['instructor'], $items);
        $this->seedAttendance($students, $academic['schedules'], $users['admin']);
        $this->seedClinicAndEmergency($students[0], $users['clinic'], $users['instructor'], $academic['schedules'][0]);
        $this->seedActivityLogs($users['admin'], $users['instructor']);
    }

    private function seedUsers(): array
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@sample.com'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('sample'),
                'role' => 'admin',
                'rfid_tag' => 'RFID-ADMIN-SAMPLE',
            ],
        );

        $instructor = User::updateOrCreate(
            ['email' => 'instructor@sample.com'],
            [
                'name' => 'Sample Instructor',
                'password' => Hash::make('sample'),
                'role' => 'instructor',
                'rfid_tag' => 'RFID-INSTRUCTOR-SAMPLE',
            ],
        );

        $clinic = User::updateOrCreate(
            ['email' => 'clinic@sample.com'],
            [
                'name' => 'Clinic Staff',
                'password' => Hash::make('sample'),
                'role' => 'clinic',
                'rfid_tag' => 'RFID-CLINIC-SAMPLE',
            ],
        );

        $console = User::updateOrCreate(
            ['email' => 'console@sample.com'],
            [
                'name' => 'Attendance Console',
                'password' => Hash::make('sample'),
                'role' => 'console',
                'rfid_tag' => 'RFID-CONSOLE-SAMPLE',
            ],
        );

        return compact('admin', 'instructor', 'clinic', 'console');
    }

    private function seedAcademicSetup(User $instructorUser): array
    {
        $strand = Strand::updateOrCreate(
            ['strand_code' => 'TVL-ICT'],
            [
                'strand_name' => 'Technical-Vocational-Livelihood - ICT',
                'department' => 'Senior High School',
                'status' => 'active',
            ],
        );

        $instructor = Instructor::updateOrCreate(
            ['user_id' => $instructorUser->user_id],
            [
                'strand_id' => $strand->strand_id,
                'instructor_number' => 'INS-SAMPLE-001',
                'status' => 'active',
            ],
        );

        $labs = collect([
            ['name' => 'Laboratory 1', 'location' => 'Building A - Room 101'],
            ['name' => 'Laboratory 2', 'location' => 'Building A - Room 102'],
        ])->map(fn (array $lab) => Laboratory::updateOrCreate(
            ['name' => $lab['name']],
            [
                'description' => $lab['name'] . ' for RFID attendance and borrowing simulation.',
                'location' => $lab['location'],
                'status' => 'active',
            ],
        ))->values();

        $sections = collect([
            ['section_name' => 'ICT 11-A', 'year_level' => 11, 'semester' => '1st Semester'],
            ['section_name' => 'ICT 12-A', 'year_level' => 12, 'semester' => '1st Semester'],
        ])->map(fn (array $section) => Section::updateOrCreate(
            [
                'section_name' => $section['section_name'],
                'school_year' => $this->schoolYear,
            ],
            [
                'strand_id' => $strand->strand_id,
                'year_level' => $section['year_level'],
                'semester' => $section['semester'],
                'status' => 'active',
            ],
        ))->values();

        $subjectRows = [
            [
                'section' => $sections[0],
                'lab' => $labs[0],
                'code' => 'ICT-PROG-11',
                'name' => 'Computer Programming 1',
                'description' => 'Programming fundamentals for Grade 11 ICT students.',
                'weekdays' => 'Mon,Tue,Wed,Thu,Fri,Sat',
                'time_start' => '00:00:00',
                'time_end' => '23:59:59',
            ],
            [
                'section' => $sections[1],
                'lab' => $labs[1],
                'code' => 'ICT-NET-12',
                'name' => 'Computer Networking 2',
                'description' => 'Network configuration and troubleshooting for Grade 12 ICT students.',
                'weekdays' => 'Mon,Wed,Fri',
                'time_start' => '13:00:00',
                'time_end' => '15:00:00',
            ],
        ];

        $subjects = [];
        $schedules = [];

        foreach ($subjectRows as $row) {
            $subject = Subject::updateOrCreate(
                [
                    'subject_code' => $row['code'],
                    'section_id' => $row['section']->section_id,
                ],
                [
                    'user_id' => $instructorUser->user_id,
                    'subject_name' => $row['name'],
                    'subject_description' => $row['description'],
                    'year_level' => $row['section']->year_level,
                    'department' => 'Senior High School',
                    'unit' => 3,
                    'semester' => $row['section']->semester,
                ],
            );

            $schedule = Schedule::updateOrCreate(
                [
                    'section_id' => $row['section']->section_id,
                    'subject_code' => $subject->subject_code,
                ],
                [
                    'laboratory_id' => $row['lab']->laboratory_id,
                    'instructor_id' => $instructor->instructor_id,
                    'weekdays' => $row['weekdays'],
                    'time_start' => $row['time_start'],
                    'time_end' => $row['time_end'],
                    'room' => $row['lab']->name,
                ],
            );

            $subjects[] = $subject;
            $schedules[] = $schedule;
        }

        return [
            'strand' => $strand,
            'instructor' => $instructor,
            'sections' => $sections,
            'subjects' => collect($subjects),
            'schedules' => collect($schedules),
        ];
    }

    private function seedStudents(Strand $strand, $sections)
    {
        $rows = [
            ['section' => $sections[0], 'number' => 'SHS-ICT-1101', 'first' => 'Andrea', 'last' => 'Santos', 'gender' => 'female', 'rfid' => 'RFID-STUDENT-1101'],
            ['section' => $sections[0], 'number' => 'SHS-ICT-1102', 'first' => 'Miguel', 'last' => 'Reyes', 'gender' => 'male', 'rfid' => 'RFID-STUDENT-1102'],
            ['section' => $sections[0], 'number' => 'SHS-ICT-1103', 'first' => 'Lara', 'last' => 'Cruz', 'gender' => 'female', 'rfid' => 'RFID-STUDENT-1103'],
            ['section' => $sections[1], 'number' => 'SHS-ICT-1201', 'first' => 'Rafael', 'last' => 'Garcia', 'gender' => 'male', 'rfid' => 'RFID-STUDENT-1201'],
            ['section' => $sections[1], 'number' => 'SHS-ICT-1202', 'first' => 'Nina', 'last' => 'Dela Cruz', 'gender' => 'female', 'rfid' => 'RFID-STUDENT-1202'],
        ];

        return collect($rows)->map(function (array $row) use ($strand) {
            return Students::updateOrCreate(
                ['student_number' => $row['number']],
                [
                    'section_id' => $row['section']->section_id,
                    'strand_id' => $strand->strand_id,
                    'first_name' => $row['first'],
                    'middle_name' => null,
                    'last_name' => $row['last'],
                    'gender' => $row['gender'],
                    'email' => strtolower(str_replace(' ', '.', $row['first'] . '.' . $row['last'])) . '@student.sample.com',
                    'phone' => '0917000' . substr($row['number'], -4),
                    'year_level' => $row['section']->year_level,
                    'semester' => $row['section']->semester,
                    'school_year' => $this->schoolYear,
                    'rfid_tag' => $row['rfid'],
                    'face_images' => [],
                    'status' => 'active',
                ],
            );
        })->values();
    }

    private function seedInventoryItems()
    {
        $rows = [
            ['barcode' => 'ITEM-KB-001', 'name' => 'USB Keyboard', 'sku' => 'KB-001', 'description' => 'Computer peripheral', 'status' => 'Borrowed'],
            ['barcode' => 'ITEM-MSE-001', 'name' => 'Optical Mouse', 'sku' => 'MSE-001', 'description' => 'Computer peripheral', 'status' => 'Borrowed'],
            ['barcode' => 'ITEM-HDMI-001', 'name' => 'HDMI Cable', 'sku' => 'HDMI-001', 'description' => 'Cable and adapter', 'status' => 'Available'],
            ['barcode' => 'ITEM-PRJ-001', 'name' => 'Portable Projector', 'sku' => 'PRJ-001', 'description' => 'Presentation equipment', 'status' => 'Borrowed'],
            ['barcode' => 'ITEM-LTP-001', 'name' => 'Laboratory Laptop', 'sku' => 'LTP-001', 'description' => 'Computer unit', 'status' => 'Maintenance'],
            ['barcode' => 'ITEM-CAM-001', 'name' => 'Web Camera', 'sku' => 'CAM-001', 'description' => 'Face verification camera', 'status' => 'Available'],
            ['barcode' => 'ITEM-ETH-001', 'name' => 'Ethernet Cable', 'sku' => 'ETH-001', 'description' => 'Network cable', 'status' => 'Available'],
            ['barcode' => 'ITEM-RDR-001', 'name' => 'RFID Reader', 'sku' => 'RDR-001', 'description' => 'Attendance scanner device', 'status' => 'Available'],
        ];

        return collect($rows)->map(fn (array $row) => Item::updateOrCreate(
            ['barcode' => $row['barcode']],
            $row,
        ))->values();
    }

    private function seedBorrowing($students, User $instructor, $items): void
    {
        $records = [
            [
                'key' => 'DEMO-BORROW-STUDENT-ACTIVE',
                'borrower_type' => 'student',
                'student' => $students[0],
                'user' => null,
                'status' => 'active',
                'borrowed_at' => now()->subDays(1),
                'returned_at' => null,
                'due_date' => now()->addDays(2)->toDateString(),
                'remarks' => 'Demo active borrowing for attendance panel return flow.',
                'items' => [
                    ['item' => $items[0], 'status' => 'borrowed'],
                    ['item' => $items[1], 'status' => 'borrowed'],
                ],
            ],
            [
                'key' => 'DEMO-BORROW-INSTRUCTOR-ACTIVE',
                'borrower_type' => 'instructor',
                'student' => null,
                'user' => $instructor,
                'status' => 'active',
                'borrowed_at' => now()->subHours(4),
                'returned_at' => null,
                'due_date' => now()->addWeek()->toDateString(),
                'remarks' => 'Demo instructor borrowing.',
                'items' => [
                    ['item' => $items[3], 'status' => 'borrowed'],
                ],
            ],
            [
                'key' => 'DEMO-BORROW-STUDENT-RETURNED',
                'borrower_type' => 'student',
                'student' => $students[1],
                'user' => null,
                'status' => 'returned',
                'borrowed_at' => now()->subDays(8),
                'returned_at' => now()->subDays(7),
                'due_date' => now()->subDays(6)->toDateString(),
                'remarks' => 'Demo returned borrowing history.',
                'items' => [
                    ['item' => $items[2], 'status' => 'returned'],
                ],
            ],
        ];

        foreach ($records as $record) {
            $borrowing = Borrowing::updateOrCreate(
                ['remarks' => $record['remarks']],
                [
                    'student_id' => $record['student']?->student_id,
                    'user_id' => $record['user']?->user_id,
                    'borrower_type' => $record['borrower_type'],
                    'borrowed_at' => $record['borrowed_at'],
                    'returned_at' => $record['returned_at'],
                    'status' => $record['status'],
                    'due_date' => $record['due_date'],
                ],
            );

            BorrowingItem::query()->where('borrowing_id', $borrowing->borrowing_id)->delete();

            foreach ($record['items'] as $entry) {
                BorrowingItem::create([
                    'borrowing_id' => $borrowing->borrowing_id,
                    'item_id' => $entry['item']->item_id,
                    'quantity' => 1,
                    'status' => $entry['status'],
                ]);
            }
        }
    }

    private function seedAttendance($students, $schedules, User $admin): void
    {
        $schedule = $schedules[0];
        $today = Carbon::today();

        $sessionId = DB::table('attendance_sessions')->updateOrInsert(
            [
                'subject_code' => $schedule->subject_code,
                'schedule_id' => $schedule->scheduled_id,
                'date' => $today->toDateString(),
                'room' => $schedule->room,
            ],
            [
                'time_start' => '08:00:00',
                'time_end' => null,
                'status' => 'attendance',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $sessionId = DB::table('attendance_sessions')
            ->where('subject_code', $schedule->subject_code)
            ->where('schedule_id', $schedule->scheduled_id)
            ->whereDate('date', $today->toDateString())
            ->where('room', $schedule->room)
            ->value('attendance_id');

        DB::table('attendances')->updateOrInsert(
            ['attendance_id' => $sessionId],
            [
                'student_id' => $students[0]->student_id,
                'schedule_id' => $schedule->scheduled_id,
                'date' => $today->toDateString(),
                'time_start' => '08:00:00',
                'time_end' => '09:30:00',
                'time_in' => '08:02:00',
                'time_out' => null,
                'status' => 'present',
                'subject_code' => $schedule->subject_code,
                'room' => $schedule->room,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $logs = [
            [$students[0], '08:02:00', null, 'present'],
            [$students[1], '08:05:00', '09:25:00', 'completed'],
            [$students[2], '08:21:00', null, 'late'],
        ];

        foreach ($logs as [$student, $timeIn, $timeOut, $status]) {
            DB::table('attendance_logs')->updateOrInsert(
                [
                    'attendance_id' => $sessionId,
                    'student_id' => $student->student_id,
                ],
                [
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        ActivityLog::updateOrCreate(
            [
                'user_id' => $admin->user_id,
                'action' => 'demo_attendance_seeded',
                'table_name' => 'attendance_logs',
            ],
            [
                'description' => 'Seeded demo attendance session with present, completed, and late student scans.',
            ],
        );
    }

    private function seedClinicAndEmergency(Students $student, User $clinic, User $instructor, Schedule $schedule): void
    {
        $type = EmergencyType::updateOrCreate(
            ['name' => 'Fainting'],
            [
                'category' => 'clinic',
                'default_message' => 'Clinic emergency: student/person fainted. Please respond immediately.',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        $alert = EmergencyAlert::updateOrCreate(
            [
                'room' => $schedule->room,
                'subject_code' => $schedule->subject_code,
                'message' => 'Demo clinic alert from attendance control panel.',
            ],
            [
                'emergency_type_id' => $type->emergency_type_id,
                'triggered_by_user_id' => $instructor->user_id,
                'schedule_id' => $schedule->scheduled_id,
                'triggered_by_name' => $instructor->name,
                'sub_type' => 'RFID Timed In',
                'severity' => 'urgent',
                'status' => 'acknowledged',
                'metadata' => ['student_rfid' => $student->rfid_tag],
                'resolved_at' => null,
            ],
        );

        ClinicCase::updateOrCreate(
            [
                'emergency_alert_id' => $alert->emergency_alert_id,
                'patient_name' => trim($student->first_name . ' ' . $student->last_name),
            ],
            [
                'student_id' => $student->student_id,
                'handled_by_user_id' => $clinic->user_id,
                'patient_type' => 'student',
                'case_type' => 'Fainting',
                'symptoms' => 'Dizziness after class activity.',
                'action_taken' => 'Observed in clinic and advised hydration.',
                'notes' => 'Demo case for clinic dashboard.',
                'status' => 'monitoring',
                'occurred_at' => now()->subHours(2),
            ],
        );

        PatientHistory::updateOrCreate(
            [
                'student_id' => $student->student_id,
                'summary' => 'Demo clinic visit after fainting alert.',
            ],
            [
                'recorded_by_user_id' => $clinic->user_id,
                'patient_type' => 'student',
                'patient_name' => trim($student->first_name . ' ' . $student->last_name),
                'notes' => 'Vital signs stable during monitoring.',
                'occurred_at' => now()->subHours(2),
            ],
        );
    }

    private function seedActivityLogs(User $admin, User $instructor): void
    {
        $logs = [
            [
                'user_id' => $admin->user_id,
                'action' => 'demo_inventory_seeded',
                'table_name' => 'inventory_items',
                'description' => 'Seeded demo equipment with available, borrowed, and maintenance statuses.',
            ],
            [
                'user_id' => $instructor->user_id,
                'action' => 'demo_schedule_ready',
                'table_name' => 'schedules',
                'description' => 'Sample instructor schedule is ready for attendance panel RFID simulation.',
            ],
        ];

        foreach ($logs as $log) {
            ActivityLog::updateOrCreate(
                [
                    'user_id' => $log['user_id'],
                    'action' => $log['action'],
                    'table_name' => $log['table_name'],
                ],
                ['description' => $log['description']],
            );
        }
    }
}
