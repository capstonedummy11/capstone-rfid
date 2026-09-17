<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('non console accounts can request a password reset link', function () {
    Notification::fake();
    $user = User::factory()->create([
        'role' => 'instructor',
        'email' => 'instructor-reset@example.test',
    ]);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('console accounts do not receive password reset links', function () {
    Notification::fake();
    $console = User::factory()->create([
        'role' => 'console',
        'email' => 'console-reset@example.test',
    ]);

    $this->post(route('password.email'), ['email' => $console->email])
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

test('new non console account must change temporary password before dashboard access', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('password.first-login'));
});

test('user can replace a temporary password and continue', function () {
    $user = User::factory()->create([
        'role' => 'student',
        'password' => Hash::make('Temporary123!'),
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->put(route('password.first-login.update'), [
            'password' => 'PrivatePassword123!',
            'password_confirmation' => 'PrivatePassword123!',
        ])
        ->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('PrivatePassword123!', $user->password))->toBeTrue();
});

test('console accounts are excluded from first login password change', function () {
    $console = User::factory()->create([
        'role' => 'console',
        'must_change_password' => true,
    ]);

    $this->actingAs($console)
        ->get(route('password.first-login'))
        ->assertForbidden();
});

test('staff password reset returns to staff login and clears first login flag', function () {
    $user = User::factory()->create([
        'role' => 'clinic',
        'email' => 'clinic-reset@example.test',
        'must_change_password' => true,
    ]);
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'RecoveredPassword123!',
        'password_confirmation' => 'RecoveredPassword123!',
    ])->assertRedirect(route('staff.login'));

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('RecoveredPassword123!', $user->password))->toBeTrue();
});
