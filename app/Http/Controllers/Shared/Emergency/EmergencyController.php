<?php
// File purpose: Emergency alerts, hotline setup, at Clinic dispatch para sa Console at Clinic.

namespace App\Http\Controllers\Shared\Emergency;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\EmergencyAlert;
use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use App\Models\PatientHistory;
use App\Models\Students;
use App\Models\User;
use App\Notifications\ClinicDispatchAssigned;
use App\Notifications\EmergencyParentAlert;
use App\Services\SmsService;
use App\Services\ClinicCaseAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

// Used by roles: Console to create alerts; Clinic and Admin to configure hotlines/types and dispatch alerts.
class EmergencyController
{
    // @function storeAlert: Sine-save ang alert sa Emergency flow.
    // @useIn storeAlert: routes/web.php:181 (attendanceControlPanel.emergencyAlert)
    /**
     * @feature     Emergency Alerts and Delivery
     * @actor       Console user; Clinic staff respond afterward
     * @flow        Dito sine-save ang emergency alert at ina-attempt ang hotline at Parent notifications.
     * @uses        resources/js/pages/AttendanceConsole/AttendanceControlPanel/AttendanceControlPanelPage.vue; routes/attendance-console.php: EmergencyController::storeAlert
     * @related     Clinic open-alert queue, dispatch, cases, at reports.
     * @disable     1) Needs developer check: walang nakumpirmang delivery-only switch; tukuyin ang hotline SMS at specific-student Parent email/SMS calls sa storeAlert.
     * @disable     2) Magdagdag ng guard sa sending calls lamang; panatilihin ang emergency_alerts save, storeAlert route, Console action, at Clinic queue/dispatch.
     * @disable     3) I-test ang alert save at Clinic response kahit walang notifications; i-check ang sent/failure logs at reports.
     * @sideEffects Gumagawa ng emergency_alert at metadata; ina-attempt ang hotline SMS at specific-student Parent email/SMS.
     * @dependsOn   Clinic open-alert queue, dispatch, cases, at reports.
     * @performance Minimal na bawas sa provider calls kung delivery lang ang naka-off; Needs developer check: sukatin ang live request time.
     * @dataImpact  Nananatili ang existing at bagong emergency alerts; walang deletion sa delivery-only steps.
     * @reEnable    1) Ibalik ang delivery guard sa sending state. 2) I-test ang hotline at Parent delivery. 3) I-check ang alert save, Clinic queue, logs, at reports.
     * @editable    Clinic Hotlines at Admin SMS Settings: contact/number/provider; message construction ay code.
     */
    public function storeAlert(Request $request, SmsService $sms): JsonResponse
    {
        $validated = $request->validate([
            'emergency_type_id' => ['required', 'exists:emergency_types,emergency_type_id'],
            'room' => ['nullable', 'string', 'max:255'],
            'subject_code' => ['nullable', 'string', 'max:255'],
            'schedule_id' => ['nullable', 'integer'],
            'triggered_by_user_id' => ['nullable', 'integer'],
            'triggered_by_name' => ['nullable', 'string', 'max:255'],
            'sub_type' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $type = EmergencyType::query()->findOrFail($validated['emergency_type_id']);

        $duplicate = EmergencyAlert::query()
            ->where('emergency_type_id', $type->emergency_type_id)
            ->where('room', $validated['room'] ?? null)
            ->where('status', 'open')
            ->where('created_at', '>=', now()->subSeconds(10))
            ->latest('emergency_alert_id')
            ->first();
        if ($duplicate) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'alert' => $duplicate->load('type'),
                'sms' => ['sent' => false, 'reason' => 'duplicate_suppressed'],
            ]);
        }

