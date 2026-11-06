<?php

namespace App\Services;

use App\Contracts\SmsProvider;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhilSmsService implements SmsProvider
{
    public function __construct(private readonly ?array $credentials = null) {}

    public static function normalizeNumber(string $number): string
    {
        $number = preg_replace('/[\s()\-]/', '', trim($number));
        if (preg_match('/^09\d{9}$/', $number)) {
            $number = '63'.substr($number, 1);
        }
        $number = preg_replace('/^\+/', '', $number);

        return preg_match('/^639\d{9}$/', $number) ? $number : '';
    }

    public function send(string $recipient, string $message): array
    {
        $credentials = $this->credentials ?? SystemSetting::smsCredentials('philsms');
        if (! config('services.philsms.enabled', true)) {
            return ['sent' => false, 'reason' => 'disabled'];
        }
        if ($credentials['token'] === '') {
            return ['sent' => false, 'reason' => 'missing_api_token'];
        }
        if ($credentials['sender_id'] === '') {
            return ['sent' => false, 'reason' => 'missing_sender_id'];
        }
        $numbers = array_map(self::normalizeNumber(...), explode(',', $recipient));
        if (in_array('', $numbers, true)) {
            return ['sent' => false, 'reason' => 'invalid_recipient'];
        }

        try {
            $response = Http::asJson()->acceptJson()->withToken($credentials['token'])
                ->timeout(10)->post(config('services.philsms.endpoint'), [
                    'recipient' => implode(',', $numbers),
                    'sender_id' => $credentials['sender_id'],
                    'type' => 'plain',
                    'message' => mb_substr($message, 0, 1000),
                ]);
            $sent = $response->successful() && $response->json('status') === 'success';
            if (! $sent) {
                // Provider messages may echo credentials, numbers, or message content.
                // Record only a recognized short reason, never the raw response.
                $error = strtolower((string) $response->json('message', ''));
                $reason = match (true) {
                    str_contains($error, 'balance'), str_contains($error, 'credit') => 'insufficient_credits',
                    str_contains($error, 'sender') => 'sender_rejected',
                    str_contains($error, 'token'), str_contains($error, 'unauthor') => 'authentication_failed',
                    default => 'provider_rejected',
                };
                Log::warning('PhilSMS request failed.', ['status' => $response->status(), 'message' => $reason]);
            }

            return ['sent' => $sent, 'status' => $response->status(), 'reason' => $sent ? null : $reason];
        } catch (\Throwable $exception) {
            Log::warning('PhilSMS request errored.', ['exception' => $exception::class]);

            return ['sent' => false, 'reason' => 'request_failed'];
        }
    }

    public function check(): array
    {
        // No unverified balance endpoint: use the explicit test send to verify access.
        return ['success' => false, 'message' => 'Use Send Test SMS to verify this account.'];
    }
}
