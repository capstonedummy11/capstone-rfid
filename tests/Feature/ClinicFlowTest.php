<?php

use App\Models\EmergencyAlert;
use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->assertInertia(fn (Assert $page) => $page->component('Clinic/CaseLogs'));

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.patient-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Clinic/PatientHistory'));

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Clinic/Reports'));

    $this->actingAs($fixture['clinic'])
        ->get(route('clinic.emergency-hotlines.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/EmergencyHotlines')
            ->has('hotlines', 1)
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

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $fixture['clinic']->user_id,
        'action' => 'create',
        'table_name' => 'clinic_cases',
    ]);
});
