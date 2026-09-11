<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Instructor;
use App\Models\OnlineClass;
use App\Services\OnlineClassAttendanceFinalizer;
use App\Services\AcademicYearRolloverService;
use App\Services\AcademicYearService;
use Illuminate\Support\Facades\Artisan;
use App\Models\Section;
use App\Models\Schedule;
use App\Models\Strand;
use App\Models\StudentEnrollment;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('admin can create a draft academic year', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.academic-years.store'), [
            'name' => '2026-2027',
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'active_semester' => '1st Semester',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('academic_years', [
        'name' => '2026-2027',
        'status' => AcademicYear::STATUS_DRAFT,
    ]);
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->user_id,
        'action' => 'create',
        'table_name' => 'academic_years',
    ]);
});

test('academic year name must contain consecutive years', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.academic-years.store'), [
            'name' => '2026-2028',
            'starts_on' => '2026-06-01',
            'ends_on' => '2028-03-31',
        ])
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('academic_years', 0);
});

test('activating a year closes the previously active year', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $old = AcademicYear::create([
        'name' => '2025-2026',
        'starts_on' => '2025-06-01',
        'ends_on' => '2026-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
    ]);
    $next = AcademicYear::create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_DRAFT,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.academic-years.activate', $next))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($old->fresh()->status)->toBe(AcademicYear::STATUS_CLOSED)
        ->and($next->fresh()->status)->toBe(AcademicYear::STATUS_ACTIVE)
        ->and(AcademicYear::query()->where('status', AcademicYear::STATUS_ACTIVE)->count())->toBe(1);
});

