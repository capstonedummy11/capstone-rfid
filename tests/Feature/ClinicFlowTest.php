<?php

use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use App\Models\PatientHistory;
use App\Models\Section;
use App\Models\Strand;
use App\Models\Students;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\ClinicDispatchAssigned;
use App\Notifications\EmergencyParentAlert;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('dashboard notifications link to case logs and follow case resolution and reopening', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'room' => 'Clinic Room',
        'status' => 'acknowledged',
        'message' => 'Needs treatment.',
        'dispatched_at' => now(),
    ]);
    $case = ClinicCase::query()->create([
        'emergency_alert_id' => $alert->emergency_alert_id,
        'handled_by_user_id' => $fixture['clinic']->user_id,
        'patient_name' => 'Test Patient',
        'status' => 'monitoring',
    ]);

    $this->actingAs($fixture['clinic'])->get(route('clinic.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts.0.cases.0.id', $case->clinic_case_id)
            ->where('alerts.0.cases.0.patient_name', 'Test Patient')
            ->where('alerts.0.cases.0.status', 'monitoring'));
    $this->get(route('clinic.case-logs', ['case' => $case->clinic_case_id]))
        ->assertInertia(fn (Assert $page) => $page->where('selectedCaseId', $case->clinic_case_id));

    $payload = [
        'patient_name' => 'Test Patient',
        'emergency_alert_id' => $alert->emergency_alert_id,
        'status' => 'resolved',
    ];
    $this->put(route('clinic.case-logs.update', $case->clinic_case_id), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($alert->fresh()->status)->toBe('resolved');
    expect($alert->fresh()->resolved_at)->not->toBeNull();
    $this->get(route('clinic.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('alerts.0.status', 'resolved')
        ->where('alerts.0.cases.0.status', 'resolved')
        ->has('assignedDispatches', 0));

    $payload['status'] = 'monitoring';
    $this->put(route('clinic.case-logs.update', $case->clinic_case_id), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($alert->fresh()->status)->toBe('acknowledged');
    expect($alert->fresh()->resolved_at)->toBeNull();

    $payload['status'] = 'open';
    $this->put(route('clinic.case-logs.update', $case->clinic_case_id), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($alert->fresh()->status)->toBe('open');

    $payload['status'] = 'cancelled';
    $this->put(route('clinic.case-logs.update', $case->clinic_case_id), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($alert->fresh()->status)->toBe('cancelled');
    expect($alert->fresh()->resolved_at)->toBeNull();
});

test('an emergency remains active until every linked patient case is resolved', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'status' => 'acknowledged',
        'message' => 'Two patients need treatment.',
    ]);
    $first = ClinicCase::query()->create([
        'emergency_alert_id' => $alert->emergency_alert_id,
        'patient_name' => 'First Patient',
        'status' => 'monitoring',
    ]);
    ClinicCase::query()->create([
        'emergency_alert_id' => $alert->emergency_alert_id,
        'patient_name' => 'Second Patient',
        'status' => 'monitoring',
    ]);
    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.case-logs.update', $first->clinic_case_id), [
            'emergency_alert_id' => $alert->emergency_alert_id,
            'patient_name' => 'First Patient',
            'status' => 'resolved',
        ])->assertSessionHasNoErrors();
    expect($alert->fresh()->status)->toBe('acknowledged');
    expect($alert->fresh()->resolved_at)->toBeNull();
});

test('dashboard status changes update linked cases without touching unrelated cases', function (string $status, string $caseStatus) {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'status' => 'resolved',
        'resolved_at' => now(),
        'message' => 'Patient needs treatment.',
    ]);
    $case = ClinicCase::query()->create([
        'emergency_alert_id' => $alert->emergency_alert_id,
        'patient_name' => 'Linked Patient',
        'status' => 'resolved',
    ]);
    $unrelated = ClinicCase::query()->create(['patient_name' => 'Unrelated', 'status' => 'open']);

    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.emergency-alerts.update', $alert->emergency_alert_id), ['status' => $status])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($case->fresh()->status)->toBe($caseStatus);
    expect($unrelated->fresh()->status)->toBe('open');
    expect($alert->fresh()->resolved_at !== null)->toBe($status === 'resolved');
})->with([
    ['open', 'open'],
    ['acknowledged', 'monitoring'],
    ['resolved', 'resolved'],
    ['cancelled', 'cancelled'],
]);

