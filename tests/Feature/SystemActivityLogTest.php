<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
            ->component('Admin/ActivityLogs/ActivityLogsPage')
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
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
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

test('a form error is recorded as a failed request in system activity logs', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $admin = User::factory()->create(['role' => 'admin', 'is_root_admin' => true]);

    $this->actingAs($admin)->post(route('admin.users.store'), [])
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->user_id,
        'route_name' => 'admin.users.store',
        'http_method' => 'POST',
        'outcome' => 'failure',
        'severity' => 'warning',
        'status_code' => 302,
        'description' => 'POST user request failed with form errors.',
    ]);

    $this->actingAs($admin)->get(route('admin.activity-logs.index', [
        'outcome' => 'failure',
        'module' => 'user',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Admin/ActivityLogs/ActivityLogsPage')
        ->has('logs.data', 1)
        ->where('logs.data.0.route_name', 'admin.users.store'));
});

test('an unexpected page error is reported as a failed system activity event', function () {
    Route::middleware('web')->get('/_test/failing-page', fn () => throw new RuntimeException('Internal diagnostic detail'))
        ->name('testing.failing-page');

    $this->get('/_test/failing-page')->assertInternalServerError();

    $this->assertDatabaseHas('activity_logs', [
        'route_name' => 'testing.failing-page',
        'http_method' => 'GET',
        'status_code' => 500,
        'outcome' => 'failure',
        'severity' => 'error',
    ]);
});

test('an error response from a page is audited without recording successful page views', function () {
    Route::middleware('web')->get('/_test/error-response', fn () => response('Unavailable', 503))
        ->name('testing.error-response');
    Route::middleware('web')->get('/_test/healthy-page', fn () => response('OK'))
        ->name('testing.healthy-page');

    $this->get('/_test/error-response')->assertStatus(503);
    $this->get('/_test/healthy-page')->assertOk();
    $this->assertDatabaseHas('activity_logs', [
        'route_name' => 'testing.error-response',
        'http_method' => 'GET',
        'status_code' => 503,
        'outcome' => 'failure',
        'severity' => 'error',
    ]);
    $this->assertDatabaseMissing('activity_logs', ['route_name' => 'testing.healthy-page']);
});

test('an expected page exception keeps its client error status in the audit', function () {
    Route::middleware('web')->get('/_test/missing-page', fn () => abort(404))
        ->name('testing.missing-page');

    $this->get('/_test/missing-page')->assertNotFound();

    $this->assertDatabaseHas('activity_logs', [
        'route_name' => 'testing.missing-page',
        'http_method' => 'GET',
        'status_code' => 404,
        'outcome' => 'failure',
        'severity' => 'warning',
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
