<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('admin can filter the system activity log by audit fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    ActivityLog::query()->create([
        'event_id' => fake()->uuid(),
        'user_id' => $admin->user_id,
        'user_name' => $admin->name,
        'user_role' => 'admin',
        'action' => 'update',
        'table_name' => 'settings',
        'module' => 'settings',
        'outcome' => 'success',
        'severity' => 'info',
        'subject_type' => 'system_setting',
        'subject_id' => '12',
        'ip_address' => '127.0.0.1',
        'description' => 'Updated a system setting.',
    ]);

    ActivityLog::query()->create([
        'event_id' => fake()->uuid(),
        'user_role' => 'student',
        'action' => 'join',
        'table_name' => 'online_class',
        'module' => 'online_class',
        'outcome' => 'failure',
        'severity' => 'warning',
        'description' => 'Online class join failed.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.activity-logs.index', [
            'module' => 'settings',
            'outcome' => 'success',
            'subject_type' => 'system_setting',
            'subject_id' => '12',
            'ip_address' => '127.0.0.1',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Admin/ActivityLogs')
            ->has('logs.data', 1)
            ->where('logs.data.0.module', 'settings')
            ->where('logs.data.0.outcome', 'success')
        );
});

test('state changing requests automatically write request audit metadata', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $admin = User::factory()->create([
        'role' => 'admin',
        'is_root_admin' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Audit Test Clinic',
            'email' => 'audit-clinic@example.com',
            'password' => 'password123',
            'role' => 'clinic',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->user_id,
        'user_role' => 'admin',
        'module' => 'user',
        'route_name' => 'admin.users.store',
        'http_method' => 'POST',
        'outcome' => 'success',
    ]);
});

test('admin can export the filtered system activity log and other roles cannot view it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    ActivityLog::query()->create([
        'event_id' => fake()->uuid(),
        'user_id' => $admin->user_id,
        'action' => 'export',
        'table_name' => 'reports',
        'module' => 'reports',
        'outcome' => 'success',
        'severity' => 'info',
        'description' => 'Exported reports.',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.activity-logs.export', ['module' => 'reports']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())->toContain('Exported reports.');

    $this->actingAs($student)
        ->get(route('admin.activity-logs.index'))
        ->assertForbidden();
});
