<?php

namespace App\Http\Controllers;

use App\Models\EmergencyAlert;
use App\Models\EmergencyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'ok' => true,
            'alert' => $alert->load('type'),
        ]);
    }

    public function storeType(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'default_message' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        EmergencyType::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => (EmergencyType::max('sort_order') ?? 0) + 1,
        ]);

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

        return back()->with('success', 'Emergency type updated.');
    }

    public function destroyType(int $id)
    {
        EmergencyType::findOrFail($id)->delete();

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
            ? trim($student->first_name . ' ' . $student->last_name)
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

        return back()->with('success', 'Emergency response dispatched.');
    }
}
