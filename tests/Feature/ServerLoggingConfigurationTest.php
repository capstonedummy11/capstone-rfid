<?php

test('server logging writes errors to Laravel daily files and the server error log', function () {
    expect(config('logging.channels.server'))
        ->toMatchArray([
            'driver' => 'stack',
            'channels' => ['daily', 'errorlog'],
            'ignore_exceptions' => true,
        ]);
});
