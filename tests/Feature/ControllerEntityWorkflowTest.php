<?php

use App\Models\AcademicYear;
use App\Models\ClinicCase;
use App\Models\Device;
use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use App\Models\Instructor;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Laboratory;
use App\Models\Message;
use App\Models\OnlineClass;
use App\Models\PanelDevice;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function controllerEntityAcademicContext(): array
{
    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);
    $strand = Strand::query()->create([
        'strand_code' => 'CTRL',
        'strand_name' => 'Controller Test Strand',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'academic_year_id' => $year->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'CTRL 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ]);

    return compact('year', 'strand', 'section');
}

test('academic year controller creates updates and changes lifecycle state', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.academic-years.store'), [
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'active_semester' => '1st Semester',
    ])->assertRedirect()->assertSessionHas('success');

    $year = AcademicYear::query()->where('name', '2026-2027')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.academic-years.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/AcademicYears')
            ->where('academicYears.0.name', '2026-2027'));

    $this->actingAs($admin)->put(route('admin.academic-years.update', $year), [
        'name' => '2026-2027',
        'starts_on' => '2026-06-03',
        'ends_on' => '2027-03-30',
        'active_semester' => '2nd Semester',
    ])->assertRedirect()->assertSessionHas('success');

    expect($year->fresh()->starts_on->toDateString())->toBe('2026-06-03')
        ->and($year->fresh()->active_semester)->toBe('2nd Semester');

    $this->actingAs($admin)->post(route('admin.academic-years.activate', $year))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.academic-years.close', $year))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.academic-years.reopen', $year), [
        'reason' => 'Controller entity workflow test verified reopening.',
    ])->assertRedirect()->assertSessionHas('success');

    expect($year->fresh()->status)->toBe(AcademicYear::STATUS_DRAFT);
});

