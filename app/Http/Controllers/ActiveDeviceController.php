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

    public function updatePanelDevicePin(Request $request, int $panelSessionId): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:32'],
        ]);

        $session = RfidPanelSession::query()->findOrFail($panelSessionId);
        $label = trim((string) ($session->panel_id ?: SystemSetting::string(SystemSetting::PANEL_DEVICE_LABEL, 'Attendance Console')));

        PanelDevice::query()->updateOrCreate(
            ['label' => $label],
            [
                'pin_hash' => Hash::make((string) $validated['pin']),
                'is_active' => true,
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => "{$label} PIN updated.",
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
        return RfidPanelSession::query()
            ->with(['schedule.subject', 'schedule.section', 'schedule.instructor.user', 'openedBy'])
            ->orderByDesc('created_at')
            ->get()
            ->unique('room')
            ->map(function (RfidPanelSession $session) {
            $status = strtolower((string) ($session->status ?? 'offline'));
            $isOpen = $session && ! $session->ended_at && $status !== 'offline';
            $isActive = $isOpen && in_array($status, ['attendance', 'borrowing'], true);
            $isWaiting = $isOpen && in_array($status, ['online', 'paused'], true);
            $schedule = $session->schedule;

            return [
                'panel_session_id' => $session->panel_session_id,
                'device_label' => $session->panel_id ?? SystemSetting::string(SystemSetting::PANEL_DEVICE_LABEL, 'Attendance Console'),
                'room' => $session->room,
                'status' => $status,
                'status_label' => $isActive ? 'Active' : ($isWaiting ? 'Waiting' : 'Offline'),
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
