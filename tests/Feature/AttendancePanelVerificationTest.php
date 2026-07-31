<?php

use App\Models\Instructor;
use App\Models\RfidPanelSession;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function attendanceVerificationFixture(bool $withFace = false): array
{
    $strand = Strand::query()->create([
        'strand_code' => 'ICT-ATT',
        'strand_name' => 'ICT Attendance',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'strand_id' => $strand->strand_id,
        'section_name' => 'ICT ATT 11-A',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'status' => 'active',
    ]);
    $instructorUser = User::factory()->create([
        'role' => 'instructor',
        'rfid_tag' => 'INSTRUCTOR-ATT-RFID',
    ]);
    $instructor = Instructor::query()->create([
        'user_id' => $instructorUser->user_id,
        'strand_id' => $strand->strand_id,
        'instructor_number' => 'INS-ATT-001',
        'status' => 'active',
    ]);
    Subject::query()->create([
        'section_id' => $section->section_id,
        'user_id' => $instructorUser->user_id,
        'subject_name' => 'Attendance Security',
        'subject_code' => 'ATT-SEC-101',
        'department' => 'SHS',
        'unit' => 3,
        'semester' => '1st Semester',
    ]);
    $schedule = Schedule::query()->create([
        'instructor_id' => $instructor->instructor_id,
        'section_id' => $section->section_id,
        'subject_code' => 'ATT-SEC-101',
        'weekdays' => now()->format('l'),
        'time_start' => '08:00:00',
        'time_end' => '17:00:00',
        'room' => 'COMLAB-ATT',
    ]);
    $student = Students::query()->create([
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'student_number' => 'STU-ATT-001',
        'first_name' => 'Attendance',
        'last_name' => 'Student',
        'gender' => 'female',
        'email' => 'attendance.student@example.com',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'rfid_tag' => 'STUDENT-ATT-RFID',
        'status' => 'active',
        'face_images' => $withFace ? ['student_faces/missing-reference.jpg'] : [],
    ]);
    $console = User::factory()->create(['role' => 'console']);
    $attendanceSessionId = DB::table('attendance_sessions')->insertGetId([
        'subject_code' => 'ATT-SEC-101',
        'schedule_id' => $schedule->scheduled_id,
        'date' => now()->toDateString(),
        'time_start' => now()->format('H:i:s'),
        'status' => 'attendance',
        'room' => 'COMLAB-ATT',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('student', 'instructorUser', 'schedule', 'console', 'attendanceSessionId');
}

test('direct student tap cannot record attendance without server-side verification', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = attendanceVerificationFixture();

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.studentTap'), [
            'rfid' => $fixture['student']->rfid_tag,
            'room' => 'COMLAB-ATT',
            'subject_code' => 'ATT-SEC-101',
            'schedule_id' => $fixture['schedule']->scheduled_id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('requires_verification', true);

    $this->assertDatabaseCount('attendances', 0);
    $this->assertDatabaseCount('attendance_logs', 0);
});

test('instructor can manually change assigned student attendance inside configured edit window', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = attendanceVerificationFixture();
    SystemSetting::setInteger(SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, 5);

    $this->actingAs($fixture['instructorUser'])
        ->withSession(['instructor_verified' => true])
        ->patch(route('admin.attendance.logs.status'), [
            'session_id' => $fixture['attendanceSessionId'],
            'student_id' => $fixture['student']->student_id,
            'status' => 'excused',
            'remarks' => 'Approved medical excuse.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('attendances', [
        'student_id' => $fixture['student']->student_id,
        'schedule_id' => $fixture['schedule']->scheduled_id,
        'status' => 'excused',
        'remarks' => 'Approved medical excuse.',
    ]);
    $this->assertDatabaseHas('attendance_logs', [
        'student_id' => $fixture['student']->student_id,
        'verification_method' => 'instructor_manual_edit',
        'tap_type' => 'Manual Edit',
    ]);
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['instructorUser']->user_id,
        'action' => 'attendance_status_changed',
        'table_name' => 'attendances',
    ]);
});

