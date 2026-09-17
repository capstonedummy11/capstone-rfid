<?php

namespace App\Http\Controllers;

use App\Models\Laboratory;
use App\Models\PanelDevice;
use App\Models\RfidPanelSession;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ActiveDeviceController
{
    public function index()
    {
        return Inertia::render('Auth/Admin/ActiveDevices', [
            'devices' => $this->deviceRows(),
            'laboratories' => Laboratory::query()
                ->orderBy('name')
                ->get(['laboratory_id', 'name', 'description', 'location', 'status'])
                ->values(),
            'featureSettings' => SystemSetting::featureFlags(),
            'panelAccess' => [
                'device_label' => SystemSetting::string(SystemSetting::PANEL_DEVICE_LABEL, 'Attendance Console'),
            ],
            'title' => 'Laboratories & Devices',
        ]);
    }

    public function updatePanelAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_label' => ['required', 'string', 'max:255'],
            'pin' => ['nullable', 'string', 'min:4', 'max:32'],
        ]);

        SystemSetting::setString(SystemSetting::PANEL_DEVICE_LABEL, trim($validated['device_label']));

        if (! empty($validated['pin'])) {
            SystemSetting::setString(SystemSetting::PANEL_PIN_HASH, Hash::make((string) $validated['pin']));
        }

        return response()->json([
            'ok' => true,
            'message' => 'Panel access settings updated.',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'laboratory_id' => ['required', 'exists:laboratories,laboratory_id', 'unique:panel_devices,laboratory_id'],
            'label' => ['required', 'string', 'max:255', 'unique:panel_devices,label'],
            'description' => ['nullable', 'string', 'max:500'],
            'pin' => ['required', 'string', 'min:4', 'max:32'],
            'is_active' => ['required', 'boolean'],
        ]);

        PanelDevice::query()->create([
            'laboratory_id' => $validated['laboratory_id'],
            'label' => trim($validated['label']),
            'description' => $validated['description'] ?? null,
            'pin_hash' => Hash::make($validated['pin']),
            'is_active' => $validated['is_active'],
        ]);

        return back()->with('success', 'Panel device created.');
    }

    public function update(Request $request, PanelDevice $device)
    {
        $validated = $request->validate([
            'laboratory_id' => [
                'required',
                'exists:laboratories,laboratory_id',
                Rule::unique('panel_devices', 'laboratory_id')->ignore($device->panel_device_id, 'panel_device_id'),
            ],
            'label' => ['required', 'string', 'max:255', 'unique:panel_devices,label,'.$device->panel_device_id.',panel_device_id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);

        $device->update($validated);

        return back()->with('success', 'Panel device updated.');
    }

    public function destroy(PanelDevice $device)
    {
        $hasOpenSession = RfidPanelSession::query()
            ->where('panel_id', $device->label)
            ->whereNull('ended_at')
            ->where('status', '!=', 'offline')
            ->exists();

        abort_if($hasOpenSession, 422, 'Log out the active panel before deleting this device.');
        $device->delete();

        return back()->with('success', 'Panel device deleted.');
    }

    public function updatePanelDevicePin(Request $request, PanelDevice $device): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:32'],
        ]);

        $device->update(['pin_hash' => Hash::make((string) $validated['pin'])]);

        return response()->json([
            'ok' => true,
            'message' => "{$device->label} PIN updated.",
        ]);
    }

    public function forceLogout(Request $request, int $panelSessionId): JsonResponse
    {
        $session = RfidPanelSession::query()->findOrFail($panelSessionId);

        $session->forceFill([
            'status' => 'offline',
            'is_listening' => false,
            'ended_at' => now(),
            'meta' => [
                ...($session->meta ?? []),
                'forced_logout' => true,
                'forced_logout_at' => now()->toISOString(),
                'forced_logout_by_user_id' => $request->user()?->user_id,
            ],
        ])->save();

        $attendanceSessionId = DB::table('attendance_sessions')
            ->where('room', $session->room)
            ->whereDate('date', now()->toDateString())
            ->whereNull('time_end')
            ->orderByDesc('attendance_id')
            ->value('attendance_id');

        if ($attendanceSessionId) {
            DB::table('attendance_sessions')
                ->where('attendance_id', $attendanceSessionId)
                ->update([
                'status' => 'offline',
                'time_end' => now()->format('H:i:s'),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => "{$session->room} was logged out.",
        ]);
    }

    private function deviceRows()
    {
        return PanelDevice::query()
            ->with('laboratory')
            ->orderBy('label')
            ->get()
            ->map(function (PanelDevice $device) {
            $session = RfidPanelSession::query()
                ->with(['schedule.subject', 'schedule.section', 'schedule.instructor.user', 'openedBy'])
                ->where('panel_id', $device->label)
                ->orderByDesc('created_at')
                ->first();
            $status = strtolower((string) ($session->status ?? 'offline'));
            $isOpen = $session && ! $session->ended_at && $status !== 'offline';
            $isActive = $isOpen && in_array($status, ['attendance', 'borrowing'], true);
            $isWaiting = $isOpen && in_array($status, ['online', 'paused'], true);
            $schedule = $session->schedule;

            return [
                'panel_device_id' => $device->panel_device_id,
                'panel_session_id' => $session?->panel_session_id,
                'laboratory_id' => $device->laboratory_id,
                'device_label' => $device->label,
                'description' => $device->description,
                'room' => $device->laboratory?->name ?? $session?->room ?? 'Unassigned',
                'laboratory_status' => $device->laboratory?->status,
                'is_enabled' => $device->is_active,
                'status' => $status,
                'status_label' => ! $device->is_active ? 'Disabled' : ($isActive ? 'Active' : ($isWaiting ? 'Waiting' : 'Offline')),
                'is_active' => $isActive,
                'is_waiting' => $isWaiting,
                'can_force_logout' => (bool) $isOpen,
                'mode' => $status,
                'subject' => $schedule?->subject?->subject_name ?? $session?->subject_code ?? 'No active attendance',
                'subject_code' => $session?->subject_code,
                'section' => $schedule?->section?->section_name,
                'instructor' => $schedule?->instructor?->user?->name ?? $session?->openedBy?->name,
                'opened_at' => $session?->listening_started_at?->format('Y-m-d H:i:s') ?? $session?->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $session?->updated_at?->format('Y-m-d H:i:s'),
            ];
        })->values();
    }
}