test('closed academic year can be reopened only with an audit reason', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $year = AcademicYear::create([
        'name' => '2025-2026',
        'starts_on' => '2025-06-01',
        'ends_on' => '2026-03-31',
        'status' => AcademicYear::STATUS_CLOSED,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.academic-years.reopen', $year), ['reason' => 'short'])
        ->assertSessionHasErrors('reason');

    $this->actingAs($admin)
        ->post(route('admin.academic-years.reopen', $year), [
            'reason' => 'Correct a verified historical configuration issue.',
        ])
        ->assertSessionHas('success');

    expect($year->fresh()->status)->toBe(AcademicYear::STATUS_DRAFT)
        ->and($year->fresh()->reopen_reason)->toBe('Correct a verified historical configuration issue.');
});

test('non admin cannot access academic year management', function () {
    $instructor = User::factory()->create(['role' => 'instructor']);

    $this->actingAs($instructor)
        ->get(route('admin.academic-years.index'))
        ->assertForbidden();
});

test('student placement updates create a new year enrollment without overwriting history', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $strand = Strand::create([
        'strand_code' => 'ICT-AY',
        'strand_name' => 'ICT Academic Year',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $yearASection = Section::create([
        'strand_id' => $strand->strand_id,
        'section_name' => 'ICT AY 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'status' => 'active',
    ]);
    $yearBSection = Section::create([
        'strand_id' => $strand->strand_id,
        'section_name' => 'ICT AY 12-A',
        'year_level' => 12,
        'semester' => '1st Semester',
        'school_year' => '2027-2028',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->post(route('admin.students.store'), [
        'student_number' => 'AY-STUDENT-001',
        'first_name' => 'Yearly',
        'middle_name' => null,
        'last_name' => 'Student',
        'email' => 'yearly.student@example.com',
        'phone' => null,
        'gender' => 'female',
        'strand_id' => $strand->strand_id,
        'section_id' => $yearASection->section_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'rfid_tag' => 'AY-STUDENT-RFID',
        'status' => 'active',
    ])->assertSessionHas('success');

    $student = Students::where('student_number', 'AY-STUDENT-001')->firstOrFail();
    $yearAEnrollment = StudentEnrollment::where('student_id', $student->student_id)->firstOrFail();

    $this->actingAs($admin)->put(route('admin.students.update', $student->student_id), [
        'student_number' => $student->student_number,
        'first_name' => $student->first_name,
        'middle_name' => null,
        'last_name' => $student->last_name,
        'email' => $student->email,
        'phone' => null,
        'gender' => $student->gender,
        'strand_id' => $strand->strand_id,
        'section_id' => $yearBSection->section_id,
        'year_level' => 12,
        'semester' => '1st Semester',
        'school_year' => '2027-2028',
        'rfid_tag' => $student->rfid_tag,
        'status' => 'active',
    ])->assertSessionHas('success');

    expect(StudentEnrollment::where('student_id', $student->student_id)->count())->toBe(2)
        ->and($student->fresh()->section_id)->toBeNull()
        ->and($student->fresh()->school_year)->toBeNull()
        ->and($yearAEnrollment->fresh()->section_id)->toBe($yearASection->section_id)
        ->and($yearAEnrollment->fresh()->year_level)->toBe(11)
        ->and(StudentEnrollment::where('student_id', $student->student_id)
            ->whereHas('academicYear', fn ($query) => $query->where('name', '2027-2028'))
            ->value('section_id'))->toBe($yearBSection->section_id);
});

test('same section name is allowed in different academic years but not twice in one period', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $strand = Strand::create([
        'strand_code' => 'ICT-SEC',
        'strand_name' => 'ICT Sections',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    AcademicYear::create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
    ]);
    AcademicYear::create([
        'name' => '2027-2028',
        'starts_on' => '2027-06-01',
        'ends_on' => '2028-03-31',
        'status' => AcademicYear::STATUS_DRAFT,
    ]);

    $payload = [
        'section_name' => 'ICT 11-A',
        'strand_id' => $strand->strand_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'status' => 'active',
    ];

    $this->actingAs($admin)->post(route('admin.sections.store'), $payload)->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.sections.store'), $payload)->assertSessionHasErrors('section_name');
    $this->actingAs($admin)->post(route('admin.sections.store'), [
        ...$payload,
        'school_year' => '2027-2028',
    ])->assertSessionHas('success');

    expect(Section::where('section_name', 'ICT 11-A')->count())->toBe(2);
});

test('closed year sections cannot be edited or deleted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $strand = Strand::create([
        'strand_code' => 'ICT-LOCK',
        'strand_name' => 'ICT Locked',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $year = AcademicYear::create([
        'name' => '2025-2026',
        'starts_on' => '2025-06-01',
        'ends_on' => '2026-03-31',
        'status' => AcademicYear::STATUS_CLOSED,
    ]);
    $section = Section::create([
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'Locked Section',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ]);

    $this->actingAs($admin)->put(route('admin.sections.update', $section->section_id), [
        'section_name' => 'Changed Section',
        'strand_id' => $strand->strand_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ])->assertSessionHasErrors('section');

    $this->actingAs($admin)
        ->delete(route('admin.sections.destroy', $section->section_id))
        ->assertSessionHasErrors('section');

    $this->assertDatabaseHas('sections', [
        'section_id' => $section->section_id,
        'section_name' => 'Locked Section',
    ]);
});

test('one catalog subject can have isolated offerings in multiple academic years', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $strand = Strand::create([
        'strand_code' => 'ICT-OFFER',
        'strand_name' => 'ICT Offerings',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $yearA = AcademicYear::create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
    ]);
    $yearB = AcademicYear::create([
        'name' => '2027-2028',
        'starts_on' => '2027-06-01',
        'ends_on' => '2028-03-31',
        'status' => AcademicYear::STATUS_DRAFT,
    ]);
    $sectionA = Section::create([
        'academic_year_id' => $yearA->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'Offering 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $yearA->name,
        'status' => 'active',
    ]);
    $sectionB = Section::create([
        'academic_year_id' => $yearB->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'Offering 12-A',
        'year_level' => 12,
        'semester' => '1st Semester',
        'school_year' => $yearB->name,
        'status' => 'active',
    ]);

    $this->actingAs($admin)->post(route('admin.subjects.store'), [
        'section_id' => $sectionA->section_id,
        'user_id' => null,
        'subject_name' => 'Programming Fundamentals',
        'subject_code' => 'PF-101',
        'subject_description' => null,
        'department' => 'ICT',
        'unit' => 3,
        'semester' => '1st Semester',
    ])->assertSessionHas('success');

    $subject = Subject::where('subject_code', 'PF-101')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.subjects.offerings.store', $subject), [
        'section_id' => $sectionB->section_id,
        'user_id' => null,
        'semester' => '1st Semester',
        'status' => 'active',
    ])->assertSessionHas('success');

    expect(Subject::where('subject_code', 'PF-101')->count())->toBe(1)
        ->and($subject->section_id)->toBeNull()
        ->and($subject->user_id)->toBeNull()
        ->and(SubjectOffering::where('subject_id', $subject->subject_id)->count())->toBe(2)
        ->and(SubjectOffering::where('subject_id', $subject->subject_id)->pluck('academic_year_id')->sort()->values()->all())
        ->toBe(collect([$yearA->academic_year_id, $yearB->academic_year_id])->sort()->values()->all());
});

