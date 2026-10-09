<?php

// FEATURE:emergency-alerts - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Services;

use App\Models\EmergencyAlert;
use App\Models\EmergencyHotline;
use App\Models\Students;
use App\Models\SystemSetting;
use App\Models\User;

class SmsService
{
    private array $deliveryResults = [];

    public function resetDeliveryResults(): void
    {
        $this->deliveryResults = [];
    }

    public function deliverySummary(int $missingPhones = 0): array
    {
        $succeeded = count(array_filter($this->deliveryResults, fn ($result) => $result['sent'] ?? false));
        $total = count($this->deliveryResults) + $missingPhones;
        $reasons = collect($this->deliveryResults)
            ->flatMap(fn ($result) => collect($result['attempts'] ?? [])->pluck('reason')
                ->push(($result['sent'] ?? false) ? null : ($result['reason'] ?? 'provider_rejected')))
            ->filter()->unique()->values()->all();
        if ($missingPhones) {
            $reasons[] = 'missing_parent_phone';
        }

        return [
            'total_recipients' => $total, 'succeeded' => $succeeded, 'failed' => $total - $succeeded,
            'providers' => collect($this->deliveryResults)->pluck('provider')->filter()->unique()->values()->all(),
            'attempted_providers' => collect($this->deliveryResults)->flatMap(fn ($result) => collect($result['attempts'] ?? [])->pluck('provider'))->unique()->values()->all(),
            'fallback_used' => collect($this->deliveryResults)->contains(fn ($result) => count($result['attempts'] ?? []) > 1),
            'reasons' => $reasons,
        ];
    }

    private function track(array $result): array
    {
        $this->deliveryResults[] = $result;

        return $result;
    }

    // @function __construct: Tinatanggap ang dependencies ng Sms sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang SmsService
    public function __construct(
        private readonly SmsProviderRegistry $providers,
    ) {}

    // @function sendEmergencyAlert: Ipinapadala ang emergency alert sa Sms flow.
    // @useIn sendEmergencyAlert: app/Http/Controllers/Shared/Emergency/EmergencyController.php
    public function sendEmergencyAlert(EmergencyHotline $hotline, EmergencyAlert $alert): array
    {
        if (! $hotline->sms_enabled) {
            return ['sent' => false, 'reason' => 'hotline_sms_disabled'];
        }

        return $this->track($this->sendWithFallback(
            $alert,
            $this->emergencyMessage($hotline, $alert),
            $hotline->phone_number,
        ));
    }

    // @function sendParentAlert: Ipinapadala ang parent alert sa Sms flow.
    // @useIn sendParentAlert: app/Http/Controllers/Shared/Emergency/EmergencyController.php
    public function sendParentAlert(User $parent, Students $student, EmergencyAlert $alert): array
    {
        return $this->track($this->sendWithFallback(
            $alert,
            $this->parentMessage($student, $alert),
            $parent->phone,
        ));
    }

    // @function checkProvider: Sini-check ang provider sa Sms flow.
    // @useIn checkProvider: app/Http/Controllers/Shared/SystemSettings/SystemSettingsController.php
    public function checkProvider(string $provider): array
    {
        return $this->providers->get($provider)->check();
    }

    // @function sendWithFallback: Ipinapadala ang with fallback sa Sms flow.
    // @useIn sendWithFallback: SmsService::sendEmergencyAlert (app/Services/SmsService.php)
    private function sendWithFallback(EmergencyAlert $alert, string $message, ?string $recipient): array
    {
        if (trim((string) $recipient) === '') {
            return ['sent' => false, 'reason' => 'missing_recipient'];
        }

        $settings = SystemSetting::smsProviderSettings();
        $available = collect($settings['providers'] ?? [])
            ->filter(fn (array $provider) => (bool) ($provider['available'] ?? false))
            ->keys()
            ->values();

        if ($available->isEmpty()) {
            return ['sent' => false, 'reason' => 'no_provider_available'];
        }

        $primary = $settings['primary'] ?? $available->first();
        $order = collect([$primary])
            ->merge($available)
            ->filter()
            ->unique()
            ->values();
        $attempts = [];

        foreach ($order as $providerName) {
            $result = $this->providers->get($providerName)->send((string) $recipient, $message);
            $attempts[] = [
                'provider' => $providerName,
                'sent' => (bool) ($result['sent'] ?? false),
                'reason' => $result['reason'] ?? (($result['sent'] ?? false) ? null : 'provider_rejected'),
            ];

            if ($result['sent'] ?? false) {
                return [...$result, 'provider' => $providerName, 'attempts' => $attempts];
            }
        }

        return [
            'sent' => false,
            'reason' => 'all_providers_failed',
            'attempts' => $attempts,
        ];
    }

    // @function emergencyMessage: Binubuo ang emergency message string para sa Sms.
    // @useIn emergencyMessage: SmsService::sendEmergencyAlert (app/Services/SmsService.php)
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

    // @function parentMessage: Binubuo ang parent message string para sa Sms.
    // @useIn parentMessage: SmsService::sendParentAlert (app/Services/SmsService.php)
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
