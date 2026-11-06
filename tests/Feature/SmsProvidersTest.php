<?php

use App\Models\ActivityLog;
use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use App\Models\Students;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PhilSmsService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    config([
        'services.philsms.token' => '',
        'services.philsms.sender_id' => 'PhilSMS',
        'services.philsms.enabled' => true,
        'services.philsms.endpoint' => 'https://dashboard.philsms.com/api/v3/sms/send',
    ]);
    Http::preventStrayRequests();
});

test('PhilSMS normalizes Philippine mobile numbers strictly', function (string $input, string $expected) {
    expect(PhilSmsService::normalizeNumber($input))->toBe($expected);
})->with([
    ['09171234567', '639171234567'], ['+639171234567', '639171234567'],
    ['639171234567', '639171234567'], ['(0917) 123-4567', '639171234567'],
    ['++639171234567', ''], ['0917abc1234567', ''], ['0917', ''],
]);

test('each provider can test entered credentials without changing the active provider', function (string $provider) {
    $admin = User::factory()->create(['role' => 'admin']);
    config(['services.semaphore.enabled' => true, 'services.iprog.enabled' => true]);
    SystemSetting::setBoolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, true);
    SystemSetting::setString(SystemSetting::SMS_PRIMARY_PROVIDER, 'semaphore');
    Http::fake([
        'dashboard.philsms.com/*' => Http::response(['status' => 'success']),
        'api.semaphore.co/*' => Http::response([['status' => 'Queued']]),
        'www.iprogsms.com/*' => Http::response(['status' => 200]),
    ]);

    $this->actingAs($admin)->postJson(route('admin.settings.sms.providers.test', $provider), [
        'phone' => '09171234567', 'token' => 'unsaved-secret', 'sender_id' => 'PhilSMS',
    ])->assertOk()->assertExactJson(['success' => true, 'message' => 'Test SMS sent successfully']);

    Http::assertSent(function ($request) use ($provider) {
        $credentialMatches = match ($provider) {
            'philsms' => $request->hasHeader('Authorization', 'Bearer unsaved-secret') && $request['recipient'] === '639171234567' && $request['sender_id'] === 'PhilSMS' && $request['type'] === 'plain',
            'semaphore' => $request['apikey'] === 'unsaved-secret' && $request['number'] === '639171234567',
            'iprog' => $request['api_token'] === 'unsaved-secret' && $request['phone_number'] === '639171234567',
        };

        return $credentialMatches && $request['message'] === 'Test message from '.config('app.name').'. Your SMS setup is working.';
    });
    expect(SystemSetting::smsProviderSettings()['primary'])->toBe('semaphore');
    expect(SystemSetting::string('sms.'.$provider.'.token', ''))->toBe('');
    $audit = ActivityLog::where('action', 'sms_test_sent')->sole();
    expect($audit->user_id)->toBe($admin->user_id)
        ->and($audit->ip_address)->not->toBeNull()
        ->and($audit->description)->toContain($provider, '639*****4567')
        ->not->toContain('unsaved-secret', '09171234567', '639171234567');
})->with(['semaphore', 'iprog', 'philsms']);

test('test sends use saved credentials when token is blank', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    SystemSetting::setString('sms.philsms.token', Crypt::encryptString('saved-secret'));
    SystemSetting::setString('sms.philsms.sender_id', 'PhilSMS');
    Http::fake(['dashboard.philsms.com/*' => Http::response(['status' => 'success'])]);
    $this->actingAs($admin)->postJson(route('admin.settings.sms.providers.test', 'philsms'), [
        'phone' => '+639171234567', 'token' => '', 'message' => 'My own test',
    ])->assertOk()->assertJsonPath('success', true);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer saved-secret') && $request['message'] === 'My own test');
});

