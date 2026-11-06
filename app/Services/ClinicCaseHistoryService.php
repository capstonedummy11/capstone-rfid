<?php

namespace App\Services;

use App\Models\ClinicCase;
use App\Models\PatientHistory;
use Illuminate\Support\Facades\Auth;

class ClinicCaseHistoryService
{
    public function sync(ClinicCase $case): PatientHistory
    {
        return PatientHistory::query()->updateOrCreate(
            ['clinic_case_id' => $case->clinic_case_id],
            [
                'student_id' => $case->student_id,
                'user_id' => $case->user_id,
                'recorded_by_user_id' => Auth::id() ?? $case->handled_by_user_id,
                'patient_type' => $case->patient_type,
                'patient_name' => $case->patient_name,
                'summary' => mb_substr(trim(($case->case_type ?: 'Clinic case').': '.($case->symptoms ?: 'No symptoms recorded')), 0, 255),
                'notes' => trim(implode("\n\n", array_filter([
                    $case->action_taken ? 'Action: '.$case->action_taken : null,
                    $case->notes,
                ]))),
                'occurred_at' => $case->occurred_at ?: $case->created_at,
            ],
        );
    }
}
