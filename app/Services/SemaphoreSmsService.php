<?php

// FEATURE:emergency-alerts - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Services;

use App\Contracts\SmsProvider;
use App\Models\EmergencyAlert;
use App\Models\EmergencyHotline;
use App\Models\Students;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemaphoreSmsService implements SmsProvider
{
    public function __construct(private readonly ?array $credentials = null) {}

    // @function send: Ipinapadala ang semaphore sms sa Semaphore Sms flow.
    // @useIn send: SemaphoreSmsService::sendEmergencyAlert (app/Services/SemaphoreSmsService.php)
    public function send(string $recipient, string $message): array
    {
        if (! config('services.semaphore.enabled', true)) {
            return ['sent' => false, 'reason' => 'disabled'];
        }

        $apiKey = ($this->credentials ?? SystemSetting::smsCredentials('semaphore'))['token'];
        if ($apiKey === '') {
            return ['sent' => false, 'reason' => 'missing_api_key'];
        }

        $number = $this->normalizeNumber($recipient);
        if ($number === '') {
            return ['sent' => false, 'reason' => 'missing_recipient'];
        }

        $payload = [
            'apikey' => $apiKey,
            'number' => $number,
            'message' => mb_substr($message, 0, 1000),
        ];
        $senderName = trim(($this->credentials ?? SystemSetting::smsCredentials('semaphore'))['sender_id']);
        if ($senderName !== '') {
            $payload['sendername'] = $senderName;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post((string) config('services.semaphore.endpoint'), $payload);

            return [
                'sent' => $response->successful(),
                'status' => $response->status(),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Semaphore SMS request errored.', [
                'exception' => $exception::class,
            ]);

            return ['sent' => false, 'reason' => 'request_failed'];
        }
    }

    // @function check: Sini-check ang semaphore sms sa Semaphore Sms flow.
    // @useIn check: TODO(verify): walang direct caller na nakita sa static search
    public function check(): array
    {
        if (! config('services.semaphore.enabled', true)) {
            return ['success' => false, 'message' => 'Semaphore is disabled by environment configuration.'];
        }

        $apiKey = ($this->credentials ?? SystemSetting::smsCredentials('semaphore'))['token'];
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'Semaphore API key is not configured.'];
        }

        try {
            $response = Http::timeout(10)->get(
                (string) config('services.semaphore.account_endpoint', 'https://api.semaphore.co/api/v4/account'),
                ['apikey' => $apiKey],
            );

            if (! $response->successful()) {
                return ['success' => false, 'message' => 'Semaphore account check failed.'];
            }

            $balance = $response->json('credit_balance');

            return [
                'success' => true,
                'message' => is_numeric($balance)
                    ? 'Semaphore account is available. Balance: '.$balance.' credits.'
                    : 'Semaphore account is available.',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Semaphore account check errored.', [
                'exception' => $exception::class,
            ]);

            return ['success' => false, 'message' => 'Semaphore account check timed out or could not connect.'];
        }
    }

    // @function sendEmergencyAlert: Ipinapadala ang emergency alert sa Semaphore Sms flow.
    // @useIn sendEmergencyAlert: TODO(verify): walang direct caller na nakita sa static search
    public function sendEmergencyAlert(EmergencyHotline $hotline, EmergencyAlert $alert): array
    {
        if (! config('services.semaphore.enabled', true)) {
            return ['sent' => false, 'reason' => 'disabled'];
        }

        if (! $hotline->sms_enabled) {
            return ['sent' => false, 'reason' => 'hotline_sms_disabled'];
        }

        return $this->send($hotline->phone_number, $this->message($hotline, $alert));
    }

    // @function sendParentAlert: Ipinapadala ang parent alert sa Semaphore Sms flow.
    // @useIn sendParentAlert: TODO(verify): walang direct caller na nakita sa static search
    public function sendParentAlert(User $parent, Students $student, EmergencyAlert $alert): array
    {
        if (! config('services.semaphore.enabled', true)) {
            return ['sent' => false, 'reason' => 'disabled'];
        }

        $studentName = trim($student->first_name.' '.$student->last_name);

        return $this->send($parent->phone, implode(' ', [
            'Emergency alert for '.$studentName.'.',
            'Type: '.($alert->type?->name ?? 'Emergency').'.',
            'Location: '.($alert->room ?: 'N/A').'.',
            'Details: '.$alert->message,
        ]));
    }

    // @function normalizeNumber: Nino-normalize ang number sa Semaphore Sms flow.
    // @useIn normalizeNumber: SemaphoreSmsService::send (app/Services/SemaphoreSmsService.php)
    private function normalizeNumber(?string $number): string
    {
        return preg_replace('/[^\d+]/', '', (string) $number) ?? '';
    }

    // @function message: Binubuo ang message string para sa Semaphore Sms.
    // @useIn message: SemaphoreSmsService::sendEmergencyAlert (app/Services/SemaphoreSmsService.php)
    private function message(EmergencyHotline $hotline, EmergencyAlert $alert): string
    {
        $parts = [
            'Emergency alert from attendance panel.',
            'Type: '.($alert->type?->name ?? 'Emergency'),
            'Room: '.($alert->room ?: 'N/A'),
            'Triggered by: '.($alert->triggered_by_name ?: 'N/A'),
            'Message: '.$alert->message,
            'Hotline: '.$hotline->name,
        ];

        return mb_substr(implode(' ', $parts), 0, 1000);
    }
}
