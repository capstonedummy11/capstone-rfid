<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get(route('staff.login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('staff.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

test('instructors are redirected to email otp verification after login', function () {
    $user = User::factory()->create(['role' => 'instructor']);

    $response = $this->post(route('staff.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('instructor.verify'));
    $this->assertAuthenticatedAs($user);
});

test('instructor can request an email otp', function () {
    Mail::fake();
    $user = User::factory()->create([
        'role' => 'instructor',
        'email' => 'instructor.otp@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('instructor.verify.otp.send'))
        ->assertRedirect()
        ->assertSessionHas('success', 'OTP sent to instructor.otp@example.com. It expires in 10 minutes.')
        ->assertSessionHas('instructor_login_otp')
        ->assertSessionHas('instructor_login_otp_expires_at');

});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('staff.login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('landingPage'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('staff.login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('staff.login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