test('missing credentials or invalid recipient block sends and audit failure', function (array $input) {
    $admin = User::factory()->create(['role' => 'admin']);
    Http::fake();
    $this->actingAs($admin)->postJson(route('admin.settings.sms.providers.test', 'philsms'), $input)
        ->assertUnprocessable()->assertJsonPath('success', false);
    Http::assertNothingSent();
    expect(ActivityLog::where('action', 'sms_test_failed')->count())->toBe(1);
})->with([
    [['phone' => '09171234567', 'sender_id' => 'PhilSMS']],
    [['phone' => '09171234567', 'token' => 'secret', 'sender_id' => '']],
    [['phone' => 'bad number', 'token' => 'secret', 'sender_id' => 'PhilSMS']],
]);

test('PhilSMS response status and timeouts produce safe failures', function (string $failure) {
    $admin = User::factory()->create(['role' => 'admin']);
    Http::fake(['dashboard.philsms.com/*' => $failure === 'timeout'
        ? fn () => throw new ConnectionException('token secret phone 639171234567')
        : Http::response(['status' => 'error', 'message' => 'Invalid token secret phone 639171234567'], 200)]);
    $response = $this->actingAs($admin)->postJson(route('admin.settings.sms.providers.test', 'philsms'), [
        'phone' => '09171234567', 'token' => 'secret', 'sender_id' => 'PhilSMS',
    ])->assertOk()->assertJsonPath('success', false);
    expect($response->getContent())->not->toContain('secret', '639171234567', 'PhilSMS');
    expect(ActivityLog::where('action', 'sms_test_failed')->sole()->description)
        ->toContain('philsms', '639*****4567')->not->toContain('secret', '639171234567');
})->with(['api_error', 'timeout']);

test('non admins cannot send test SMS', function () {
    Http::fake();
    $this->actingAs(User::factory()->create(['role' => 'clinic']))
        ->postJson(route('admin.settings.sms.providers.test', 'philsms'), ['phone' => '09171234567'])
        ->assertForbidden();
    Http::assertNothingSent();
});

test('PhilSMS supports comma separated recipients and rejects invalid lists before sending', function () {
    Http::fake(['dashboard.philsms.com/*' => Http::response(['status' => 'success'])]);
    $provider = new PhilSmsService(['token' => 'secret', 'sender_id' => 'PhilSMS']);
    expect($provider->send('09171234567,+639181234567', 'Test')['sent'])->toBeTrue();
    Http::assertSent(fn ($request) => $request['recipient'] === '639171234567,639181234567');
    expect($provider->send('09171234567,invalid', 'Test'))->toBe(['sent' => false, 'reason' => 'invalid_recipient']);
    Http::assertSentCount(1);
});

