<?php

use App\Models\AcademicYear;
use App\Models\Instructor;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\StudentEnrollment;
use App\Models\StudentExcuseLetter;
use App\Models\StudentPortalMessage;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('current enrollment instructor can be searched and receives an excuse letter after attendance is logged', function () {
    Storage::fake('public');
    Carbon::setTestNow('2026-09-21 08:05:00');

    SystemSetting::setBoolean(SystemSetting::PARENT_PORTAL_ENABLED, false);

    $admin = User::factory()->create(['role' => 'admin']);
    $studentUser = User::factory()->create([
        'name' => 'Workflow Student',
        'email' => 'workflow.student@example.test',
        'role' => 'student',
    ]);
    $instructorUser = User::factory()->create([
        'name' => 'Current Schedule Instructor',
        'email' => 'current.instructor@example.test',
        'role' => 'instructor',
    ]);
    $unassignedUser = User::factory()->create([
        'name' => 'Unassigned Instructor',
        'role' => 'instructor',
    ]);
    $parentUser = User::factory()->create([
        'name' => 'Workflow Parent',
        'email' => 'workflow.parent@example.test',
        'role' => 'parent',
    ]);

    $strand = Strand::query()->create([
        'strand_code' => 'WFLOW',
        'strand_name' => 'Workflow Strand',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $archivedYear = AcademicYear::query()->create([
        'name' => '2025-2026',
        'starts_on' => '2025-06-01',
        'ends_on' => '2026-03-31',
        'status' => AcademicYear::STATUS_ARCHIVED,
        'active_semester' => '2nd Semester',
    ]);
    $activeYear = AcademicYear::query()->create([
        'name' => '2026-2027',
        'starts_on' => '2026-06-01',
        'ends_on' => '2027-03-31',
        'status' => AcademicYear::STATUS_ACTIVE,
        'active_semester' => '1st Semester',
    ]);
    $legacySection = Section::query()->create([
        'academic_year_id' => $archivedYear->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'Legacy Section',
        'year_level' => 11,
        'semester' => '2nd Semester',
        'school_year' => $archivedYear->name,
        'status' => 'inactive',
    ]);
    $currentSection = Section::query()->create([
        'academic_year_id' => $activeYear->academic_year_id,
        'strand_id' => $strand->strand_id,
        'section_name' => 'Current Section',
        'year_level' => 12,
        'semester' => '1st Semester',
        'school_year' => $activeYear->name,
        'status' => 'active',
    ]);
    $student = Students::query()->create([
        'section_id' => $legacySection->section_id,
        'strand_id' => $strand->strand_id,
        'student_number' => 'WFLOW-001',
        'first_name' => 'Workflow',
        'last_name' => 'Student',
        'gender' => 'female',
        'email' => $studentUser->email,
        'year_level' => 11,
        'semester' => '2nd Semester',
        'school_year' => $archivedYear->name,
        'rfid_tag' => 'WFLOW-RFID-001',
        'status' => 'active',
    ]);
    $enrollment = StudentEnrollment::query()->create([
        'student_id' => $student->student_id,
        'academic_year_id' => $activeYear->academic_year_id,
        'section_id' => $currentSection->section_id,
        'strand_id' => $strand->strand_id,
        'year_level' => 12,
        'semester' => '1st Semester',
        'status' => 'enrolled',
        'enrolled_at' => '2026-06-01',
    ]);
    $parentUser->linkedStudents()->attach($student->student_id, ['relationship' => 'mother']);
    $instructor = Instructor::query()->create([
        'user_id' => $instructorUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'INS-WFLOW-001',
        'status' => 'active',
    ]);
    $subject = Subject::query()->create([
        'subject_code' => 'WFLOW-SUBJ',
        'subject_name' => 'Workflow Subject',
        'unit' => 3,
    ]);
    $offering = SubjectOffering::query()->create([
        'academic_year_id' => $activeYear->academic_year_id,
        'subject_id' => $subject->subject_id,
        'section_id' => $currentSection->section_id,
        'instructor_id' => $instructor->instructor_id,
        'semester' => '1st Semester',
        'status' => 'active',
    ]);
    $schedule = Schedule::query()->create([
        'academic_year_id' => $activeYear->academic_year_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $currentSection->section_id,
        'subject_code' => $subject->subject_code,
        'semester' => '1st Semester',
        'weekdays' => 'Mon',
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
        'room' => 'Workflow Laboratory',
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.attendance.scan'), ['rfid_tag' => $student->rfid_tag])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('entry.studentNumber', $student->student_number);

    $this->assertDatabaseHas('attendances', [
        'student_id' => $student->student_id,
        'schedule_id' => $schedule->scheduled_id,
        'subject_offering_id' => $offering->subject_offering_id,
        'date' => '2026-09-21 00:00:00',
        'status' => 'late',
    ]);
    $this->assertDatabaseHas('attendance_logs', [
        'student_id' => $student->student_id,
        'schedule_id' => $schedule->scheduled_id,
        'tap_type' => 'Check-in',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.logs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/SubjectSelection')
            ->has('subjects', 1)
            ->where('subjects.0.id', $subject->subject_id)
            ->where('subjects.0.code', $subject->subject_code)
            ->where('subjects.0.section', $currentSection->section_name));

    $this->actingAs($instructorUser)
        ->withSession(['instructor_verified' => true])
        ->get(route('admin.attendance.logs'))
        ->assertRedirect(route('admin.attendance.subject', $subject));

    $this->actingAs($instructorUser)
        ->withSession(['instructor_verified' => true])
        ->get(route('admin.attendance.subject', $subject))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Dashboard')
            ->where('subject.id', $subject->subject_id)
            ->where('overview.total_students', 1)
            ->where('overview.total_sessions', 1)
            ->has('sessions', 1));

    $this->actingAs($studentUser)
        ->get(route('student-parent.excuse-letters.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/ExcuseLetters')
            ->where('student.section', $currentSection->section_name)
            ->has('recipientSuggestions', 1)
            ->where('recipientSuggestions.0.user_id', $instructorUser->user_id)
            ->where('recipientSuggestions.0.name', $instructorUser->name));

    $this->actingAs($studentUser)
        ->post(route('student-parent.excuse-letters.store'), [
            'subject' => $subject->subject_name,
            'from_date' => '2026-09-21',
            'to_date' => '2026-09-21',
            'reason' => 'Medical appointment during the scheduled class.',
            'recipient_user_ids' => [$instructorUser->user_id],
            'attachment' => UploadedFile::fake()->create('medical-certificate.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Excuse letter submitted and sent to the teacher.');

    $letter = StudentExcuseLetter::query()->firstOrFail();
    expect($letter->student_enrollment_id)->toBe($enrollment->student_enrollment_id)
        ->and($letter->recipient_user_ids)->toBe([$instructorUser->user_id])
        ->and($letter->attachment_name)->toBe('medical-certificate.pdf')
        ->and($letter->status)->toBe('approved');
    Storage::disk('public')->assertExists($letter->attachment_path);

    $this->assertDatabaseHas('student_portal_messages', [
        'student_id' => $student->student_id,
        'student_excuse_letter_id' => $letter->student_excuse_letter_id,
        'recipient_user_id' => $instructorUser->user_id,
        'instructor_user_id' => $instructorUser->user_id,
    ]);
    $portalMessage = StudentPortalMessage::query()->firstOrFail();
    expect($portalMessage->attachment_size)->toBeGreaterThan(0);

    $this->actingAs($instructorUser)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Messages/Index')
            ->has('messages', 1)
            ->where('messages.0.excuse_letter_id', $letter->student_excuse_letter_id)
            ->where('messages.0.can_review_excuse_letter', true)
            ->where('messages.0.excuse_letter_review.student.email', $student->email)
            ->where('messages.0.excuse_letter_review.parents.0.email', $parentUser->email));

    $this->actingAs($unassignedUser)
        ->put(route('messages.excuse-letters.review', $portalMessage), [
            'decision' => 'approved',
            'recipients' => ['student'],
            'email_subject' => 'Unauthorized review',
            'email_body' => 'This email must not be sent.',
        ])
        ->assertForbidden();

    $this->actingAs($instructorUser)
        ->put(route('messages.excuse-letters.review', $portalMessage), [
            'decision' => 'approved',
            'recipients' => [],
            'email_subject' => 'Excuse Letter Approved - Workflow Student',
            'email_body' => 'Your excuse letter was approved.',
        ])
        ->assertSessionHasErrors('recipients');

    $this->actingAs($instructorUser)
        ->put(route('messages.excuse-letters.review', $portalMessage), [
            'decision' => 'approved',
            'recipients' => ['student', 'parent'],
            'email_subject' => 'Excuse Letter Approved - Workflow Student',
            'email_body' => 'Your excuse letter was approved.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Excuse letter approved and email sent to 2 recipient(s).');

    $letter->refresh();
    $portalMessage->refresh();
    expect($letter->status)->toBe('approved')
        ->and($portalMessage->excuse_letter_review_decision)->toBe('approved')
        ->and($portalMessage->excuse_letter_reviewed_by_user_id)->toBe($instructorUser->user_id)
        ->and($portalMessage->excuse_letter_review_email_subject)->toBe('Excuse Letter Approved - Workflow Student')
        ->and($portalMessage->excuse_letter_review_recipients)->toHaveCount(2);

    $this->actingAs($instructorUser)
        ->put(route('messages.excuse-letters.review', $portalMessage), [
            'decision' => 'denied',
            'recipients' => ['student'],
            'email_subject' => 'Second decision',
            'email_body' => 'This duplicate decision must not be sent.',
        ])
        ->assertSessionHasErrors('decision');

    $this->actingAs($studentUser)
        ->post(route('student-parent.excuse-letters.store'), [
            'subject' => 'Second excuse letter',
            'from_date' => '2026-09-22',
            'to_date' => '2026-09-22',
            'reason' => 'A second letter used to test the instructor denial flow.',
            'recipient_user_ids' => [$instructorUser->user_id],
        ])
        ->assertRedirect();

    $deniedLetter = StudentExcuseLetter::query()->latest('student_excuse_letter_id')->firstOrFail();
    $deniedPortalMessage = StudentPortalMessage::query()->latest('student_portal_message_id')->firstOrFail();
    $this->actingAs($instructorUser)
        ->put(route('messages.excuse-letters.review', $deniedPortalMessage), [
            'decision' => 'denied',
            'recipients' => ['student'],
            'email_subject' => 'Excuse Letter Denied - Workflow Student',
            'email_body' => 'Your excuse letter was denied. Please contact your instructor.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Excuse letter denied and email sent to 1 recipient(s).');

    $deniedLetter->refresh();
    $deniedPortalMessage->refresh();
    expect($deniedLetter->status)->toBe('approved')
        ->and($deniedPortalMessage->excuse_letter_review_decision)->toBe('denied')
        ->and($deniedPortalMessage->excuse_letter_reviewed_by_user_id)->toBe($instructorUser->user_id)
        ->and($deniedPortalMessage->excuse_letter_review_recipients)->toHaveCount(1)
        ->and($deniedPortalMessage->excuse_letter_review_recipients[0]['email'])->toBe($student->email);

    $sentEmails = app('mailer')->getSymfonyTransport()->messages();
    expect($sentEmails)->toHaveCount(5);

    $emailRecipients = $sentEmails
        ->flatMap(fn ($sentEmail) => $sentEmail->getEnvelope()->getRecipients())
        ->map(fn ($address) => $address->getAddress());
    expect($emailRecipients)->toContain(
        $instructorUser->email,
        $student->email,
        $parentUser->email,
    );

    $this->actingAs($studentUser)
        ->post(route('student-parent.excuse-letters.store'), [
            'subject' => 'Invalid recipient attempt',
            'from_date' => '2026-09-21',
            'to_date' => '2026-09-21',
            'reason' => 'This must not be broadcast to assigned instructors.',
            'recipient_user_ids' => [$unassignedUser->user_id],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('recipient_user_ids');

    $this->assertDatabaseCount('student_excuse_letters', 2);
    $this->assertDatabaseCount('student_portal_messages', 2);
});