test('closed year subject offerings are locked and protect the catalog', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $strand = Strand::create([
        'strand_code' => 'ICT-OFFER-LOCK',
        'strand_name' => 'ICT Offering Lock',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $year = AcademicYear::create([
        'name' => '2025-2026',
        'starts_on' => '2025-06-01',
        'ends_on' => '2026-03-31',
        'status' => AcademicYear::STATUS_CLOSED,
    ]);
    $section = Section::create([
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'Closed Offering Section',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ]);
    $subject = Subject::create([
        'section_id' => $section->section_id,
        'user_id' => null,
        'subject_name' => 'Historical Subject',
        'subject_code' => 'HIST-101',
        'unit' => 3,
        'semester' => '1st Semester',
    ]);
    $offering = SubjectOffering::create([
        'academic_year_id' => $year->academic_year_id,
        'subject_id' => $subject->subject_id,
        'section_id' => $section->section_id,
        'instructor_id' => null,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.subjects.offerings.destroy', $offering))
        ->assertSessionHasErrors('offering');
    $this->actingAs($admin)
        ->delete(route('admin.subjects.destroy', $subject->subject_id))
        ->assertSessionHasErrors('subject');

    $this->assertDatabaseHas('subject_offerings', ['subject_offering_id' => $offering->subject_offering_id]);
    $this->assertDatabaseHas('subjects', ['subject_id' => $subject->subject_id]);
});

test('schedule derives academic context from its selected subject offering', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $strand = Strand::create([
        'strand_code' => 'ICT-SCHED',
        'strand_name' => 'ICT Schedule',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $year = AcademicYear::create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
    ]);
    $section = Section::create([
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'Schedule 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ]);
    $subject = Subject::create([
        'subject_name' => 'Schedule Subject',
        'subject_code' => 'SCHED-101',
        'unit' => 3,
    ]);
    $offering = SubjectOffering::create([
        'academic_year_id' => $year->academic_year_id,
        'subject_id' => $subject->subject_id,
        'section_id' => $section->section_id,
        'instructor_id' => null,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->post(route('admin.schedules.store'), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => null,
        'instructor_id' => null,
        'section_id' => null,
        'subject_code' => null,
        'weekdays' => 'Mon',
        'time_start' => '08:00',
        'time_end' => '10:00',
        'room' => 'ROOM-AY',
    ])->assertSessionHas('success');

    $schedule = Schedule::firstOrFail();
    expect($schedule->academic_year_id)->toBe($year->academic_year_id)
        ->and($schedule->subject_offering_id)->toBe($offering->subject_offering_id)
        ->and($schedule->section_id)->toBe($section->section_id)
        ->and($schedule->subject_code)->toBe('SCHED-101')
        ->and($schedule->semester)->toBe('1st Semester');
});

test('closed year schedules are hidden from active scope and cannot be changed', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $strand = Strand::create([
        'strand_code' => 'ICT-SCHED-LOCK',
        'strand_name' => 'ICT Schedule Lock',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $activeYear = AcademicYear::create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
    ]);
    $closedYear = AcademicYear::create([
        'name' => '2025-2026',
        'starts_on' => '2025-06-01',
        'ends_on' => '2026-03-31',
        'status' => AcademicYear::STATUS_CLOSED,
    ]);
    $section = Section::create([
        'academic_year_id' => $closedYear->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'Closed Schedule Section',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $closedYear->name,
        'status' => 'active',
    ]);
    $subject = Subject::create(['subject_name' => 'Closed Schedule', 'subject_code' => 'CLOSED-SCHED', 'unit' => 3]);
    $offering = SubjectOffering::create([
        'academic_year_id' => $closedYear->academic_year_id,
        'subject_id' => $subject->subject_id,
        'section_id' => $section->section_id,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);
    $schedule = Schedule::create([
        'academic_year_id' => $closedYear->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'section_id' => $section->section_id,
        'subject_code' => $subject->subject_code,
        'semester' => '1st Semester',
        'weekdays' => 'Mon',
        'time_start' => '08:00:00',
        'time_end' => '10:00:00',
        'room' => 'ROOM-CLOSED',
    ]);

    expect(Schedule::forActiveAcademicYear()->whereKey($schedule->scheduled_id)->exists())->toBeFalse()
        ->and($activeYear->status)->toBe(AcademicYear::STATUS_ACTIVE);

    $this->actingAs($admin)->delete(route('admin.schedules.destroy', $schedule->scheduled_id))->assertSessionHasErrors('schedule');
    $this->assertDatabaseHas('schedules', ['scheduled_id' => $schedule->scheduled_id]);
});

test('physical attendance keeps the schedule year offering and student enrollment', function () {
    $strand = Strand::create(['strand_code' => 'ICT-ATT', 'strand_name' => 'ICT Attendance', 'department' => 'SHS', 'status' => 'active']);
    $year = AcademicYear::create(['name' => '2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => AcademicYear::STATUS_ACTIVE]);
    $section = Section::create([
        'academic_year_id' => $year->academic_year_id, 'strand_id' => $strand->strand_id,
        'section_name' => 'Attendance 11-A', 'year_level' => 11, 'semester' => '1st Semester',
        'school_year' => $year->name, 'status' => 'active',
    ]);
    $student = Students::create([
        'section_id' => $section->section_id, 'strand_id' => $strand->strand_id, 'student_number' => 'AY-ATT-001',
        'first_name' => 'Year', 'last_name' => 'Student', 'gender' => 'male', 'year_level' => 11,
        'semester' => '1st Semester', 'school_year' => $year->name, 'status' => 'active',
    ]);
    $enrollment = StudentEnrollment::create([
        'student_id' => $student->student_id, 'academic_year_id' => $year->academic_year_id,
        'section_id' => $section->section_id, 'strand_id' => $strand->strand_id, 'year_level' => 11,
        'semester' => '1st Semester', 'status' => 'active', 'enrolled_at' => '2026-06-01',
    ]);
    $subject = Subject::create(['subject_name' => 'Attendance Context', 'subject_code' => 'ATT-CTX', 'unit' => 3]);
    $offering = SubjectOffering::create([
        'academic_year_id' => $year->academic_year_id, 'subject_id' => $subject->subject_id,
        'section_id' => $section->section_id, 'semester' => '1st Semester', 'status' => 'active',
    ]);
    $schedule = Schedule::create([
        'academic_year_id' => $year->academic_year_id, 'subject_offering_id' => $offering->subject_offering_id,
        'section_id' => $section->section_id, 'subject_code' => $subject->subject_code, 'semester' => '1st Semester',
        'weekdays' => 'Mon', 'time_start' => '08:00:00', 'time_end' => '10:00:00', 'room' => 'ROOM-ATT',
    ]);

    $attendance = Attendance::create([
        'student_id' => $student->student_id, 'schedule_id' => $schedule->scheduled_id, 'date' => '2026-08-03',
        'time_in' => '08:00:00', 'status' => 'present', 'subject_code' => $subject->subject_code, 'room' => 'ROOM-ATT',
    ]);

    expect($attendance->academic_year_id)->toBe($year->academic_year_id)
        ->and($attendance->subject_offering_id)->toBe($offering->subject_offering_id)
        ->and($attendance->student_enrollment_id)->toBe($enrollment->student_enrollment_id);
});

test('online class finalization uses its historical enrollment roster', function () {
    $strand = Strand::create(['strand_code' => 'ICT-ONLINE', 'strand_name' => 'ICT Online', 'department' => 'SHS', 'status' => 'active']);
    $year = AcademicYear::create(['name' => '2025-2026', 'starts_on' => '2025-06-01', 'ends_on' => '2026-03-31', 'status' => AcademicYear::STATUS_CLOSED]);
    $section = Section::create([
        'academic_year_id' => $year->academic_year_id, 'strand_id' => $strand->strand_id,
        'section_name' => 'Online 11-A', 'year_level' => 11, 'semester' => '1st Semester',
        'school_year' => $year->name, 'status' => 'active',
    ]);
    $instructorUser = User::factory()->create(['role' => 'instructor']);
    $instructor = Instructor::create(['user_id' => $instructorUser->user_id, 'strand_id' => $strand->strand_id, 'instructor_number' => 'INS-ONLINE', 'status' => 'active']);
    $subject = Subject::create(['subject_name' => 'Historical Online', 'subject_code' => 'ONLINE-HIST', 'unit' => 3]);
    $offering = SubjectOffering::create([
        'academic_year_id' => $year->academic_year_id, 'subject_id' => $subject->subject_id, 'section_id' => $section->section_id,
        'instructor_id' => $instructor->instructor_id, 'semester' => '1st Semester', 'status' => 'active',
    ]);
    $schedule = Schedule::create([
        'academic_year_id' => $year->academic_year_id, 'subject_offering_id' => $offering->subject_offering_id,
        'instructor_id' => $instructor->instructor_id, 'section_id' => $section->section_id,
        'subject_code' => $subject->subject_code, 'semester' => '1st Semester', 'weekdays' => 'Mon',
        'time_start' => '08:00:00', 'time_end' => '09:00:00', 'room' => 'ONLINE',
    ]);
    $historicalStudent = Students::create([
        'section_id' => $section->section_id, 'strand_id' => $strand->strand_id, 'student_number' => 'ONLINE-OLD',
        'first_name' => 'Historical', 'last_name' => 'Student', 'gender' => 'female', 'year_level' => 12,
        'semester' => '1st Semester', 'school_year' => '2026-2027', 'status' => 'active',
    ]);
    $enrollment = StudentEnrollment::create([
        'student_id' => $historicalStudent->student_id, 'academic_year_id' => $year->academic_year_id,
        'section_id' => $section->section_id, 'strand_id' => $strand->strand_id, 'year_level' => 11,
        'semester' => '1st Semester', 'status' => 'active', 'enrolled_at' => '2025-06-01',
    ]);
    $currentOnlyStudent = Students::create([
        'section_id' => $section->section_id, 'strand_id' => $strand->strand_id, 'student_number' => 'ONLINE-NEW',
        'first_name' => 'Current', 'last_name' => 'Only', 'gender' => 'male', 'year_level' => 11,
        'semester' => '1st Semester', 'school_year' => '2026-2027', 'status' => 'active',
    ]);
    $class = OnlineClass::create([
        'schedule_id' => $schedule->scheduled_id, 'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id, 'subject_code' => $subject->subject_code, 'title' => 'Historical Class',
        'meeting_link' => 'https://example.com/class', 'scheduled_date' => now()->subDay()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'scheduled',
    ]);

    app(OnlineClassAttendanceFinalizer::class)->finalize($class);

    $this->assertDatabaseHas('online_class_attendances', [
        'online_class_id' => $class->online_class_id, 'student_id' => $historicalStudent->student_id,
        'academic_year_id' => $year->academic_year_id, 'subject_offering_id' => $offering->subject_offering_id,
        'student_enrollment_id' => $enrollment->student_enrollment_id, 'status' => 'absent',
    ]);
    $this->assertDatabaseMissing('online_class_attendances', [
        'online_class_id' => $class->online_class_id, 'student_id' => $currentOnlyStudent->student_id,
    ]);
});

test('academic year rollover is transactional idempotent and preserves source history', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $strand = Strand::create(['strand_code' => 'ICT-ROLL', 'strand_name' => 'ICT Rollover', 'department' => 'SHS', 'status' => 'active']);
    $source = AcademicYear::create(['name' => '2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => AcademicYear::STATUS_CLOSED]);
    $destination = AcademicYear::create(['name' => '2027-2028', 'starts_on' => '2027-06-01', 'ends_on' => '2028-03-31', 'status' => AcademicYear::STATUS_DRAFT]);
    $section = Section::create([
        'academic_year_id' => $source->academic_year_id, 'strand_id' => $strand->strand_id, 'section_name' => 'Rollover 11-A',
        'year_level' => 11, 'semester' => '1st Semester', 'school_year' => $source->name, 'status' => 'active',
    ]);
    $student = Students::create([
        'section_id' => $section->section_id, 'strand_id' => $strand->strand_id, 'student_number' => 'ROLL-001',
        'first_name' => 'Rollover', 'last_name' => 'Student', 'gender' => 'female', 'year_level' => 11,
        'semester' => '1st Semester', 'school_year' => $source->name, 'status' => 'active',
    ]);
    $sourceEnrollment = StudentEnrollment::create([
        'student_id' => $student->student_id, 'academic_year_id' => $source->academic_year_id, 'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id, 'year_level' => 11, 'semester' => '1st Semester', 'status' => 'enrolled', 'enrolled_at' => '2026-06-01',
    ]);
    $subject = Subject::create(['subject_name' => 'Rollover Subject', 'subject_code' => 'ROLL-101', 'unit' => 3]);
    $offering = SubjectOffering::create([
        'academic_year_id' => $source->academic_year_id, 'subject_id' => $subject->subject_id, 'section_id' => $section->section_id,
        'semester' => '1st Semester', 'status' => 'active',
    ]);
    $schedule = Schedule::create([
        'academic_year_id' => $source->academic_year_id, 'subject_offering_id' => $offering->subject_offering_id,
        'section_id' => $section->section_id, 'subject_code' => $subject->subject_code, 'semester' => '1st Semester',
        'weekdays' => 'Mon', 'time_start' => '08:00:00', 'time_end' => '09:00:00', 'room' => 'ROLL-LAB',
    ]);
    Attendance::create([
        'student_id' => $student->student_id, 'schedule_id' => $schedule->scheduled_id, 'date' => '2026-08-03',
        'time_in' => '08:00:00', 'status' => 'present', 'subject_code' => $subject->subject_code, 'room' => 'ROLL-LAB',
    ]);

    $service = app(AcademicYearRolloverService::class);
    $preview = $service->preview($source, $destination);
    expect($preview['counts']['students'])->toBe(1);
    expect(DB::table('academic_year_rollovers')->count())->toBe(0);

    $mapping = [['source_section_id' => $section->section_id, 'destination_name' => 'Rollover 12-A', 'destination_year_level' => 12]];
    $decision = [['source_student_enrollment_id' => $sourceEnrollment->student_enrollment_id, 'decision' => 'promote']];
    $first = $service->execute($source, $destination, $admin, $decision, $mapping);
    $second = $service->execute($source, $destination, $admin, $decision, $mapping);

        expect($second->academic_year_rollover_id)->toBe($first->academic_year_rollover_id)
        ->and(StudentEnrollment::where('student_id', $student->student_id)->where('academic_year_id', $destination->academic_year_id)->count())->toBe(1)
        ->and(Schedule::where('academic_year_id', $destination->academic_year_id)->count())->toBe(0)
        ->and(Attendance::where('academic_year_id', $destination->academic_year_id)->count())->toBe(0)
        ->and(Attendance::where('academic_year_id', $source->academic_year_id)->count())->toBe(1);

    app(AcademicYearService::class)->activate($destination, $admin);
    expect(AcademicYear::where('status', AcademicYear::STATUS_ACTIVE)->count())->toBe(1)
        ->and(Schedule::forActiveAcademicYear()->count())->toBe(0)
        ->and(Artisan::call('academic-years:check-integrity', ['--json' => true]))->toBe(0);
});

test('unauthorized users cannot mutate or reopen academic years', function () {
    $student = User::factory()->create(['role' => 'student']);
    $year = AcademicYear::create(['name' => '2025-2026', 'starts_on' => '2025-06-01', 'ends_on' => '2026-03-31', 'status' => AcademicYear::STATUS_CLOSED]);

    $this->actingAs($student)->post(route('admin.academic-years.reopen', $year), ['reason' => 'Unauthorized attempted reopen.'])->assertForbidden();
    $this->actingAs($student)->post(route('admin.academic-years.activate', $year))->assertForbidden();
    expect($year->fresh()->status)->toBe(AcademicYear::STATUS_CLOSED);
});
