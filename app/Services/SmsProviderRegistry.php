<?php

namespace App\Services;

use App\Contracts\SmsProvider;

class SmsProviderRegistry
{
    // @function __construct: Tinatanggap ang dependencies ng Sms Provider Registry sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang SmsProviderRegistry
    public function __construct(
        private readonly SemaphoreSmsService $semaphore,
        private readonly IprogSmsService $iprog,
    ) {}

    // @function get: Kinukuha ang sms provider registry sa Sms Provider Registry flow.
    // @useIn get: app/Services/SmsService.php
    public function get(string $provider): SmsProvider
    {
        return match ($provider) {
            'semaphore' => $this->semaphore,
            'iprog' => $this->iprog,
            default => throw new \InvalidArgumentException('Unsupported SMS provider.'),
        };
    }

    // @function names: Kinukuha ang names result para sa Sms Provider Registry.
    // @useIn names: TODO(verify): walang direct caller na nakita sa static search
    public function names(): array
    {
        return ['semaphore', 'iprog'];
    }
}