        $alert = EmergencyAlert::create([
            'emergency_type_id' => $type->emergency_type_id,
            'triggered_by_user_id' => $validated['triggered_by_user_id'] ?? null,
            'schedule_id' => $validated['schedule_id'] ?? null,
            'room' => $validated['room'] ?? null,
            'subject_code' => $validated['subject_code'] ?? null,
            'triggered_by_name' => $validated['triggered_by_name'] ?? null,
            'sub_type' => $validated['sub_type'] ?? 'Emergency Alert',
            'severity' => $type->category === 'disaster' ? 'critical' : 'urgent',
            'message' => $validated['message'] ?: $type->default_message ?: $type->name,
            'metadata' => $validated['metadata'] ?? [],
        ]);

        $this->logActivity(
            $request,
            'create',
            'emergency_alerts',
            'Emergency alert '.$alert->emergency_alert_id.' triggered from attendance panel for '.$type->name.'.',
        );

        $smsResult = $this->sendHotlineSms($validated['metadata'] ?? [], $alert, $sms);
        $parentResult = $this->notifyParents($alert, $validated['metadata'] ?? [], $sms);

        $alert->update([
            'metadata' => array_merge($alert->metadata ?? [], [
                'parent_notification' => [
                    'parents_found' => $parentResult['parents_found'],
                    'email_sent' => $parentResult['email_sent'],
                    'sms_sent' => $parentResult['sms_sent'],
                    'failures' => $parentResult['failures'],
                    'warnings' => $parentResult['warnings'],
                ],
            ]),
        ]);