test('strand controller creates then reuses the strand for update index and delete', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.strands.store'), [
        'strand_code' => 'ENT',
        'strand_name' => 'Entity Strand',
        'department' => 'SHS',
        'status' => 'active',
    ])->assertRedirect()->assertSessionHas('success');

    $strand = Strand::query()->where('strand_code', 'ENT')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.strands.index', ['search' => 'ENT']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Strands')
            ->where('strands.0.strand_id', $strand->strand_id));

    $this->actingAs($admin)->put(route('admin.strands.update', $strand->strand_id), [
        'strand_code' => 'ENT2',
        'strand_name' => 'Entity Strand Updated',
        'department' => 'Senior High',
        'status' => 'inactive',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('strands', [
        'strand_id' => $strand->strand_id,
        'strand_code' => 'ENT2',
        'status' => 'inactive',
    ]);

    $this->actingAs($admin)->delete(route('admin.strands.destroy', $strand->strand_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertSoftDeleted('strands', ['strand_id' => $strand->strand_id]);
});

test('section controller creates then reuses the section for index update and delete', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $year = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
    ]);
    $strand = Strand::query()->create([
        'strand_code' => 'SEC',
        'strand_name' => 'Section Strand',
        'department' => 'SHS',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->post(route('admin.sections.store'), [
        'section_name' => 'SEC 11-A',
        'strand_id' => $strand->strand_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'active',
    ])->assertRedirect()->assertSessionHas('success');

    $section = Section::query()->where('section_name', 'SEC 11-A')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.sections.index', ['search' => 'SEC 11-A']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Sections')
            ->where('sections.0.section_id', $section->section_id));

    $this->actingAs($admin)->put(route('admin.sections.update', $section->section_id), [
        'section_name' => 'SEC 11-B',
        'strand_id' => $strand->strand_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'status' => 'inactive',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('sections', [
        'section_id' => $section->section_id,
        'section_name' => 'SEC 11-B',
        'status' => 'inactive',
    ]);

    $this->actingAs($admin)->delete(route('admin.sections.destroy', $section->section_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('sections', ['section_id' => $section->section_id]);
});

test('subject controller creates catalog subject offering then updates removes offering and deletes catalog', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ['year' => $year, 'section' => $section] = controllerEntityAcademicContext();

    $this->actingAs($admin)->post(route('admin.subjects.store'), [
        'section_id' => $section->section_id,
        'user_id' => null,
        'subject_name' => 'Controller Subject',
        'subject_code' => 'CTRL-101',
        'subject_description' => 'Initial subject',
        'department' => 'ICT',
        'unit' => 3,
        'semester' => '1st Semester',
    ])->assertRedirect()->assertSessionHas('success');

    $subject = Subject::query()->where('subject_code', 'CTRL-101')->firstOrFail();
    $offering = SubjectOffering::query()->where('subject_id', $subject->subject_id)->firstOrFail();

    $this->actingAs($admin)->get(route('admin.subjects.index', ['search' => 'CTRL-101']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Subjects')
            ->where('subjects.0.subject_id', $subject->subject_id)
            ->where('subjects.0.offerings.0.academic_year', $year->name));

    $this->actingAs($admin)->put(route('admin.subjects.update', $subject->subject_id), [
        'section_id' => $section->section_id,
        'user_id' => null,
        'subject_name' => 'Controller Subject Updated',
        'subject_code' => 'CTRL-102',
        'subject_description' => 'Updated subject',
        'department' => 'ICT',
        'unit' => 4,
        'semester' => '1st Semester',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('subjects', [
        'subject_id' => $subject->subject_id,
        'subject_code' => 'CTRL-102',
        'unit' => 4,
    ]);

    $this->actingAs($admin)->delete(route('admin.subjects.offerings.destroy', $offering))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->actingAs($admin)->delete(route('admin.subjects.destroy', $subject->subject_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('subjects', ['subject_id' => $subject->subject_id]);
});

test('instructor controller creates linked user then updates index and deletes through the user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ['strand' => $strand] = controllerEntityAcademicContext();

    $this->actingAs($admin)->post(route('admin.instructors.store'), [
        'instructor_number' => 'INS-CTRL-001',
        'first_name' => 'Control Name',
        'middle_name' => null,
        'last_name' => 'Instructor',
        'email' => 'control.instructor@example.test',
        'phone' => '09170000001',
        'gender' => 'male',
        'strand_id' => $strand->strand_id,
        'rfid_tag' => 'INS-CTRL-RFID',
        'status' => 'active',
    ])->assertRedirect()->assertSessionHas(
        'success',
        fn (string $message) => str_contains($message, 'controlnameinstructor')
    );

    $instructor = Instructor::query()->with('user')->where('instructor_number', 'INS-CTRL-001')->firstOrFail();
    expect(Hash::check('controlnameinstructor', $instructor->user->password))->toBeTrue()
        ->and($instructor->user->must_change_password)->toBeTrue();

    $this->actingAs($admin)->get(route('admin.instructors.index', ['search' => 'INS-CTRL-001']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Instructors')
            ->where('instructors.0.instructor_id', $instructor->instructor_id));

    $this->actingAs($admin)->put(route('admin.instructors.update', $instructor->instructor_id), [
        'instructor_number' => 'INS-CTRL-002',
        'first_name' => 'Control',
        'middle_name' => 'Middle',
        'last_name' => 'Instructor',
        'email' => 'control.instructor.updated@example.test',
        'phone' => '09170000002',
        'gender' => 'male',
        'strand_id' => $strand->strand_id,
        'rfid_tag' => 'INS-CTRL-RFID-2',
        'status' => 'on_leave',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('instructors', [
        'instructor_id' => $instructor->instructor_id,
        'instructor_number' => 'INS-CTRL-002',
        'status' => 'on_leave',
    ]);
    $this->assertDatabaseHas('users', [
        'user_id' => $instructor->user_id,
        'email' => 'control.instructor.updated@example.test',
        'rfid_tag' => 'INS-CTRL-RFID-2',
    ]);

    $instructor->user->forceFill([
        'password' => Hash::make('private-instructor-password'),
        'must_change_password' => false,
        'remember_token' => 'existing-remember-token',
    ])->save();
    DB::table('sessions')->insert([
        'id' => 'instructor-session-to-revoke',
        'user_id' => $instructor->user_id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Feature test',
        'payload' => 'test-session-payload',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.instructors.password.reset-default', $instructor->instructor_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $instructor->user->refresh();
    expect(Hash::check('controlinstructor', $instructor->user->password))->toBeTrue()
        ->and($instructor->user->must_change_password)->toBeTrue()
        ->and($instructor->user->remember_token)->not->toBe('existing-remember-token');
    $this->assertDatabaseMissing('sessions', ['id' => 'instructor-session-to-revoke']);

    $instructor->user->forceFill([
        'security_question' => 'What was the name of your first school?',
        'security_answer_hash' => Hash::make('north high'),
        'security_questions' => [
            ['question' => 'What was the name of your first school?', 'answer_hash' => Hash::make('north high')],
            ['question' => 'What is your mother\'s maiden name?', 'answer_hash' => Hash::make('rivera')],
            ['question' => 'What was the name of your first pet?', 'answer_hash' => Hash::make('buddy')],
        ],
        'remember_token' => 'security-question-remember-token',
    ])->save();
    DB::table('sessions')->insert([
        'id' => 'security-question-session-to-revoke',
        'user_id' => $instructor->user_id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Feature test',
        'payload' => 'test-session-payload',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.instructors.security-questions.reset', $instructor->instructor_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $instructor->user->refresh();
    expect($instructor->user->security_question)->toBeNull()
        ->and($instructor->user->security_answer_hash)->toBeNull()
        ->and($instructor->user->security_questions)->toBeNull()
        ->and($instructor->user->remember_token)->not->toBe('security-question-remember-token');
    $this->assertDatabaseMissing('sessions', ['id' => 'security-question-session-to-revoke']);

    $this->actingAs($admin)->delete(route('admin.instructors.destroy', $instructor->instructor_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertSoftDeleted('users', ['user_id' => $instructor->user_id]);
});

test('schedule controller creates from offering then updates indexes and deletes the schedule', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ['section' => $section, 'strand' => $strand] = controllerEntityAcademicContext();
    $laboratory = Laboratory::query()->create([
        'name' => 'Schedule Lab',
        'description' => 'Schedule controller test lab.',
        'location' => 'A101',
        'status' => 'active',
    ]);
    $subject = Subject::query()->create([
        'subject_name' => 'Schedule Subject',
        'subject_code' => 'SCHED-CTRL',
        'unit' => 3,
    ]);
    $instructorUser = User::factory()->create(['role' => 'instructor']);
    $instructor = Instructor::query()->create([
        'user_id' => $instructorUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'INS-SCHED-CTRL',
        'status' => 'active',
    ]);
    $offering = SubjectOffering::query()->create([
        'academic_year_id' => $section->academic_year_id,
        'subject_id' => $subject->subject_id,
        'section_id' => $section->section_id,
        'instructor_id' => $instructor->instructor_id,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);

    $this->actingAs($admin)->post(route('admin.schedules.store'), [
        'subject_offering_id' => null,
        'laboratory_id' => $laboratory->laboratory_id,
        'section_id' => null,
        'subject_code' => null,
        'weekdays' => '',
        'time_start' => '',
        'time_end' => '',
    ])->assertRedirect()->assertSessionHasErrors([
        'section_id',
        'subject_code',
        'weekdays',
        'time_start',
        'time_end',
    ]);
    $this->assertDatabaseCount('schedules', 0);

    $this->actingAs($admin)->post(route('admin.schedules.store'), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'weekdays' => 'Mon',
        'time_start' => '08:10',
        'time_end' => '09:10',
        'room' => 'A101',
    ])->assertRedirect()->assertSessionHasErrors(['time_start', 'time_end']);
    $this->assertDatabaseCount('schedules', 0);

    $this->actingAs($admin)->post(route('admin.schedules.store'), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'weekdays' => 'Tue',
        'time_start' => '08:15',
        'time_end' => '09:45',
        'room' => 'A101',
    ])->assertRedirect()->assertSessionHas('success');

    $quarterHourSchedule = Schedule::query()->firstOrFail();
    $this->assertDatabaseHas('schedules', [
        'scheduled_id' => $quarterHourSchedule->scheduled_id,
        'weekdays' => 'Tue',
        'time_start' => '08:15:00',
        'time_end' => '09:45:00',
    ]);

    $this->actingAs($admin)->put(route('admin.schedules.update', $quarterHourSchedule->scheduled_id), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'weekdays' => 'Wed',
        'time_start' => '08:45',
        'time_end' => '10:15',
        'room' => 'A101',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('schedules', [
        'scheduled_id' => $quarterHourSchedule->scheduled_id,
        'weekdays' => 'Wed',
        'time_start' => '08:45:00',
        'time_end' => '10:15:00',
    ]);

    $this->actingAs($admin)->delete(route('admin.schedules.destroy', $quarterHourSchedule->scheduled_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertDatabaseCount('schedules', 0);

    $this->actingAs($admin)->post(route('admin.schedules.store'), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => null,
        'section_id' => null,
        'subject_code' => null,
        'weekdays' => 'Mon',
        'time_start' => '08:00',
        'time_end' => '09:00',
        'room' => 'A101',
    ])->assertRedirect()->assertSessionHas('success');

    $schedule = Schedule::query()->where('subject_offering_id', $offering->subject_offering_id)->firstOrFail();

    $this->actingAs($admin)->post(route('admin.schedules.store'), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => null,
        'section_id' => null,
        'subject_code' => null,
        'weekdays' => 'Funday',
        'time_start' => '12:00',
        'time_end' => '13:00',
        'room' => 'A101',
    ])->assertRedirect()->assertSessionHasErrors('weekdays');
    $this->assertDatabaseCount('schedules', 1);

    $this->actingAs($admin)->post(route('admin.schedules.store'), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => null,
        'section_id' => null,
        'subject_code' => null,
        'weekdays' => 'Monday',
        'time_start' => '08:30',
        'time_end' => '09:30',
        'room' => 'A101',
    ])->assertRedirect()->assertSessionHasErrors('time_start');
    $this->assertDatabaseCount('schedules', 1);

    $this->actingAs($admin)->post(route('admin.schedules.store'), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => null,
        'section_id' => null,
        'subject_code' => null,
        'weekdays' => 'Mon',
        'time_start' => '09:00',
        'time_end' => '10:00',
        'room' => 'A101',
    ])->assertRedirect()->assertSessionHas('success');

    $adjacentSchedule = Schedule::query()
        ->where('scheduled_id', '!=', $schedule->scheduled_id)
        ->where('weekdays', 'Mon')
        ->firstOrFail();
    $this->actingAs($admin)->delete(route('admin.schedules.destroy', $adjacentSchedule->scheduled_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $sundaySchedule = Schedule::query()->create([
        'academic_year_id' => $section->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => $subject->subject_code,
        'semester' => '1st Semester',
        'weekdays' => 'Sun',
        'time_start' => '08:00:00',
        'time_end' => '12:00:00',
        'room' => 'A101',
    ]);

    $this->actingAs($admin)->put(route('admin.schedules.update', $sundaySchedule->scheduled_id), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => null,
        'section_id' => null,
        'subject_code' => null,
        'weekdays' => 'Mon',
        'time_start' => '08:30',
        'time_end' => '09:30',
        'room' => 'A101',
    ])->assertRedirect()->assertSessionHasErrors('time_start');

    $this->assertDatabaseHas('schedules', [
        'scheduled_id' => $sundaySchedule->scheduled_id,
        'weekdays' => 'Sun',
        'time_start' => '08:00:00',
        'time_end' => '12:00:00',
    ]);

    $this->actingAs($admin)->delete(route('admin.schedules.destroy', $sundaySchedule->scheduled_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)->get(route('admin.schedules.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Schedules')
            ->where('schedules.0.scheduled_id', $schedule->scheduled_id));

    $this->actingAs($admin)->put(route('admin.schedules.update', $schedule->scheduled_id), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => null,
        'section_id' => null,
        'subject_code' => null,
        'weekdays' => 'Tue',
        'time_start' => '11:00',
        'time_end' => '10:00',
        'room' => 'A102',
    ])->assertRedirect()->assertSessionHasErrors('time_end');

    $this->assertDatabaseHas('schedules', [
        'scheduled_id' => $schedule->scheduled_id,
        'weekdays' => 'Mon',
        'room' => 'A101',
    ]);

    $this->actingAs($admin)->put(route('admin.schedules.update', $schedule->scheduled_id), [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => null,
        'section_id' => null,
        'subject_code' => null,
        'weekdays' => 'Tue',
        'time_start' => '10:00',
        'time_end' => '11:00',
        'room' => 'A102',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('schedules', [
        'scheduled_id' => $schedule->scheduled_id,
        'weekdays' => 'Tue',
        'room' => 'A102',
    ]);

    DB::table('online_classes')->insert([
        'schedule_id' => $schedule->scheduled_id,
        'academic_year_id' => $section->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => $subject->subject_code,
        'title' => 'Protected schedule history',
        'meeting_link' => 'https://example.test/class',
        'scheduled_date' => '2026-09-21',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'status' => 'completed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)->delete(route('admin.schedules.destroy', $schedule->scheduled_id))
        ->assertRedirect()
        ->assertSessionHasErrors('schedule');
    $this->assertDatabaseHas('schedules', ['scheduled_id' => $schedule->scheduled_id]);

    DB::table('online_classes')->where('schedule_id', $schedule->scheduled_id)->delete();

    $this->actingAs($admin)->delete(route('admin.schedules.destroy', $schedule->scheduled_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('schedules', ['scheduled_id' => $schedule->scheduled_id]);
});

test('schedule update and delete guard is limited to the running time and matching room', function () {
    Carbon::setTestNow('2026-09-21 09:00:00');

    $admin = User::factory()->create(['role' => 'admin']);
    ['section' => $section, 'strand' => $strand] = controllerEntityAcademicContext();
    $laboratory = Laboratory::query()->create([
        'name' => 'Guard Room A',
        'description' => 'Schedule guard test room.',
        'location' => 'Guard Room A',
        'status' => 'active',
    ]);
    $subject = Subject::query()->create([
        'subject_name' => 'Guarded Schedule Subject',
        'subject_code' => 'SCHED-GUARD',
        'unit' => 3,
    ]);
    $instructorUser = User::factory()->create(['role' => 'instructor']);
    $instructor = Instructor::query()->create([
        'user_id' => $instructorUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'INS-SCHED-GUARD',
        'status' => 'active',
    ]);
    $offering = SubjectOffering::query()->create([
        'academic_year_id' => $section->academic_year_id,
        'subject_id' => $subject->subject_id,
        'section_id' => $section->section_id,
        'instructor_id' => $instructor->instructor_id,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);
    $schedule = Schedule::query()->create([
        'academic_year_id' => $section->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => $subject->subject_code,
        'semester' => '1st Semester',
        'weekdays' => 'Mon',
        'time_start' => '08:00:00',
        'time_end' => '10:00:00',
        'room' => 'Guard Room A',
    ]);

    $panelSessionId = DB::table('rfid_panel_sessions')->insertGetId([
        'room' => 'Guard Room B',
        'status' => 'attendance',
        'schedule_id' => $schedule->scheduled_id,
        'is_listening' => true,
        'listening_started_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'subject_offering_id' => $offering->subject_offering_id,
        'laboratory_id' => $laboratory->laboratory_id,
        'weekdays' => 'Mon',
        'time_start' => '08:00',
        'time_end' => '10:00',
        'room' => 'Guard Room A',
    ];

    $this->actingAs($admin)
        ->put(route('admin.schedules.update', $schedule->scheduled_id), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    DB::table('rfid_panel_sessions')->where('panel_session_id', $panelSessionId)->update([
        'room' => 'Guard Room A',
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.schedules.update', $schedule->scheduled_id), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('schedule');
    $this->actingAs($admin)
        ->delete(route('admin.schedules.destroy', $schedule->scheduled_id))
        ->assertRedirect()
        ->assertSessionHasErrors('schedule');

    Carbon::setTestNow('2026-09-21 10:00:00');

    $this->actingAs($admin)
        ->delete(route('admin.schedules.destroy', $schedule->scheduled_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('schedules', ['scheduled_id' => $schedule->scheduled_id]);

    Carbon::setTestNow();
});

test('student controller creates enrollment account parent link reset update and delete', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ['year' => $year, 'strand' => $strand, 'section' => $section] = controllerEntityAcademicContext();

    $this->actingAs($admin)->post(route('admin.students.store'), [
        'student_number' => 'STU-CTRL-001',
        'first_name' => 'Control',
        'middle_name' => null,
        'last_name' => 'Student',
        'email' => 'control.student@example.test',
        'phone' => '09170000003',
        'gender' => 'female',
        'strand_id' => $strand->strand_id,
        'section_id' => $section->section_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'rfid_tag' => 'STU-CTRL-RFID',
        'status' => 'active',
    ])->assertRedirect()->assertSessionHas('success');

    $student = Students::query()->where('student_number', 'STU-CTRL-001')->firstOrFail();
    $studentUser = User::query()->where('email', 'control.student@example.test')->firstOrFail();

    $this->assertDatabaseHas('student_enrollments', [
        'student_id' => $student->student_id,
        'academic_year_id' => $year->academic_year_id,
        'section_id' => $section->section_id,
        'status' => 'enrolled',
    ]);

    $this->actingAs($admin)->get(route('admin.students.index', ['search' => 'STU-CTRL-001']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/Students')
            ->where('students.0.student_id', $student->student_id));

    $this->actingAs($admin)->post(route('admin.students.parents.store', $student->student_id), [
        'first_name' => 'Control',
        'last_name' => 'Parent',
        'email' => 'control.parent@example.test',
        'phone' => '09170000004',
        'gender' => 'female',
        'relationship' => 'Mother',
    ])->assertRedirect()->assertSessionHas('success');
    $parent = User::query()->where('email', 'control.parent@example.test')->firstOrFail();
    expect(Hash::check('controlparent', $parent->password))->toBeTrue()
        ->and($parent->must_change_password)->toBeTrue();
    $parentPasswordHash = $parent->password;

    $this->actingAs($admin)->put(route('admin.students.parents.update', [$student->student_id, $parent->user_id]), [
        'first_name' => 'Control',
        'last_name' => 'Parent Updated',
        'email' => 'control.parent.updated@example.test',
        'phone' => '09170000005',
        'gender' => 'female',
        'relationship' => 'Guardian',
        'password' => null,
    ])->assertRedirect()->assertSessionHas('success');
    expect($parent->fresh()->password)->toBe($parentPasswordHash);

    $this->assertDatabaseHas('parent_student_links', [
        'student_id' => $student->student_id,
        'parent_user_id' => $parent->user_id,
        'relationship' => 'Guardian',
    ]);

    $this->actingAs($admin)->put(route('admin.students.password.reset-default', $student->student_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    expect(Hash::check('controlstudent', $studentUser->fresh()->password))->toBeTrue();

    $this->actingAs($admin)->put(route('admin.students.update', $student->student_id), [
        'student_number' => 'STU-CTRL-002',
        'first_name' => 'Control',
        'middle_name' => 'Middle',
        'last_name' => 'Student',
        'email' => 'control.student.updated@example.test',
        'phone' => '09170000006',
        'gender' => 'female',
        'strand_id' => $strand->strand_id,
        'section_id' => $section->section_id,
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'rfid_tag' => 'STU-CTRL-RFID-2',
        'status' => 'inactive',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('students', [
        'student_id' => $student->student_id,
        'student_number' => 'STU-CTRL-002',
        'status' => 'inactive',
    ]);

    $this->actingAs($admin)->delete(route('admin.students.parents.destroy', [$student->student_id, $parent->user_id]))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('parent_student_links', [
        'student_id' => $student->student_id,
        'parent_user_id' => $parent->user_id,
    ]);

    $this->actingAs($admin)->delete(route('admin.students.destroy', $student->student_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertSoftDeleted('students', ['student_id' => $student->student_id]);
});

test('message instructor create form validates and stores an encrypted inbox message', function () {
    $instructor = User::factory()->create(['role' => 'instructor']);

    $this->post(route('messages.store'), [
        'instructor_user_id' => $instructor->user_id,
        'sender_type' => 'student',
        'sender_name' => 'Form Tester',
        'body' => '',
    ])->assertRedirect()->assertSessionHasErrors('body');
    $this->assertDatabaseCount('messages', 0);

    $this->post(route('messages.store'), [
        'instructor_user_id' => $instructor->user_id,
        'sender_type' => 'student',
        'sender_name' => 'Form Tester',
        'sender_email' => 'form.tester@example.test',
        'student_number' => 'FORM-001',
        'body' => 'Please review my attendance concern.',
    ])->assertRedirect()->assertSessionHas('success');

    $message = Message::query()->firstOrFail();
    expect($message->subject)->toBe('Instructor conversation')
        ->and($message->body)->toBe('Please review my attendance concern.');
    $this->assertDatabaseHas('messages', [
        'message_id' => $message->message_id,
        'instructor_user_id' => $instructor->user_id,
        'sender_type' => 'student',
        'body' => 'Encrypted message',
    ]);
});

test('online class modal validates creates and soft deletes a class', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ['year' => $year, 'strand' => $strand, 'section' => $section] = controllerEntityAcademicContext();
    $instructorUser = User::factory()->create(['role' => 'instructor']);
    $instructor = Instructor::query()->create([
        'user_id' => $instructorUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'INS-ONLINE-MODAL',
        'status' => 'active',
    ]);
    $subject = Subject::query()->create([
        'subject_name' => 'Online Modal Testing',
        'subject_code' => 'ONLINE-MODAL',
        'unit' => 3,
    ]);
    $offering = SubjectOffering::query()->create([
        'academic_year_id' => $year->academic_year_id,
        'subject_id' => $subject->subject_id,
        'section_id' => $section->section_id,
        'instructor_id' => $instructor->instructor_id,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);
    $schedule = Schedule::query()->create([
        'academic_year_id' => $year->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => $subject->subject_code,
        'semester' => '1st Semester',
        'weekdays' => 'Mon',
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
        'room' => 'Virtual Room',
    ]);

    $this->actingAs($admin)->post(route('admin.online-classes.store'), [
        'schedule_id' => $schedule->scheduled_id,
        'title' => '',
        'meeting_link' => 'not-a-url',
        'scheduled_date' => 'invalid-date',
        'start_time' => '09:00',
        'end_time' => '08:00',
        'require_face_recognition' => false,
    ])->assertRedirect()->assertSessionHasErrors([
        'title',
        'meeting_link',
        'scheduled_date',
        'end_time',
    ]);
    $this->assertDatabaseCount('online_classes', 0);

    $this->actingAs($admin)->post(route('admin.online-classes.store'), [
        'schedule_id' => $schedule->scheduled_id,
        'title' => 'Controller Online Class',
        'description' => 'Created from the modal workflow test.',
        'meeting_link' => 'https://example.test/online-class',
        'scheduled_date' => '2026-09-28',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'require_face_recognition' => false,
    ])->assertRedirect()->assertSessionHas('success');

    $onlineClass = OnlineClass::query()->firstOrFail();
    $this->assertDatabaseHas('online_classes', [
        'online_class_id' => $onlineClass->online_class_id,
        'schedule_id' => $schedule->scheduled_id,
        'academic_year_id' => $year->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'title' => 'Controller Online Class',
    ]);

    $this->actingAs($admin)->delete(route('admin.online-classes.destroy', $onlineClass))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertSoftDeleted('online_classes', [
        'online_class_id' => $onlineClass->online_class_id,
    ]);
});

test('rfid clear actions remove student and instructor assignments', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    ['year' => $year, 'strand' => $strand, 'section' => $section] = controllerEntityAcademicContext();
    $student = Students::query()->create([
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'student_number' => 'RFID-CLEAR-001',
        'first_name' => 'RFID',
        'last_name' => 'Student',
        'email' => 'rfid.clear.student@example.test',
        'gender' => 'female',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => $year->name,
        'rfid_tag' => 'RFID-STUDENT-CLEAR',
        'status' => 'active',
    ]);
    $instructor = User::factory()->create([
        'role' => 'instructor',
        'rfid_tag' => 'RFID-INSTRUCTOR-CLEAR',
    ]);

    $this->actingAs($admin)->delete(route('admin.rfid.destroy', ['type' => 'student', 'id' => $student->student_id]))
        ->assertRedirect(route('admin.rfid'))
        ->assertSessionHas('success');
    $this->actingAs($admin)->delete(route('admin.rfid.destroy', ['type' => 'instructor', 'id' => $instructor->user_id]))
        ->assertRedirect(route('admin.rfid'))
        ->assertSessionHas('success');

    expect($student->fresh()->rfid_tag)->toBeNull()
        ->and($instructor->fresh()->rfid_tag)->toBeNull();
});

test('laboratory and active device controllers create update index pin and delete managed device context', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.laboratories.store'), [
        'name' => 'Entity Lab',
        'description' => 'Created through laboratory controller.',
        'location' => 'Building C',
        'status' => 'active',
    ])->assertRedirect()->assertSessionHas('success');
    $laboratory = Laboratory::query()->where('name', 'Entity Lab')->firstOrFail();

    $this->actingAs($admin)->put(route('admin.laboratories.update', $laboratory->laboratory_id), [
        'name' => 'Entity Lab Updated',
        'description' => 'Updated through laboratory controller.',
        'location' => 'Building D',
        'status' => 'inactive',
    ])->assertRedirect()->assertSessionHas('success');

    $this->actingAs($admin)->post(route('admin.active-devices.store'), [
        'laboratory_id' => $laboratory->laboratory_id,
        'label' => 'ENTITY-LAB-PANEL',
        'description' => 'Managed panel.',
        'pin' => '2468',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHas('success');
    $device = PanelDevice::query()->where('label', 'ENTITY-LAB-PANEL')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.active-devices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/ActiveDevices')
            ->where('devices', fn ($devices) => collect($devices)->contains(
                fn (array $row) => (int) $row['panel_device_id'] === (int) $device->panel_device_id
                    && $row['device_label'] === 'ENTITY-LAB-PANEL',
            )));

    $this->actingAs($admin)->put(route('admin.active-devices.update', $device), [
        'laboratory_id' => $laboratory->laboratory_id,
        'label' => 'ENTITY-LAB-PANEL-2',
        'description' => 'Updated managed panel.',
        'is_active' => false,
    ])->assertRedirect()->assertSessionHas('success');

    $this->actingAs($admin)->putJson(route('admin.active-devices.pin.update', $device), [
        'pin' => '1357',
    ])->assertOk()->assertJson(['ok' => true]);

    expect($device->fresh()->label)->toBe('ENTITY-LAB-PANEL-2')
        ->and($device->fresh()->is_active)->toBeFalse()
        ->and(Hash::check('1357', $device->fresh()->pin_hash))->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.active-devices.destroy', $device))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('panel_devices', ['panel_device_id' => $device->panel_device_id]);

    $this->actingAs($admin)->delete(route('admin.laboratories.destroy', $laboratory->laboratory_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('laboratories', ['laboratory_id' => $laboratory->laboratory_id]);
});

test('inventory item and legacy inventory controllers create update index and delete records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    SystemSetting::setBoolean(SystemSetting::INVENTORY_ENABLED, true);

    $this->actingAs($admin)->post(route('admin.items.store'), [
        'barcode' => 'ITEM-CTRL-001',
        'name' => 'Controller Inventory Item',
        'sku' => 'SKU-CTRL-001',
        'description' => 'Created through item controller.',
        'status' => 'Available',
    ])->assertRedirect()->assertSessionHas('success');
    $item = Item::query()->where('barcode', 'ITEM-CTRL-001')->firstOrFail();

    $this->actingAs($admin)->put(route('admin.items.update', $item), [
        'barcode' => 'ITEM-CTRL-002',
        'name' => 'Controller Inventory Item Updated',
        'sku' => 'SKU-CTRL-002',
        'description' => 'Updated through item controller.',
        'status' => 'Unavailable',
    ])->assertRedirect();
    $this->assertDatabaseHas('inventory_items', [
        'item_id' => $item->item_id,
        'barcode' => 'ITEM-CTRL-002',
        'status' => 'Unavailable',
    ]);

    $legacyItem = Device::query()->create([
        'item_name' => 'Legacy Controller Item',
        'item_description' => 'Used by InventoryController.',
        'item_sku' => 'LEG-CTRL-001',
        'item_barcode' => 'LEG-CTRL-001',
        'status' => 'available',
    ]);

    $this->actingAs($admin)->post(route('admin.inventory.store'), [
        'item_id' => $legacyItem->item_id,
        'quantity' => 10,
        'transaction_type' => 'initial_stock',
    ])->assertRedirect()->assertSessionHas('success');
    $inventory = Inventory::query()->where('item_id', $legacyItem->item_id)->firstOrFail();
    $this->assertDatabaseHas('transactions', [
        'inventory_id' => $inventory->inventory_id,
        'item_id' => $legacyItem->item_id,
        'quantity' => 10,
        'transaction_type' => 'initial_stock',
    ]);

    $this->actingAs($admin)->put(route('admin.inventory.update', $inventory->inventory_id), [
        'item_id' => $legacyItem->item_id,
        'quantity' => 7,
        'transaction_type' => 'deduction',
    ])->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseHas('inventories', [
        'inventory_id' => $inventory->inventory_id,
        'quantity' => 7,
    ]);

    $this->actingAs($admin)->delete(route('admin.inventory.destroy', $inventory->inventory_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('inventories', ['inventory_id' => $inventory->inventory_id]);

    $this->actingAs($admin)->delete(route('admin.items.destroy', $item))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertSoftDeleted('inventory_items', ['item_id' => $item->item_id]);
});

test('admin user controller creates managed user then updates indexes and deletes it', function () {
    $root = User::factory()->create([
        'role' => 'admin',
        'is_root_admin' => true,
    ]);

    $this->actingAs($root)->post(route('admin.users.store'), [
        'name' => 'Managed Registrar',
        'email' => 'managed.registrar@example.test',
        'password' => 'password123',
        'role' => 'registrar',
        'phone' => '09170000007',
        'is_root_admin' => false,
    ])->assertRedirect()->assertSessionHas('success');
    $registrar = User::query()->where('email', 'managed.registrar@example.test')->firstOrFail();

    $this->actingAs($root)->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/UserManagement')
            ->where('users.1.id', $registrar->user_id));

    $this->actingAs($root)->put(route('admin.users.update', $registrar->user_id), [
        'name' => 'Managed Clinic',
        'email' => 'managed.clinic@example.test',
        'password' => null,
        'role' => 'clinic',
        'phone' => '09170000008',
        'is_root_admin' => false,
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'user_id' => $registrar->user_id,
        'email' => 'managed.clinic@example.test',
        'role' => 'clinic',
    ]);

    $this->actingAs($root)->delete(route('admin.users.destroy', $registrar->user_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertSoftDeleted('users', ['user_id' => $registrar->user_id]);
});

test('emergency type and hotline controllers create update index and soft delete records', function () {
    $clinic = User::factory()->create(['role' => 'clinic']);

    $this->actingAs($clinic)->post(route('clinic.emergency-types.store'), [
        'name' => 'Controller Emergency',
        'category' => 'medical',
        'default_message' => 'Controller emergency message.',
        'is_active' => true,
        'sort_order' => 5,
    ])->assertRedirect()->assertSessionHas('success');
    $type = EmergencyType::query()->where('name', 'Controller Emergency')->firstOrFail();

    $this->actingAs($clinic)->put(route('clinic.emergency-types.update', $type->emergency_type_id), [
        'name' => 'Controller Emergency Updated',
        'category' => 'clinic',
        'default_message' => 'Updated controller emergency message.',
        'is_active' => false,
        'sort_order' => 6,
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('emergency_types', [
        'emergency_type_id' => $type->emergency_type_id,
        'name' => 'Controller Emergency Updated',
        'category' => 'clinic',
        'is_active' => false,
    ]);

    $this->actingAs($clinic)->post(route('clinic.emergency-hotlines.store'), [
        'name' => 'Controller Hotline',
        'category' => 'clinic',
        'phone_number' => '09170000009',
        'contact_person' => 'Clinic Desk',
        'sms_enabled' => true,
        'is_active' => true,
        'sort_order' => 2,
        'notes' => 'Controller hotline notes.',
    ])->assertRedirect()->assertSessionHas('success');
    $hotline = EmergencyHotline::query()->where('name', 'Controller Hotline')->firstOrFail();

    $this->actingAs($clinic)->get(route('clinic.emergency-hotlines.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/EmergencyHotlines')
            ->where('hotlines.0.emergency_hotline_id', $hotline->emergency_hotline_id));

    $this->actingAs($clinic)->put(route('clinic.emergency-hotlines.update', $hotline->emergency_hotline_id), [
        'name' => 'Controller Hotline Updated',
        'category' => 'general',
        'phone_number' => '09170000010',
        'contact_person' => 'General Desk',
        'sms_enabled' => false,
        'is_active' => false,
        'sort_order' => 3,
        'notes' => 'Updated hotline notes.',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('emergency_hotlines', [
        'emergency_hotline_id' => $hotline->emergency_hotline_id,
        'name' => 'Controller Hotline Updated',
        'category' => 'general',
        'is_active' => false,
    ]);

    $this->actingAs($clinic)->delete(route('clinic.emergency-hotlines.destroy', $hotline->emergency_hotline_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->actingAs($clinic)->delete(route('clinic.emergency-types.destroy', $type->emergency_type_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('emergency_hotlines', ['emergency_hotline_id' => $hotline->emergency_hotline_id]);
    $this->assertSoftDeleted('emergency_types', ['emergency_type_id' => $type->emergency_type_id]);
});

test('clinic case and patient history controllers create update index convert and delete history', function () {
    $clinic = User::factory()->create(['role' => 'clinic']);

    $this->actingAs($clinic)->post(route('clinic.case-logs.store'), [
        'emergency_alert_id' => null,
        'student_id' => null,
        'user_id' => $clinic->user_id,
        'patient_type' => 'visitor',
        'patient_name' => 'Controller Patient',
        'case_type' => 'Headache',
        'symptoms' => 'Reported headache.',
        'action_taken' => 'Rested in clinic.',
        'notes' => 'Initial case notes.',
        'status' => 'open',
        'occurred_at' => '2026-09-16 08:30:00',
    ])->assertRedirect()->assertSessionHas('success');
    $case = ClinicCase::query()->where('patient_name', 'Controller Patient')->firstOrFail();

    $this->actingAs($clinic)->get(route('clinic.case-logs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/CaseLogs')
            ->where('cases.0.id', $case->clinic_case_id));

    $this->actingAs($clinic)->put(route('clinic.case-logs.update', $case->clinic_case_id), [
        'emergency_alert_id' => null,
        'student_id' => null,
        'user_id' => $clinic->user_id,
        'patient_type' => 'visitor',
        'patient_name' => 'Controller Patient Updated',
        'case_type' => 'Headache',
        'symptoms' => 'Reported stronger headache.',
        'action_taken' => 'Referred for checkup.',
        'notes' => 'Updated case notes.',
        'status' => 'monitoring',
        'occurred_at' => '2026-09-16 09:00:00',
    ])->assertRedirect()->assertSessionHas('success');

    $this->actingAs($clinic)->post(route('clinic.case-logs.history', $case->clinic_case_id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $history = $case->fresh()->patient_name === 'Controller Patient Updated'
        ? \App\Models\PatientHistory::query()->where('patient_name', 'Controller Patient Updated')->firstOrFail()
        : \App\Models\PatientHistory::query()->firstOrFail();

    $this->actingAs($clinic)->post(route('clinic.patient-history.store'), [
        'student_id' => null,
        'user_id' => $clinic->user_id,
        'patient_type' => 'visitor',
        'patient_name' => 'Manual History Patient',
        'summary' => 'Manual history summary.',
        'notes' => 'Manual history notes.',
        'occurred_at' => '2026-09-16 10:00:00',
    ])->assertRedirect()->assertSessionHas('success');
    $manualHistory = \App\Models\PatientHistory::query()->where('patient_name', 'Manual History Patient')->firstOrFail();

    $this->actingAs($clinic)->get(route('clinic.patient-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/PatientHistory')
            ->where('histories.0.id', $manualHistory->patient_history_id));

    $this->actingAs($clinic)->put(route('clinic.patient-history.update', $manualHistory->patient_history_id), [
        'student_id' => null,
        'user_id' => $clinic->user_id,
        'patient_type' => 'visitor',
        'patient_name' => 'Manual History Patient Updated',
        'summary' => 'Manual history summary updated.',
        'notes' => 'Manual history notes updated.',
        'occurred_at' => '2026-09-16 11:00:00',
    ])->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('patient_histories', [
        'patient_history_id' => $manualHistory->patient_history_id,
        'patient_name' => 'Manual History Patient Updated',
    ]);

    $this->actingAs($clinic)->delete(route('clinic.patient-history.destroy', $manualHistory->patient_history_id))
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('patient_histories', ['patient_history_id' => $manualHistory->patient_history_id]);
    $this->assertDatabaseHas('patient_histories', ['patient_history_id' => $history->patient_history_id]);
});
