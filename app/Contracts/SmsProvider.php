<?php

namespace App\Contracts;

interface SmsProvider
{
    /**
     * Send one SMS without exposing provider-specific request details to callers.
     *
     * @return array{sent: bool, status?: int, reason?: string}
     */
    public function send(string $recipient, string $message): array;

    /**
     * Verify provider credentials/account access without sending an SMS.
     *
     * @return array{success: bool, message: string}
     */
    public function check(): array;
}
