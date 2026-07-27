<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use App\Models\EmergencyType;
use App\Models\PatientHistory;
use App\Models\Section;
use App\Models\Students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicController
{
    public function dashboard(Request $request)
    {
        $alerts = EmergencyAlert::with(['type', 'cases'])->latest('emergency_alert_id')->limit(20)->get();
        $activeAlerts = EmergencyAlert::with(['type', 'cases'])
            ->where('status', 'open')
            ->latest('emergency_alert_id')
            ->limit(20)
            ->get();
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
                'pendingSubtitle' => $openAlerts . ' OPEN ALERTS',
                'todaySubtitle' => $todayAlerts . ' TODAY',
                'yearRange' => $this->yearRange(),
            ],
            'alerts' => $this->formatAlerts($alerts),
            'emergencyDetails' => $this->formatEmergencyDetails($activeAlerts),
            'calendarEvents' => $this->calendarEvents(),
            'emergencyTypes' => $this->emergencyTypes(),
        ]);
    }

    public function caseLogs()
    {
        return Inertia::render('Clinic/CaseLogs', [
            'cases' => ClinicCase::with('alert.type')->latest('clinic_case_id')->get()->map(fn (ClinicCase $case) => $this->casePayload($case))->values(),
            'emergencyTypes' => $this->emergencyTypes(),
        ]);
    }

    public function patientHistory()
    {
        return Inertia::render('Clinic/PatientHistory', [
            'histories' => PatientHistory::latest('patient_history_id')->get()->map(fn (PatientHistory $history) => $this->historyPayload($history))->values(),
            'recentCases' => ClinicCase::latest('clinic_case_id')->take(25)->get()->map(fn (ClinicCase $case) => [
                'id' => $case->clinic_case_id,
                'patient_name' => $case->patient_name,
                'patient_type' => $case->patient_type,
                'summary' => trim(($case->case_type ?: 'Clinic case').': '.($case->symptoms ?: $case->notes ?: 'No summary')),
                'notes' => $case->action_taken,
                'occurred_at' => optional($case->occurred_at)->format('Y-m-d\TH:i'),
            ])->values(),
        ]);
    }

    public function reports(Request $request)
    {
        $filters = [
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'status' => $request->string('status')->toString(),
            'case_type' => $request->string('case_type')->toString(),
        ];

        $alerts = $this->filteredAlerts($filters);
        $cases = $this->filteredCases($filters);
        $resolvedAlerts = (clone $alerts)->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);
        $responseMinutes = $resolvedAlerts
            ->map(fn (EmergencyAlert $alert) => $alert->created_at && $alert->resolved_at ? $alert->created_at->diffInMinutes($alert->resolved_at) : null)
            ->filter();

        return Inertia::render('Clinic/Reports', [
            'filters' => $filters,
            'summary' => [
                'alerts' => (clone $alerts)->count(),
                'cases' => (clone $cases)->count(),
                'openCases' => (clone $cases)->whereIn('status', ['open', 'monitoring'])->count(),
                'resolvedAlerts' => $resolvedAlerts->count(),
                'averageResponseMinutes' => $responseMinutes->count() ? round($responseMinutes->avg()) : null,
            ],
            'alertsByType' => $this->filteredAlerts($filters)
                ->join('emergency_types', 'emergency_types.emergency_type_id', '=', 'emergency_alerts.emergency_type_id')
                ->selectRaw('emergency_types.name as name, COUNT(*) as total')
                ->groupBy('emergency_types.name')
                ->orderByDesc('total')
                ->get(),
            'alertsByStatus' => $this->filteredAlerts($filters)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'caseBreakdown' => $this->filteredCases($filters)
                ->selectRaw("COALESCE(case_type, 'Unclassified') as name, COUNT(*) as total")
                ->groupBy('case_type')
                ->orderByDesc('total')
                ->get(),
            'caseTrends' => $this->filteredCases($filters)
                ->selectRaw('DATE(COALESCE(occurred_at, created_at)) as date, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'recentCases' => $this->filteredCases($filters)
                ->latest('clinic_case_id')
                ->take(10)
                ->get()
                ->map(fn (ClinicCase $case) => $this->casePayload($case)),
        ]);
    }

    public function exportReports(Request $request): StreamedResponse
    {
        $filters = [
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'status' => $request->string('status')->toString(),
            'case_type' => $request->string('case_type')->toString(),
        ];

        $cases = $this->filteredCases($filters)->latest('clinic_case_id')->get();

        return response()->streamDownload(function () use ($cases) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Patient', 'Type', 'Case', 'Status', 'Symptoms', 'Action Taken', 'Occurred At', 'Notes']);

            foreach ($cases as $case) {
                fputcsv($handle, [
                    $case->patient_name,
                    $case->patient_type,
                    $case->case_type,
                    $case->status,
                    $case->symptoms,
                    $case->action_taken,
                    optional($case->occurred_at)->format('Y-m-d H:i'),
                    $case->notes,
                ]);
            }

            fclose($handle);
        }, 'clinic-report-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function storeCase(Request $request)
    {
        $case = ClinicCase::query()->create($this->validatedCase($request) + [
            'handled_by_user_id' => $request->user()?->user_id,
        ]);

        $this->logActivity($request, 'create', 'clinic_cases', 'Created clinic case '.$case->clinic_case_id.' for '.$case->patient_name.'.');

        return back()->with('success', 'Clinic case created.');
    }

    public function updateCase(Request $request, int $id)
    {
        $case = ClinicCase::query()->findOrFail($id);
        $case->update($this->validatedCase($request));

        $this->logActivity($request, 'update', 'clinic_cases', 'Updated clinic case '.$case->clinic_case_id.'.');

        return back()->with('success', 'Clinic case updated.');
    }

    public function createHistoryFromCase(Request $request, int $id)
    {
        $case = ClinicCase::query()->findOrFail($id);

        PatientHistory::query()->create([
            'student_id' => $case->student_id,
            'user_id' => $case->user_id,
            'recorded_by_user_id' => $request->user()?->user_id,
            'patient_type' => $case->patient_type,
            'patient_name' => $case->patient_name,
            'summary' => substr(trim(($case->case_type ?: 'Clinic case').': '.($case->symptoms ?: 'No symptoms recorded')), 0, 255),
            'notes' => trim(implode("\n\n", array_filter([
                $case->action_taken ? 'Action: '.$case->action_taken : null,
                $case->notes,
            ]))),
            'occurred_at' => $case->occurred_at ?: now(),
        ]);

        $case->update(['status' => $case->status === 'open' ? 'monitoring' : $case->status]);

        $this->logActivity($request, 'create', 'patient_histories', 'Created patient history from clinic case '.$case->clinic_case_id.'.');

        return back()->with('success', 'Patient history created from case.');
    }

    public function storeHistory(Request $request)
    {
        $history = PatientHistory::query()->create($this->validatedHistory($request) + [
            'recorded_by_user_id' => $request->user()?->user_id,
        ]);

        $this->logActivity($request, 'create', 'patient_histories', 'Created patient history '.$history->patient_history_id.' for '.$history->patient_name.'.');

        return back()->with('success', 'Patient history saved.');
    }

    public function updateHistory(Request $request, int $id)
    {
        $history = PatientHistory::query()->findOrFail($id);
        $history->update($this->validatedHistory($request));

        $this->logActivity($request, 'update', 'patient_histories', 'Updated patient history '.$history->patient_history_id.'.');

        return back()->with('success', 'Patient history updated.');
    }

    public function destroyHistory(Request $request, int $id)
    {
        $history = PatientHistory::query()->findOrFail($id);
        $patientName = $history->patient_name;
        $history->delete();

        $this->logActivity($request, 'delete', 'patient_histories', 'Deleted patient history for '.$patientName.'.');

        return back()->with('success', 'Patient history deleted.');
    }

    private function emergencyTypes()
    {
        return EmergencyType::orderBy('sort_order')->orderBy('name')->get()->map(fn (EmergencyType $type) => [
            'emergency_type_id' => $type->emergency_type_id,
            'name' => $type->name,
            'category' => $type->category,
            'default_message' => $type->default_message,
            'is_active' => $type->is_active,
            'sort_order' => $type->sort_order,
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

    private function validatedCase(Request $request): array
    {
        return $request->validate([
            'emergency_alert_id' => ['nullable', 'integer', 'exists:emergency_alerts,emergency_alert_id'],
            'student_id' => ['nullable', 'integer', 'exists:students,student_id'],
            'user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'patient_type' => ['nullable', 'string', 'max:255'],
            'patient_name' => ['required', 'string', 'max:255'],
            'case_type' => ['nullable', 'string', 'max:255'],
            'symptoms' => ['nullable', 'string'],
            'action_taken' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:open,monitoring,resolved,referred'],
            'occurred_at' => ['nullable', 'date'],
        ]);
    }

    private function validatedHistory(Request $request): array
    {
        return $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,student_id'],
            'user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'patient_type' => ['nullable', 'string', 'max:255'],
            'patient_name' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
        ]);
    }

    private function casePayload(ClinicCase $case): array
    {
        return [
            'id' => $case->clinic_case_id,
            'emergency_alert_id' => $case->emergency_alert_id,
            'student_id' => $case->student_id,
            'user_id' => $case->user_id,
            'patient_name' => $case->patient_name,
            'patient_type' => $case->patient_type,
            'case_type' => $case->case_type,
            'symptoms' => $case->symptoms,
            'action_taken' => $case->action_taken,
            'status' => $case->status,
            'occurred_at' => optional($case->occurred_at)->format('Y-m-d H:i'),
            'occurred_at_input' => optional($case->occurred_at)->format('Y-m-d\TH:i'),
            'notes' => $case->notes,
            'alert' => $case->alert?->type?->name,
        ];
    }

    private function historyPayload(PatientHistory $history): array
    {
        return [
            'id' => $history->patient_history_id,
            'student_id' => $history->student_id,
            'user_id' => $history->user_id,
            'patient_name' => $history->patient_name,
            'patient_type' => $history->patient_type,
            'summary' => $history->summary,
            'notes' => $history->notes,
            'occurred_at' => optional($history->occurred_at)->format('Y-m-d H:i'),
            'occurred_at_input' => optional($history->occurred_at)->format('Y-m-d\TH:i'),
        ];
    }

    private function filteredAlerts(array $filters)
    {
        return EmergencyAlert::query()
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('emergency_alerts.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('emergency_alerts.created_at', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('emergency_alerts.status', $status));
    }

    private function filteredCases(array $filters)
    {
        return ClinicCase::query()
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['case_type'] ?? null, fn ($query, $type) => $query->where('case_type', $type));
    }

    private function logActivity(Request $request, string $action, string $tableName, string $description): void
    {
        ActivityLog::query()->create([
            'user_id' => $request->user()?->user_id,
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }
}
