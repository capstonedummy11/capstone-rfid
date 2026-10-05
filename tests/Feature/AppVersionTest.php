<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

test('application version is shared with every Inertia page', function () {
    config(['app.version' => '1.1']);

    $shared = app(HandleInertiaRequests::class)->share(Request::create('/'));

    expect($shared['appVersion'])->toBe('1.1');
});
