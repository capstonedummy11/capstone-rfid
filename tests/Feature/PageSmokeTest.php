<?php

use App\Models\AcademicYear;
use App\Models\ClinicCase;
use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use App\Models\Instructor;
use App\Models\Laboratory;
use App\Models\OnlineClass;
use App\Models\PanelDevice;
use App\Models\PatientHistory;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\StudentEnrollment;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function pageSmokeFixture(): array
{
    SystemSetting::setBoolean(SystemSetting::PARENT_PORTAL_ENABLED, true);
    SystemSetting::setBoolean(SystemSetting::PARENT_EXCUSE_LETTERS_ENABLED, true);
    SystemSetting::setBoolean(SystemSetting::INVENTORY_ENABLED, true);
    SystemSetting::setBoolean(SystemSetting::ONLINE_CLASSES_ENABLED, true);
    SystemSetting::setBoolean(SystemSetting::BORROWING_ENABLED, true);

    $admin = User::factory()->create(['role' => 'admin', 'is_root_admin' => true, 'must_change_password' => false]);
    $clinic = User::factory()->create(['role' => 'clinic', 'must_change_password' => false]);
    $registrar = User::factory()->create(['role' => 'registrar', 'must_change_password' => false]);
    $console = User::factory()->create(['role' => 'console', 'must_change_password' => false]);

    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);
    $strand = Strand::query()->create([
        'strand_code' => 'SMK',
        'strand_name' => 'Smoke Test Strand',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'SMK 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ]);
    $laboratory = Laboratory::query()->create([
        'name' => 'Smoke Lab',
        'description' => 'Smoke-test laboratory.',
        'location' => 'Room 101',
        'status' => 'active',
    ]);
    PanelDevice::query()->create([
        'laboratory_id' => $laboratory->laboratory_id,
        'label' => 'Smoke Panel',
        'description' => 'Smoke-test panel device.',
        'pin_hash' => Hash::make('123456'),
        'is_active' => true,
    ]);

    $instructorUser = User::factory()->create([
        'role' => 'instructor',
        'must_change_password' => false,
        'rfid_tag' => 'RFID-SMOKE-INSTRUCTOR',
    ]);
    $instructor = Instructor::query()->create([
        'user_id' => $instructorUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'SMK-INST-001',
        'status' => 'active',
    ]);
    $subject = Subject::query()->create([
        'section_id' => $section->section_id,
        'user_id' => $instructorUser->user_id,
        'subject_name' => 'Smoke Testing',
        'subject_code' => 'SMK-101',
        'subject_description' => 'Subject for page smoke tests.',
        'department' => 'SHS',
        'unit' => 3,
        'semester' => '1st Semester',
        'year_level' => 11,
    ]);
    $offering = SubjectOffering::query()->create([
        'academic_year_id' => $year->academic_year_id,
        'semester' => '1st Semester',
        'section_id' => $section->section_id,
        'subject_id' => $subject->subject_id,
        'instructor_id' => $instructor->instructor_id,
        'status' => 'active',
    ]);
    $schedule = Schedule::query()->create([
        'academic_year_id' => $year->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => $subject->subject_code,
        'weekdays' => now()->format('l'),
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
        'room' => $laboratory->name,
        'semester' => '1st Semester',
    ]);
    $student = Students::query()->create([
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'student_number' => 'SMK-STU-001',
        'first_name' => 'Smoke',
        'last_name' => 'Student',
        'email' => 'smoke.student@example.test',
        'phone' => '09170000001',
        'gender' => 'male',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'rfid_tag' => 'RFID-SMOKE-STUDENT',
        'face_images' => [],
        'status' => 'active',
    ]);
    StudentEnrollment::query()->create([
        'student_id' => $student->student_id,
        'academic_year_id' => $year->academic_year_id,
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'status' => 'enrolled',
        'enrolled_at' => '2026-06-01',
    ]);
    $studentUser = User::factory()->create([
        'name' => 'Smoke Student',
        'email' => $student->email,
        'role' => 'student',
        'must_change_password' => false,
    ]);
    $parent = User::factory()->create(['role' => 'parent', 'must_change_password' => false]);
    $parent->linkedStudents()->attach($student->student_id, ['relationship' => 'guardian']);

    $attendanceSessionId = DB::table('attendance_sessions')->insertGetId([
        'subject_code' => $subject->subject_code,
        'schedule_id' => $schedule->scheduled_id,
        'academic_year_id' => $year->academic_year_id,
        'date' => now()->toDateString(),
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
        'status' => 'completed',
        'room' => $laboratory->name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('attendances')->insert([
        'student_id' => $student->student_id,
        'schedule_id' => $schedule->scheduled_id,
        'subject_code' => $subject->subject_code,
        'date' => now()->toDateString(),
        'time_in' => '08:00:00',
        'status' => 'present',
        'room' => $laboratory->name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    OnlineClass::query()->create([
        'schedule_id' => $schedule->scheduled_id,
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => $subject->subject_code,
        'title' => 'Smoke Online Class',
        'meeting_link' => 'https://example.test/meeting',
        'scheduled_date' => now()->toDateString(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'require_face_recognition' => false,
        'status' => 'scheduled',
        'created_by_user_id' => $admin->user_id,
        'updated_by_user_id' => $admin->user_id,
    ]);

    $emergencyType = EmergencyType::query()->create([
        'name' => 'Smoke Emergency',
        'category' => 'medical',
        'default_message' => 'Smoke test alert.',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    EmergencyHotline::query()->create([
        'emergency_type_id' => $emergencyType->emergency_type_id,
        'name' => 'Smoke Hotline',
        'phone_number' => '911',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    ClinicCase::query()->create([
        'student_id' => $student->student_id,
        'handled_by_user_id' => $clinic->user_id,
        'patient_type' => 'student',
        'patient_name' => 'Smoke Student',
        'case_type' => 'checkup',
        'symptoms' => 'Smoke test symptom',
        'action_taken' => 'Observed',
        'status' => 'open',
        'occurred_at' => now(),
    ]);
    PatientHistory::query()->create([
        'student_id' => $student->student_id,
        'recorded_by_user_id' => $clinic->user_id,
        'patient_type' => 'student',
        'patient_name' => 'Smoke Student',
        'summary' => 'Smoke history.',
        'notes' => 'No issue.',
        'occurred_at' => now(),
    ]);

    return compact(
        'admin',
        'clinic',
        'registrar',
        'console',
        'instructorUser',
        'studentUser',
        'parent',
        'year',
        'subject',
        'student',
        'attendanceSessionId',
    );
}

test('application pages do not return server errors', function () {
    $fixture = pageSmokeFixture();
    $admin = $fixture['admin'];
    $instructor = $fixture['instructorUser'];
    $clinic = $fixture['clinic'];
    $registrar = $fixture['registrar'];
    $student = $fixture['studentUser'];
    $parent = $fixture['parent'];
    $console = $fixture['console'];
    $subject = $fixture['subject'];

    $routes = [
        ['guest landing', null, route('landingPage')],
        ['guest about', null, route('about')],
        ['staff login', null, route('staff.login')],
        ['legacy staff login', null, route('staff.login.legacy')],
        ['student login alias', null, route('studentParentLogin')],
        ['register', null, route('register')],
        ['messages create', null, route('messages.create')],
        ['attendance panel login', null, route('attendanceControlPanel.login')],

        ['admin dashboard', $admin, route('admin.dashboard')],
        ['admin academic years', $admin, route('admin.academic-years.index')],
        ['admin active devices', $admin, route('admin.active-devices.index')],
        ['admin activity logs', $admin, route('admin.activity-logs.index')],
        ['admin attendance logs', $admin, route('admin.attendance.logs')],
        ['admin attendance scanner', $admin, route('admin.attendance.scanner')],
        ['admin attendance subject', $admin, route('admin.attendance.subject', $subject)],
        ['admin attendance summary', $admin, route('admin.attendance.summary', $subject)],
        ['admin attendance student', $admin, route('admin.attendance.student', [$subject, $fixture['student']])],
        ['admin attendance session', $admin, route('admin.attendance.session', [$subject, $fixture['attendanceSessionId']])],
        ['admin attendance legacy logs', $admin, route('admin.attendance.logs.legacy')],
        ['admin borrow', $admin, route('admin.borrow')],
        ['admin instructors', $admin, route('admin.instructors.index')],
        ['admin inventory', $admin, route('admin.inventory')],
        ['admin laboratories', $admin, route('admin.laboratories')],
        ['admin messages', $admin, route('admin.messages.index')],
        ['admin online class logs', $admin, route('admin.online-class-logs.index')],
        ['admin online classes', $admin, route('admin.online-classes.index')],
        ['admin rfid', $admin, route('admin.rfid')],
        ['admin schedules', $admin, route('admin.schedules.index')],
        ['admin sections', $admin, route('admin.sections.index')],
        ['admin settings', $admin, route('admin.settings.edit')],
        ['admin strands', $admin, route('admin.strands.index')],
        ['admin students', $admin, route('admin.students.index')],
        ['admin students management', $admin, route('admin.studentsManagement')],
        ['admin subjects', $admin, route('admin.subjects.index')],
        ['admin users', $admin, route('admin.users.index')],
        ['admin reports', $admin, route('reports.index')],
        ['profile settings', $admin, route('profile.edit')],
        ['password settings', $admin, route('user-password.edit')],
        ['appearance settings', $admin, route('appearance.edit')],
        ['two factor settings', $admin, route('two-factor.show')],

        ['instructor verify', $instructor, route('instructor.verify')],
        ['instructor dashboard', $instructor, route('admin.dashboard'), ['instructor_verified' => true]],
        ['instructor schedules', $instructor, route('admin.schedules.index'), ['instructor_verified' => true]],
        ['instructor students', $instructor, route('admin.students.index'), ['instructor_verified' => true]],
        ['instructor attendance logs', $instructor, route('admin.attendance.logs'), ['instructor_verified' => true]],
        ['instructor online classes', $instructor, route('admin.online-classes.index'), ['instructor_verified' => true]],
        ['instructor messages', $instructor, route('messages.index'), ['instructor_verified' => true]],
        ['instructor reports', $instructor, route('reports.index'), ['instructor_verified' => true]],

        ['clinic dashboard', $clinic, route('clinic.dashboard')],
        ['clinic case logs', $clinic, route('clinic.case-logs')],
        ['clinic patient history', $clinic, route('clinic.patient-history')],
        ['clinic reports', $clinic, route('clinic.reports')],
        ['clinic emergency hotlines', $clinic, route('clinic.emergency-hotlines.index')],
        ['clinic messages', $clinic, route('messages.index')],
        ['clinic global reports', $clinic, route('reports.index')],

        ['registrar dashboard', $registrar, route('registrar.dashboard')],
        ['registrar biometric enrollment', $registrar, route('registrar.biometric-enrollment')],
        ['registrar instructor faces', $registrar, route('registrar.instructor-face-enrollment')],
        ['registrar messages', $registrar, route('messages.index')],
        ['registrar reports', $registrar, route('reports.index')],

        ['console control panel', $console, route('attendanceControlPanel'), ['panel.room' => 'Smoke Lab']],

        ['student dashboard', $student, route('student-parent.dashboard')],
        ['student profile', $student, route('student-parent.profile.show')],
        ['student attendance', $student, route('student-parent.attendance')],
        ['student excuse letters', $student, route('student-parent.excuse-letters.index')],
        ['student messages', $student, route('messages.index')],
        ['student notifications', $student, route('student-parent.notifications.index')],
        ['student online classes', $student, route('student-parent.online-classes.index')],
        ['student reports', $student, route('reports.index')],

        ['parent dashboard', $parent, route('student-parent.dashboard')],
        ['parent profile', $parent, route('student-parent.profile.show')],
        ['parent attendance', $parent, route('student-parent.attendance')],
        ['parent excuse letters', $parent, route('student-parent.excuse-letters.index')],
        ['parent messages', $parent, route('messages.index')],
        ['parent notifications', $parent, route('student-parent.notifications.index')],
        ['parent online classes', $parent, route('student-parent.online-classes.index')],
        ['parent reports', $parent, route('reports.index')],
    ];

    $failures = [];

    foreach ($routes as $route) {
        [$label, $user, $url] = $route;
        $session = $route[3] ?? [];
        $pending = $this->withSession($session ?? []);

        if ($user) {
            $pending->actingAs($user);
        }

        $response = $pending->get($url);

        if ($response->getStatusCode() >= 500) {
            $message = $response->exception?->getMessage() ?: $response->getContent();
            $failures[] = "{$label} [{$response->getStatusCode()}] {$url}: {$message}";
        }

        if ($user && $response->getStatusCode() === 200) {
            try {
                $response->assertInertia(fn (Assert $page) => $page
                    ->has('title')
                    ->where('title', fn ($title) => is_string($title) && trim($title) !== '')
                );
            } catch (Throwable $exception) {
                $failures[] = "{$label} missing header title: {$exception->getMessage()}";
            }
        }
    }

    expect($failures)->toBe([], implode(PHP_EOL, $failures));
});
