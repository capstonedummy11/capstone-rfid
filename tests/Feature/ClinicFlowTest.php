<?php

use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use App\Models\PatientHistory;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

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
            ->component('Clinic/Dashboard')
            ->has('alerts')
            ->has('emergencyTypes')
        );

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.case-logs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/CaseLogs')
            ->has('cases')
            ->has('emergencyTypes', 1)
        );

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.patient-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/PatientHistory')
            ->has('histories')
            ->has('recentCases')
        );

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Reports')
            ->has('summary')
            ->has('caseBreakdown')
            ->has('caseTrends')
            ->has('recentCases')
        );

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.emergency-hotlines.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/EmergencyHotlines')
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
            ->component('Clinic/Dashboard')
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
            ->component('AttendanceControlPanel')
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

test('clinic dispatch creates case record and writes activity log', function () {
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
        ->post(route('clinic.emergency-alerts.dispatch', $alert->emergency_alert_id))
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency response dispatched.');

    $this->assertDatabaseHas('clinic_cases', [
        'emergency_alert_id' => $alert->emergency_alert_id,
        'handled_by_user_id' => $fixture['clinic']->user_id,
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

test('clinic can create update and convert case logs into patient history', function () {
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

    $this->actingAs($fixture['clinic'])
        ->post(route('clinic.case-logs.history', $case->clinic_case_id))
        ->assertRedirect()
        ->assertSessionHas('success', 'Patient history created from case.');

    $this->assertDatabaseHas('patient_histories', [
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
            ->component('Clinic/Reports')
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
