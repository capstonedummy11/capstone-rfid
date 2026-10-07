<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('admin can enable demo attendance panel and configure demo rfids', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'borrowing_enabled' => false,
            'inventory_enabled' => false,
            'parent_portal_enabled' => false,
            'face_recognition_enabled' => false,
            'online_classes_enabled' => true,
            'online_class_face_recognition_default' => false,
            'demo_attendance_panel_enabled' => true,
            'demo_attendance_panel_rfids' => [
                'professor_tap' => 'PROF-ONE',
                'student_tap' => 'STUDENT-ONE',
                'second_student_tap' => 'STUDENT-TWO',
                'second_professor_tap' => 'PROF-TWO',
            ],
            'late_threshold_minutes' => 15,
            'security_questions' => SystemSetting::DEFAULT_SECURITY_QUESTIONS,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SystemSetting::demoAttendancePanelSettings())->toMatchArray([
        'enabled' => true,
        'rfids' => [
            'professor_tap' => 'PROF-ONE',
            'student_tap' => 'STUDENT-ONE',
            'second_student_tap' => 'STUDENT-TWO',
            'second_professor_tap' => 'PROF-TWO',
        ],
    ]);
});

test('sms provider settings default to unavailable and resolve the primary provider', function () {
    $defaults = SystemSetting::smsProviderSettings();
    expect($defaults['providers']['semaphore']['available'])->toBeFalse()
        ->and($defaults['providers']['iprog']['available'])->toBeFalse()
        ->and($defaults['primary'])->toBeNull();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'borrowing_enabled' => false,
            'inventory_enabled' => false,
            'parent_portal_enabled' => false,
            'face_recognition_enabled' => false,
            'online_classes_enabled' => true,
            'online_class_face_recognition_default' => false,
            'demo_attendance_panel_enabled' => false,
            'demo_attendance_panel_rfids' => SystemSetting::DEFAULT_DEMO_ATTENDANCE_PANEL_RFIDS,
            'late_threshold_minutes' => 15,
            'security_questions' => SystemSetting::DEFAULT_SECURITY_QUESTIONS,
            'sms_semaphore_available' => true,
            'sms_iprog_available' => true,
            'sms_primary_provider' => 'iprog',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('smsSettings.primary', 'iprog')
            ->where('smsSettings.providers.iprog.available', true)
            ->where('smsSettings.providers.semaphore.available', true));

    expect(SystemSetting::smsProviderSettings()['primary'])->toBe('iprog');

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'borrowing_enabled' => false,
            'inventory_enabled' => false,
            'parent_portal_enabled' => false,
            'face_recognition_enabled' => false,
            'online_classes_enabled' => true,
            'online_class_face_recognition_default' => false,
            'demo_attendance_panel_enabled' => false,
            'demo_attendance_panel_rfids' => SystemSetting::DEFAULT_DEMO_ATTENDANCE_PANEL_RFIDS,
            'late_threshold_minutes' => 15,
            'security_questions' => SystemSetting::DEFAULT_SECURITY_QUESTIONS,
            'sms_semaphore_available' => true,
            'sms_iprog_available' => false,
            'sms_primary_provider' => 'iprog',
        ])
        ->assertRedirect();

    expect(SystemSetting::smsProviderSettings()['primary'])->toBe('semaphore');
});

test('admin can check SMS providers without sending a message', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $admin = User::factory()->create(['role' => 'admin']);
    config([
        'services.semaphore.enabled' => true,
        'services.semaphore.key' => 'test-semaphore-key',
        'services.semaphore.account_endpoint' => 'https://api.semaphore.co/api/v4/account',
    ]);
    Http::fake([
        'api.semaphore.co/*' => Http::response(['credit_balance' => 12], 200),
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.settings.sms.providers.check', 'semaphore'))
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Semaphore account is available. Balance: 12 credits.',
        ]);

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->url() === 'https://api.semaphore.co/api/v4/account?apikey=test-semaphore-key');
});

test('non-admin users cannot check SMS providers', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $nonAdmin = User::factory()->create(['role' => 'clinic']);
    Http::fake();

    $this->actingAs($nonAdmin)
        ->postJson(route('admin.settings.sms.providers.check', 'semaphore'))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('SMS provider check returns safe failures for provider errors and timeouts', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $admin = User::factory()->create(['role' => 'admin']);
    config([
        'services.iprog.enabled' => true,
        'services.iprog.token' => 'test-iprog-token',
        'services.iprog.balance_endpoint' => 'https://www.iprogsms.com/api/v1/account/sms_credits',
    ]);
    Http::fake([
        'www.iprogsms.com/*' => Http::response(['status' => 'error'], 200),
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.settings.sms.providers.check', 'iprog'))
        ->assertOk()
        ->assertJson([
            'success' => false,
            'message' => 'IPROG SMS account check failed.',
        ]);

    Http::fake([
        'www.iprogsms.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'),
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.settings.sms.providers.check', 'iprog'))
        ->assertOk()
        ->assertExactJson([
            'success' => false,
            'message' => 'IPROG SMS account check timed out or could not connect.',
        ]);
});

test('admin can upload select and delete clinic emergency dashboard sounds', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $clinic = User::factory()->create(['role' => 'clinic']);

    $this->actingAs($admin)
        ->post(route('admin.settings.emergency-sounds.store'), [
            'name' => 'Clinic Bell',
            'sound' => UploadedFile::fake()->create('clinic-alert.mp3', 64, 'audio/mpeg'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency sound uploaded and selected.');

    $library = SystemSetting::array(SystemSetting::CLINIC_EMERGENCY_SOUND_LIBRARY);
    $uploaded = $library['sounds'][0];

    Storage::disk('public')->assertExists($uploaded['path']);
    expect($library['selected_id'])->toBe($uploaded['id']);

    $this->actingAs($clinic)
        ->get(route('clinic.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('emergencySound.selected_id', $uploaded['id'])
            ->where('emergencySound.selected_url', route('clinic.emergency-sounds.show', ['id' => $uploaded['id']]))
        );

    $this->actingAs($admin)
        ->put(route('admin.settings.emergency-sounds.select', ['id' => SystemSetting::DEFAULT_CLINIC_EMERGENCY_SOUND_ID]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency sound selected.');

    expect(SystemSetting::clinicEmergencySoundSettings()['selected_id'])
        ->toBe(SystemSetting::DEFAULT_CLINIC_EMERGENCY_SOUND_ID);

    $this->actingAs($admin)
        ->delete(route('admin.settings.emergency-sounds.destroy', ['id' => $uploaded['id']]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency sound deleted.');

    Storage::disk('public')->assertMissing($uploaded['path']);
    expect(SystemSetting::array(SystemSetting::CLINIC_EMERGENCY_SOUND_LIBRARY)['sounds'])->toBe([]);
});
