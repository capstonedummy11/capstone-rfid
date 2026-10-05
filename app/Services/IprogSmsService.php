<?php

namespace App\Services;

use App\Contracts\SmsProvider;
use App\Models\EmergencyAlert;
use App\Models\EmergencyHotline;
use App\Models\Students;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IprogSmsService implements SmsProvider
{
    public function send(string $recipient, string $message): array
    {
        return $this->sendMessage($recipient, $message);
    }

    public function check(): array
    {
        $token = trim((string) config('services.iprog.token', ''));
        if (! config('services.iprog.enabled', true)) {
            return ['success' => false, 'message' => 'IPROG SMS is disabled by environment configuration.'];
        }
        if ($token === '') {
            return ['success' => false, 'message' => 'IPROG SMS API token is not configured.'];
        }

        try {
            $response = Http::timeout(10)->get(
                (string) config('services.iprog.balance_endpoint', 'https://www.iprogsms.com/api/v1/account/sms_credits'),
                ['api_token' => $token],
            );

            if (! $response->successful() || $response->json('status') !== 'success') {
                return ['success' => false, 'message' => 'IPROG SMS account check failed.'];
            }

            $balance = $response->json('data.load_balance');

            return [
                'success' => true,
                'message' => is_numeric($balance)
                    ? 'IPROG SMS account is available. Balance: '.$balance.' credits.'
                    : 'IPROG SMS account is available.',
            ];
        } catch (\Throwable $exception) {
            Log::warning('IPROG SMS account check errored.', [
                'exception' => $exception::class,
            ]);

            return ['success' => false, 'message' => 'IPROG SMS account check timed out or could not connect.'];
        }
    }

    public function sendEmergencyAlert(EmergencyHotline $hotline, EmergencyAlert $alert): array
    {
        if (! config('services.iprog.enabled', true)) {
            return ['sent' => false, 'reason' => 'disabled'];
        }

        if (! $hotline->sms_enabled) {
            return ['sent' => false, 'reason' => 'hotline_sms_disabled'];
        }

        return $this->sendMessage(
            $hotline->phone_number,
            $this->emergencyMessage($hotline, $alert),
            $alert,
            ['hotline_id' => $hotline->emergency_hotline_id],
        );
    }

    public function sendParentAlert(User $parent, Students $student, EmergencyAlert $alert): array
    {
        return $this->sendMessage(
            $parent->phone,
            $this->parentMessage($student, $alert),
            $alert,
            ['parent_user_id' => $parent->user_id],
        );
    }

    private function sendMessage(?string $recipient, string $message, ?EmergencyAlert $alert = null, array $logContext = []): array
    {
        if (! config('services.iprog.enabled', true)) {
            return ['sent' => false, 'reason' => 'disabled'];
        }

        $token = trim((string) config('services.iprog.token', ''));
        if ($token === '') {
            return ['sent' => false, 'reason' => 'missing_api_token'];
        }

        $number = $this->normalizeNumber($recipient);
        if ($number === '') {
            return ['sent' => false, 'reason' => 'missing_recipient'];
        }

        try {
            $response = Http::asJson()
                ->timeout(10)
                ->post((string) config('services.iprog.endpoint'), [
                    'api_token' => $token,
                    'phone_number' => $number,
                    'message' => mb_substr($message, 0, 1000),
                ]);

            $providerStatus = $response->json('status');
            $sent = $response->successful() && ((string) $providerStatus === '200' || $providerStatus === 200);

            if (! $sent) {
                Log::warning('IPROG SMS request failed.', [
                    'alert_id' => $alert?->emergency_alert_id,
                    ...$logContext,
                    'http_status' => $response->status(),
                    'provider_status' => $providerStatus,
                ]);
            }

            return [
                'sent' => $sent,
                'status' => $response->status(),
                'message_id' => $response->json('message_id'),
            ];
        } catch (\Throwable $exception) {
            Log::warning('IPROG SMS request errored.', [
                'alert_id' => $alert?->emergency_alert_id,
                ...$logContext,
                'message' => $exception->getMessage(),
            ]);

            return ['sent' => false, 'reason' => 'request_failed'];
        }
    }

    private function normalizeNumber(?string $number): string
    {
        $number = preg_replace('/[^\d+]/', '', (string) $number) ?? '';
        $number = ltrim($number, '+');

        if (str_starts_with($number, '0')) {
            return '63'.substr($number, 1);
        }

        return $number;
    }

    private function emergencyMessage(EmergencyHotline $hotline, EmergencyAlert $alert): string
    {
        return implode(' ', [
            'Emergency alert from attendance panel.',
            'Type: '.($alert->type?->name ?? 'Emergency').'.',
            'Room: '.($alert->room ?: 'N/A').'.',
            'Triggered by: '.($alert->triggered_by_name ?: 'N/A').'.',
            'Message: '.$alert->message,
            'Hotline: '.$hotline->name.'.',
        ]);
    }

    private function parentMessage(Students $student, EmergencyAlert $alert): string
    {
        return implode(' ', [
            'Emergency alert for '.trim($student->first_name.' '.$student->last_name).'.',
            'Type: '.($alert->type?->name ?? 'Emergency').'.',
            'Location: '.($alert->room ?: 'N/A').'.',
            'Details: '.$alert->message,
        ]);
    }
}
