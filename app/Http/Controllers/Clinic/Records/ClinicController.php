<?php

namespace App\Http\Controllers\Clinic\Records;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\ClinicCase;
use App\Models\EmergencyAlert;
use App\Models\EmergencyType;
use App\Models\PatientHistory;
use App\Models\Section;
use App\Models\Students;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ClinicCaseAlertService;
use App\Services\ClinicCaseHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicController
{
    // @function dashboard: Ibinabalik ang Clinic/Dashboard page at data para sa request.
    // @useIn dashboard: routes/web.php:458 (dashboard)
    // Pinagsasama ang alert queue, dispatch assignments, at counts para sa Clinic dashboard.
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

        return Inertia::render('Clinic/Dashboard/DashboardPage', [
            'title' => 'Clinic Dashboard',
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
                'casesSubtitle' => $clinicCases.' CASES RECORDED',
                'respondsSubtitle' => $totalResponds.' RESPONSES SENT',
                'pendingSubtitle' => $openAlerts.' OPEN ALERTS',
                'todaySubtitle' => $todayAlerts.' TODAY',
                'yearRange' => $this->yearRange(),
            ],
            'alerts' => $this->formatAlerts($alerts),
            'emergencyDetails' => $this->formatEmergencyDetails($activeAlerts),
            'calendarEvents' => $this->calendarEvents(),
            'emergencyTypes' => $this->emergencyTypes(),
            'emergencySound' => SystemSetting::clinicEmergencySoundSettings(),
            'clinicAccounts' => User::query()
                ->whereRaw('LOWER(role) = ?', ['clinic'])
                ->orderBy('name')
                ->get(['user_id', 'name', 'email'])
                ->values(),
            'assignedDispatches' => ClinicCase::query()
                ->with(['alert.type', 'assignedResponder'])
                ->where('handled_by_user_id', $currentUser?->user_id)
                ->whereIn('status', ['open', 'monitoring'])
                ->latest('clinic_case_id')
                ->get()
                ->map(fn (ClinicCase $case) => $this->dispatchAssignmentPayload($case))
                ->values(),
        ]);
    }

    // @function caseLogs: Ibinabalik ang Clinic/CaseLogs page at data para sa request.
    // @useIn caseLogs: routes/web.php:460 (case-logs)
    /**
     * @feature     Case Logs and Patient History
     *
     * @actor       Clinic
     *
     * @flow        Dito ini-record ang clinic cases at patient history.
     *
     * @uses        resources/js/pages/Clinic/CaseLogs/CaseLogsPage.vue; routes/clinic.php: ClinicController::caseLogs, ClinicController::storeCase, ClinicController::updateCase, ClinicController::createHistoryFromCase, ClinicController::patientHistory, ClinicController::storeHistory, ClinicController::updateHistory, ClinicController::destroyHistory
     *
     * @related     Clinic dispatch follow-up at Clinic reports.
     *
     * @disable     1) Suriin ang Case Logs and Patient History callers, pending work, at dependent screens; Needs developer check: exact shared routes at background consumers.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Clinic/CaseLogs/CaseLogsPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     *
     * @sideEffects Nagbabago ang clinic_cases, patient_histories, at activity logs.
     *
     * @dependsOn   Clinic dispatch follow-up at Clinic reports.
     *
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     *
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     *
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     *
     * @editable    Clinic Case Logs/Patient History: case, summary, notes, at status.
     */
    public function caseLogs(Request $request)
    {
        return Inertia::render('Clinic/CaseLogs/CaseLogsPage', [
            'title' => 'Clinic Case Logs',
            'cases' => ClinicCase::with(['alert.type', 'assignedResponder'])->latest('clinic_case_id')->get()->map(fn (ClinicCase $case) => $this->casePayload($case))->values(),
            'emergencyTypes' => $this->emergencyTypes(),
            'selectedCaseId' => $request->integer('case') ?: null,
        ]);
    }

    // @function exportCaseLogs: Exports all clinic case logs as an Excel-compatible CSV.
    // @useIn exportCaseLogs: routes/clinic.php (clinic.case-logs.export)
    public function exportCaseLogs(): StreamedResponse
    {
        $cases = ClinicCase::query()
            ->with('assignedResponder')
            ->latest('clinic_case_id')
            ->get();

        return response()->streamDownload(function () use ($cases) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Case ID',
                'Emergency Alert ID',
                'Patient Name',
                'Patient Type',
                'Case Type',
                'Status',
                'Symptoms',
                'Action Taken',
                'Notes',
                'Occurred At',
                'Assigned Responder',
                'Created At',
                'Updated At',
            ]);

            foreach ($cases as $case) {
                fputcsv($handle, [
                    $case->clinic_case_id,
                    $case->emergency_alert_id,
                    $this->csvText($case->patient_name),
                    $this->csvText($case->patient_type),
                    $this->csvText($case->case_type),
                    $this->csvText($case->status),
                    $this->csvText($case->symptoms),
                    $this->csvText($case->action_taken),
                    $this->csvText($case->notes),
                    optional($case->occurred_at)->toIso8601String(),
                    $this->csvText($case->assignedResponder?->name),
                    optional($case->created_at)->toIso8601String(),
                    optional($case->updated_at)->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, 'clinic-case-logs-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // @function patientHistory: Ibinabalik ang Clinic/PatientHistory page at data para sa request.
    // @useIn patientHistory: routes/web.php:468 (patient-history)
    // Kinukuha ang patient records na ipapakita sa history page.
    public function patientHistory()
    {
        return Inertia::render('Clinic/PatientHistory/PatientHistoryPage', [
            'title' => 'Patient History',
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

    // @function reports: Ibinabalik ang Clinic/Reports page at data para sa request.
    // @useIn reports: routes/web.php:476 (reports)
    /**
     * @feature     Clinic Reports
     *
     * @actor       Clinic
     *
     * @flow        Dito fina-filter at ine-export ang clinic activity.
     *
     * @uses        resources/js/pages/Clinic/Reports/ReportsPage.vue; routes/clinic.php: ClinicController::reports, ClinicController::exportReports
     *
     * @related     Clinic monitoring and review.
     *
     * @disable     1) Suriin ang Clinic Reports callers, pending work, at dependent screens; Needs developer check: exact shared routes at background consumers.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Clinic/Reports/ReportsPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     *
     * @sideEffects Nagbabasa ng Clinic records at nag-e-export ng CSV; maaaring ma-audit ang export.
     *
     * @dependsOn   Clinic monitoring and review.
     *
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     *
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     *
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     *
     * @editable    Clinic Reports: filters; walang no-code report-formula editor na nakumpirma.
     */
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

        return Inertia::render('Clinic/Reports/ReportsPage', [
            'title' => 'Clinic Reports',
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
                ->whereNull('emergency_types.deleted_at')
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

    // @function exportReports: Ine-export ang reports sa Clinic flow.
    // @useIn exportReports: routes/web.php:478 (reports.export)
    // Nag-stream ng CSV gamit ang parehong filters ng Clinic reports page.
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

    // @function storeCase: Sine-save ang case sa Clinic flow.
    // @useIn storeCase: routes/web.php:462 (case-logs.store)
    // Gumagawa ng Clinic Case mula sa validated patient at incident fields.
    public function storeCase(Request $request)
    {
        $validated = $this->validatedCase($request);
        $case = DB::transaction(function () use ($validated, $request) {
            $case = ClinicCase::query()->create($validated + [
                'handled_by_user_id' => $request->user()?->user_id,
            ]);
            app(ClinicCaseAlertService::class)->syncFromCase($case);

            return $case;
        });

        $this->logActivity($request, 'create', 'clinic_cases', 'Created clinic case '.$case->clinic_case_id.' for '.$case->patient_name.'.');

        return back()->with('success', 'Clinic case created.');
    }

    // @function updateCase: Ina-update ang case sa Clinic flow.
    // @useIn updateCase: routes/web.php:464 (case-logs.update)
    // Binabago ang napiling Clinic Case gamit ang validated fields.
    public function updateCase(Request $request, int $id)
    {
        $case = ClinicCase::query()->findOrFail($id);
        $validated = $this->validatedCase($request);
        DB::transaction(function () use ($case, $validated) {
            $case->update($validated);
            app(ClinicCaseAlertService::class)->syncFromCase($case);
        });

        $this->logActivity($request, 'update', 'clinic_cases', 'Updated clinic case '.$case->clinic_case_id.'.');

        return back()->with('success', 'Clinic case updated.');
    }

    // @function createHistoryFromCase: Gumagawa ng ang history from case sa Clinic flow.
    // @useIn createHistoryFromCase: routes/web.php:466 (case-logs.history)
    // Gumagawa ng Patient History record mula sa existing Clinic Case.
    public function createHistoryFromCase(Request $request, int $id)
    {
        $case = ClinicCase::query()->findOrFail($id);

        app(ClinicCaseHistoryService::class)->sync($case);

        return back()->with('success', 'Patient history is up to date.');
    }

    // @function storeHistory: Sine-save ang history sa Clinic flow.
    // @useIn storeHistory: routes/web.php:470 (patient-history.store)
    // Gumagawa ng standalone Patient History record mula sa form.
    public function storeHistory(Request $request)
    {
        $history = PatientHistory::query()->create($this->validatedHistory($request) + [
            'recorded_by_user_id' => $request->user()?->user_id,
        ]);

        $this->logActivity($request, 'create', 'patient_histories', 'Created patient history '.$history->patient_history_id.' for '.$history->patient_name.'.');

        return back()->with('success', 'Patient history saved.');
    }

    // @function updateHistory: Ina-update ang history sa Clinic flow.
    // @useIn updateHistory: routes/web.php:472 (patient-history.update)
    // Binabago ang napiling Patient History record.
    public function updateHistory(Request $request, int $id)
    {
        $history = PatientHistory::query()->findOrFail($id);
        $history->update($this->validatedHistory($request));

        $this->logActivity($request, 'update', 'patient_histories', 'Updated patient history '.$history->patient_history_id.'.');

        return back()->with('success', 'Patient history updated.');
    }

    // @function destroyHistory: Tinatanggal ang history sa Clinic flow.
    // @useIn destroyHistory: routes/web.php:474 (patient-history.destroy)
    // Tinatanggal ang napiling Patient History record at nilolog ang action.
    public function destroyHistory(Request $request, int $id)
    {
        $history = PatientHistory::query()->findOrFail($id);
        $patientName = $history->patient_name;
        $history->delete();

        $this->logActivity($request, 'delete', 'patient_histories', 'Deleted patient history for '.$patientName.'.');

        return back()->with('success', 'Patient history deleted.');
    }

    // @function emergencyTypes: Kinukuha ang emergency types result para sa Clinic.
    // @useIn emergencyTypes: ClinicController::dashboard (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Kinukuha ang ordered emergency types para sa form at dashboard.
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

    // @function formatAlerts: Fino-format ang alerts sa Clinic flow.
    // @useIn formatAlerts: ClinicController::dashboard (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Ginagawang dashboard rows ang alert records.
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
            'cases' => $alert->cases->map(fn (ClinicCase $case) => [
                'id' => $case->clinic_case_id,
                'patient_name' => $case->patient_name,
                'status' => $case->status,
            ])->values(),
        ])->values();
    }

    // @function formatEmergencyDetails: Fino-format ang emergency details sa Clinic flow.
    // @useIn formatEmergencyDetails: ClinicController::dashboard (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Dinadagdagan ang alert rows ng patient at response details.
    private function formatEmergencyDetails($alerts)
    {
        return $alerts->map(function (EmergencyAlert $alert) {
            $case = $alert->cases->sortByDesc('clinic_case_id')->first();
            $student = $this->studentForAlert($alert, $case);
            $metadata = $alert->metadata ?? [];
            $patients = collect($metadata['students'] ?? [])
                ->filter(fn ($person) => is_array($person) && ! empty($person['student_name']))
                ->map(fn ($person) => [
                    'student_id' => $person['student_id'] ?? null,
                    'student_number' => $person['student_number'] ?? null,
                    'name' => $person['student_name'],
                    'section' => $person['section'] ?? null,
                    'photo' => $person['photo'] ?? null,
                ])
                ->values();
            $patientName = $patients->isNotEmpty()
                ? $patients->pluck('name')->implode(', ')
                : (($metadata['emergency_scope'] ?? null) === 'all'
                    ? 'Everyone / Area-wide'
                    : ($case?->patient_name
                    ?: ($student ? trim($student->first_name.' '.$student->last_name) : ($alert->triggered_by_name ?: 'Unknown Patient'))));
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
                'patients' => $patients,
                'location' => $alert->room ?: 'No room assigned',
                'department' => $alert->type?->category ?: 'General',
                'category' => $category,
                'symptoms' => $metadata['symptoms'] ?? $case?->symptoms ?: $alert->message,
                'symptoms_color' => $category,
                'phone' => $student?->phone,
                'time_sent' => optional($alert->created_at)->format('g:i A'),
                'acknowledged_at' => optional($alert->acknowledged_at)->format('g:i A'),
                'dispatched_at' => optional($alert->dispatched_at)->format('g:i A'),
                'response_seconds' => $alert->response_seconds,
                'email' => $student?->email,
            ];
        })->values();
    }

    // @function dispatchAssignmentPayload: Ipinapadala ang assignment payload sa Clinic flow.
    // @useIn dispatchAssignmentPayload: ClinicController::dashboard (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Binubuo ang responder assignment card kasama ang recent patient context.
    private function dispatchAssignmentPayload(ClinicCase $case): array
    {
        $history = $case->student_id
            ? PatientHistory::query()
                ->where('student_id', $case->student_id)
                ->latest('occurred_at')
                ->take(3)
                ->get()
                ->map(fn (PatientHistory $record) => [
                    'date' => optional($record->occurred_at)->format('Y-m-d'),
                    'summary' => $record->summary,
                ])
                ->values()
            : collect();
        $attendance = $case->student_id
            ? Attendance::query()
                ->where('student_id', $case->student_id)
                ->latest('date')
                ->take(3)
                ->get()
                ->map(fn (Attendance $record) => [
                    'date' => optional($record->date)->format('Y-m-d'),
                    'status' => ucfirst((string) $record->status),
                ])
                ->values()
            : collect();

        return [
            'case_id' => $case->clinic_case_id,
            'patient_name' => $case->patient_name,
            'assigned_responder_name' => $case->assignedResponder?->name,
            'assigned_responder_email' => $case->assignedResponder?->email,
            'case_type' => $case->case_type,
            'symptoms' => $case->symptoms,
            'location' => $case->alert?->room ?: 'No location provided',
            'assigned_at' => optional($case->occurred_at)->format('Y-m-d g:i A'),
            'history' => $history,
            'attendance' => $attendance,
        ];
    }

    // @function studentForAlert: Kinukuha ang student for alert result para sa Clinic.
    // @useIn studentForAlert: ClinicController::formatEmergencyDetails (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Hinahanap ang linked student mula sa case o alert metadata.
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

    // @function studentAvatar: Binubuo ang student avatar string para sa Clinic.
    // @useIn studentAvatar: ClinicController::formatEmergencyDetails (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Ginagawang URL ang unang enrolled face image para sa patient preview.
    private function studentAvatar(?Students $student): ?string
    {
        $faceImages = $student?->face_images ?? [];
        $firstImage = is_array($faceImages) ? ($faceImages[0] ?? null) : null;

        return $firstImage ? Storage::url($firstImage) : null;
    }

    // @function calendarEvents: Kinukuha ang calendar events result para sa Clinic.
    // @useIn calendarEvents: ClinicController::dashboard (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Ginagawang calendar entries ang recent emergency alerts.
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

    // @function yearRange: Binubuo ang year range string para sa Clinic.
    // @useIn yearRange: ClinicController::dashboard (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Kinukuha ang school-year label mula sa section data o kasalukuyang taon.
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

        return $year.' - '.($year + 1);
    }

    // @function validatedCase: Kinukuha ang validated case result para sa Clinic.
    // @useIn validatedCase: ClinicController::storeCase (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Kinukuha ang validated case fields mula sa request.
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
            'status' => ['required', 'in:open,monitoring,resolved,referred,cancelled'],
            'occurred_at' => ['nullable', 'date'],
        ]);
    }

    // @function validatedHistory: Kinukuha ang validated history result para sa Clinic.
    // @useIn validatedHistory: ClinicController::storeHistory (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Kinukuha ang validated history fields mula sa request.
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

    // @function casePayload: Binubuo ang case payload value.
    // @useIn casePayload: ClinicController::caseLogs (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Pinipili ang Clinic Case fields na ibabalik sa page.
    private function casePayload(ClinicCase $case): array
    {
        return [
            'id' => $case->clinic_case_id,
            'emergency_alert_id' => $case->emergency_alert_id,
            'student_id' => $case->student_id,
            'user_id' => $case->user_id,
            'patient_name' => $case->patient_name,
            'assigned_responder_name' => $case->assignedResponder?->name,
            'assigned_responder_email' => $case->assignedResponder?->email,
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

    /** Prevent spreadsheet applications from executing case-log text as a formula. */
    private function csvText(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }

    // @function historyPayload: Binubuo ang history payload value.
    // @useIn historyPayload: ClinicController::patientHistory (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Pinipili ang Patient History fields na ipapakita sa page.
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

    // @function filteredAlerts: Kinukuha ang filtered alerts result para sa Clinic.
    // @useIn filteredAlerts: ClinicController::reports (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Inilalapat ang report filters sa emergency alerts query.
    private function filteredAlerts(array $filters)
    {
        return EmergencyAlert::query()
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('emergency_alerts.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('emergency_alerts.created_at', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('emergency_alerts.status', $status));
    }

    // @function filteredCases: Kinukuha ang filtered cases result para sa Clinic.
    // @useIn filteredCases: ClinicController::reports (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Inilalapat ang report filters sa Clinic Cases query.
    private function filteredCases(array $filters)
    {
        return ClinicCase::query()
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['case_type'] ?? null, fn ($query, $type) => $query->where('case_type', $type));
    }

    // @function logActivity: Nilolog ang activity sa Clinic flow.
    // @useIn logActivity: ClinicController::storeCase (app/Http/Controllers/Clinic/Records/ClinicController.php)
    // Nagtatala ng activity para sa history at audit.
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