test('instructor cannot manually change attendance outside configured edit window', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = attendanceVerificationFixture();
    SystemSetting::setInteger(SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, 3);
    DB::table('attendance_sessions')
        ->where('attendance_id', $fixture['attendanceSessionId'])
        ->update(['date' => now()->subDays(3)->toDateString()]);

    $this->actingAs($fixture['instructorUser'])
        ->withSession(['instructor_verified' => true])
        ->patch(route('admin.attendance.logs.status'), [
            'session_id' => $fixture['attendanceSessionId'],
            'student_id' => $fixture['student']->student_id,
            'status' => 'present',
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('attendances', [
        'student_id' => $fixture['student']->student_id,
        'schedule_id' => $fixture['schedule']->scheduled_id,
    ]);
});

test('instructor attendance module skips subject selection when exactly one subject is assigned', function () {
    $fixture = attendanceVerificationFixture();
    $subject = Subject::query()->where('subject_code', 'ATT-SEC-101')->firstOrFail();

    $this->actingAs($fixture['instructorUser'])
        ->withSession(['instructor_verified' => true])
        ->get(route('admin.attendance.logs'))
        ->assertRedirect(route('admin.attendance.subject', $subject));
});

test('administrator can browse all attendance subjects and open subject analytics', function () {
    $fixture = attendanceVerificationFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $subject = Subject::query()->where('subject_code', 'ATT-SEC-101')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.attendance.logs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/SubjectSelection')
            ->has('subjects', 1)
            ->where('subjects.0.id', $subject->subject_id)
            ->where('currentUserRole', 'admin'));

    $this->actingAs($admin)
        ->get(route('admin.attendance.subject', $subject))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Dashboard')
            ->where('subject.id', $subject->subject_id)
            ->where('overview.total_students', 1)
            ->where('overview.total_sessions', 1)
            ->has('sessions', 1));

    $this->actingAs($admin)
        ->get(route('admin.attendance.summary', $subject))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Summary')
            ->where('statuses', ['Present', 'Absent', 'Late', 'Excused', 'Unexcused', 'Online Class'])
            ->has('rows', 1)
            ->where('rows.0.student_id', $fixture['student']->student_id));
});

test('online class participation is counted as online class and present attendance', function () {
    $fixture = attendanceVerificationFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $subject = Subject::query()->where('subject_code', 'ATT-SEC-101')->firstOrFail();
    $onlineClassId = DB::table('online_classes')->insertGetId([
        'schedule_id' => $fixture['schedule']->scheduled_id,
        'instructor_id' => $fixture['schedule']->instructor_id,
        'section_id' => $fixture['student']->section_id,
        'subject_code' => $subject->subject_code,
        'title' => 'Online Attendance',
        'meeting_link' => 'https://example.test/meeting',
        'scheduled_date' => now()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'completed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('online_class_attendances')->insert([
        'online_class_id' => $onlineClassId,
        'student_id' => $fixture['student']->student_id,
        'joined_at' => now(),
        'status' => 'joined',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.summary', $subject))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.counts.Online Class', 1)
            ->where('rows.0.counts.Present', 1)
            ->where('rows.0.attendance_rate', 50));
});

test('instructor cannot open attendance for an unassigned subject', function () {
    $fixture = attendanceVerificationFixture();
    $otherSubject = Subject::query()->create([
        'section_id' => $fixture['student']->section_id,
        'user_id' => $fixture['instructorUser']->user_id,
        'subject_name' => 'Unassigned Subject',
        'subject_code' => 'UNASSIGNED-101',
        'department' => 'SHS',
        'semester' => '1st Semester',
    ]);

    $this->actingAs($fixture['instructorUser'])
        ->withSession(['instructor_verified' => true])
        ->get(route('admin.attendance.subject', $otherSubject))
        ->assertForbidden();
});

