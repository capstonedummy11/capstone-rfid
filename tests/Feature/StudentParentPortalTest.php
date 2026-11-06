<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Instructor;
use App\Models\Message;
use App\Models\OnlineClass;
use App\Models\OnlineClassNotification;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\StudentEnrollment;
use App\Models\StudentExcuseLetter;
use App\Models\StudentPortalMessage;
use App\Models\Students;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\MessengerMessageReceived;
use App\Services\MessengerEmailNotificationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function portalFixture(): array
{
    SystemSetting::setBoolean(SystemSetting::PARENT_PORTAL_ENABLED, true);
    SystemSetting::setBoolean(SystemSetting::PARENT_EXCUSE_LETTERS_ENABLED, true);

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

    Attendance::query()->create([
        'student_id' => $fixture['student']->student_id,
        'date' => '2026-07-06',
        'status' => 'absent',
        'subject_code' => 'PROG2',
        'room' => 'ICT Lab',
    ]);

    StudentExcuseLetter::query()->create([
        'student_id' => $fixture['student']->student_id,
        'submitted_by_user_id' => $fixture['studentUser']->user_id,
        'submitted_by_role' => 'student',
        'subject' => 'Medical absence',
        'from_date' => '2026-07-06',
        'to_date' => '2026-07-06',
        'reason' => 'Medical appointment.',
    ]);

    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/Dashboard/DashboardPage')
            ->where('student.student_number', 'SHS-ICT-TEST-01')
            ->where('stats.present', 1)
            ->where('stats.absent', 1)
            ->where('stats.excuse_letters', 1)
        );

    $this->actingAs($fixture['parentUser'])
        ->get(route('student-parent.attendance', ['student_id' => $fixture['student']->student_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/Attendance/AttendancePage')
            ->where('student.student_number', 'SHS-ICT-TEST-01')
            ->has('linkedStudents', 1)
            ->has('attendance', 2)
        );

    SystemSetting::setInteger(SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, 4);
    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.attendance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentParent/Attendance/AttendancePage')
            ->where('currentUserRole', 'student')
            ->where('absentAttendanceDays', 4)
            ->has('letters', 1));
});

