<?php

namespace App\Contracts;

interface SmsProvider
{
    // @function send: Ipinapadala ang sms sa Sms flow.
    // @useIn send: app/Services/SmsService.php
    /**
     * Send one SMS without exposing provider-specific request details to callers.
     *
     * @return array{sent: bool, status?: int, reason?: string}
     */
    public function send(string $recipient, string $message): array;

    // @function check: Sini-check ang sms sa Sms flow.
    // @useIn check: app/Services/SmsService.php
    /**
     * Verify provider credentials/account access without sending an SMS.
     *
     * @return array{success: bool, message: string}
     */
    public function check(): array;
}
