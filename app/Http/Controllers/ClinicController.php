<?php

namespace App\Http\Controllers;

use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use App\Models\EmergencyType;
use App\Models\PatientHistory;
use App\Models\Section;
use App\Models\Students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClinicController
{
    public function dashboard(Request $request)
    {
        $alerts = EmergencyAlert::with(['type', 'cases'])->latest('emergency_alert_id')->limit(20)->get();
        $currentUser = $request->user();
        $openAlerts = EmergencyAlert::where('status', 'open')->count();
        $todayAlerts = EmergencyAlert::whereDate('created_at', today())->count();
        $clinicCases = ClinicCase::count();
        $totalResponds = EmergencyAlert::whereIn('status', ['acknowledged', 'resolved'])->count();

        return Inertia::render('Clinic/Dashboard', [
            'currentUser' => [
                'name' => $currentUser?->name,
                'email' => $currentUser?->email,
                'avatar' => null,
            ],
            'counts' => [
                'openAlerts' => $openAlerts,
                'todayAlerts' => $todayAlerts,
                'clinicCases' => $clinicCases,
                'patientHistories' => PatientHistory::count(),
                'totalResponds' => $totalResponds,
                'casesSubtitle' => $clinicCases . ' CASES RECORDED',
                'respondsSubtitle' => $totalResponds . ' RESPONSES SENT',
                'pendingSubtitle' => $openAlerts . ' EMPLOYEES NEEDED',
                'todaySubtitle' => $todayAlerts . ' TODAY',
                'yearRange' => $this->yearRange(),
            ],
            'alerts' => $this->formatAlerts($alerts),
            'emergencyDetails' => $this->formatEmergencyDetails($alerts),
            'calendarEvents' => $this->calendarEvents(),
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
            'sub_type' => $alert->sub_type,
            'status' => $alert->status,
            'created_at' => optional($alert->created_at)->format('Y-m-d H:i'),
        ])->values();
    }

    private function formatEmergencyDetails($alerts)
    {
        return $alerts->map(function (EmergencyAlert $alert) {
            $case = $alert->cases->sortByDesc('clinic_case_id')->first();
            $student = $this->studentForAlert($alert, $case);
            $patientName = $case?->patient_name
                ?: ($student ? trim($student->first_name . ' ' . $student->last_name) : ($alert->triggered_by_name ?: 'Unknown Patient'));
            $severity = strtolower((string) $alert->severity);
            $status = strtolower((string) $alert->status);
            $category = match (true) {
                $severity === 'critical' => 'Critical',
                $status === 'open' => 'Pending',
                default => 'Normal',
            };

            return [
                'id' => $alert->emergency_alert_id,
                'patient_name' => $patientName,
                'patient_avatar' => $this->studentAvatar($student),
                'location' => $alert->room ?: 'No room assigned',
                'department' => $alert->type?->category ?: 'General',
                'category' => $category,
                'symptoms' => $case?->symptoms ?: $alert->message,
                'symptoms_color' => $category,
                'phone' => $student?->phone,
                'time_sent' => optional($alert->created_at)->format('g:i A'),
                'email' => $student?->email,
            ];
        })->values();
    }

    private function studentForAlert(EmergencyAlert $alert, ?ClinicCase $case): ?Students
    {
        if ($case?->student_id) {
            return Students::query()->find($case->student_id);
        }

        $metadata = $alert->metadata ?? [];
        if (! empty($metadata['student_id'])) {
            return Students::query()->find($metadata['student_id']);
        }

        if (! empty($metadata['student_rfid'])) {
            return Students::query()->where('rfid_tag', $metadata['student_rfid'])->first();
        }

        return null;
    }

    private function studentAvatar(?Students $student): ?string
    {
        $faceImages = $student?->face_images ?? [];
        $firstImage = is_array($faceImages) ? ($faceImages[0] ?? null) : null;

        return $firstImage ? Storage::url($firstImage) : null;
    }

    private function calendarEvents()
    {
        return EmergencyAlert::query()
            ->whereNotNull('created_at')
            ->selectRaw('DATE(created_at) as event_date')
            ->distinct()
            ->orderBy('event_date')
            ->pluck('event_date')
            ->values();
    }

    private function yearRange(): string
    {
        $schoolYear = Section::query()
            ->whereNotNull('school_year')
            ->orderByDesc('school_year')
            ->value('school_year');

        if ($schoolYear) {
            return str_replace('-', ' - ', $schoolYear);
        }

        $year = now()->year;

        return $year . ' - ' . ($year + 1);
    }
}