test('disabled Student and Parent Messenger access does not remove their excuse notifications', function () {
    $fixture = portalFixture();
    SystemSetting::setBoolean(SystemSetting::MESSENGER_ROLE_ENABLED['student'], false);
    SystemSetting::setBoolean(SystemSetting::MESSENGER_ROLE_ENABLED['parent'], false);

    $letter = StudentExcuseLetter::query()->create([
        'student_id' => $fixture['student']->student_id,
        'submitted_by_user_id' => $fixture['studentUser']->user_id,
        'submitted_by_role' => 'student',
        'subject' => 'Medical appointment',
        'from_date' => now()->toDateString(),
        'to_date' => now()->toDateString(),
        'reason' => 'Clinic visit',
        'status' => 'pending_parent_approval',
    ]);
    $parentNotice = StudentPortalMessage::query()->create([
        'student_id' => $fixture['student']->student_id,
        'student_excuse_letter_id' => $letter->student_excuse_letter_id,
        'sender_user_id' => $fixture['studentUser']->user_id,
        'recipient_user_id' => $fixture['parentUser']->user_id,
        'sender_role' => 'student',
        'subject' => 'Excuse letter needs your approval',
        'body' => 'Please review the excuse letter.',
    ]);
    $studentNotice = StudentPortalMessage::query()->create([
        'student_id' => $fixture['student']->student_id,
        'student_excuse_letter_id' => $letter->student_excuse_letter_id,
        'sender_user_id' => $fixture['instructorUser']->user_id,
        'recipient_user_id' => $fixture['studentUser']->user_id,
        'sender_role' => 'instructor',
        'subject' => 'Excuse Letter approved',
        'body' => 'Your excuse letter was approved.',
    ]);

    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.messages.index'))
        ->assertNotFound();
    $this->actingAs($fixture['parentUser'])
        ->get(route('student-parent.messages.index', ['student_id' => $fixture['student']->student_id]))
        ->assertNotFound();

    $this->actingAs($fixture['studentUser'])
        ->getJson(route('student-parent.notifications.poll'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('items.0.id', 'message-'.$studentNotice->student_portal_message_id)
        ->assertJsonPath('items.0.title', 'Excuse Letter approved');

    $this->actingAs($fixture['parentUser'])
        ->getJson(route('student-parent.notifications.poll', ['student_id' => $fixture['student']->student_id]))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('items.0.id', 'message-'.$parentNotice->student_portal_message_id)
        ->assertJsonPath('items.0.title', 'Excuse letter needs your approval');

    $this->actingAs($fixture['parentUser'])
        ->put(route('student-parent.notifications.read', ['notification' => 'message-'.$parentNotice->student_portal_message_id, 'student_id' => $fixture['student']->student_id]))
        ->assertRedirect();

    expect($parentNotice->fresh()->read_at)->not->toBeNull();
});

test('student excuse-letter updates stay in notifications and only appear in Messenger when enabled', function () {
    $fixture = portalFixture();
    $letter = StudentExcuseLetter::query()->create([
        'student_id' => $fixture['student']->student_id,
        'submitted_by_user_id' => $fixture['studentUser']->user_id,
        'submitted_by_role' => 'student',
        'subject' => 'Medical appointment',
        'from_date' => now()->toDateString(),
        'to_date' => now()->toDateString(),
        'reason' => 'Clinic visit',
        'status' => 'approved',
    ]);
    $notice = StudentPortalMessage::query()->create([
        'student_id' => $fixture['student']->student_id,
        'student_excuse_letter_id' => $letter->student_excuse_letter_id,
        'sender_user_id' => $fixture['instructorUser']->user_id,
        'recipient_user_id' => $fixture['studentUser']->user_id,
        'sender_role' => 'instructor',
        'subject' => 'Approved Excuse Letter: Medical appointment',
        'body' => 'Your excuse letter was approved.',
    ]);

    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shared/Messages/Index/IndexPage')->has('messages', 0));

    $this->getJson(route('student-parent.notifications.poll'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('items.0.id', 'message-'.$notice->student_portal_message_id);

    SystemSetting::setBoolean(SystemSetting::MESSENGER_STUDENT_EXCUSE_LETTER_UPDATES_ENABLED, true);
    $this->get(route('student-parent.messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shared/Messages/Index/IndexPage')
            ->has('messages', 1)
            ->where('messages.0.excuse_letter_id', $letter->student_excuse_letter_id));
});

test('student and parent only see portal messages where they are sender or recipient', function () {
    $fixture = portalFixture();

    $studentMessage = StudentPortalMessage::query()->create([
        'student_id' => $fixture['student']->student_id,
        'sender_user_id' => $fixture['studentUser']->user_id,
        'recipient_user_id' => $fixture['instructorUser']->user_id,
        'sender_role' => 'student',
        'instructor_user_id' => $fixture['instructorUser']->user_id,
        'subject' => 'Student message',
        'body' => 'Student body',
    ]);

    $parentMessage = StudentPortalMessage::query()->create([
        'student_id' => $fixture['student']->student_id,
        'sender_user_id' => $fixture['parentUser']->user_id,
        'recipient_user_id' => $fixture['instructorUser']->user_id,
        'sender_role' => 'parent',
        'instructor_user_id' => $fixture['instructorUser']->user_id,
        'subject' => 'Parent private message',
        'body' => 'Parent body',
    ]);

    StudentPortalMessage::query()->create([
        'student_id' => $fixture['student']->student_id,
        'sender_user_id' => $fixture['instructorUser']->user_id,
        'recipient_user_id' => $fixture['studentUser']->user_id,
        'sender_role' => 'instructor',
        'instructor_user_id' => $fixture['instructorUser']->user_id,
        'subject' => 'Re: Student message',
        'body' => 'Instructor reply',
    ]);

    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shared/Messages/Index/IndexPage')
            ->has('messages', 2)
            ->has('recipients')
        );

    $this->actingAs($fixture['parentUser'])
        ->get(route('student-parent.messages.index', ['student_id' => $fixture['student']->student_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shared/Messages/Index/IndexPage')
            ->has('messages', 1)
            ->where('messages.0.sender_role', 'parent')
        );

    expect($studentMessage->fresh()->subject)->toBe('Student message')
        ->and($parentMessage->fresh()->body)->toBe('Parent body');

    $this->assertDatabaseHas('student_portal_messages', [
        'student_portal_message_id' => $studentMessage->student_portal_message_id,
        'subject' => 'Encrypted message',
        'body' => 'Encrypted message',
    ]);
});

test('message polling returns only new messages from the authenticated conversation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $clinic = User::factory()->create(['role' => 'clinic']);
    $other = User::factory()->create(['role' => 'registrar']);

    $first = StudentPortalMessage::query()->create([
        'sender_user_id' => $admin->user_id,
        'recipient_user_id' => $clinic->user_id,
        'sender_role' => 'admin',
        'subject' => 'First',
        'body' => 'First secure message.',
    ]);
    $second = StudentPortalMessage::query()->create([
        'sender_user_id' => $admin->user_id,
        'recipient_user_id' => $clinic->user_id,
        'sender_role' => 'admin',
        'subject' => 'Second',
        'body' => 'Second secure message.',
    ]);
    StudentPortalMessage::query()->create([
        'sender_user_id' => $other->user_id,
        'recipient_user_id' => $clinic->user_id,
        'sender_role' => 'registrar',
        'subject' => 'Other conversation',
        'body' => 'This must stay private to its conversation.',
    ]);

    $this->actingAs($clinic)
        ->getJson(route('messages.poll', [
            'conversation_user_id' => $admin->user_id,
            'after_id' => $first->student_portal_message_id,
        ]))
        ->assertOk()
        ->assertJsonPath('unread_count', 3)
        ->assertJsonPath('messages.0.id', $second->student_portal_message_id)
        ->assertJsonPath('messages.0.sender', $admin->name)
        ->assertJsonPath('messages.0.direction', 'incoming')
        ->assertJsonPath('messages.0.text', 'Second secure message.')
        ->assertJsonStructure([
            'messages' => [['id', 'sender', 'direction', 'text', 'timestamp', 'read_at']],
            'unread_count',
            'unread_by_conversation',
        ])
        ->assertJsonMissingPath('messages.0.sender_user_id')
        ->assertJsonMissingPath('messages.0.recipient_user_id')
        ->assertJsonMissingPath('messages.0.attachment_path')
        ->assertJsonMissingPath('messages.0.body_ciphertext');
});

test('student notification polling is scoped and mark all read updates the badge source', function () {
    $fixture = portalFixture();
    $onlineClass = OnlineClass::query()->create([
        'schedule_id' => 999,
        'instructor_id' => Instructor::query()
            ->where('user_id', $fixture['instructorUser']->user_id)
            ->value('instructor_id'),
        'section_id' => $fixture['section']->section_id,
        'subject_code' => 'POLL',
        'title' => 'Polling class',
        'meeting_link' => 'https://example.test/meeting',
        'scheduled_date' => now()->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'require_face_recognition' => false,
        'status' => 'scheduled',
    ]);
    $notification = OnlineClassNotification::query()->create([
        'online_class_id' => $onlineClass->online_class_id,
        'student_id' => $fixture['student']->student_id,
        'event' => 'created',
        'title' => 'New online class',
        'body' => 'A class has been scheduled.',
    ]);

    $this->actingAs($fixture['studentUser'])
        ->getJson(route('student-parent.notifications.poll'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('items.0.id', $notification->online_class_notification_id)
        ->assertJsonPath('items.0.title', 'New online class')
        ->assertJsonMissingPath('items.0.email_error');

    $this->actingAs($fixture['studentUser'])
        ->put(route('student-parent.notifications.read-all'))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($fixture['studentUser'])
        ->getJson(route('student-parent.notifications.poll', [
            'after_id' => $notification->online_class_notification_id,
        ]))
        ->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonPath('items', []);
});

test('authenticated users can search recipients and exchange attachment messages', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin.messages@example.com',
        'role' => 'admin',
    ]);
    $clinic = User::factory()->create([
        'name' => 'Clinic User',
        'email' => 'clinic.messages@example.com',
        'role' => 'clinic',
    ]);
    $registrar = User::factory()->create([
        'name' => 'Registrar User',
        'email' => 'registrar.messages@example.com',
        'role' => 'registrar',
    ]);
    $console = User::factory()->create([
        'name' => 'Console User',
        'email' => 'console.messages@example.com',
        'role' => 'console',
    ]);

    $this->actingAs($admin)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shared/Messages/Index/IndexPage')
            ->has('recipients', 2)
        );

    $this->actingAs($console)
        ->get(route('messages.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('messages.conversation.store'), [
            'recipient_user_id' => $clinic->user_id,
            'body' => 'Please review the clinic note.',
            'attachment' => Illuminate\Http\UploadedFile::fake()->create('clinic-note.pdf', 12, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Message sent.');

    $message = StudentPortalMessage::query()->firstOrFail();

    expect($message->sender_user_id)->toBe($admin->user_id)
        ->and($message->recipient_user_id)->toBe($clinic->user_id)
        ->and($message->attachment_name)->toBe('clinic-note.pdf');

    $this->actingAs($clinic)
        ->getJson(route('messages.unread-status'))
        ->assertOk()
        ->assertJson([
            'unread_count' => 1,
            'latest' => [
                'id' => $message->student_portal_message_id,
                'sender' => 'Admin User',
                'preview' => 'Please review the clinic note.',
            ],
        ]);

    $this->actingAs($clinic)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shared/Messages/Index/IndexPage')
            ->has('messages', 1)
            ->where('messages.0.sender', 'Admin User')
            ->where('messages.0.recipient', 'Clinic User')
            ->where('messages.0.sender_user_id', $admin->user_id)
            ->where('messages.0.recipient_user_id', $clinic->user_id)
        );

    $this->actingAs($clinic)
        ->post(route('messages.conversation.store'), [
            'recipient_user_id' => $admin->user_id,
            'body' => 'I saw the clinic note.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Message sent.');

    $this->actingAs($clinic)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shared/Messages/Index/IndexPage')
            ->has('messages', 2)
        );

    $this->actingAs($clinic)
        ->get(route('messages.attachments.show', $message))
        ->assertOk();

    $this->actingAs($registrar)
        ->get(route('messages.attachments.show', $message))
        ->assertForbidden();
});

test('messenger email notifications are limited per sender and recipient', function () {
    Notification::fake();
    config([
        'cache.default' => 'array',
        'messenger.email_notification_cooldown_minutes' => 5,
    ]);
    Cache::flush();

    $sender = User::factory()->create([
        'name' => 'Message Sender',
        'email' => 'message.sender@example.com',
        'role' => 'student',
    ]);
    $recipient = User::factory()->create([
        'name' => 'Message Recipient',
        'email' => 'message.recipient@example.com',
        'role' => 'instructor',
    ]);
    $firstMessage = StudentPortalMessage::query()->create([
        'sender_user_id' => $sender->user_id,
        'recipient_user_id' => $recipient->user_id,
        'sender_role' => 'student',
        'subject' => 'Conversation',
        'body' => 'First message.',
    ]);
    $secondMessage = StudentPortalMessage::query()->create([
        'sender_user_id' => $sender->user_id,
        'recipient_user_id' => $recipient->user_id,
        'sender_role' => 'student',
        'subject' => 'Conversation',
        'body' => 'Second message.',
    ]);
    $notifier = app(MessengerEmailNotificationService::class);

    expect($notifier->notify($sender, $recipient, $firstMessage))->toBeTrue()
        ->and($notifier->notify($sender, $recipient, $secondMessage))->toBeFalse();

    Notification::assertSentToTimes($recipient, MessengerMessageReceived::class, 1);

    $this->travel(6)->minutes();

    expect($notifier->notify($sender, $recipient, $secondMessage))->toBeTrue();
    Notification::assertSentToTimes($recipient, MessengerMessageReceived::class, 2);
});

test('instructor inbox replies create student portal replies', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = portalFixture();

    $message = Message::query()->create([
        'instructor_user_id' => $fixture['instructorUser']->user_id,
        'sender_type' => 'student',
        'sender_name' => 'Andrea Santos',
        'sender_email' => $fixture['student']->email,
        'student_number' => $fixture['student']->student_number,
        'subject' => 'Need help',
        'body' => 'Please reply.',
    ]);

    $this->actingAs($fixture['instructorUser'])
        ->withSession(['instructor_verified' => true])
        ->post(route('admin.messages.reply', $message), [
            'body' => 'Please attend the consultation.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Reply sent to the student portal.');

    $reply = StudentPortalMessage::query()
        ->where('student_id', $fixture['student']->student_id)
        ->where('sender_role', 'instructor')
        ->firstOrFail();

    expect($reply->recipient_user_id)->toBe($fixture['studentUser']->user_id)
        ->and($reply->subject)->toBe('Re: Need help')
        ->and($reply->body)->toBe('Please attend the consultation.');

    $this->actingAs($fixture['instructorUser'])
        ->withSession(['instructor_verified' => true])
        ->get(route('admin.messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shared/Messages/Index/IndexPage')
            ->has('conversations', 1)
            ->where('conversations.0.participant.name', 'Andrea Santos')
            ->has('conversations.0.messages', 2)
            ->where('conversations.0.messages.0.direction', 'incoming')
            ->where('conversations.0.messages.0.body', 'Please reply.')
            ->where('conversations.0.messages.1.direction', 'outgoing')
            ->where('conversations.0.messages.1.body', 'Please attend the consultation.')
        );

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['instructorUser']->user_id,
        'action' => 'create',
        'table_name' => 'student_portal_messages',
    ]);

    $this->assertDatabaseHas('messages', [
        'message_id' => $message->message_id,
        'subject' => 'Encrypted message',
        'body' => 'Encrypted message',
    ]);
});

test('student portal password uses the twelve character rule and specific message', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = portalFixture();

    $this->actingAs($fixture['studentUser'])
        ->put(route('student-parent.password.update'), [
            'current_password' => 'password',
            'password' => 'ElevenChar!',
            'password_confirmation' => 'ElevenChar!',
        ])
        ->assertSessionHasErrors([
            'password' => 'Password must be at least 12 characters long.',
        ]);

    $this->actingAs($fixture['studentUser'])
        ->put(route('student-parent.password.update'), [
            'current_password' => 'password',
            'password' => 'TwelveChars!',
            'password_confirmation' => 'TwelveChars!',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Password updated.');

    expect(Hash::check('TwelveChars!', $fixture['studentUser']->fresh()->password))->toBeTrue();
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

test('student can upload an account profile picture without changing biometric faces', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    $fixture = portalFixture();

    $this->actingAs($fixture['studentUser'])
        ->post(route('student-parent.profile.update'), [
            '_method' => 'put',
            'name' => $fixture['studentUser']->name,
            'phone' => $fixture['studentUser']->phone,
            'gender' => $fixture['studentUser']->gender,
            'profile_photo' => UploadedFile::fake()->image('student-profile.jpg', 300, 300)->size(500),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Profile updated.');

    $fixture['studentUser']->refresh();

    expect($fixture['studentUser']->profile_photo_path)->toStartWith('profile-photos/')
        ->and($fixture['student']->fresh()->face_images)->toBe($fixture['student']->face_images);
    Storage::disk('public')->assertExists($fixture['studentUser']->profile_photo_path);
});

test('student excuse letter stays pending until an instructor approves it', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Mail::fake();
    $fixture = portalFixture();

    $this->actingAs($fixture['studentUser'])
        ->post(route('student-parent.excuse-letters.store'), [
            'subject' => 'Programming I',
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-02',
            'reason' => 'Medical appointment.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Excuse letter submitted, but no assigned instructor was found for this section.');

    $letter = StudentExcuseLetter::query()->firstOrFail();

    expect($letter->status)->toBe('pending_instructor_approval')
        ->and($letter->parent_signature)->toBeNull();

    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.excuse-letters.download', $letter))
        ->assertStatus(422);

    $this->actingAs($fixture['parentUser'])
        ->put(route('student-parent.excuse-letters.approve', ['letter' => $letter]), [
            'parent_signature' => 'Maria Santos',
            'parent_approval_notes' => 'Approved after checking the appointment.',
        ])
        ->assertForbidden();

    $letter->refresh();

    expect($letter->status)->toBe('pending_instructor_approval')
        ->and($letter->parent_signature)->toBeNull()
        ->and($letter->parent_approved_by_user_id)->toBeNull();
});

test('new excuse letter keeps the active academic year enrollment context', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Mail::fake();
    $fixture = portalFixture();
    $year = AcademicYear::create([
        'name' => '2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => AcademicYear::STATUS_ACTIVE,
    ]);
    $fixture['section']->update(['academic_year_id' => $year->academic_year_id]);
    $enrollment = StudentEnrollment::create([
        'student_id' => $fixture['student']->student_id, 'academic_year_id' => $year->academic_year_id,
        'section_id' => $fixture['section']->section_id, 'strand_id' => $fixture['strand']->strand_id,
        'year_level' => 11, 'semester' => '1st Semester', 'status' => 'active', 'enrolled_at' => '2026-06-01',
    ]);

    $this->actingAs($fixture['studentUser'])->post(route('student-parent.excuse-letters.store'), [
        'subject' => 'Year-aware letter', 'from_date' => '2026-07-01', 'to_date' => '2026-07-02', 'reason' => 'Medical appointment.',
    ])->assertRedirect();

    $this->assertDatabaseHas('student_excuse_letters', [
        'student_id' => $fixture['student']->student_id,
        'academic_year_id' => $year->academic_year_id,
        'student_enrollment_id' => $enrollment->student_enrollment_id,
    ]);
});

test('parent-created excuse letter also waits for instructor approval', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = portalFixture();

    $this->actingAs($fixture['parentUser'])
        ->post(route('student-parent.excuse-letters.store', ['student_id' => $fixture['student']->student_id]), [
            'subject' => 'Programming I',
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-02',
            'reason' => 'Medical appointment.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('student_excuse_letters', 1);

    $letter = StudentExcuseLetter::query()->firstOrFail();

    expect($letter->status)->toBe('pending_instructor_approval')
        ->and($letter->parent_signature)->toBeNull()
        ->and($letter->parent_approved_by_user_id)->toBeNull();

    $this->actingAs($fixture['parentUser'])
        ->get(route('student-parent.excuse-letters.download', $letter))
        ->assertStatus(422);
});

test('required parent approval notifies only the parent before instructor review', function () {
    $fixture = portalFixture();
    $instructor = Instructor::query()->where('user_id', $fixture['instructorUser']->user_id)->firstOrFail();
    Schedule::query()->create([
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $fixture['section']->section_id,
        'subject_code' => 'PROG1',
        'weekdays' => 'Monday',
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
        'room' => 'ICT Lab',
    ]);
    SystemSetting::setBoolean(SystemSetting::PARENT_EXCUSE_LETTER_APPROVAL_REQUIRED, true);

    $this->actingAs($fixture['studentUser'])
        ->post(route('student-parent.excuse-letters.store'), [
            'subject' => 'Programming I',
            'from_date' => '2026-09-21',
            'to_date' => '2026-09-21',
            'reason' => 'Parent approval is required first.',
        ])
        ->assertRedirect();

    $letter = StudentExcuseLetter::query()->firstOrFail();
    $parentReminder = StudentPortalMessage::query()->where('student_excuse_letter_id', $letter->student_excuse_letter_id)->firstOrFail();
    expect($letter->status)->toBe('pending_parent_approval')
        ->and($parentReminder->recipient_user_id)->toBe($fixture['parentUser']->user_id)
        ->and($parentReminder->subject)->toBe('Excuse Letter Needs Parent Approval: Programming I');
    $this->assertDatabaseMissing('student_portal_messages', [
        'student_excuse_letter_id' => $letter->student_excuse_letter_id,
        'recipient_user_id' => $fixture['instructorUser']->user_id,
    ]);

    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('recentMessages', []));
    $this->actingAs($fixture['parentUser'])
        ->get(route('student-parent.dashboard', ['student_id' => $fixture['student']->student_id]))
        ->assertInertia(fn (Assert $page) => $page->where('recentMessages.0.subject', 'Excuse Letter Needs Parent Approval: Programming I'));

    $this->actingAs($fixture['parentUser'])
        ->put(route('student-parent.excuse-letters.approve', $letter), ['parent_signature' => 'Maria Santos'])
        ->assertRedirect();

    expect($letter->fresh()->status)->toBe('pending_instructor_approval')
        ->and($letter->fresh()->parent_signature)->toBe('Maria Santos');
    $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('recentMessages', []));
    $this->assertDatabaseHas('student_portal_messages', [
        'student_excuse_letter_id' => $letter->student_excuse_letter_id,
        'recipient_user_id' => $fixture['instructorUser']->user_id,
        'subject' => 'Encrypted message',
    ]);
});

test('submitted excuse letter is sent to instructor messenger for review', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Mail::fake();
    Storage::fake('public');
    $fixture = portalFixture();
    $instructor = Instructor::query()
        ->where('user_id', $fixture['instructorUser']->user_id)
        ->firstOrFail();

    Schedule::query()->create([
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $fixture['section']->section_id,
        'subject_code' => 'PROG1',
        'weekdays' => 'Monday',
        'time_start' => '08:00:00',
        'time_end' => '09:00:00',
        'room' => 'ICT Lab',
    ]);

    $this->actingAs($fixture['parentUser'])
        ->post(route('student-parent.excuse-letters.store', ['student_id' => $fixture['student']->student_id]), [
            'subject' => 'Programming I',
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-02',
            'reason' => 'Medical appointment.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Excuse letter submitted for instructor review.');

    $letter = StudentExcuseLetter::query()->firstOrFail();
    $message = StudentPortalMessage::query()->firstOrFail();

    expect($letter->status)->toBe('pending_instructor_approval')
        ->and($message->recipient_user_id)->toBe($fixture['instructorUser']->user_id)
        ->and($message->subject)->toBe('Excuse Letter Submitted: Programming I')
        ->and($message->attachment_name)->toBe('excuse-letter-'.$letter->student_excuse_letter_id.'.pdf')
        ->and($message->attachment_mime)->toBe('application/pdf')
        ->and($message->attachment_size)->toBeGreaterThan(0);

    Storage::disk('public')->assertExists($message->attachment_path);
    expect(substr(Storage::disk('public')->get($message->attachment_path), 0, 4))->toBe('%PDF');
});

test('student can download an approved generated excuse letter pdf', function () {
    $fixture = portalFixture();

    $letter = StudentExcuseLetter::query()->create([
        'student_id' => $fixture['student']->student_id,
        'submitted_by_user_id' => $fixture['studentUser']->user_id,
        'submitted_by_role' => 'student',
        'subject' => 'Programming I',
        'from_date' => '2026-07-01',
        'to_date' => '2026-07-02',
        'reason' => 'Medical appointment.',
        'status' => 'approved',
        'parent_signature' => null,
        'parent_approved_by_user_id' => null,
        'parent_approved_at' => null,
    ]);

    $response = $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.excuse-letters.download', $letter))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['studentUser']->user_id,
        'action' => 'download',
        'table_name' => 'student_excuse_letters',
    ]);
});

test('generated excuse letter omits parent approval when the setting is disabled', function () {
    $fixture = portalFixture();

    SystemSetting::setBoolean(SystemSetting::PARENT_EXCUSE_LETTERS_ENABLED, false);

    $letter = StudentExcuseLetter::query()->create([
        'student_id' => $fixture['student']->student_id,
        'submitted_by_user_id' => $fixture['studentUser']->user_id,
        'submitted_by_role' => 'student',
        'subject' => 'Programming I',
        'from_date' => '2026-07-01',
        'to_date' => '2026-07-02',
        'reason' => 'Medical appointment.',
        'status' => 'approved',
        'parent_signature' => 'Maria Santos',
        'parent_approved_by_user_id' => $fixture['parentUser']->user_id,
        'parent_approved_at' => now(),
    ]);

    $response = $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.excuse-letters.download', $letter))
        ->assertOk();

    $pdf = $response->getContent();

    expect($pdf)->toStartWith('%PDF')
        ->and($pdf)->not->toContain('Parent Approval')
        ->and($pdf)->not->toContain('Parent Signature:')
        ->and($pdf)->not->toContain('Approved By:')
        ->and($pdf)->not->toContain('Approved At:');
});

test('generated excuse letter does not include a parent signature line', function () {
    $fixture = portalFixture();

    SystemSetting::setBoolean(SystemSetting::PARENT_EXCUSE_LETTERS_ENABLED, true);

    $letter = StudentExcuseLetter::query()->create([
        'student_id' => $fixture['student']->student_id,
        'submitted_by_user_id' => $fixture['studentUser']->user_id,
        'submitted_by_role' => 'student',
        'subject' => 'Programming I',
        'from_date' => '2026-07-01',
        'to_date' => '2026-07-02',
        'reason' => 'Medical appointment.',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.excuse-letters.download', $letter))
        ->assertOk();

    $pdf = $response->getContent();

    expect($pdf)->not->toContain('Parent Signature: ')
        ->and($pdf)->not->toContain('Not yet signed');
});

test('generated excuse letter includes parent signature only after required parent approval', function () {
    $fixture = portalFixture();
    SystemSetting::setBoolean(SystemSetting::PARENT_EXCUSE_LETTER_APPROVAL_REQUIRED, true);
    $letter = StudentExcuseLetter::query()->create([
        'student_id' => $fixture['student']->student_id,
        'submitted_by_user_id' => $fixture['studentUser']->user_id,
        'submitted_by_role' => 'student',
        'subject' => 'Programming I',
        'from_date' => '2026-07-01',
        'to_date' => '2026-07-02',
        'reason' => 'Medical appointment.',
        'status' => 'approved',
        'parent_signature' => 'Maria Santos',
        'parent_approved_by_user_id' => $fixture['parentUser']->user_id,
        'parent_approved_at' => now(),
    ]);

    $pdf = $this->actingAs($fixture['studentUser'])
        ->get(route('student-parent.excuse-letters.download', $letter))
        ->assertOk()
        ->getContent();

    expect($pdf)->toContain('Parent Approval')
        ->and($pdf)->toContain('Parent Signature: Maria Santos');
});