test('mixed linked case states keep the emergency acknowledged', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'status' => 'acknowledged',
        'message' => 'Multiple patients need treatment.',
    ]);
    $first = ClinicCase::query()->create([
        'emergency_alert_id' => $alert->emergency_alert_id,
        'patient_name' => 'First Patient',
        'status' => 'monitoring',
    ]);
    ClinicCase::query()->create([
        'emergency_alert_id' => $alert->emergency_alert_id,
        'patient_name' => 'Second Patient',
        'status' => 'monitoring',
    ]);

    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.case-logs.update', $first->clinic_case_id), [
            'emergency_alert_id' => $alert->emergency_alert_id,
            'patient_name' => 'First Patient',
            'status' => 'cancelled',
        ])->assertSessionHasNoErrors();

    expect($alert->fresh()->status)->toBe('acknowledged');
});

function clinicFixture(): array
{
    $clinic = User::factory()->create([
        'name' => 'Clinic User',
        'email' => 'clinic.flow@example.com',
        'role' => 'clinic',
    ]);

    $console = User::factory()->create([
        'name' => 'Console User',
        'email' => 'console.flow@example.com',
        'role' => 'console',
    ]);

    $type = EmergencyType::query()->create([
        'name' => 'Fainting',
        'category' => 'clinic',
        'default_message' => 'Clinic emergency: student/person fainted.',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $hotline = EmergencyHotline::query()->create([
        'name' => 'School Clinic',
        'category' => 'clinic',
        'phone_number' => 'Local 101',
        'contact_person' => 'Clinic Staff',
        'sms_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    return compact('clinic', 'console', 'type', 'hotline');
}

test('clinic can view dashboard reports case logs patient history and hotline management', function () {
    $fixture = clinicFixture();

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Dashboard/DashboardPage')
            ->has('alerts')
            ->has('emergencyTypes')
        );

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.case-logs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/CaseLogs/CaseLogsPage')
            ->has('cases')
            ->has('emergencyTypes', 1)
        );

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.patient-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/PatientHistory/PatientHistoryPage')
            ->has('histories')
            ->has('recentCases')
        );

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Reports/ReportsPage')
            ->has('summary')
            ->has('caseBreakdown')
            ->has('caseTrends')
            ->has('recentCases')
        );

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.emergency-hotlines.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/EmergencyHotlines/EmergencyHotlinesPage')
            ->has('hotlines', 1)
        );
});

test('clinic can manage emergency types from dashboard tools', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.emergency-types.store'), [
            'name' => 'High Fever',
            'category' => 'clinic',
            'default_message' => 'Clinic emergency: high fever reported.',
            'is_active' => true,
            'sort_order' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency type added.');

    $type = EmergencyType::query()->where('name', 'High Fever')->firstOrFail();

    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.emergency-types.update', $type->emergency_type_id), [
            'name' => 'Severe Fever',
            'category' => 'clinic',
            'default_message' => 'Clinic emergency: severe fever reported.',
            'is_active' => false,
            'sort_order' => 4,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency type updated.');

    $this->assertDatabaseHas('emergency_types', [
        'emergency_type_id' => $type->emergency_type_id,
        'name' => 'Severe Fever',
        'is_active' => false,
        'sort_order' => 4,
    ]);

    $this->actingAs($fixture['clinic'])
        ->delete(route('clinic.emergency-types.destroy', $type->emergency_type_id))
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency type deleted.');

    expect(EmergencyType::withTrashed()->find($type->emergency_type_id)?->trashed())->toBeTrue();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['clinic']->user_id,
        'action' => 'create',
        'table_name' => 'emergency_types',
    ]);
});

test('clinic can export all case logs as an Excel-compatible CSV', function () {
    $fixture = clinicFixture();
    ClinicCase::query()->create([
        'patient_name' => '=Case Log Patient',
        'patient_type' => 'student',
        'case_type' => 'Fainting',
        'status' => 'resolved',
        'symptoms' => 'Dizziness',
        'action_taken' => 'Observed and released',
        'occurred_at' => '2026-10-10 17:25:00',
    ]);

    $response = $this->actingAs($fixture['clinic'])
        ->get(route('clinic.case-logs.export'));

    $response->assertOk()->assertDownload();
    expect($response->streamedContent())
        ->toStartWith("\xEF\xBB\xBFCase ID,Emergency Alert ID,Patient Name,Patient Type,Case Type,Status")
        ->toContain("'=Case Log Patient")
        ->toContain('Fainting')
        ->toContain('resolved')
        ->toContain('2026-10-10T17:25:00');
});

