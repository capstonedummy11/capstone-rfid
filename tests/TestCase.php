<?php

namespace Tests;

use App\Support\AuthenticatedSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $session = $this->app['session.store'];

        $session->forget([
            AuthenticatedSession::USER_ID,
            AuthenticatedSession::LOGIN_ID,
            AuthenticatedSession::ISSUED_AT,
        ]);
        $session->migrate(true);

        return parent::actingAs($user, $guard);
    }
}
