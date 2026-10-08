<?php

namespace App\Http\Controllers\Admin\ActiveDevices;

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
    // @function index: Ibinabalik ang Auth/Admin/ActiveDevices page at data para sa request.
    // @useIn index: routes/web.php:374 (active-devices.index)
    /**
     * @feature   Laboratories and Devices
     * @actor     Admin
     * @flow      Dito kino-configure ang rooms, panel devices, PIN, at remote logout.
     * @uses      resources/js/pages/Admin/ActiveDevices/ActiveDevicesPage.vue; routes/web.php: ActiveDeviceController::index, ActiveDeviceController::store, ActiveDeviceController::update, ActiveDeviceController::destroy, ActiveDeviceController::updatePanelAccess, ActiveDeviceController::updatePanelDevicePin, ActiveDeviceController::forceLogout
     * @related   Admin workspace
     * @disable   1) I-comment out ang routes/web.php: ActiveDeviceController::index/store/update/destroy/updatePanelAccess/updatePanelDevicePin/forceLogout at LaboratoryController::indexAdmin/store/update/destroy.
     * @disable   2) Itago ang action sa resources/js/pages/Admin/ActiveDevices/ActiveDevicesPage.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Itago rin ang resources/js/pages/Admin/Laboratories/LaboratoriesPage.vue; ihinto ang app/Http/Controllers/Admin/ActiveDevices/ActiveDeviceController.php: index at app/Http/Controllers/Admin/Laboratories/LaboratoryController.php: indexAdmin matapos alisin ang routes. Side effect: hindi na ma-manage ang rooms at PIN; maaapektuhan ang Console access.
     */
    public function index()
    {
        return Inertia::render('Admin/ActiveDevices/ActiveDevicesPage', [
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

    // @function updatePanelAccess: Ina-update ang panel access sa Active Device flow.
    // @useIn updatePanelAccess: routes/web.php:378 (active-devices.panel-access.update)
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

    // @function store: Pinoproseso ang bagong Active Device record.
    // @useIn store: routes/web.php:376 (active-devices.store)
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

    // @function update: Pinoproseso ang pagbabago sa Active Device record.
    // @useIn update: routes/web.php:380 (active-devices.update)
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

    // @function destroy: Pinoproseso ang pagtanggal ng Active Device record.
    // @useIn destroy: routes/web.php:382 (active-devices.destroy)
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

    // @function updatePanelDevicePin: Ina-update ang panel device pin sa Active Device flow.
    // @useIn updatePanelDevicePin: routes/web.php:384 (active-devices.pin.update)
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

    // @function forceLogout: Pinoproseso ang force logout at nagbabalik ng JSON response.
    // @useIn forceLogout: routes/web.php:386 (active-devices.force-logout)
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

    // @function deviceRows: Binubuo ang device rows value.
    // @useIn deviceRows: ActiveDeviceController::index (app/Http/Controllers/Admin/ActiveDevices/ActiveDeviceController.php)
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
            $schedule = $session?->schedule;

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