test('emergency type sort order must be unique among existing types', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.emergency-types.store'), [
            'name' => 'Duplicate Position',
            'category' => 'clinic',
            'default_message' => 'This type should not be created.',
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'sort_order' => 'This sort order is already used by another emergency type.',
        ]);

    $this->assertDatabaseMissing('emergency_types', ['name' => 'Duplicate Position']);

    $otherType = EmergencyType::query()->create([
        'name' => 'High Fever',
        'category' => 'clinic',
        'default_message' => 'Clinic emergency: high fever reported.',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.emergency-types.update', $otherType->emergency_type_id), [
            'name' => 'High Fever',
            'category' => 'clinic',
            'default_message' => 'Clinic emergency: high fever reported.',
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('sort_order');

    expect($otherType->fresh()->sort_order)->toBe(2);

    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.emergency-types.update', $otherType->emergency_type_id), [
            'name' => 'High Fever Updated',
            'category' => 'clinic',
            'default_message' => 'Clinic emergency: updated high fever report.',
            'is_active' => true,
            'sort_order' => 2,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('emergency_types', [
        'emergency_type_id' => $otherType->emergency_type_id,
        'name' => 'High Fever Updated',
        'sort_order' => 2,
    ]);
});

test('clinic dashboard shows only open emergency details cards', function () {
    $fixture = clinicFixture();

    EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'room' => 'Laboratory 1',
        'triggered_by_name' => 'Open Alert',
        'severity' => 'urgent',
        'status' => 'open',
        'message' => 'Needs clinic response.',
        'metadata' => [],
    ]);

    EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'room' => 'Laboratory 2',
        'triggered_by_name' => 'Handled Alert',
        'severity' => 'urgent',
        'status' => 'acknowledged',
        'message' => 'Already handled.',
        'metadata' => [],
    ]);

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Dashboard/DashboardPage')
            ->has('emergencyDetails', 1)
            ->where('emergencyDetails.0.patient_name', 'Open Alert')
        );
});

test('clinic emergency hotline crud writes admin-visible activity logs', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.emergency-hotlines.store'), [
            'name' => 'Nurse Desk',
            'category' => 'clinic',
            'phone_number' => 'Local 202',
            'contact_person' => 'Nurse',
            'sms_enabled' => true,
            'is_active' => true,
            'sort_order' => 2,
            'notes' => 'Backup clinic contact.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency hotline added.');

    $hotline = EmergencyHotline::query()->where('name', 'Nurse Desk')->firstOrFail();

    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.emergency-hotlines.update', $hotline->emergency_hotline_id), [
            'name' => 'Nurse Desk Updated',
            'category' => 'clinic',
            'phone_number' => 'Local 203',
            'contact_person' => 'Nurse Lead',
            'sms_enabled' => false,
            'is_active' => true,
            'sort_order' => 3,
            'notes' => 'Updated backup clinic contact.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency hotline updated.');

    $this->actingAs($fixture['clinic'])
        ->delete(route('clinic.emergency-hotlines.destroy', $hotline->emergency_hotline_id))
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency hotline deleted.');

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['clinic']->user_id,
        'action' => 'create',
        'table_name' => 'emergency_hotlines',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['clinic']->user_id,
        'action' => 'update',
        'table_name' => 'emergency_hotlines',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['clinic']->user_id,
        'action' => 'delete',
        'table_name' => 'emergency_hotlines',
    ]);
});

test('attendance panel receives emergency hotlines and emergency alert calls write logs', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();

    $this->actingAs($fixture['console'])
        ->withSession(['panel.room' => 'B202'])
        ->get(route('attendanceControlPanel'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AttendanceConsole/AttendanceControlPanel/AttendanceControlPanelPage')
            ->has('emergencyHotlines', 1)
            ->where('emergencyHotlines.0.name', 'School Clinic')
        );

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.emergencyAlert'), [
            'emergency_type_id' => $fixture['type']->emergency_type_id,
            'room' => 'B202',
            'triggered_by_name' => 'Sample Instructor',
            'message' => 'Clinic emergency: student/person fainted. Hotline: School Clinic Local 101.',
            'metadata' => [
                'panel' => 'attendance-control-panel',
                'emergency_hotline_id' => $fixture['hotline']->emergency_hotline_id,
                'emergency_hotline_name' => 'School Clinic',
                'emergency_hotline_phone' => 'Local 101',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $alert = EmergencyAlert::query()->firstOrFail();

    expect($alert->metadata['emergency_hotline_name'])->toBe('School Clinic');

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['console']->user_id,
        'action' => 'create',
        'table_name' => 'emergency_alerts',
    ]);
});

