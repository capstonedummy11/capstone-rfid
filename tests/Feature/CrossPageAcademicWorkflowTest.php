<?php

use App\Models\AcademicYear;
use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use App\Models\EmergencyType;
use App\Models\Instructor;
use App\Models\Laboratory;
use App\Models\PatientHistory;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\StudentEnrollment;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('admin can create a strand entity and reuse it on connected pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.strands.store'), [
            'strand_code' => 'ENT',
            'strand_name' => 'Entity Creation Strand',
            'department' => 'SHS',
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('strands', [
        'strand_code' => 'ENT',
        'strand_name' => 'Entity Creation Strand',
        'department' => 'SHS',
        'status' => 'active',
    ]);

    $strand = Strand::query()->where('strand_code', 'ENT')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.strands.index', ['search' => 'ENT']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Strands')
            ->has('strands', 1)
            ->where('strands.0.strand_code', 'ENT')
            ->where('strands.0.strand_name', 'Entity Creation Strand')
        );

    $this->actingAs($admin)
        ->get(route('admin.sections.index', ['strand' => $strand->strand_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Sections')
            ->has('strandOptions', 1)
            ->where('strandOptions.0.strand_id', $strand->strand_id)
            ->where('strandOptions.0.strand_code', 'ENT')
            ->where('strandOptions.0.strand_name', 'Entity Creation Strand')
        );

    $this->actingAs($admin)
        ->get(route('admin.students.index', ['strand' => $strand->strand_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Students')
            ->has('strandOptions', 1)
            ->where('strandOptions.0.strand_id', $strand->strand_id)
            ->where('strandOptions.0.strand_code', 'ENT')
            ->where('strandOptions.0.strand_name', 'Entity Creation Strand')
        );

    $this->actingAs($admin)
        ->get(route('admin.instructors.index', ['strand' => 'ENT']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Instructors')
            ->has('strands', 1)
            ->where('strands.0.strand_id', $strand->strand_id)
            ->where('strands.0.strand_code', 'ENT')
            ->where('strands.0.strand_name', 'Entity Creation Strand')
        );
});

test('admin-created academic setup data is reused across strand section student and subject pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.strands.store'), [
            'strand_code' => 'XPT',
            'strand_name' => 'Cross Page Testing',
            'department' => 'SHS',
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $strand = Strand::query()->where('strand_code', 'XPT')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.sections.index', ['strand' => $strand->strand_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Sections')
            ->has('strandOptions', 1)
            ->where('strandOptions.0.strand_id', $strand->strand_id)
            ->where('strandOptions.0.strand_code', 'XPT')
        );

    $this->actingAs($admin)
        ->post(route('admin.sections.store'), [
            'section_name' => 'XPT 11-A',
            'strand_id' => $strand->strand_id,
            'year_level' => 11,
            'semester' => '1st Semester',
            'school_year' => $year->name,
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $section = Section::query()->where('section_name', 'XPT 11-A')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.students.index', [
            'strand' => $strand->strand_id,
            'section' => $section->section_id,
            'school_year' => $year->name,
            'semester' => '1st Semester',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Students')
            ->has('strandOptions', 1)
            ->where('strandOptions.0.strand_id', $strand->strand_id)
            ->where('strandOptions.0.strand_code', 'XPT')
            ->has('sectionOptions', 1)
            ->where('sectionOptions.0.section_id', $section->section_id)
            ->where('sectionOptions.0.strand_id', $strand->strand_id)
            ->where('sectionOptions.0.section_name', 'XPT 11-A')
        );

    $this->actingAs($admin)
        ->post(route('admin.students.store'), [
            'student_number' => 'XPT-2026-001',
            'first_name' => 'Cross',
            'middle_name' => null,
            'last_name' => 'Page',
            'email' => 'cross.page.student@example.test',
            'phone' => null,
            'gender' => 'female',
            'strand_id' => $strand->strand_id,
            'section_id' => $section->section_id,
            'year_level' => 11,
            'semester' => '1st Semester',
            'school_year' => $year->name,
            'rfid_tag' => 'XPT-RFID-001',
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $student = Students::query()->where('student_number', 'XPT-2026-001')->firstOrFail();

    $this->assertDatabaseHas('student_enrollments', [
        'student_id' => $student->student_id,
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_id' => $section->section_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'status' => 'enrolled',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.students.index', [
            'search' => 'XPT-2026-001',
            'school_year' => $year->name,
            'semester' => '1st Semester',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Students')
            ->has('students', 1)
            ->where('students.0.student_number', 'XPT-2026-001')
            ->where('students.0.strand_id', $strand->strand_id)
            ->where('students.0.strand_code', 'XPT')
            ->where('students.0.section_id', $section->section_id)
            ->where('students.0.section_name', 'XPT 11-A')
            ->where('students.0.school_year', $year->name)
        );

    $this->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'section_id' => $section->section_id,
            'user_id' => null,
            'subject_name' => 'Cross Page Subject',
            'subject_code' => 'XPT-101',
            'subject_description' => 'Created from the shared cross-page academic setup test.',
            'department' => 'SHS',
            'unit' => 3,
            'semester' => '1st Semester',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $subject = Subject::query()->where('subject_code', 'XPT-101')->firstOrFail();

    $this->assertDatabaseHas('subject_offerings', [
        'subject_id' => $subject->subject_id,
        'academic_year_id' => $year->academic_year_id,
        'section_id' => $section->section_id,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.subjects.index', [
            'search' => 'XPT-101',
            'academic_year_id' => $year->academic_year_id,
            'semester' => '1st Semester',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Subjects')
            ->has('subjects', 1)
            ->where('subjects.0.subject_code', 'XPT-101')
            ->where('subjects.0.offerings.0.academic_year', $year->name)
            ->where('subjects.0.offerings.0.section_id', $section->section_id)
            ->where('subjects.0.offerings.0.section_name', 'XPT 11-A')
            ->where('sectionOptions.0.section_id', $section->section_id)
            ->where('sectionOptions.0.academic_year_id', $year->academic_year_id)
            ->where('sectionOptions.0.year_level', 11)
        );

    expect(StudentEnrollment::query()->where('student_id', $student->student_id)->count())->toBe(1)
        ->and(SubjectOffering::query()->where('subject_id', $subject->subject_id)->count())->toBe(1);
});

test('instructor subject offering and schedule share the same academic context across pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);
    $strand = Strand::query()->create([
        'strand_code' => 'TIS',
        'strand_name' => 'Teaching Integration Strand',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'TIS 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ]);
    $laboratory = Laboratory::query()->create([
        'name' => 'Cross Page Lab',
        'description' => 'Lab used by cross-page workflow tests.',
        'location' => 'Room 101',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.instructors.store'), [
            'instructor_number' => 'TIS-INST-001',
            'first_name' => 'Teaching',
            'middle_name' => null,
            'last_name' => 'Instructor',
            'email' => 'teaching.instructor@example.test',
            'phone' => '09170000001',
            'gender' => 'male',
            'strand_id' => $strand->strand_id,
            'rfid_tag' => 'TIS-INST-RFID',
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $instructor = Instructor::query()
        ->with('user')
        ->where('instructor_number', 'TIS-INST-001')
        ->firstOrFail();
    $instructor->user->update(['must_change_password' => false]);

    $this->actingAs($admin)
        ->get(route('admin.instructors.index', ['strand' => 'TIS']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Instructors')
            ->has('instructors', 1)
            ->where('instructors.0.instructor_number', 'TIS-INST-001')
            ->where('instructors.0.strand_id', $strand->strand_id)
            ->where('instructors.0.strand_code', 'TIS')
            ->where('instructors.0.email', 'teaching.instructor@example.test')
        );

    $this->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'section_id' => $section->section_id,
            'user_id' => $instructor->user_id,
            'subject_name' => 'Teaching Integration Subject',
            'subject_code' => 'TIS-101',
            'subject_description' => 'Subject used by cross-page workflow tests.',
            'department' => 'SHS',
            'unit' => 3,
            'semester' => '1st Semester',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $subject = Subject::query()->where('subject_code', 'TIS-101')->firstOrFail();
    $offering = SubjectOffering::query()
        ->where('subject_id', $subject->subject_id)
        ->where('section_id', $section->section_id)
        ->firstOrFail();

    expect((int) $offering->instructor_id)->toBe((int) $instructor->instructor_id)
        ->and((int) $offering->academic_year_id)->toBe((int) $year->academic_year_id);

    $this->actingAs($admin)
        ->get(route('admin.subjects.index', [
            'search' => 'TIS-101',
            'academic_year_id' => $year->academic_year_id,
            'semester' => '1st Semester',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Subjects')
            ->has('subjects', 1)
            ->where('subjects.0.subject_code', 'TIS-101')
            ->where('subjects.0.offerings.0.section_id', $section->section_id)
            ->where('subjects.0.offerings.0.instructor_id', $instructor->instructor_id)
            ->where('subjects.0.offerings.0.instructor_name', 'Teaching')
            ->where('instructorOptions.0.user_id', $instructor->user_id)
            ->where('instructorOptions.0.name', 'Teaching')
        );

    $this->actingAs($admin)
        ->post(route('admin.schedules.store'), [
            'subject_offering_id' => $offering->subject_offering_id,
            'laboratory_id' => $laboratory->laboratory_id,
            'instructor_id' => $instructor->instructor_id,
            'section_id' => $section->section_id,
            'subject_code' => 'TIS-101',
            'weekdays' => 'Mon',
            'time_start' => '08:00',
            'time_end' => '09:00',
            'room' => 'Room 101',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $schedule = Schedule::query()->where('subject_offering_id', $offering->subject_offering_id)->firstOrFail();

    $this->assertDatabaseHas('schedules', [
        'scheduled_id' => $schedule->scheduled_id,
        'academic_year_id' => $year->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => 'TIS-101',
        'semester' => '1st Semester',
        'laboratory_id' => $laboratory->laboratory_id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.schedules.index', [
            'academic_year_id' => $year->academic_year_id,
            'semester' => '1st Semester',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Schedules')
            ->has('schedules', 1)
            ->where('schedules.0.scheduled_id', $schedule->scheduled_id)
            ->where('schedules.0.academic_year_id', $year->academic_year_id)
            ->where('schedules.0.subject_offering_id', $offering->subject_offering_id)
            ->where('schedules.0.instructor_id', $instructor->instructor_id)
            ->where('schedules.0.instructor_name', 'Teaching')
            ->where('schedules.0.section_id', $section->section_id)
            ->where('schedules.0.section_name', 'TIS 11-A')
            ->where('schedules.0.subject_code', 'TIS-101')
            ->where('schedules.0.laboratory_id', $laboratory->laboratory_id)
            ->where('subjectOfferingOptions.0.subject_offering_id', $offering->subject_offering_id)
            ->where('subjectOfferingOptions.0.instructor_id', $instructor->instructor_id)
        );

    $this->actingAs($instructor->user)
        ->withSession(['instructor_verified' => true])
        ->get(route('admin.schedules.index', [
            'academic_year_id' => $year->academic_year_id,
            'semester' => '1st Semester',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Schedules')
            ->where('currentUserRole', 'instructor')
            ->where('canManageSchedules', false)
            ->has('schedules', 1)
            ->where('schedules.0.scheduled_id', $schedule->scheduled_id)
            ->where('schedules.0.instructor_id', $instructor->instructor_id)
        );
});

test('admin instructor page ignores instructor rows whose user account was removed', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $faculty = User::factory()->create(['role' => 'instructor']);
    $strand = Strand::query()->create([
        'strand_code' => 'ORPH',
        'strand_name' => 'Orphan Guard Strand',
        'department' => 'SHS',
        'status' => 'active',
    ]);

    Instructor::query()->create([
        'user_id' => $faculty->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'ORPH-INST-001',
        'status' => 'active',
    ]);

    $faculty->delete();

    $this->actingAs($admin)
        ->get(route('admin.instructors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Instructors')
            ->has('instructors', 0));
});

test('admin managed clinic account is reused by clinic dashboard case logs and patient history pages', function () {
    $root = User::factory()->create([
        'role' => 'admin',
        'is_root_admin' => true,
    ]);
    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);
    $strand = Strand::query()->create([
        'strand_code' => 'CLN',
        'strand_name' => 'Clinic Cross Page',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'CLN 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ]);
    $student = Students::query()->create([
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'student_number' => 'CLN-001',
        'first_name' => 'Clinic',
        'last_name' => 'Student',
        'email' => 'clinic.student@example.test',
        'gender' => 'female',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
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
    ]);

    $this->actingAs($root)
        ->post(route('admin.users.store'), [
            'name' => 'Clinic Cross Page',
            'email' => 'clinic.cross.page@example.test',
            'password' => 'password123',
            'role' => 'clinic',
            'phone' => '09170000002',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $clinic = User::query()->where('email', 'clinic.cross.page@example.test')->firstOrFail();
    $clinic->update(['must_change_password' => false]);

    $this->actingAs($root)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/UserManagement')
            ->where('stats.clinic', 1)
            ->where('users.1.email', 'clinic.cross.page@example.test')
            ->where('users.1.role', 'clinic')
        );

    $type = EmergencyType::query()->create([
        'name' => 'Clinic Cross Page Emergency',
        'category' => 'medical',
        'default_message' => 'Clinic cross-page test emergency.',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $type->emergency_type_id,
        'triggered_by_user_id' => $root->user_id,
        'room' => 'Clinic Test Room',
        'subject_code' => null,
        'triggered_by_name' => $root->name,
        'severity' => 'urgent',
        'status' => 'open',
        'message' => 'Clinic cross-page alert.',
        'metadata' => ['student_id' => $student->student_id],
    ]);

    $this->actingAs($clinic)
        ->post(route('clinic.case-logs.store'), [
            'emergency_alert_id' => $alert->emergency_alert_id,
            'student_id' => $student->student_id,
            'user_id' => $clinic->user_id,
            'patient_type' => 'student',
            'patient_name' => 'Clinic Student',
            'case_type' => 'Dizziness',
            'symptoms' => 'Reported dizziness during class.',
            'action_taken' => 'Observed and hydrated.',
            'notes' => 'Cross-page clinic case.',
            'status' => 'monitoring',
            'occurred_at' => '2026-09-16 09:30:00',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $case = ClinicCase::query()->where('patient_name', 'Clinic Student')->firstOrFail();

    $this->actingAs($clinic)
        ->get(route('clinic.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Dashboard')
            ->where('currentUser.email', 'clinic.cross.page@example.test')
            ->where('clinicAccounts.0.email', 'clinic.cross.page@example.test')
            ->has('assignedDispatches', 1)
            ->where('assignedDispatches.0.case_id', $case->clinic_case_id)
            ->where('assignedDispatches.0.assigned_responder_email', 'clinic.cross.page@example.test')
            ->where('assignedDispatches.0.patient_name', 'Clinic Student')
        );

    $this->actingAs($clinic)
        ->get(route('clinic.case-logs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/CaseLogs')
            ->has('cases', 1)
            ->where('cases.0.id', $case->clinic_case_id)
            ->where('cases.0.student_id', $student->student_id)
            ->where('cases.0.user_id', $clinic->user_id)
            ->where('cases.0.assigned_responder_email', 'clinic.cross.page@example.test')
            ->where('cases.0.alert', 'Clinic Cross Page Emergency')
        );

    $this->actingAs($clinic)
        ->post(route('clinic.case-logs.history', $case->clinic_case_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $history = PatientHistory::query()
        ->where('student_id', $student->student_id)
        ->where('recorded_by_user_id', $clinic->user_id)
        ->firstOrFail();

    $this->actingAs($clinic)
        ->get(route('clinic.patient-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/PatientHistory')
            ->has('histories', 1)
            ->where('histories.0.id', $history->patient_history_id)
            ->where('histories.0.student_id', $student->student_id)
            ->where('histories.0.patient_name', 'Clinic Student')
            ->where('histories.0.summary', 'Dizziness: Reported dizziness during class.')
            ->has('recentCases', 1)
            ->where('recentCases.0.id', $case->clinic_case_id)
            ->where('recentCases.0.patient_name', 'Clinic Student')
        );
});
