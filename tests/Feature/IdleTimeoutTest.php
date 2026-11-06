<?php

use App\Http\Middleware\EnforceIdleTimeout;
use App\Models\User;
use App\Support\AuthenticatedSession;
use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config()->set('idle_timeout.idle_minutes', 1);
    config()->set('idle_timeout.warning_seconds', 10);
    config()->set('idle_timeout.enabled_roles', ['student', 'parent', 'admin', 'instructor']);
    config()->set('idle_timeout.exempt_roles', ['clinic', 'console']);

    Route::middleware('web')->get('/_idle-timeout-test', fn () => response()->json(['ok' => true]));
});

test('protected roles are logged out after the idle window', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $response = $this->actingAs($user)->withSession([
        AuthenticatedSession::USER_ID => (string) $user->getKey(),
        AuthenticatedSession::LOGIN_ID => "login-$role",
        EnforceIdleTimeout::LAST_ACTIVITY_AT => now()->subSeconds(71)->timestamp,
    ])->getJson('/_idle-timeout-test');

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'idle_timeout')
        ->assertJsonPath('message', 'You were logged out due to inactivity.');
    $this->assertGuest();
})->with(['student', 'parent', 'admin', 'instructor']);

test('a background poll does not refresh the backend activity timestamp', function () {
    $user = User::factory()->create(['role' => 'student']);
    $lastActivityAt = now()->subSeconds(30)->timestamp;

    $this->actingAs($user)->withSession([
        AuthenticatedSession::USER_ID => (string) $user->getKey(),
        AuthenticatedSession::LOGIN_ID => 'student-poll-session',
        EnforceIdleTimeout::LAST_ACTIVITY_AT => $lastActivityAt,
    ])->withHeader('X-Background-Poll', '1')
        ->getJson('/_idle-timeout-test')
        ->assertOk()
        ->assertSessionHas(EnforceIdleTimeout::LAST_ACTIVITY_AT, $lastActivityAt);
});

test('real user requests refresh the backend activity timestamp', function () {
    $user = User::factory()->create(['role' => 'student']);
    $lastActivityAt = now()->subSeconds(30)->timestamp;

    $this->actingAs($user)->withSession([
        AuthenticatedSession::USER_ID => (string) $user->getKey(),
        AuthenticatedSession::LOGIN_ID => 'student-active-session',
        EnforceIdleTimeout::LAST_ACTIVITY_AT => $lastActivityAt,
    ])->getJson('/_idle-timeout-test')
        ->assertOk()
        ->assertSessionHas(EnforceIdleTimeout::LAST_ACTIVITY_AT, now()->timestamp);
});

test('exempt roles are not expired by the idle middleware', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $lastActivityAt = now()->subDay()->timestamp;

    $this->actingAs($user)->withSession([
        AuthenticatedSession::USER_ID => (string) $user->getKey(),
        AuthenticatedSession::LOGIN_ID => "login-$role",
        EnforceIdleTimeout::LAST_ACTIVITY_AT => $lastActivityAt,
    ])->getJson('/_idle-timeout-test')
        ->assertOk()
        ->assertSessionHas(EnforceIdleTimeout::LAST_ACTIVITY_AT, $lastActivityAt);

    $this->assertAuthenticatedAs($user);
})->with(['clinic', 'console']);

test('the physical session outlasts the idle warning window', function () {
    expect(config('session.lifetime'))->toBeGreaterThanOrEqual(2);
});
