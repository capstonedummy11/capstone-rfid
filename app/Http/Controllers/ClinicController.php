<?php

namespace App\Http\Controllers;

use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use App\Models\EmergencyType;
use App\Models\PatientHistory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClinicController
{
    public function dashboard()
    {
        $alerts = EmergencyAlert::with('type')->latest('emergency_alert_id')->limit(20)->get();

        return Inertia::render('Clinic/Dashboard', [
            'counts' => [
                'openAlerts' => EmergencyAlert::where('status', 'open')->count(),
                'todayAlerts' => EmergencyAlert::whereDate('created_at', today())->count(),
                'clinicCases' => ClinicCase::count(),
                'patientHistories' => PatientHistory::count(),
            ],
            'alerts' => $this->formatAlerts($alerts),
            'emergencyTypes' => $this->emergencyTypes(),
        ]);
    }

    public function caseLogs()
    {
        return Inertia::render('Clinic/CaseLogs', [
            'cases' => ClinicCase::with('alert.type')->latest('clinic_case_id')->get()->map(fn (ClinicCase $case) => [
                'id' => $case->clinic_case_id,
                'patient_name' => $case->patient_name,
                'case_type' => $case->case_type,
                'status' => $case->status,
                'occurred_at' => optional($case->occurred_at)->format('Y-m-d H:i'),
                'notes' => $case->notes,
                'alert' => $case->alert?->type?->name,
            ])->values(),
        ]);
    }

    public function patientHistory()
    {
        return Inertia::render('Clinic/PatientHistory', [
            'histories' => PatientHistory::latest('patient_history_id')->get()->map(fn (PatientHistory $history) => [
                'id' => $history->patient_history_id,
                'patient_name' => $history->patient_name,
                'patient_type' => $history->patient_type,
                'summary' => $history->summary,
                'notes' => $history->notes,
                'occurred_at' => optional($history->occurred_at)->format('Y-m-d H:i'),
            ])->values(),
        ]);
    }

    public function reports()
    {
        return Inertia::render('Clinic/Reports', [
            'alertsByType' => EmergencyAlert::query()
                ->join('emergency_types', 'emergency_types.emergency_type_id', '=', 'emergency_alerts.emergency_type_id')
                ->selectRaw('emergency_types.name as name, COUNT(*) as total')
                ->groupBy('emergency_types.name')
                ->orderByDesc('total')
                ->get(),
            'alertsByStatus' => EmergencyAlert::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
        ]);
    }

    private function emergencyTypes()
    {
        return EmergencyType::orderBy('sort_order')->orderBy('name')->get()->map(fn (EmergencyType $type) => [
            'emergency_type_id' => $type->emergency_type_id,
            'name' => $type->name,
            'category' => $type->category,
            'default_message' => $type->default_message,
            'is_active' => $type->is_active,
        ])->values();
    }

    private function formatAlerts($alerts)
    {
        return $alerts->map(fn (EmergencyAlert $alert) => [
            'emergency_alert_id' => $alert->emergency_alert_id,
            'type' => $alert->type?->name ?? 'Emergency',
            'category' => $alert->type?->category,
            'room' => $alert->room,
            'message' => $alert->message,
            'triggered_by_name' => $alert->triggered_by_name,
            'status' => $alert->status,
            'created_at' => optional($alert->created_at)->format('Y-m-d H:i'),
        ])->values();
    }
}
