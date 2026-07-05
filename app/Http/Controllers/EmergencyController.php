<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EmergencyAlert;
use App\Models\EmergencyHotline;
use App\Models\EmergencyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EmergencyController
{
    public function storeAlert(Request $request): JsonResponse
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

        return response()->json([
            'ok' => true,
            'alert' => $alert->load('type'),
        ]);
    }

    public function hotlines()
    {
        return Inertia::render('Clinic/EmergencyHotlines', [
            'hotlines' => EmergencyHotline::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (EmergencyHotline $hotline) => $this->hotlinePayload($hotline))
                ->values(),
        ]);
    }

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

    public function destroyHotline(Request $request, int $id)
    {
        $hotline = EmergencyHotline::query()->findOrFail($id);
        $name = $hotline->name;
        $phone = $hotline->phone_number;

        $hotline->delete();

        $this->logActivity($request, 'delete', 'emergency_hotlines', 'Deleted emergency hotline '.$name.' ('.$phone.').');

        return back()->with('success', 'Emergency hotline deleted.');
    }

    public function storeType(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'default_message' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = EmergencyType::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => (EmergencyType::max('sort_order') ?? 0) + 1,
        ]);

        $this->logActivity($request, 'create', 'emergency_types', 'Created emergency type '.$type->name.'.');

        return back()->with('success', 'Emergency type added.');
    }

    public function updateType(Request $request, int $id)
    {
        $type = EmergencyType::findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'default_message' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type->update([
            ...$validated,
            'is_active' => $validated['is_active'] ?? false,
        ]);

        $this->logActivity($request, 'update', 'emergency_types', 'Updated emergency type '.$type->name.'.');

        return back()->with('success', 'Emergency type updated.');
    }

    public function destroyType(Request $request, int $id)
    {
        $type = EmergencyType::findOrFail($id);
        $name = $type->name;

        $type->delete();

        $this->logActivity($request, 'delete', 'emergency_types', 'Deleted emergency type '.$name.'.');

        return back()->with('success', 'Emergency type deleted.');
    }

    public function updateAlertStatus(Request $request, int $id)
    {
        $alert = EmergencyAlert::findOrFail($id);
        $validated = $request->validate([
            'status' => ['required', 'in:open,acknowledged,resolved,cancelled'],
        ]);

        $alert->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === 'resolved' ? now() : $alert->resolved_at,
        ]);

        $this->logActivity($request, 'update', 'emergency_alerts', 'Updated emergency alert '.$alert->emergency_alert_id.' status to '.$validated['status'].'.');

        return back()->with('success', 'Emergency alert updated.');
    }

    public function dispatchAlert(Request $request, int $id)
    {
        $alert = EmergencyAlert::with('type')->findOrFail($id);
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

        $patientName = $student
            ? trim($student->first_name.' '.$student->last_name)
            : ($alert->triggered_by_name ?: 'Unknown Patient');

        $alert->update(['status' => 'acknowledged']);

        \App\Models\ClinicCase::updateOrCreate(
            [
                'emergency_alert_id' => $alert->emergency_alert_id,
                'patient_name' => $patientName,
            ],
            [
                'student_id' => $student?->student_id,
                'handled_by_user_id' => $request->user()?->user_id,
                'patient_type' => $student ? 'student' : 'user',
                'case_type' => $alert->type?->name ?? 'Emergency',
                'symptoms' => $metadata['symptoms'] ?? $alert->message,
                'action_taken' => 'Dispatched clinic response.',
                'notes' => 'Created from clinic emergency dispatch.',
                'status' => 'monitoring',
                'occurred_at' => now(),
            ],
        );

        $this->logActivity($request, 'create', 'clinic_cases', 'Dispatched clinic response for emergency alert '.$alert->emergency_alert_id.'.');

        return back()->with('success', 'Emergency response dispatched.');
    }

    private function validateHotline(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:40'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'sms_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

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
