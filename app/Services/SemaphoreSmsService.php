<?php

namespace App\Services;

use App\Models\EmergencyAlert;
use App\Models\EmergencyHotline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemaphoreSmsService
{
    public function sendEmergencyAlert(EmergencyHotline $hotline, EmergencyAlert $alert): array
    {
        if (! config('services.semaphore.enabled', true)) {
            return ['sent' => false, 'reason' => 'disabled'];
        }

        if (! $hotline->sms_enabled) {
            return ['sent' => false, 'reason' => 'hotline_sms_disabled'];
        }

        $apiKey = (string) config('services.semaphore.key', '');
        if ($apiKey === '') {
            return ['sent' => false, 'reason' => 'missing_api_key'];
        }

        $number = $this->normalizeNumber($hotline->phone_number);
        if ($number === '') {
            return ['sent' => false, 'reason' => 'missing_recipient'];
        }

        $payload = [
            'apikey' => $apiKey,
            'number' => $number,
            'message' => $this->message($hotline, $alert),
        ];

        $senderName = trim((string) config('services.semaphore.sender_name', ''));
        if ($senderName !== '') {
            $payload['sendername'] = $senderName;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post((string) config('services.semaphore.endpoint'), $payload);

            if (! $response->successful()) {
                Log::warning('Semaphore SMS request failed.', [
                    'alert_id' => $alert->emergency_alert_id,
                    'hotline_id' => $hotline->emergency_hotline_id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return [
                'sent' => $response->successful(),
                'status' => $response->status(),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Semaphore SMS request errored.', [
                'alert_id' => $alert->emergency_alert_id,
                'hotline_id' => $hotline->emergency_hotline_id,
                'message' => $exception->getMessage(),
            ]);

            return ['sent' => false, 'reason' => 'request_failed'];
        }
    }

    private function normalizeNumber(?string $number): string
    {
        return preg_replace('/[^\d+]/', '', (string) $number) ?? '';
    }

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
