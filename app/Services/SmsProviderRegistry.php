<?php

namespace App\Services;

use App\Contracts\SmsProvider;

class SmsProviderRegistry
{
    public function __construct(
        private readonly SemaphoreSmsService $semaphore,
        private readonly IprogSmsService $iprog,
    ) {}

    public function get(string $provider): SmsProvider
    {
        return match ($provider) {
            'semaphore' => $this->semaphore,
            'iprog' => $this->iprog,
            default => throw new \InvalidArgumentException('Unsupported SMS provider.'),
        };
    }

    public function names(): array
    {
        return ['semaphore', 'iprog'];
    }
}
