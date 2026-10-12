<?php

use App\Models\AcademicYear;
use App\Models\Instructor;
use App\Models\OnlineClass;
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
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('online class created by admin is shown to an enrolled student', function () {
    Mail::fake();
    SystemSetting::setBoolean(SystemSetting::PARENT_PORTAL_ENABLED, true);
    SystemSetting::setBoolean(SystemSetting::ONLINE_CLASSES_ENABLED, true);

    $admin = User::factory()->create(['role' => 'admin']);
    $academicYear = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);
    $strand = Strand::query()->create([
        'strand_code' => 'OCW',
        'strand_name' => 'Online Class Workflow',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'academic_year_id' => $academicYear->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'OCW 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $academicYear->name,
        'status' => 'active',
    ]);
    $instructorUser = User::factory()->create([
        'name' => 'Online Workflow Instructor',
        'role' => 'instructor',
        'must_change_password' => false,
    ]);
    $instructor = Instructor::query()->create([
        'user_id' => $instructorUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'OCW-INST-001',
        'status' => 'active',
    ]);
    $subject = Subject::query()->create([
        'subject_name' => 'Online Workflow Subject',
        'subject_code' => 'OCW-101',
        'unit' => 3,
    ]);
    $offering = SubjectOffering::query()->create([
        'academic_year_id' => $academicYear->academic_year_id,
        'subject_id' => $subject->subject_id,
        'section_id' => $section->section_id,
        'instructor_id' => $instructor->instructor_id,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);
    $schedule = Schedule::query()->create([
        'academic_year_id' => $academicYear->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => $subject->subject_code,
        'semester' => '1st Semester',
        'weekdays' => 'Mon',
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
        'room' => 'OCW Virtual Room',
    ]);
    $student = Students::query()->create([
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'student_number' => 'OCW-STU-001',
        'first_name' => 'Online',
        'last_name' => 'Student',
        'email' => 'online.student@example.test',
        'gender' => 'female',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $academicYear->name,
        'rfid_tag' => 'OCW-RFID-001',
        'status' => 'active',
    ]);
    $studentUser = User::factory()->create([
        'name' => 'Online Student',
        'email' => $student->email,
        'role' => 'student',
        'must_change_password' => false,
    ]);
    StudentEnrollment::query()->create([
        'student_id' => $student->student_id,
        'academic_year_id' => $academicYear->academic_year_id,
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'status' => 'enrolled',
        'enrolled_at' => $academicYear->starts_on,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.online-classes.store'), [
            'schedule_id' => $schedule->scheduled_id,
            'title' => 'Shared Online Class Session',
            'description' => 'Created from the staff page for enrolled students.',
            'meeting_link' => 'https://example.test/shared-online-class',
            'scheduled_date' => now()->addDays(7)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'require_face_recognition' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $onlineClass = OnlineClass::query()->where('title', 'Shared Online Class Session')->firstOrFail();
    $this->assertDatabaseHas('online_classes', [
        'online_class_id' => $onlineClass->online_class_id,
        'schedule_id' => $schedule->scheduled_id,
        'academic_year_id' => $academicYear->academic_year_id,
        'section_id' => $section->section_id,
        'title' => 'Shared Online Class Session',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.online-classes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shared/OnlineClasses/OnlineClassesPage')
            ->has('onlineClasses', 1)
            ->where('onlineClasses.0.online_class_id', $onlineClass->online_class_id)
            ->where('onlineClasses.0.title', 'Shared Online Class Session')
            ->where('onlineClasses.0.section_name', 'OCW 11-A')
        );

    $this->actingAs($studentUser)
        ->get(route('student-parent.online-classes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/OnlineClasses/OnlineClassesPage')
            ->where('student.student_number', 'OCW-STU-001')
            ->has('onlineClasses', 1)
            ->where('onlineClasses.0.online_class_id', $onlineClass->online_class_id)
            ->where('onlineClasses.0.title', 'Shared Online Class Session')
            ->where('onlineClasses.0.subject_name', 'Online Workflow Subject')
            ->where('onlineClasses.0.instructor_name', 'Online Workflow Instructor')
            ->where('onlineClasses.0.can_join', true)
        );
});