test('attendance panel sends semaphore sms for sms enabled hotline', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    config([
        'services.semaphore.enabled' => true,
        'services.semaphore.key' => 'test-semaphore-key',
        'services.semaphore.sender_name' => 'CAPSTONE',
        'services.semaphore.endpoint' => 'https://api.semaphore.co/api/v4/messages',
    ]);
    SystemSetting::setBoolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, true);
    Http::fake([
        'api.semaphore.co/*' => Http::response([['status' => 'Queued']], 200),
    ]);

    $fixture = clinicFixture();
    $fixture['hotline']->update(['phone_number' => '09171234567']);

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.emergencyAlert'), [
            'emergency_type_id' => $fixture['type']->emergency_type_id,
            'room' => 'B202',
            'triggered_by_name' => 'Sample Instructor',
            'message' => 'Clinic emergency: student/person fainted.',
            'metadata' => [
                'panel' => 'attendance-control-panel',
                'emergency_hotline_id' => $fixture['hotline']->emergency_hotline_id,
                'emergency_hotline_name' => 'School Clinic',
                'emergency_hotline_phone' => '09171234567',
                'emergency_hotline_sms_enabled' => true,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('sms.sent', true);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.semaphore.co/api/v4/messages'
        && $request['apikey'] === 'test-semaphore-key'
        && $request['number'] === '09171234567'
        && $request['sendername'] === 'CAPSTONE'
        && str_contains($request['message'], 'Clinic emergency: student/person fainted.'));
});

test('attendance panel does not call an SMS provider when none is available', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Http::fake();

    $fixture = clinicFixture();
    $fixture['hotline']->update(['phone_number' => '09171234567']);

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.emergencyAlert'), [
            'emergency_type_id' => $fixture['type']->emergency_type_id,
            'room' => 'B202',
            'message' => 'No provider test.',
            'metadata' => ['emergency_hotline_id' => $fixture['hotline']->emergency_hotline_id],
        ])
        ->assertOk()
        ->assertJsonPath('sms.sent', false)
        ->assertJsonPath('sms.reason', 'no_provider_available');

    Http::assertNothingSent();
});

test('sms service uses the primary provider first and falls back to the other available provider', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    config([
        'services.semaphore.enabled' => true,
        'services.semaphore.key' => 'test-semaphore-key',
        'services.semaphore.endpoint' => 'https://api.semaphore.co/api/v4/messages',
        'services.iprog.enabled' => true,
        'services.iprog.token' => 'test-iprog-token',
        'services.iprog.endpoint' => 'https://www.iprogsms.com/api/v1/sms_messages',
    ]);
    SystemSetting::setBoolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, true);
    SystemSetting::setBoolean(SystemSetting::SMS_IPROG_AVAILABLE, true);
    SystemSetting::setString(SystemSetting::SMS_PRIMARY_PROVIDER, 'iprog');
    Http::fake([
        'www.iprogsms.com/*' => Http::response(['status' => 500], 500),
        'api.semaphore.co/*' => Http::response([['status' => 'Queued']], 200),
    ]);

    $fixture = clinicFixture();
    $fixture['hotline']->update(['phone_number' => '09171234567']);

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.emergencyAlert'), [
            'emergency_type_id' => $fixture['type']->emergency_type_id,
            'room' => 'B202',
            'message' => 'Fallback provider test.',
            'metadata' => ['emergency_hotline_id' => $fixture['hotline']->emergency_hotline_id],
        ])
        ->assertOk()
        ->assertJsonPath('sms.sent', true)
        ->assertJsonPath('sms.provider', 'semaphore')
        ->assertJsonPath('sms.attempts.0.provider', 'iprog')
        ->assertJsonPath('sms.attempts.1.provider', 'semaphore');
});

test('attendance panel can send sms through iprog', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    config([
        'services.iprog.enabled' => true,
        'services.iprog.token' => 'test-iprog-token',
        'services.iprog.endpoint' => 'https://www.iprogsms.com/api/v1/sms_messages',
    ]);
    SystemSetting::setBoolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, false);
    SystemSetting::setBoolean(SystemSetting::SMS_IPROG_AVAILABLE, true);
    SystemSetting::setString(SystemSetting::SMS_PRIMARY_PROVIDER, 'iprog');
    Http::fake([
        'www.iprogsms.com/*' => Http::response([
            'status' => 200,
            'message' => 'Your SMS message has been successfully added to the queue.',
            'message_id' => 'iSms-test',
        ], 200),
    ]);

    $fixture = clinicFixture();
    $fixture['hotline']->update(['phone_number' => '09171234567']);

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.emergencyAlert'), [
            'emergency_type_id' => $fixture['type']->emergency_type_id,
            'room' => 'B202',
            'triggered_by_name' => 'Sample Instructor',
            'message' => 'Clinic emergency through IPROG.',
            'metadata' => [
                'emergency_hotline_id' => $fixture['hotline']->emergency_hotline_id,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('sms.sent', true)
        ->assertJsonPath('sms.message_id', 'iSms-test');

    Http::assertSent(fn ($request) => $request->url() === 'https://www.iprogsms.com/api/v1/sms_messages'
        && $request['api_token'] === 'test-iprog-token'
        && $request['phone_number'] === '639171234567'
        && str_contains($request['message'], 'Clinic emergency through IPROG.'));
});