test('SMS actions appear in the existing audit filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Http::fake(['dashboard.philsms.com/*' => Http::response(['status' => 'success'])]);
    $this->actingAs($admin)->postJson(route('admin.settings.sms.providers.test', 'philsms'), [
        'phone' => '09171234567', 'token' => 'secret', 'sender_id' => 'PhilSMS',
    ])->assertOk();
    $this->get(route('admin.activity-logs.index', ['action' => 'sms_test_sent']))
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('options.actions', fn ($actions) => in_array('sms_test_sent', $actions->all()))
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'sms_test_sent'));
});

function smsSettingsPayload(array $overrides = []): array
{
    return array_replace([
        'borrowing_enabled' => false, 'inventory_enabled' => false, 'parent_portal_enabled' => false,
        'face_recognition_enabled' => false, 'online_classes_enabled' => true,
        'online_class_face_recognition_default' => false, 'demo_attendance_panel_enabled' => false,
        'demo_attendance_panel_rfids' => SystemSetting::DEFAULT_DEMO_ATTENDANCE_PANEL_RFIDS,
        'late_threshold_minutes' => 15,
    ], $overrides);
}

test('PhilSMS settings encrypt tokens preserve blank secrets and audit changes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    SystemSetting::setBoolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, true);
    SystemSetting::setString(SystemSetting::SMS_PRIMARY_PROVIDER, 'semaphore');
    $payload = smsSettingsPayload([
        'sms_philsms_available' => true, 'sms_primary_provider' => 'philsms',
        'sms_credentials' => ['philsms' => ['token' => 'private-secret', 'sender_id' => 'PhilSMS']],
    ]);
    $this->actingAs($admin)->put(route('admin.settings.update'), $payload)->assertSessionHasNoErrors();
    $encrypted = SystemSetting::string('sms.philsms.token');
    expect($encrypted)->not->toContain('private-secret');
    expect(Crypt::decryptString($encrypted))->toBe('private-secret');
    expect(SystemSetting::smsProviderSettings()['primary'])->toBe('philsms');
    expect(json_encode(SystemSetting::smsProviderSettings()))->not->toContain('private-secret');
    expect(ActivityLog::where('action', 'sms_config_created')->count())->toBe(1);
    expect(ActivityLog::where('action', 'sms_provider_changed')->count())->toBe(1);
    expect(json_decode(ActivityLog::where('action', 'sms_provider_changed')->sole()->description, true))
        ->toBe(['old_provider' => 'semaphore', 'new_provider' => 'philsms']);
    $payload['sms_credentials']['philsms'] = ['token' => '', 'sender_id' => 'SchoolDemo'];
    $this->put(route('admin.settings.update'), $payload)->assertSessionHasNoErrors();
    expect(SystemSetting::smsCredentials('philsms')['token'])->toBe('private-secret');
    expect(ActivityLog::where('action', 'sms_config_updated')->count())->toBe(1);
    expect(ActivityLog::where('action', 'like', 'sms_%')->pluck('description')->implode(' '))
        ->not->toContain('private-secret', $encrypted);
});

test('selecting PhilSMS with missing credentials rejects settings without changing selection', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->put(route('admin.settings.update'), smsSettingsPayload([
        'sms_philsms_available' => true, 'sms_primary_provider' => 'philsms',
        'sms_credentials' => ['philsms' => ['token' => '', 'sender_id' => '']],
    ]))->assertSessionHasErrors(['sms_credentials.philsms.token', 'sms_credentials.philsms.sender_id']);
    expect(SystemSetting::smsProviderSettings()['primary'])->toBeNull();
});

test('PhilSMS emergency summaries audit success failure and fallback', function (string $scenario) {
    $console = User::factory()->create(['role' => 'console']);
    $type = EmergencyType::create(['name' => 'Medical', 'category' => 'clinic', 'is_active' => true]);
    $hotline = EmergencyHotline::create(['name' => 'Clinic', 'category' => 'clinic', 'phone_number' => '09171234567', 'sms_enabled' => true, 'is_active' => true]);
    config(['services.philsms.token' => 'emergency-secret', 'services.semaphore.key' => 'fallback-secret', 'services.semaphore.enabled' => true]);
    SystemSetting::setBoolean(SystemSetting::SMS_PHILSMS_AVAILABLE, true);
    SystemSetting::setString(SystemSetting::SMS_PRIMARY_PROVIDER, 'philsms');
    SystemSetting::setBoolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, $scenario === 'fallback');
    Http::fake([
        'dashboard.philsms.com/*' => Http::response(['status' => $scenario === 'success' ? 'success' : 'error']),
        'api.semaphore.co/*' => Http::response([['status' => 'Queued']]),
    ]);
    $sent = $scenario !== 'failure';
    $this->actingAs($console)->postJson(route('attendanceControlPanel.emergencyAlert'), [
        'emergency_type_id' => $type->getKey(), 'room' => 'B202', 'message' => 'Sensitive emergency body',
        'metadata' => ['emergency_scope' => 'all', 'emergency_hotline_id' => $hotline->getKey()],
    ])->assertOk()->assertJsonPath('sms_summary.succeeded', $sent ? 1 : 0)
        ->assertJsonPath('sms_summary.failed', $sent ? 0 : 1);
    $audit = ActivityLog::where('action', $sent ? 'emergency_text_sent' : 'emergency_text_failed')->sole();
    $details = json_decode($audit->description, true);
    expect($details['providers'])->toBe($sent ? [$scenario === 'fallback' ? 'semaphore' : 'philsms'] : [])
        ->and($details['fallback_used'])->toBe($scenario === 'fallback')
        ->and($details['total_recipients'])->toBe(1);
    expect($audit->description)->not->toContain('emergency-secret', 'fallback-secret', '09171234567', '639171234567', 'Sensitive emergency body');
})->with(['success', 'failure', 'fallback']);

test('partial emergency SMS delivery creates one failure summary', function () {
    Notification::fake();
    $console = User::factory()->create(['role' => 'console']);
    $type = EmergencyType::create(['name' => 'Medical', 'category' => 'clinic', 'is_active' => true]);
    $hotline = EmergencyHotline::create(['name' => 'Clinic', 'category' => 'clinic', 'phone_number' => '09171234567', 'sms_enabled' => true, 'is_active' => true]);
    $student = Students::create([
        'first_name' => 'Test', 'last_name' => 'Student', 'student_number' => 'SMS-001',
        'email' => 'sms.student@example.test', 'gender' => 'female', 'status' => 'active',
    ]);
    $parent = User::factory()->create(['role' => 'parent', 'phone' => '09181234567']);
    $student->parentUsers()->attach($parent->getKey());
    config(['services.philsms.token' => 'secret']);
    SystemSetting::setBoolean(SystemSetting::SMS_PHILSMS_AVAILABLE, true);
    Http::fake(['dashboard.philsms.com/*' => Http::sequence()->push(['status' => 'success'])->push(['status' => 'error'])]);
    $this->actingAs($console)->postJson(route('attendanceControlPanel.emergencyAlert'), [
        'emergency_type_id' => $type->getKey(), 'message' => 'Partial delivery',
        'metadata' => ['emergency_scope' => 'people', 'student_id' => $student->getKey(), 'emergency_hotline_id' => $hotline->getKey()],
    ])->assertOk()->assertJsonPath('sms_summary.succeeded', 1)->assertJsonPath('sms_summary.failed', 1);
    $details = json_decode(ActivityLog::where('action', 'emergency_text_failed')->sole()->description, true);
    expect($details['succeeded'])->toBe(1)->and($details['failed'])->toBe(1)->and($details['total_recipients'])->toBe(2);
});

test('audit storage failure does not break a test send', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Http::fake(['dashboard.philsms.com/*' => Http::response(['status' => 'success'])]);
    $event = 'eloquent.creating: '.ActivityLog::class;
    $dispatcher = ActivityLog::getEventDispatcher();
    $dispatcher->listen($event, function ($log) {
        if ($log->action === 'sms_test_sent') {
            throw new RuntimeException('Audit unavailable');
        }
    });
    try {
        $this->actingAs($admin)->postJson(route('admin.settings.sms.providers.test', 'philsms'), [
            'phone' => '09171234567', 'token' => 'secret', 'sender_id' => 'PhilSMS',
        ])->assertOk()->assertJsonPath('success', true);
        Http::assertSentCount(1);
    } finally {
        $dispatcher->forget($event);
    }
});

test('audit storage failure does not prevent emergency SMS delivery', function () {
    $console = User::factory()->create(['role' => 'console']);
    $type = EmergencyType::create(['name' => 'Medical', 'category' => 'clinic', 'is_active' => true]);
    $hotline = EmergencyHotline::create(['name' => 'Clinic', 'category' => 'clinic', 'phone_number' => '09171234567', 'sms_enabled' => true, 'is_active' => true]);
    config(['services.philsms.token' => 'secret']);
    SystemSetting::setBoolean(SystemSetting::SMS_PHILSMS_AVAILABLE, true);
    Http::fake(['dashboard.philsms.com/*' => Http::response(['status' => 'success'])]);
    $event = 'eloquent.creating: '.ActivityLog::class;
    $dispatcher = ActivityLog::getEventDispatcher();
    $dispatcher->listen($event, fn () => throw new RuntimeException('Audit unavailable'));
    try {
        $this->actingAs($console)->postJson(route('attendanceControlPanel.emergencyAlert'), [
            'emergency_type_id' => $type->getKey(), 'message' => 'Emergency',
            'metadata' => ['emergency_hotline_id' => $hotline->getKey()],
        ])->assertOk()->assertJsonPath('sms_summary.succeeded', 1);
        Http::assertSentCount(1);
    } finally {
        $dispatcher->forget($event);
    }
});
