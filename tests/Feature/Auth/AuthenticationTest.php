<?php

use App\Mail\AdminLoginOtpMail;
use App\Models\User;
use App\Services\Auth\AdminLoginOtpService;
use App\Support\AuthenticatedSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get(route('staff.login'));

    $response->assertOk();
});

test('admin password login requires the emailed otp before protected access', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->post(route('staff.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.login-verification.show', absolute: false));
    $response->assertSessionHas(AuthenticatedSession::USER_ID, (string) $user->getKey());
    $response->assertSessionHas(AuthenticatedSession::LOGIN_ID)
        ->assertSessionHas(AdminLoginOtpService::HASH)
        ->assertSessionMissing(AdminLoginOtpService::VERIFIED_LOGIN_ID);

    $code = null;
    Mail::assertSent(AdminLoginOtpMail::class, function (AdminLoginOtpMail $mail) use ($user, &$code) {
        $code = $mail->code;

        return $mail->hasTo($user->email);
    });

    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login-verification.show'));
    $this->get(route('admin.login-verification.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/AdminLoginVerification')
            ->where('email', fn ($email) => str_contains($email, '@'))
        );

    $this->post(route('admin.login-verification.verify'), ['otp' => '000000'])
        ->assertSessionHasErrors('otp');
    $this->post(route('admin.login-verification.verify'), ['otp' => $code])
        ->assertRedirect(route('admin.dashboard'));
    $this->get(route('admin.dashboard'))->assertOk();
});

test('new root admin completes the private password step before verifying the login otp', function () {
    Mail::fake();
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_root_admin' => true,
        'must_change_password' => true,
    ]);

    $this->post(route('staff.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.login-verification.show'));

    $this->get(route('admin.login-verification.show'))
        ->assertRedirect(route('password.first-login'));
    $this->get(route('password.first-login'))->assertOk();

    $this->put(route('password.first-login.update'), [
        'password' => 'PrivatePassword123!',
        'password_confirmation' => 'PrivatePassword123!',
    ])->assertRedirect(route('dashboard'));

    expect($admin->fresh()->must_change_password)->toBeFalse();
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login-verification.show'));
    $this->get(route('admin.login-verification.show'))->assertOk();

    $code = Mail::sent(AdminLoginOtpMail::class)->sole()->code;
    $this->post(route('admin.login-verification.verify'), ['otp' => $code])
        ->assertRedirect(route('admin.dashboard'));
    $this->get(route('admin.dashboard'))->assertOk();
});

test('resending an admin login otp invalidates the previous code', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->post(route('staff.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);
    $firstCode = Mail::sent(AdminLoginOtpMail::class)->first()->code;

    $this->post(route('admin.login-verification.resend'))
        ->assertSessionHasErrors('otp');

    $this->travel((int) config('admin_login_otp.resend_seconds'))->seconds();
    $this->post(route('admin.login-verification.resend'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $secondCode = Mail::sent(AdminLoginOtpMail::class)->last()->code;

    expect($secondCode)->not->toBe($firstCode);
    $this->post(route('admin.login-verification.verify'), ['otp' => $firstCode])
        ->assertSessionHasErrors('otp');
    $this->post(route('admin.login-verification.verify'), ['otp' => $secondCode])
        ->assertRedirect(route('admin.dashboard'));
});

test('expired admin login otp is rejected and protected access remains blocked', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->post(route('staff.login.store'), ['email' => $admin->email, 'password' => 'password']);
    $code = Mail::sent(AdminLoginOtpMail::class)->sole()->code;

    $this->travel((int) config('admin_login_otp.expires_minutes'))->minutes();
    $this->travel(1)->second();

    $this->post(route('admin.login-verification.verify'), ['otp' => $code])
        ->assertSessionHasErrors('otp');
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login-verification.show'));
});

test('admin login otp locks after the configured number of incorrect attempts', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->post(route('staff.login.store'), ['email' => $admin->email, 'password' => 'password']);

    foreach (range(1, (int) config('admin_login_otp.max_attempts')) as $attempt) {
        $response = $this->post(route('admin.login-verification.verify'), ['otp' => '000000']);
    }

    $response->assertSessionHasErrors(['otp' => 'Too many incorrect attempts. Request a new code.']);
    $response->assertSessionMissing(AdminLoginOtpService::HASH);
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login-verification.show'));
});

test('remember email does not create persistent authentication', function () {
    Mail::fake();
    config()->set('session.expire_on_close', true);

    $user = User::factory()->create();
    $initialRememberToken = $user->getRememberToken();

    $response = $this->post(route('staff.login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $sessionCookie = $response->getCookie(config('session.cookie'));

    expect($sessionCookie)->not->toBeNull()
        ->and($sessionCookie->getExpiresTime())->toBe(0)
        ->and(Auth::guard()->viaRemember())->toBeFalse()
        ->and($user->refresh()->getRememberToken())->toBe($initialRememberToken);
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
        ->assertRedirect(route('admin.login-verification.show'));

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
        ])->assertRedirect(route('admin.login-verification.show'));

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