test('attendance panel notifies linked parents by email and sms for identified students', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Notification::fake();
    config([
        'services.semaphore.enabled' => true,
        'services.semaphore.key' => 'test-semaphore-key',
        'services.semaphore.sender_name' => 'CAPSTONE',
        'services.semaphore.endpoint' => 'https://api.semaphore.co/api/v4/messages',
    ]);
    SystemSetting::setBoolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, true);
    Http::fake([
        'api.semaphore.co/*' => Http::response([['status' => 'Queued']], 200),
    ]);

    $fixture = clinicFixture();
    $student = Students::query()->create([
        'first_name' => 'Fainting',
        'last_name' => 'Student',
        'student_number' => 'FAINT-001',
        'email' => 'fainting.student@example.test',
        'phone' => null,
        'gender' => 'female',
        'status' => 'active',
    ]);
    $parent = User::factory()->create([
        'name' => 'Student Parent',
        'email' => 'fainting.parent@example.test',
        'role' => 'parent',
        'phone' => '09171234567',
    ]);
    $student->parentUsers()->attach($parent->user_id, ['relationship' => 'Mother']);

    $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.emergencyAlert'), [
            'emergency_type_id' => $fixture['type']->emergency_type_id,
            'room' => 'B202',
            'triggered_by_name' => 'Sample Instructor',
            'message' => 'The student passed out.',
            'metadata' => [
                'emergency_scope' => 'specific',
                'symptoms' => 'Dizziness and fainting during class.',
                'students' => [['student_id' => $student->student_id]],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('parent_notifications.parents_found', 1)
        ->assertJsonPath('parent_notifications.email_sent', 1)
        ->assertJsonPath('parent_notifications.sms_sent', 1);

    Notification::assertSentTo($parent, EmergencyParentAlert::class, function ($notification, $channels) use ($parent) {
        $mail = $notification->toMail($parent);
        expect($channels)->toBe(['mail']);
        expect($mail->subject)->toBe('Emergency alert for Fainting Student');
        expect($mail->introLines)->toContain('Location: B202');
        expect($mail->introLines)->toContain('Symptoms / notes: Dizziness and fainting during class.');

        return true;
    });
    Http::assertSent(fn ($request) => $request->url() === 'https://api.semaphore.co/api/v4/messages'
        && $request['number'] === '09171234567'
        && str_contains($request['message'], 'Fainting Student'));
});

test('attendance panel keeps the emergency and reports parent email delivery failure', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();
    $student = Students::query()->create([
        'first_name' => 'Email',
        'last_name' => 'Student',
        'student_number' => 'EMAIL-001',
        'gender' => 'female',
        'status' => 'active',
    ]);
    $parent = User::factory()->create([
        'name' => 'Linked Parent',
        'email' => 'linked.parent@example.test',
        'role' => 'parent',
        'phone' => null,
    ]);
    $student->parentUsers()->attach($parent->user_id, ['relationship' => 'Mother']);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Mail transport unavailable.'));

    $response = $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.emergencyAlert'), [
            'emergency_type_id' => $fixture['type']->emergency_type_id,
            'room' => 'B202',
            'message' => 'Needs medical assistance.',
            'metadata' => [
                'emergency_scope' => 'people',
                'students' => [['student_id' => $student->student_id]],
            ],
        ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('parent_notifications.parents_found', 1)
        ->assertJsonPath('parent_notifications.email_sent', 0);

    expect($response->json('parent_notifications.warnings'))->toContain(
        'Emergency email to Linked Parent for Email Student could not be sent. Please contact the parent directly.',
    );
    $this->assertDatabaseHas('emergency_alerts', ['emergency_alert_id' => $response->json('alert.emergency_alert_id')]);
});

