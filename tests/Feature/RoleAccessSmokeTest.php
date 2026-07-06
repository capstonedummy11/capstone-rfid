<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('each role is sent to the correct landing destination', function (string $role, string $routeName) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('landingPage'))
        ->assertRedirect(route($routeName));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($routeName));
})->with([
    'admin' => ['admin', 'admin.dashboard'],
    'instructor' => ['instructor', 'admin.dashboard'],
    'clinic' => ['clinic', 'clinic.dashboard'],
    'console' => ['console', 'attendanceControlPanel'],
    'registrar' => ['registrar', 'registrar.dashboard'],
    'student' => ['student', 'student-parent.dashboard'],
    'parent' => ['parent', 'student-parent.dashboard'],
]);

test('each role can open its primary page', function (string $role, string $routeName, array $session = []) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->withSession($session)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'admin' => ['admin', 'admin.dashboard'],
    'instructor' => ['instructor', 'admin.dashboard', ['instructor_verified' => true]],
    'clinic' => ['clinic', 'clinic.dashboard'],
    'console' => ['console', 'attendanceControlPanel', ['panel.room' => 'B202']],
    'registrar' => ['registrar', 'registrar.dashboard'],
    'student' => ['student', 'student-parent.dashboard'],
    'parent' => ['parent', 'student-parent.dashboard'],
]);