        return response()->json([
            'ok' => true,
            'alert' => $alert->load('type'),
            'sms' => $smsResult,
            'parent_notifications' => $parentResult,
        ]);
    }

    // @function hotlines: Ibinabalik ang Clinic/EmergencyHotlines page at data para sa request.
    // @useIn hotlines: routes/web.php:480 (emergency-hotlines.index)
    /**
     * @feature     Emergency Types and Hotlines
     * @actor       Clinic
     * @flow        Dito sine-set ang emergency types at matching hotlines.
     * @uses        resources/js/pages/Clinic/EmergencyHotlines/EmergencyHotlinesPage.vue; routes/clinic.php: EmergencyController::hotlines, EmergencyController::storeHotline, EmergencyController::updateHotline, EmergencyController::destroyHotline, EmergencyController::storeType, EmergencyController::updateType, EmergencyController::destroyType
     * @related     Console emergency choices, hotline SMS routing, at Clinic dashboard.
     * @disable     1) Suriin ang Emergency Types and Hotlines callers, pending work, at dependent screens; Needs developer check: exact shared routes at background consumers.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Clinic/EmergencyHotlines/EmergencyHotlinesPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Nagbabago ang emergency_types at emergency_hotlines.
     * @dependsOn   Console emergency choices, hotline SMS routing, at Clinic dashboard.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    Clinic Emergency Types/Hotlines: name, category, number, SMS at active flags.
     */
    public function hotlines()
    {
        return Inertia::render('Clinic/EmergencyHotlines/EmergencyHotlinesPage', [
            'title' => 'Emergency Hotlines',
            'hotlines' => EmergencyHotline::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (EmergencyHotline $hotline) => $this->hotlinePayload($hotline))
                ->values(),
        ]);
    }

    // @function storeHotline: Sine-save ang hotline sa Emergency flow.
    // @useIn storeHotline: routes/web.php:482 (emergency-hotlines.store)
    public function storeHotline(Request $request)
    {
        $validated = $this->validateHotline($request);

        $hotline = EmergencyHotline::query()->create([
            ...$validated,
            'sms_enabled' => $validated['sms_enabled'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? ((EmergencyHotline::query()->max('sort_order') ?? 0) + 1),
        ]);

        $this->logActivity($request, 'create', 'emergency_hotlines', 'Created emergency hotline '.$hotline->name.' ('.$hotline->phone_number.').');

        return back()->with('success', 'Emergency hotline added.');
    }

    // @function updateHotline: Ina-update ang hotline sa Emergency flow.
    // @useIn updateHotline: routes/web.php:484 (emergency-hotlines.update)
    public function updateHotline(Request $request, int $id)
    {
        $hotline = EmergencyHotline::query()->findOrFail($id);
        $validated = $this->validateHotline($request);

        $hotline->update([
            ...$validated,
            'sms_enabled' => $validated['sms_enabled'] ?? false,
            'is_active' => $validated['is_active'] ?? false,
            'sort_order' => $validated['sort_order'] ?? $hotline->sort_order,
        ]);

        $this->logActivity($request, 'update', 'emergency_hotlines', 'Updated emergency hotline '.$hotline->name.' ('.$hotline->phone_number.').');

        return back()->with('success', 'Emergency hotline updated.');
    }

    // @function destroyHotline: Tinatanggal ang hotline sa Emergency flow.
    // @useIn destroyHotline: routes/web.php:486 (emergency-hotlines.destroy)
    public function destroyHotline(Request $request, int $id)
    {
        $hotline = EmergencyHotline::query()->findOrFail($id);
        $name = $hotline->name;
        $phone = $hotline->phone_number;

        $hotline->delete();

        $this->logActivity($request, 'delete', 'emergency_hotlines', 'Deleted emergency hotline '.$name.' ('.$phone.').');

        return back()->with('success', 'Emergency hotline deleted.');
    }

    // @function storeType: Sine-save ang type sa Emergency flow.
    // @useIn storeType: routes/web.php:488 (emergency-types.store)
    public function storeType(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'default_message' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $type = EmergencyType::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? (EmergencyType::max('sort_order') ?? 0) + 1,
        ]);

        $this->logActivity($request, 'create', 'emergency_types', 'Created emergency type '.$type->name.'.');

        return back()->with('success', 'Emergency type added.');
    }

    // @function updateType: Ina-update ang type sa Emergency flow.
    // @useIn updateType: routes/web.php:490 (emergency-types.update)
    public function updateType(Request $request, int $id)
    {
        $type = EmergencyType::findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'default_message' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $type->update([
            ...$validated,
            'is_active' => $validated['is_active'] ?? false,
            'sort_order' => $validated['sort_order'] ?? $type->sort_order,
        ]);

        $this->logActivity($request, 'update', 'emergency_types', 'Updated emergency type '.$type->name.'.');

        return back()->with('success', 'Emergency type updated.');
    }

    // @function destroyType: Tinatanggal ang type sa Emergency flow.
    // @useIn destroyType: routes/web.php:492 (emergency-types.destroy)
    public function destroyType(Request $request, int $id)
    {
        $type = EmergencyType::findOrFail($id);
        $name = $type->name;

        $type->delete();

        $this->logActivity($request, 'delete', 'emergency_types', 'Deleted emergency type '.$name.'.');

        return back()->with('success', 'Emergency type deleted.');
    }

    // @function updateAlertStatus: Ina-update ang alert status sa Emergency flow.
    // @useIn updateAlertStatus: routes/web.php:494 (emergency-alerts.update)
    public function updateAlertStatus(Request $request, int $id)
    {
        $alert = EmergencyAlert::findOrFail($id);
        $validated = $request->validate([
            'status' => ['required', 'in:open,acknowledged,resolved,cancelled'],
        ]);

        DB::transaction(function () use ($alert, $validated) {
            $alert->update([
                'status' => $validated['status'],
                'resolved_at' => $validated['status'] === 'resolved' ? ($alert->resolved_at ?? now()) : null,
                'acknowledged_at' => $validated['status'] === 'acknowledged' ? ($alert->acknowledged_at ?? now()) : $alert->acknowledged_at,
            ]);
            app(ClinicCaseAlertService::class)->syncFromAlert($alert);
        });

        $this->logActivity($request, 'update', 'emergency_alerts', 'Updated emergency alert '.$alert->emergency_alert_id.' status to '.$validated['status'].'.');

        return back()->with('success', 'Emergency alert updated.');
    }

    // @function dispatchAlert: Ipinapadala ang alert sa Emergency flow.
    // @useIn dispatchAlert: routes/web.php:496 (emergency-alerts.dispatch)
    /**
     * @feature     Emergency Alert Response and Dispatch
     * @actor       Clinic
     * @flow        Dito ina-assign ang Clinic responder at gumagawa ng linked case.
     * @uses        resources/js/pages/Clinic/Dashboard/DashboardPage.vue; routes/clinic.php: EmergencyController::dispatchAlert, EmergencyController::updateAlertStatus
     * @related     Open-alert queue, Clinic assignments, cases, at reports.
     * @disable     1) Suriin ang Emergency Alert Response and Dispatch callers, pending work, at dependent screens; Needs developer check: exact shared routes at background consumers.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Clinic/Dashboard/DashboardPage.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Ina-update ang emergency_alerts, clinic_cases, activity logs, at responder email attempt.
     * @dependsOn   Open-alert queue, Clinic assignments, cases, at reports.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    Clinic dashboard: responder selection at alert status.
     */
    public function dispatchAlert(Request $request, int $id)
    {
        $validated = $request->validate([
            'clinic_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'user_id')->where(fn ($query) => $query->whereRaw('LOWER(role) = ?', ['clinic'])->whereNull('deleted_at')),
            ],
        ]);
        $alert = EmergencyAlert::with('type')->findOrFail($id);
        $assignedClinic = User::query()->findOrFail($validated['clinic_user_id']);
        $metadata = $alert->metadata ?? [];
        $student = null;

        if (! empty($metadata['student_id'])) {
            $student = \App\Models\Students::query()->find($metadata['student_id']);
        }

        if (! $student && ! empty($metadata['student_rfid'])) {
            $student = \App\Models\Students::query()
                ->where('rfid_tag', $metadata['student_rfid'])
                ->first();
        }

        $selectedPeople = collect($metadata['students'] ?? [])
            ->filter(fn ($person) => is_array($person) && ! empty($person['student_name']))
            ->values();
        $isAreaWide = ($metadata['emergency_scope'] ?? null) === 'all';
        $patientName = $isAreaWide
            ? 'Everyone / Area-wide'
            : ($selectedPeople->isNotEmpty()
            ? $selectedPeople->pluck('student_name')->implode(', ')
            : ($student
                ? trim($student->first_name.' '.$student->last_name)
                : ($alert->triggered_by_name ?: 'Unknown Patient')));

        $dispatchedAt = now();
        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => $alert->acknowledged_at ?? $dispatchedAt,
            'dispatched_at' => $dispatchedAt,
            'response_seconds' => (int) round(max(0, $alert->created_at?->diffInSeconds($dispatchedAt) ?? 0)),
        ]);

        $casePeople = $selectedPeople->isNotEmpty() ? $selectedPeople : collect([[
            'student_id' => $student?->student_id,
            'student_name' => $patientName,
            'patient_type' => $isAreaWide ? 'area_wide' : ($student ? 'student' : 'user'),
        ]]);
        $clinicCases = $casePeople->map(function (array $person) use ($alert, $assignedClinic, $metadata, $dispatchedAt) {
            $caseStudent = ! empty($person['student_id'])
                ? \App\Models\Students::query()->find($person['student_id'])
                : null;
            $caseName = $person['student_name'] ?? ($caseStudent
                ? trim($caseStudent->first_name.' '.$caseStudent->last_name)
                : 'Unknown Patient');

            return \App\Models\ClinicCase::updateOrCreate(
                ['emergency_alert_id' => $alert->emergency_alert_id, 'patient_name' => $caseName],
                [
                    'student_id' => $caseStudent?->student_id,
                    'handled_by_user_id' => $assignedClinic->user_id,
                    'patient_type' => $person['patient_type'] ?? ($caseStudent ? 'student' : 'user'),
                    'case_type' => $alert->type?->name ?? 'Emergency',
                    'symptoms' => $metadata['symptoms'] ?? $alert->message,
                    'action_taken' => 'Dispatched clinic response.',
                    'notes' => 'Created from clinic emergency dispatch.',
                    'status' => 'monitoring',
                    'occurred_at' => $dispatchedAt,
                ],
            );
        });
        $clinicCase = $clinicCases->first();

        $historySummary = $clinicCases
            ->flatMap(fn ($case) => $this->studentDispatchHistory($case->student_id ? \App\Models\Students::query()->find($case->student_id) : null))
            ->unique()
            ->values()
            ->all();
        try {
            $assignedClinic->notify(new ClinicDispatchAssigned($alert, $clinicCase, $historySummary));
        } catch (\Throwable $exception) {
            Log::warning('Clinic dispatch assignment email could not be sent.', [
                'alert_id' => $alert->emergency_alert_id,
                'clinic_user_id' => $assignedClinic->user_id,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->logActivity($request, 'create', 'clinic_cases', 'Assigned clinic response for emergency alert '.$alert->emergency_alert_id.' to '.$assignedClinic->name.'.');

        return back()->with('success', 'Emergency response assigned to '.$assignedClinic->name.'.');
    }

    // @function studentDispatchHistory: Kinukuha ang student dispatch history result para sa Emergency.
    // @useIn studentDispatchHistory: EmergencyController::dispatchAlert (app/Http/Controllers/Shared/Emergency/EmergencyController.php)
    private function studentDispatchHistory($student): array
    {
        if (! $student) {
            return ['No linked student history was found for this alert.'];
        }

        $history = PatientHistory::query()
            ->where('student_id', $student->student_id)
            ->latest('occurred_at')
            ->take(3)
            ->get()
            ->map(fn (PatientHistory $record) => 'Clinic history: '.optional($record->occurred_at)->format('Y-m-d').' - '.$record->summary)
            ->all();
        $attendance = Attendance::query()
            ->where('student_id', $student->student_id)
            ->latest('date')
            ->take(3)
            ->get()
            ->map(fn (Attendance $record) => 'Attendance: '.optional($record->date)->format('Y-m-d').' - '.ucfirst((string) $record->status))
            ->all();

        return array_values([...$history, ...$attendance]) ?: ['No previous clinic or attendance history was found.'];
    }

    // @function validateHotline: Vinavalidate ang hotline sa Emergency flow.
    // @useIn validateHotline: EmergencyController::storeHotline (app/Http/Controllers/Shared/Emergency/EmergencyController.php)
    private function validateHotline(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in($this->hotlineCategories())],
            'phone_number' => ['required', 'string', 'max:40'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'sms_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    // @function hotlinePayload: Binubuo ang hotline payload value.
    // @useIn hotlinePayload: EmergencyController::hotlines (app/Http/Controllers/Shared/Emergency/EmergencyController.php)
    private function hotlinePayload(EmergencyHotline $hotline): array
    {
        return [
            'emergency_hotline_id' => $hotline->emergency_hotline_id,
            'name' => $hotline->name,
            'category' => $hotline->category,
            'phone_number' => $hotline->phone_number,
            'contact_person' => $hotline->contact_person,
            'sms_enabled' => $hotline->sms_enabled,
            'is_active' => $hotline->is_active,
            'sort_order' => $hotline->sort_order,
            'notes' => $hotline->notes,
        ];
    }

    // @function hotlineCategories: Kinukuha ang hotline categories result para sa Emergency.
    // @useIn hotlineCategories: EmergencyController::validateHotline (app/Http/Controllers/Shared/Emergency/EmergencyController.php)
    private function hotlineCategories(): array
    {
        return ['clinic', 'medical', 'fire', 'police', 'security', 'disaster', 'general', 'external'];
    }

    // @function sendHotlineSms: Ipinapadala ang hotline sms sa Emergency flow.
    // @useIn sendHotlineSms: EmergencyController::storeAlert (app/Http/Controllers/Shared/Emergency/EmergencyController.php)
    private function sendHotlineSms(array $metadata, EmergencyAlert $alert, SmsService $sms): array
    {
        $hotlineId = $metadata['emergency_hotline_id'] ?? null;
        if (! $hotlineId) {
            return ['sent' => false, 'reason' => 'no_hotline_selected'];
        }

        $hotline = EmergencyHotline::query()
            ->where('is_active', true)
            ->find($hotlineId);

        if (! $hotline) {
            return ['sent' => false, 'reason' => 'hotline_not_found'];
        }

        return $sms->sendEmergencyAlert($hotline, $alert->loadMissing('type'));
    }

    // @function notifyParents: Nagnonotify ang parents sa Emergency flow.
    // @useIn notifyParents: EmergencyController::storeAlert (app/Http/Controllers/Shared/Emergency/EmergencyController.php)
    private function notifyParents(EmergencyAlert $alert, array $metadata, SmsService $sms): array
    {
        $studentIds = collect($metadata['students'] ?? [])
            ->filter(fn ($student) => is_array($student) && is_numeric($student['student_id'] ?? null))
            ->pluck('student_id')
            ->push($metadata['student_id'] ?? null)
            ->filter(fn ($studentId) => is_numeric($studentId))
            ->map(fn ($studentId) => (int) $studentId)
            ->unique()
            ->values();

        if (($metadata['emergency_scope'] ?? null) === 'all' || $studentIds->isEmpty()) {
            return [
                'students_found' => 0,
                'parents_found' => 0,
                'email_sent' => 0,
                'sms_sent' => 0,
                'failures' => [],
                'warnings' => [],
                'reason' => 'no_specific_student',
            ];
        }

        $students = Students::query()
            ->with(['parentUsers' => fn ($query) => $query->whereRaw('LOWER(role) = ?', ['parent'])])
            ->whereIn('student_id', $studentIds)
            ->get();

        $result = [
            'students_found' => $students->count(),
            'parents_found' => 0,
            'email_sent' => 0,
            'sms_sent' => 0,
            'failures' => [],
            'warnings' => [],
        ];

        foreach ($students as $student) {
            foreach ($student->parentUsers as $parent) {
                $result['parents_found']++;
                $studentName = trim($student->first_name.' '.$student->last_name);
                $parentName = trim((string) $parent->name) ?: 'Linked parent';

                if (filter_var($parent->email, FILTER_VALIDATE_EMAIL)) {
                    try {
                        Notification::send($parent, new EmergencyParentAlert($alert->loadMissing('type'), $student));
                        $result['email_sent']++;
                    } catch (\Throwable $exception) {
                        $result['failures'][] = 'email';
                        $result['warnings'][] = 'Emergency email to '.$parentName.' for '.$studentName.' could not be sent. Please contact the parent directly.';
                        Log::warning('Emergency parent email could not be sent.', [
                            'alert_id' => $alert->emergency_alert_id,
                            'parent_user_id' => $parent->user_id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                } else {
                    $result['failures'][] = 'missing_parent_email';
                    $result['warnings'][] = $parentName.' has no valid email address for '.$studentName.'. Email was not sent.';
                }

                if (trim((string) $parent->phone) === '') {
                    $result['failures'][] = 'missing_parent_phone';
                    $result['warnings'][] = $parentName.' has no phone number for '.$studentName.'. SMS was not sent.';
                } else {
                    $smsResult = $sms->sendParentAlert($parent, $student, $alert->loadMissing('type'));
                    if ($smsResult['sent'] ?? false) {
                        $result['sms_sent']++;
                    } else {
                        $result['failures'][] = 'sms';
                    }
                }
            }
        }

        return $result;
    }

    // @function logActivity: Nilolog ang activity sa Emergency flow.
    // @useIn logActivity: EmergencyController::storeAlert (app/Http/Controllers/Shared/Emergency/EmergencyController.php)
    private function logActivity(Request $request, string $action, string $tableName, string $description): void
    {
        ActivityLog::query()->create([
            'user_id' => $request->user()?->user_id ?? Auth::id(),
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }
}
