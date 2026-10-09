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
        if (! $alert || $alert->status === 'cancelled') {
            return;
        }

        $cases = $alert->cases()->get();
        $status = $cases->every(fn (ClinicCase $linkedCase) => $linkedCase->status === 'resolved')
            ? 'resolved'
            : ($cases->contains(fn (ClinicCase $linkedCase) => $linkedCase->status !== 'open') || $alert->dispatched_at
                ? 'acknowledged'
                : 'open');

        $alert->update([
            'status' => $status,
            'resolved_at' => $status === 'resolved' ? ($alert->resolved_at ?? now()) : null,
            'acknowledged_at' => $status === 'acknowledged' ? ($alert->acknowledged_at ?? now()) : $alert->acknowledged_at,
        ]);
    }

    public function syncFromAlert(EmergencyAlert $alert): void
    {
        // Cancellation has no equivalent case status; keep the treatment record intact.
        if ($alert->status === 'cancelled') {
            return;
        }

        $cases = $alert->cases();
        if ($alert->status === 'acknowledged') {
            $cases->whereIn('status', ['open', 'resolved'])->update(['status' => 'monitoring']);
            return;
        }

        $cases->update(['status' => $alert->status]);
    }
}
