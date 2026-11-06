<?php

namespace App\Services;

use App\Models\ClinicCase;
use App\Models\EmergencyAlert;

class ClinicCaseAlertService
{
    public function syncFromCase(ClinicCase $case): void
    {
        if (! $case->emergency_alert_id) {
            return;
        }

        $alert = EmergencyAlert::query()->lockForUpdate()->find($case->emergency_alert_id);
        if (! $alert) {
            return;
        }

        $cases = $alert->cases()->get();
        $status = match (true) {
            $cases->every(fn (ClinicCase $linkedCase) => $linkedCase->status === 'resolved') => 'resolved',
            $cases->every(fn (ClinicCase $linkedCase) => $linkedCase->status === 'cancelled') => 'cancelled',
            $cases->every(fn (ClinicCase $linkedCase) => $linkedCase->status === 'open') => 'open',
            default => 'acknowledged',
        };

        $alert->update([
            'status' => $status,
            'resolved_at' => $status === 'resolved' ? ($alert->resolved_at ?? now()) : null,
            'acknowledged_at' => $status === 'acknowledged' ? ($alert->acknowledged_at ?? now()) : $alert->acknowledged_at,
        ]);
    }

    public function syncFromAlert(EmergencyAlert $alert): void
    {
        $caseStatus = match ($alert->status) {
            'acknowledged' => 'monitoring',
            default => $alert->status,
        };

        $alert->cases()->update(['status' => $caseStatus]);
    }
}