test('attendance panel warns about each missing parent contact method and still uses the other method', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Notification::fake();
    config([
        'services.semaphore.enabled' => true,
        'services.semaphore.key' => 'test-semaphore-key',
        'services.semaphore.endpoint' => 'https://api.semaphore.co/api/v4/messages',
    ]);
    SystemSetting::setBoolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, true);
    Http::fake([
        'api.semaphore.co/*' => Http::response([['status' => 'Queued']], 200),
    ]);

    $fixture = clinicFixture();
    $student = Students::query()->create([
        'first_name' => 'Contact',
        'last_name' => 'Warning',
        'student_number' => 'CONTACT-001',
        'email' => 'contact.warning@example.test',
        'phone' => null,
        'gender' => 'female',
        'status' => 'active',
    ]);
    $parentWithoutEmail = User::factory()->create([
        'name' => 'Parent Without Email',
        'email' => '',
        'role' => 'parent',
        'phone' => '09171234567',
    ]);
    $parentWithoutPhone = User::factory()->create([
        'name' => 'Parent Without Phone',
        'email' => 'parent.without.phone@example.test',
        'role' => 'parent',
        'phone' => null,
    ]);
    $student->parentUsers()->attach([
        $parentWithoutEmail->user_id => ['relationship' => 'Mother'],
        $parentWithoutPhone->user_id => ['relationship' => 'Father'],
    ]);

    $response = $this->actingAs($fixture['console'])
        ->postJson(route('attendanceControlPanel.emergencyAlert'), [
            'emergency_type_id' => $fixture['type']->emergency_type_id,
            'room' => 'B202',
            'message' => 'Check both contact warnings.',
            'metadata' => [
                'emergency_scope' => 'specific',
                'students' => [['student_id' => $student->student_id]],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('parent_notifications.parents_found', 2)
        ->assertJsonPath('parent_notifications.email_sent', 1)
        ->assertJsonPath('parent_notifications.sms_sent', 1)
        ->assertJsonCount(2, 'parent_notifications.warnings');

    expect($response->json('parent_notifications.warnings'))
        ->toContain('Parent Without Email has no valid email address for Contact Warning. Email was not sent.')
        ->toContain('Parent Without Phone has no phone number for Contact Warning. SMS was not sent.');

    Notification::assertNotSentTo($parentWithoutEmail, EmergencyParentAlert::class);
    Notification::assertSentTo($parentWithoutPhone, EmergencyParentAlert::class);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['number'] === '09171234567');
});

test('clinic dispatch creates case record and writes activity log', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Notification::fake();
    $fixture = clinicFixture();
    $responder = User::factory()->create([
        'name' => 'Assigned Clinic Responder',
        'email' => 'assigned.clinic@example.com',
        'role' => 'clinic',
    ]);
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'room' => 'B202',
        'triggered_by_name' => 'Sample Instructor',
        'severity' => 'urgent',
        'message' => 'Clinic emergency.',
        'metadata' => [],
    ]);

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.emergency-alerts.dispatch', $alert->emergency_alert_id), [
            'clinic_user_id' => $responder->user_id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency response assigned to Assigned Clinic Responder.');

    $this->assertDatabaseHas('clinic_cases', [
        'emergency_alert_id' => $alert->emergency_alert_id,
        'handled_by_user_id' => $responder->user_id,
        'status' => 'monitoring',
    ]);

    $this->assertDatabaseHas('emergency_alerts', [
        'emergency_alert_id' => $alert->emergency_alert_id,
        'status' => 'acknowledged',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['clinic']->user_id,
        'action' => 'create',
        'table_name' => 'clinic_cases',
    ]);

    Notification::assertSentTo($responder, ClinicDispatchAssigned::class);

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.case-logs'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cases.0.patient_name', 'Sample Instructor')
            ->where('cases.0.assigned_responder_name', 'Assigned Clinic Responder')
            ->where('cases.0.assigned_responder_email', 'assigned.clinic@example.com'));
});

test('duplicate panel alerts are suppressed within ten seconds', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();
    $payload = [
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'room' => 'B202',
        'triggered_by_name' => 'Sample Instructor',
        'message' => 'Duplicate protection test.',
        'metadata' => ['emergency_scope' => 'all'],
    ];

    $this->actingAs($fixture['console'])->postJson(route('attendanceControlPanel.emergencyAlert'), $payload)->assertOk();
    $this->actingAs($fixture['console'])->postJson(route('attendanceControlPanel.emergencyAlert'), $payload)
        ->assertOk()
        ->assertJsonPath('duplicate', true)
        ->assertJsonPath('sms.reason', 'duplicate_suppressed');

    expect(EmergencyAlert::query()->count())->toBe(1);
});

