<?php

use App\Models\Attendance;
use App\Models\Instructor;
use App\Models\Message;
use App\Models\Section;
use App\Models\Strand;
use App\Models\StudentExcuseLetter;
use App\Models\StudentPortalMessage;
use App\Models\Students;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function portalFixture(): array
{
    $strand = Strand::query()->create([
        'strand_code' => 'ICT',
        'strand_name' => 'Information and Communications Technology',
        'department' => 'Senior High School',
        'status' => 'active',
    ]);

    $section = Section::query()->create([
        'strand_id' => $strand->strand_id,
        'section_name' => 'ICT 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'status' => 'active',
    ]);

    $student = Students::query()->create([
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'student_number' => 'SHS-ICT-TEST-01',
        'first_name' => 'Andrea',
        'last_name' => 'Santos',
        'gender' => 'female',
        'email' => 'andrea.test@student.example',
        'phone' => '09170000001',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'rfid_tag' => 'RFID-TEST-01',
        'status' => 'active',
    ]);

    $studentUser = User::factory()->create([
        'name' => 'Andrea Santos',
        'email' => $student->email,
        'role' => 'student',
        'phone' => '09170000001',
        'gender' => 'female',
    ]);

    $parentUser = User::factory()->create([
        'name' => 'Maria Santos',
        'email' => 'parent.test@example.com',
        'role' => 'parent',
        'phone' => '09170000099',
        'gender' => 'female',
    ]);

    $parentUser->linkedStudents()->attach($student->student_id, ['relationship' => 'mother']);

    $instructorUser = User::factory()->create([
        'name' => 'Instructor One',
        'email' => 'instructor.test@example.com',
        'role' => 'instructor',
    ]);

    Instructor::query()->create([
        'user_id' => $instructorUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'INS-TEST-01',
        'status' => 'active',
    ]);

    return compact('strand', 'section', 'student', 'studentUser', 'parentUser', 'instructorUser');
}

test('student and parent roles can open their portal pages', function () {
    $fixture = portalFixture();

    Attendance::query()->create([
        'student_id' => $fixture['student']->student_id,
        'date' => '2026-07-05',
        'time_in' => '08:00:00',
        'time_out' => '09:00:00',
        'status' => 'present',
        'subject_code' => 'PROG1',
        'room' => 'ICT Lab',
    ]);

    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/Dashboard')
            ->where('student.student_number', 'SHS-ICT-TEST-01')
        );

    $this->actingAs($fixture['parentUser'])
        ->get(route('student-parent.attendance', ['student_id' => $fixture['student']->student_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/Attendance')
            ->where('student.student_number', 'SHS-ICT-TEST-01')
            ->has('linkedStudents', 1)
            ->has('attendance', 1)
        );
});

test('student cannot see parent-authored messages but can see instructor replies', function () {
    $fixture = portalFixture();

    StudentPortalMessage::query()->create([
        'student_id' => $fixture['student']->student_id,
        'sender_user_id' => $fixture['studentUser']->user_id,
        'sender_role' => 'student',
        'instructor_user_id' => $fixture['instructorUser']->user_id,
        'subject' => 'Student message',
        'body' => 'Student body',
    ]);

    StudentPortalMessage::query()->create([
        'student_id' => $fixture['student']->student_id,
        'sender_user_id' => $fixture['parentUser']->user_id,
        'sender_role' => 'parent',
        'instructor_user_id' => $fixture['instructorUser']->user_id,
        'subject' => 'Parent private message',
        'body' => 'Parent body',
    ]);

    StudentPortalMessage::query()->create([
        'student_id' => $fixture['student']->student_id,
        'sender_user_id' => $fixture['instructorUser']->user_id,
        'sender_role' => 'instructor',
        'instructor_user_id' => $fixture['instructorUser']->user_id,
        'subject' => 'Re: Student message',
        'body' => 'Instructor reply',
    ]);

    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/Messages')
            ->has('messages', 2)
            ->where('messages.0.sender_role', 'instructor')
            ->where('messages.1.sender_role', 'student')
        );

    $this->actingAs($fixture['parentUser'])
        ->get(route('student-parent.messages.index', ['student_id' => $fixture['student']->student_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/Messages')
            ->has('messages', 3)
        );
});

test('instructor inbox replies create student portal replies', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = portalFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    $message = Message::query()->create([
        'instructor_user_id' => $fixture['instructorUser']->user_id,
        'sender_type' => 'student',
        'sender_name' => 'Andrea Santos',
        'sender_email' => $fixture['student']->email,
        'student_number' => $fixture['student']->student_number,
        'subject' => 'Need help',
        'body' => 'Please reply.',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.messages.reply', $message), [
            'body' => 'Please attend the consultation.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Reply sent to the student portal.');

    $this->assertDatabaseHas('student_portal_messages', [
        'student_id' => $fixture['student']->student_id,
        'sender_role' => 'instructor',
        'subject' => 'Re: Need help',
        'body' => 'Please attend the consultation.',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->user_id,
        'action' => 'create',
        'table_name' => 'student_portal_messages',
    ]);
});

test('parent profile update does not change linked student phone or gender', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = portalFixture();

    $this->actingAs($fixture['parentUser'])
        ->put(route('student-parent.profile.update', ['student_id' => $fixture['student']->student_id]), [
            'name' => 'Maria Updated',
            'phone' => '09999999999',
            'gender' => 'female',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Profile updated.');

    expect($fixture['parentUser']->fresh()->phone)->toBe('09999999999')
        ->and($fixture['student']->fresh()->phone)->toBe('09170000001')
        ->and($fixture['student']->fresh()->gender)->toBe('female');

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['parentUser']->user_id,
        'action' => 'update',
        'table_name' => 'users',
    ]);
});

test('student can download a generated excuse letter document', function () {
    $fixture = portalFixture();

    $letter = StudentExcuseLetter::query()->create([
        'student_id' => $fixture['student']->student_id,
        'submitted_by_user_id' => $fixture['studentUser']->user_id,
        'submitted_by_role' => 'student',
        'subject' => 'Programming I',
        'from_date' => '2026-07-01',
        'to_date' => '2026-07-02',
        'reason' => 'Medical appointment.',
        'status' => 'submitted',
    ]);

    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.excuse-letters.download', $letter))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/msword; charset=UTF-8')
        ->assertSee('Excuse Letter')
        ->assertSee('Medical appointment.');

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['studentUser']->user_id,
        'action' => 'download',
        'table_name' => 'student_excuse_letters',
    ]);
});
