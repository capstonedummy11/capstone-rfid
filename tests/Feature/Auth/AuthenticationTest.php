<?php

use App\Models\User;
use App\Support\AuthenticatedSession;
use Illuminate\Support\Facades\Auth;
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
    $response->assertSessionHas(AuthenticatedSession::USER_ID, (string) $user->getKey());
    $response->assertSessionHas(AuthenticatedSession::LOGIN_ID);
});

test('different browser sessions keep independent authenticated users', function () {
    config()->set('session.driver', 'database');
    app('session')->forgetDrivers();

    $admin = User::factory()->create([
        'email' => 'admin.browser@example.com',
        'role' => 'admin',
    ]);
    $student = User::factory()->create([
        'email' => 'student.browser@example.com',
        'role' => 'student',
    ]);
    $cookieName = config('session.cookie');

    $adminLogin = $this->post(route('staff.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);
    $adminSessionId = $adminLogin->getCookie($cookieName)?->getValue();

    Auth::forgetGuards();
    app('session.store')->flush();
    $this->defaultCookies = [];

    $studentLogin = $this->post(route('student-parent.login.store'), [
        'email' => $student->email,
        'password' => 'password',
    ]);
    $studentSessionId = $studentLogin->getCookie($cookieName)?->getValue();

    expect($adminSessionId)->not->toBeNull()
        ->and($studentSessionId)->not->toBeNull()
        ->and($adminSessionId)->not->toBe($studentSessionId);

    Auth::forgetGuards();
    app('session.store')->flush();
    $this->defaultCookies = [];
    $this->withCookie($cookieName, $adminSessionId)
        ->get(route('landingPage'))
        ->assertRedirect(route('admin.dashboard'));

    Auth::forgetGuards();
    app('session.store')->flush();
    $this->defaultCookies = [];
    $this->withCookie($cookieName, $studentSessionId)
        ->get(route('landingPage'))
        ->assertRedirect(route('student-parent.dashboard'));
});

test('the same browser session cannot sign into a second account', function () {
    config()->set('session.driver', 'database');
    app('session')->forgetDrivers();

    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    $adminLogin = $this->post(route('staff.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $sessionId = $adminLogin->getCookie(config('session.cookie'))?->getValue();

    Auth::forgetGuards();
    app('session.store')->flush();
    $this->defaultCookies = [];

    $this->withCookie(config('session.cookie'), $sessionId)
        ->post(route('student-parent.login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($admin);
});

test('a mismatched session identity is invalidated instead of switching users', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->withSession([
            AuthenticatedSession::USER_ID => 'different-user',
            AuthenticatedSession::LOGIN_ID => 'existing-login-id',
        ])
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('staff.login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
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

test('student account cannot enter staff root admin area and uses student portal login', function () {
    $user = User::factory()->create([
        'name' => 'Miguel Reyes',
        'email' => 'miguel.reyes@student.sample.com',
        'role' => 'student',
        'is_root_admin' => true,
    ]);

    $this->post(route('staff.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->post(route('student-parent.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('student-parent.dashboard'));

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