test('multi student dispatch creates one clinic case per selected student and records response time', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Notification::fake();
    $fixture = clinicFixture();
    $responder = User::factory()->create(['role' => 'clinic']);
    $strand = Strand::query()->create([
        'strand_code' => 'EM',
        'strand_name' => 'Emergency Test',
        'department' => 'SHS',
        'status' => 'active',
    ]);
    $section = Section::query()->create([
        'strand_id' => $strand->strand_id,
        'section_name' => 'Emergency Section',
        'year_level' => 11,
        'school_year' => '2026-2027',
        'semester' => '1st Semester',
        'status' => 'active',
    ]);
    $students = collect([
        ['student_number' => 'EM-001', 'first_name' => 'First', 'last_name' => 'Student', 'email' => 'first.emergency@example.com'],
        ['student_number' => 'EM-002', 'first_name' => 'Second', 'last_name' => 'Student', 'email' => 'second.emergency@example.com'],
    ])->map(fn ($data) => Students::query()->create($data + [
        'section_id' => $section->section_id,
        'strand_id' => $strand->strand_id,
        'gender' => 'female',
        'year_level' => 11,
        'semester' => '1st Semester',
        'school_year' => '2026-2027',
        'status' => 'active',
        'face_images' => [],
    ]));
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'room' => 'B202',
        'triggered_by_name' => 'Sample Instructor',
        'severity' => 'urgent',
        'message' => 'Multiple students need help.',
        'metadata' => [
            'students' => $students->map(fn ($student) => [
                'student_id' => $student->student_id,
                'student_name' => trim($student->first_name.' '.$student->last_name),
            ])->all(),
        ],
    ]);

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.emergency-alerts.dispatch', $alert->emergency_alert_id), ['clinic_user_id' => $responder->user_id])
        ->assertRedirect();

    expect(\App\Models\ClinicCase::query()->where('emergency_alert_id', $alert->emergency_alert_id)->count())->toBe(2);
    $alert->refresh();
    expect($alert->acknowledged_at)->not->toBeNull()
        ->and($alert->dispatched_at)->not->toBeNull()
        ->and($alert->response_seconds)->toBeInt();
});

test('area wide dispatch creates one generic incident case', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Notification::fake();
    $fixture = clinicFixture();
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'room' => 'B202',
        'triggered_by_name' => 'Sample Instructor',
        'severity' => 'critical',
        'message' => 'Area-wide evacuation required.',
        'metadata' => ['emergency_scope' => 'all', 'students' => []],
    ]);

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.emergency-alerts.dispatch', $alert->emergency_alert_id), [
            'clinic_user_id' => $fixture['clinic']->user_id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('clinic_cases', [
        'emergency_alert_id' => $alert->emergency_alert_id,
        'student_id' => null,
        'patient_name' => 'Everyone / Area-wide',
        'patient_type' => 'area_wide',
        'status' => 'monitoring',
    ]);
});

test('clinic dispatch requires an active clinic responder', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();
    $instructor = User::factory()->create(['role' => 'instructor']);
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'room' => 'B202',
        'triggered_by_name' => 'Sample Instructor',
        'severity' => 'urgent',
        'message' => 'Clinic emergency.',
        'metadata' => [],
    ]);

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.emergency-alerts.dispatch', $alert->emergency_alert_id), [
            'clinic_user_id' => $instructor->user_id,
        ])
        ->assertSessionHasErrors('clinic_user_id');

    $this->assertDatabaseMissing('clinic_cases', [
        'emergency_alert_id' => $alert->emergency_alert_id,
    ]);
});

test('clinic can ignore emergency detail card by cancelling alert', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();
    $alert = EmergencyAlert::query()->create([
        'emergency_type_id' => $fixture['type']->emergency_type_id,
        'room' => 'B202',
        'triggered_by_name' => 'Sample Instructor',
        'severity' => 'urgent',
        'message' => 'Clinic emergency.',
        'metadata' => [],
    ]);

    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.emergency-alerts.update', $alert->emergency_alert_id), [
            'status' => 'cancelled',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency alert updated.');

    $this->assertDatabaseHas('emergency_alerts', [
        'emergency_alert_id' => $alert->emergency_alert_id,
        'status' => 'cancelled',
    ]);
});