test('administrator can edit attendance and export subject reports', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = attendanceVerificationFixture();
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Attendance Administrator']);
    $subject = Subject::query()->where('subject_code', 'ATT-SEC-101')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.attendance.logs.status'), [
            'session_id' => $fixture['attendanceSessionId'],
            'student_id' => $fixture['student']->student_id,
            'status' => 'present',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('attendances', [
        'student_id' => $fixture['student']->student_id,
        'status' => 'present',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.attendance.summary.export', [$subject, 'xlsx']))
        ->assertOk()
        ->assertDownload('student-attendance-summary.xlsx');

    $this->actingAs($admin)
        ->get(route('admin.attendance.session.export', [$subject, $fixture['attendanceSessionId'], 'pdf']))
        ->assertOk()
        ->assertDownload();
});

test('attendance panel room remains unlocked on refresh until panel logout', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = attendanceVerificationFixture();

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.room'), [
            'room' => 'COMLAB-ATT',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('rfid_panel_sessions', [
        'room' => 'COMLAB-ATT',
        'opened_by_user_id' => $fixture['console']->user_id,
        'status' => 'online',
        'ended_at' => null,
    ]);

    $this->flushSession();

    $this->actingAs($fixture['console'])
        ->get(route('attendanceControlPanel'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AttendanceControlPanel')
            ->where('panelRoom', 'COMLAB-ATT')
        );

    $this->actingAs($fixture['console'])
        ->withSession(['panel.room' => 'COMLAB-ATT'])
        ->postJson(route('attendanceControlPanel.logout'), [
            'room' => 'COMLAB-ATT',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(RfidPanelSession::query()
        ->where('room', 'COMLAB-ATT')
        ->whereNull('ended_at')
        ->exists())->toBeFalse();

    $this->actingAs($fixture['console'])
        ->get(route('attendanceControlPanel'))
        ->assertRedirect(route('attendanceControlPanel.login'));
});

test('attendance panel demo buttons are disabled by default and use configured rfids when enabled', function () {
    $fixture = attendanceVerificationFixture();

    $this->actingAs($fixture['console'])
        ->withSession(['panel.room' => 'COMLAB-ATT'])
        ->get(route('attendanceControlPanel'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AttendanceControlPanel')
            ->where('featureSettings.demo_attendance_panel_enabled', false)
            ->where('demoAttendancePanel.enabled', false)
        );

    SystemSetting::setBoolean(SystemSetting::DEMO_ATTENDANCE_PANEL_ENABLED, true);
    SystemSetting::setArray(SystemSetting::DEMO_ATTENDANCE_PANEL_RFIDS, [
        'professor_tap' => 'PROF-ONE',
        'student_tap' => 'STUDENT-ONE',
        'second_student_tap' => 'STUDENT-TWO',
        'second_professor_tap' => 'PROF-TWO',
    ]);

    $this->actingAs($fixture['console'])
        ->withSession(['panel.room' => 'COMLAB-ATT'])
        ->get(route('attendanceControlPanel'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AttendanceControlPanel')
            ->where('featureSettings.demo_attendance_panel_enabled', true)
            ->where('demoAttendancePanel.enabled', true)
            ->where('demoAttendancePanel.rfids.professor_tap', 'PROF-ONE')
            ->where('demoAttendancePanel.rfids.student_tap', 'STUDENT-ONE')
            ->where('demoAttendancePanel.rfids.second_student_tap', 'STUDENT-TWO')
            ->where('demoAttendancePanel.rfids.second_professor_tap', 'PROF-TWO')
        );
});

test('student without a face requires the active instructor rfid before attendance is recorded', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = attendanceVerificationFixture();

    $verificationPayload = [
        'rfid' => $fixture['student']->rfid_tag,
        'room' => 'COMLAB-ATT',
        'subject_code' => 'ATT-SEC-101',
        'schedule_id' => $fixture['schedule']->scheduled_id,
    ];

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.studentFaceCheck'), $verificationPayload)
        ->assertStatus(422)
        ->assertJsonPath('requires_instructor_rfid', true);

    $this->postJson(route('attendanceControlPanel.studentFaceCheck'), [
        ...$verificationPayload,
        'instructor_rfid' => 'WRONG-RFID',
    ])->assertStatus(422);

    $this->assertDatabaseCount('attendances', 0);

    $this->postJson(route('attendanceControlPanel.studentFaceCheck'), [
        ...$verificationPayload,
        'instructor_rfid' => $fixture['instructorUser']->rfid_tag,
    ])->assertOk()->assertJsonPath('instructor_override', true);

    $this->postJson(route('attendanceControlPanel.studentTap'), $verificationPayload)
        ->assertOk()
        ->assertJsonPath('action', 'time_in');

    $this->assertDatabaseCount('attendances', 1);
    $this->assertDatabaseCount('attendance_logs', 1);
});

test('aws unavailability stores evidence for both login and logout', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    $fixture = attendanceVerificationFixture(true);
    $payload = [
        'rfid' => $fixture['student']->rfid_tag,
        'room' => 'COMLAB-ATT',
        'subject_code' => 'ATT-SEC-101',
        'schedule_id' => $fixture['schedule']->scheduled_id,
        'image' => 'data:image/jpeg;base64,'.base64_encode('camera-image'),
    ];

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.studentFaceCheck'), $payload)
        ->assertOk()
        ->assertJsonPath('provider_unavailable', true)
        ->assertJsonPath('capture_recorded', true);

    $this->postJson(route('attendanceControlPanel.studentTap'), $payload)
        ->assertOk()
        ->assertJsonPath('action', 'time_in');

    $this->postJson(route('attendanceControlPanel.studentFaceCheck'), $payload)
        ->assertOk()
        ->assertJsonPath('capture_recorded', true);

    $this->postJson(route('attendanceControlPanel.studentTap'), $payload)
        ->assertOk()
        ->assertJsonPath('action', 'time_out');

    $log = DB::table('attendance_logs')->first();
    expect($log->time_in_face_path)->not->toBeNull()
        ->and($log->time_out_face_path)->not->toBeNull();
    Storage::disk('public')->assertExists($log->time_in_face_path);
    Storage::disk('public')->assertExists($log->time_out_face_path);

    $this->assertDatabaseCount('attendances', 1);
});

test('camera failure needs instructor rfid only once for the active class session', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = attendanceVerificationFixture(true);
    $payload = [
        'rfid' => $fixture['student']->rfid_tag,
        'room' => 'COMLAB-ATT',
        'subject_code' => 'ATT-SEC-101',
        'schedule_id' => $fixture['schedule']->scheduled_id,
        'camera_unavailable' => true,
    ];

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.studentFaceCheck'), $payload)
        ->assertStatus(422)
        ->assertJsonPath('requires_instructor_rfid', true);

    $this->postJson(route('attendanceControlPanel.studentFaceCheck'), [
        ...$payload,
        'instructor_rfid' => $fixture['instructorUser']->rfid_tag,
    ])->assertOk()->assertJsonPath('camera_session_override', true);

    $this->postJson(route('attendanceControlPanel.studentTap'), $payload)->assertOk();

    $this->postJson(route('attendanceControlPanel.studentFaceCheck'), $payload)
        ->assertOk()
        ->assertJsonPath('camera_session_override', true);

    $this->postJson(route('attendanceControlPanel.studentTap'), $payload)
        ->assertOk()
        ->assertJsonPath('action', 'time_out');
});

test('ending a class marks students without time out as cutting and absent', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = attendanceVerificationFixture();
    $verificationPayload = [
        'rfid' => $fixture['student']->rfid_tag,
        'room' => 'COMLAB-ATT',
        'subject_code' => 'ATT-SEC-101',
        'schedule_id' => $fixture['schedule']->scheduled_id,
        'instructor_rfid' => $fixture['instructorUser']->rfid_tag,
    ];

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.studentFaceCheck'), $verificationPayload)
        ->assertOk();
    $this->postJson(route('attendanceControlPanel.studentTap'), $verificationPayload)->assertOk();

    $this->postJson(route('attendanceControlPanel.sessionState'), [
        'room' => 'COMLAB-ATT',
        'status' => 'online',
        'schedule_id' => $fixture['schedule']->scheduled_id,
        'subject_code' => 'ATT-SEC-101',
    ])->assertOk();

    $this->assertDatabaseHas('attendance_logs', [
        'student_id' => $fixture['student']->student_id,
        'status' => 'absent',
        'completion_reason' => 'cutting',
        'time_out' => null,
    ]);
    $this->assertDatabaseHas('attendances', [
        'student_id' => $fixture['student']->student_id,
        'status' => 'absent',
    ]);
});

test('late threshold uses the configurable admin setting', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Carbon::setTestNow('2026-07-21 08:20:00');
    SystemSetting::setInteger(SystemSetting::ATTENDANCE_LATE_THRESHOLD_MINUTES, 30);
    $fixture = attendanceVerificationFixture();
    $payload = [
        'rfid' => $fixture['student']->rfid_tag,
        'room' => 'COMLAB-ATT',
        'subject_code' => 'ATT-SEC-101',
        'schedule_id' => $fixture['schedule']->scheduled_id,
        'instructor_rfid' => $fixture['instructorUser']->rfid_tag,
    ];

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.studentFaceCheck'), $payload)
        ->assertOk();
    $this->postJson(route('attendanceControlPanel.studentTap'), $payload)
        ->assertOk()
        ->assertJsonPath('record.status', 'Checked In');

    $this->assertDatabaseHas('attendance_logs', [
        'student_id' => $fixture['student']->student_id,
        'is_late' => false,
    ]);

    Carbon::setTestNow();
});

test('attendance evidence is visible only to authorized attendance viewers', function () {
    Storage::fake('public');
    $fixture = attendanceVerificationFixture();
    Storage::disk('public')->put('attendance_face_captures/test/time-in.jpg', 'time-in-image');
    $logId = DB::table('attendance_logs')->insertGetId([
        'attendance_id' => $fixture['attendanceSessionId'],
        'student_id' => $fixture['student']->student_id,
        'time_in' => now()->format('H:i:s'),
        'status' => 'checked_in',
        'time_in_face_path' => 'attendance_face_captures/test/time-in.jpg',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $studentUser = User::factory()->create([
        'role' => 'student',
        'email' => $fixture['student']->email,
    ]);
    $unrelatedInstructor = User::factory()->create(['role' => 'instructor']);

    $url = route('attendance.evidence', ['attendanceLog' => $logId, 'moment' => 'time-in']);

    $this->actingAs($admin)->get($url)->assertOk();
    $this->actingAs($fixture['instructorUser'])->get($url)->assertOk();
    $this->actingAs($studentUser)->get($url)->assertOk();
    $this->actingAs($unrelatedInstructor)->get($url)->assertForbidden();
});