test('clinic case saves automatically create and update one linked patient history', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.case-logs.store'), [
            'patient_name' => 'Juan Dela Cruz',
            'patient_type' => 'student',
            'case_type' => 'Fainting',
            'symptoms' => 'Dizziness during class',
            'action_taken' => 'Given water and monitored in clinic.',
            'status' => 'open',
            'occurred_at' => '2026-07-06 09:30:00',
            'notes' => 'Parent was notified.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Clinic case created.');

    $case = ClinicCase::query()->where('patient_name', 'Juan Dela Cruz')->firstOrFail();
    $history = PatientHistory::query()->where('clinic_case_id', $case->clinic_case_id)->firstOrFail();
    expect($history->notes)->toContain('Given water and monitored in clinic.');
    expect(PatientHistory::query()->where('clinic_case_id', $case->clinic_case_id)->count())->toBe(1);

    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.case-logs.update', $case->clinic_case_id), [
            'patient_name' => 'Juan Dela Cruz',
            'patient_type' => 'student',
            'case_type' => 'Fainting',
            'symptoms' => 'Dizziness during class',
            'action_taken' => 'Observed for 20 minutes and released.',
            'status' => 'resolved',
            'occurred_at' => '2026-07-06 09:30:00',
            'notes' => 'Stable before leaving clinic.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Clinic case updated.');

    $this->assertDatabaseHas('clinic_cases', [
        'clinic_case_id' => $case->clinic_case_id,
        'status' => 'resolved',
        'action_taken' => 'Observed for 20 minutes and released.',
    ]);

    expect($history->fresh()->notes)->toBe('Action: Observed for 20 minutes and released.'."\n\n".'Stable before leaving clinic.');
    expect(PatientHistory::query()->where('clinic_case_id', $case->clinic_case_id)->count())->toBe(1);

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.case-logs.history', $case->clinic_case_id))
        ->assertRedirect()
        ->assertSessionHas('success', 'Patient history is up to date.');

    $this->post(route('clinic.case-logs.history', $case->clinic_case_id))->assertRedirect();
    expect(PatientHistory::query()->where('clinic_case_id', $case->clinic_case_id)->count())->toBe(1);
    expect($history->fresh()->notes)->toBe('Action: Observed for 20 minutes and released.'."\n\n".'Stable before leaving clinic.');

    $this->assertDatabaseHas('patient_histories', [
        'patient_history_id' => $history->patient_history_id,
        'clinic_case_id' => $case->clinic_case_id,
        'recorded_by_user_id' => $fixture['clinic']->user_id,
        'patient_name' => 'Juan Dela Cruz',
        'summary' => 'Fainting: Dizziness during class',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['clinic']->user_id,
        'action' => 'update',
        'table_name' => 'clinic_cases',
    ]);
});

test('clinic can create update and delete patient history entries', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $fixture = clinicFixture();

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.patient-history.store'), [
            'patient_name' => 'Maria Santos',
            'patient_type' => 'student',
            'summary' => 'Headache',
            'notes' => 'Rested in clinic for one period.',
            'occurred_at' => '2026-07-06 10:15:00',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Patient history saved.');

    $history = PatientHistory::query()->where('patient_name', 'Maria Santos')->firstOrFail();

    $this->actingAs($fixture['clinic'])
        ->put(route('clinic.patient-history.update', $history->patient_history_id), [
            'patient_name' => 'Maria Santos',
            'patient_type' => 'student',
            'summary' => 'Mild headache',
            'notes' => 'Returned to class after observation.',
            'occurred_at' => '2026-07-06 10:15:00',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Patient history updated.');

    $this->assertDatabaseHas('patient_histories', [
        'patient_history_id' => $history->patient_history_id,
        'summary' => 'Mild headache',
        'notes' => 'Returned to class after observation.',
    ]);

    $this->actingAs($fixture['clinic'])
        ->delete(route('clinic.patient-history.destroy', $history->patient_history_id))
        ->assertRedirect()
        ->assertSessionHas('success', 'Patient history deleted.');

    $this->assertDatabaseMissing('patient_histories', [
        'patient_history_id' => $history->patient_history_id,
    ]);
});

test('clinic reports can be filtered and exported as csv', function () {
    $fixture = clinicFixture();

    ClinicCase::query()->create([
        'handled_by_user_id' => $fixture['clinic']->user_id,
        'patient_type' => 'student',
        'patient_name' => 'Export Patient',
        'case_type' => 'Fainting',
        'symptoms' => 'Weakness',
        'action_taken' => 'Observed',
        'status' => 'resolved',
        'occurred_at' => '2026-07-06 08:00:00',
    ]);

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.reports', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
            'case_type' => 'Fainting',
            'status' => 'resolved',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Reports/ReportsPage')
            ->where('summary.cases', 1)
            ->has('recentCases', 1)
        );

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.reports.export', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
            'case_type' => 'Fainting',
            'status' => 'resolved',
        ]))
        ->assertOk()
        ->assertDownload();
});
