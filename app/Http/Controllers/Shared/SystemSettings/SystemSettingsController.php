<?php

namespace App\Http\Controllers\Shared\SystemSettings;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Services\AwsFaceRecognitionService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

// Used by roles: Admin for system settings; Admin and Clinic for emergency-sound access.
class SystemSettingsController
{
    // @function edit: Ibinabalik ang Auth/Admin/SystemSettings page at data para sa request.
    // @useIn edit: routes/web.php:388 (settings.edit)
    /**
     * @feature   System Settings
     * @actor     Admin
     * @flow      Dito sine-set ang feature switches, attendance rules, SMS, at emergency sounds.
     * @uses      resources/js/pages/Admin/SystemSettings/SystemSettingsPage.vue; routes/web.php: SystemSettingsController::edit, SystemSettingsController::update, SystemSettingsController::checkSmsProvider, SystemSettingsController::storeEmergencySound, SystemSettingsController::selectEmergencySound, SystemSettingsController::destroyEmergencySound
     * @related   Admin workspace
     * @disable   1) I-comment out ang routes/web.php: SystemSettingsController::edit, SystemSettingsController::update, SystemSettingsController::checkSmsProvider, SystemSettingsController::storeEmergencySound, SystemSettingsController::selectEmergencySound, SystemSettingsController::destroyEmergencySound.
     * @disable   2) Itago ang action sa resources/js/pages/Admin/SystemSettings/SystemSettingsPage.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Controllers/Shared/SystemSettings/SystemSettingsController.php: SystemSettingsController::edit matapos alisin ang routes. Side effect: mawawala ang system settings.
     */
    public function edit()
    {
        $faceAvailability = (new AwsFaceRecognitionService)->availability();

        return Inertia::render('Admin/SystemSettings/SystemSettingsPage', [
            'title' => 'Settings',
            'featureSettings' => SystemSetting::featureFlags(),
            'demoAttendancePanelSettings' => SystemSetting::demoAttendancePanelSettings(),
            'faceRecognitionAvailability' => $faceAvailability,
            'attendanceSettings' => [
                'absent_default_days' => SystemSetting::integer(SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, 15),
                'late_threshold_minutes' => SystemSetting::integer(SystemSetting::ATTENDANCE_LATE_THRESHOLD_MINUTES, 15),
            ],
            'securitySettings' => [
                'questions' => SystemSetting::securityQuestions(),
            ],
            'clinicEmergencySoundSettings' => SystemSetting::clinicEmergencySoundSettings(),
            'smsSettings' => SystemSetting::smsProviderSettings(),
        ]);
    }

    // @function update: Pinoproseso ang pagbabago sa System Settings record.
    // @useIn update: routes/web.php:390 (settings.update)
    public function update(Request $request)
    {
        $validated = $request->validate([
            'borrowing_enabled' => ['required', 'boolean'],
            'inventory_enabled' => ['required', 'boolean'],
            'parent_portal_enabled' => ['required', 'boolean'],
            'parent_excuse_letters_enabled' => ['nullable', 'boolean'],
            'face_recognition_enabled' => ['required', 'boolean'],
            'demo_attendance_panel_enabled' => ['required', 'boolean'],
            'demo_attendance_panel_rfids' => ['required', 'array'],
            'demo_attendance_panel_rfids.professor_tap' => ['nullable', 'string', 'max:255'],
            'demo_attendance_panel_rfids.student_tap' => ['nullable', 'string', 'max:255'],
            'demo_attendance_panel_rfids.second_student_tap' => ['nullable', 'string', 'max:255'],
            'demo_attendance_panel_rfids.second_professor_tap' => ['nullable', 'string', 'max:255'],
            'online_class_face_recognition_default' => ['required', 'boolean'],
            'online_classes_enabled' => ['required', 'boolean'],
            'absent_default_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'late_threshold_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'security_questions' => ['nullable', 'array', 'min:3', 'max:20'],
            'security_questions.*' => ['required_with:security_questions', 'string', 'min:8', 'max:255', 'distinct'],
            'sms_semaphore_available' => ['sometimes', 'boolean'],
            'sms_iprog_available' => ['sometimes', 'boolean'],
            'sms_primary_provider' => ['nullable', 'string', 'in:semaphore,iprog'],
        ]);

        $faceAvailability = (new AwsFaceRecognitionService)->availability();
        if (! $faceAvailability['available'] && ((bool) $validated['face_recognition_enabled'] || (bool) $validated['online_class_face_recognition_default'])) {
            $validated['face_recognition_enabled'] = false;
            $validated['online_class_face_recognition_default'] = false;
            $warning = 'Face recognition was kept off: '.$faceAvailability['message'];
        }

        if (! (bool) $validated['face_recognition_enabled'] && (bool) $validated['online_class_face_recognition_default']) {
            $validated['online_class_face_recognition_default'] = false;
            $warning = 'Online Class facial recognition was kept off because Face Rekognition is off.';
        }

        SystemSetting::setBoolean(SystemSetting::BORROWING_ENABLED, (bool) $validated['borrowing_enabled']);
        SystemSetting::setBoolean(SystemSetting::INVENTORY_ENABLED, (bool) $validated['inventory_enabled']);
        $parentPortalEnabled = (bool) $validated['parent_portal_enabled'];
        SystemSetting::setBoolean(SystemSetting::PARENT_PORTAL_ENABLED, $parentPortalEnabled);
        SystemSetting::setBoolean(
            SystemSetting::PARENT_EXCUSE_LETTERS_ENABLED,
            $parentPortalEnabled && (bool) ($validated['parent_excuse_letters_enabled'] ?? false),
        );
        SystemSetting::setBoolean(SystemSetting::FACE_RECOGNITION_ENABLED, (bool) $validated['face_recognition_enabled']);
        SystemSetting::setBoolean(SystemSetting::DEMO_ATTENDANCE_PANEL_ENABLED, (bool) $validated['demo_attendance_panel_enabled']);
        SystemSetting::setArray(
            SystemSetting::DEMO_ATTENDANCE_PANEL_RFIDS,
            collect(SystemSetting::DEFAULT_DEMO_ATTENDANCE_PANEL_RFIDS)
                ->mapWithKeys(fn (string $default, string $key) => [
                    $key => trim((string) ($validated['demo_attendance_panel_rfids'][$key] ?? $default)),
                ])
                ->all(),
        );
        SystemSetting::setBoolean(SystemSetting::ONLINE_CLASS_FACE_RECOGNITION_DEFAULT, (bool) $validated['online_class_face_recognition_default']);
        SystemSetting::setBoolean(SystemSetting::ONLINE_CLASSES_ENABLED, (bool) $validated['online_classes_enabled']);
        if (array_key_exists('absent_default_days', $validated) && $validated['absent_default_days'] !== null) {
            SystemSetting::setInteger(SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, (int) $validated['absent_default_days']);
        }
        SystemSetting::setInteger(SystemSetting::ATTENDANCE_LATE_THRESHOLD_MINUTES, (int) $validated['late_threshold_minutes']);
        if (array_key_exists('security_questions', $validated) && $validated['security_questions'] !== null) {
            $questions = collect($validated['security_questions'])
                ->map(fn ($question) => trim((string) $question))
                ->filter()
                ->unique()
                ->values()
                ->all();

            SystemSetting::setArray(SystemSetting::SECURITY_QUESTIONS, $questions);
        }

        $smsSemaphoreAvailable = array_key_exists('sms_semaphore_available', $validated)
            ? (bool) $validated['sms_semaphore_available']
            : SystemSetting::boolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, false);
        $smsIprogAvailable = array_key_exists('sms_iprog_available', $validated)
            ? (bool) $validated['sms_iprog_available']
            : SystemSetting::boolean(SystemSetting::SMS_IPROG_AVAILABLE, false);
        $requestedSmsPrimary = array_key_exists('sms_primary_provider', $validated)
            ? $validated['sms_primary_provider']
            : SystemSetting::string(SystemSetting::SMS_PRIMARY_PROVIDER, '');
        $availableSmsProviders = collect([
            'semaphore' => $smsSemaphoreAvailable,
            'iprog' => $smsIprogAvailable,
        ])->filter()->keys();
        $smsPrimary = in_array($requestedSmsPrimary, $availableSmsProviders->all(), true)
            ? $requestedSmsPrimary
            : $availableSmsProviders->first();

        SystemSetting::setBoolean(SystemSetting::SMS_SEMAPHORE_AVAILABLE, $smsSemaphoreAvailable);
        SystemSetting::setBoolean(SystemSetting::SMS_IPROG_AVAILABLE, $smsIprogAvailable);
        SystemSetting::setString(SystemSetting::SMS_PRIMARY_PROVIDER, (string) ($smsPrimary ?? ''));

        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'table_name' => 'system_settings',
            'description' => $warning ?? 'Updated system settings.',
        ]);

        return back()->with('success', $warning ?? 'System settings updated.');
    }

    // @function checkSmsProvider: Sini-check ang sms provider sa System Settings flow.
    // @useIn checkSmsProvider: routes/web.php:392 (settings.sms.providers.check)
    public function checkSmsProvider(Request $request, string $provider, SmsService $sms): JsonResponse
    {
        abort_unless(in_array($provider, SystemSetting::SMS_PROVIDER_NAMES, true), 404);

        try {
            $result = $sms->checkProvider($provider);

            return response()->json([
                'success' => (bool) ($result['success'] ?? false),
                'message' => (string) ($result['message'] ?? 'Provider check failed.'),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('SMS provider check failed unexpectedly.', [
                'provider' => $provider,
                'user_id' => Auth::id(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The SMS provider check could not be completed.',
            ]);
        }
    }

    // @function storeEmergencySound: Sine-save ang emergency sound sa System Settings flow.
    // @useIn storeEmergencySound: routes/web.php:396 (settings.emergency-sounds.store)
    public function storeEmergencySound(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'sound' => ['required', 'file', 'mimes:mp3,wav,ogg,m4a,aac', 'max:10240'],
        ]);

        $file = $validated['sound'];
        $id = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp3');
        $path = $file->storeAs('clinic-emergency-sounds', "{$id}.{$extension}", 'public');

        if (! $path) {
            return back()->withErrors(['sound' => 'Emergency sound could not be uploaded.']);
        }

        $settings = SystemSetting::clinicEmergencySoundSettings();
        $sounds = collect($settings['sounds'])
            ->reject(fn (array $sound) => (bool) ($sound['is_default'] ?? false))
            ->map(fn (array $sound) => collect($sound)->only(['id', 'name', 'original_name', 'path', 'size', 'uploaded_at'])->all())
            ->push([
                'id' => $id,
                'name' => trim((string) ($validated['name'] ?? '')) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'uploaded_at' => now()->toDateTimeString(),
            ])
            ->values()
            ->all();

        SystemSetting::setClinicEmergencySoundLibrary($sounds, $id);

        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => 'upload',
            'table_name' => 'system_settings',
            'description' => 'Uploaded clinic emergency dashboard sound.',
        ]);

        return back()->with('success', 'Emergency sound uploaded and selected.');
    }

    // @function selectEmergencySound: Pinipili ang emergency sound sa System Settings flow.
    // @useIn selectEmergencySound: routes/web.php:398 (settings.emergency-sounds.select)
    public function selectEmergencySound(string $id)
    {
        $settings = SystemSetting::clinicEmergencySoundSettings();
        $sounds = collect($settings['sounds'])
            ->reject(fn (array $sound) => (bool) ($sound['is_default'] ?? false));

        $exists = $id === SystemSetting::DEFAULT_CLINIC_EMERGENCY_SOUND_ID
            || $sounds->contains(fn (array $sound) => $sound['id'] === $id);

        if (! $exists) {
            abort(404);
        }

        SystemSetting::setClinicEmergencySoundLibrary(
            $sounds->map(fn (array $sound) => collect($sound)->only(['id', 'name', 'original_name', 'path', 'size', 'uploaded_at'])->all())->values()->all(),
            $id,
        );

        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'table_name' => 'system_settings',
            'description' => 'Selected clinic emergency dashboard sound.',
        ]);

        return back()->with('success', 'Emergency sound selected.');
    }

    // @function destroyEmergencySound: Tinatanggal ang emergency sound sa System Settings flow.
    // @useIn destroyEmergencySound: routes/web.php:400 (settings.emergency-sounds.destroy)
    public function destroyEmergencySound(string $id)
    {
        if ($id === SystemSetting::DEFAULT_CLINIC_EMERGENCY_SOUND_ID) {
            return back()->withErrors(['sound' => 'The default emergency sound cannot be deleted.']);
        }

        $settings = SystemSetting::clinicEmergencySoundSettings();
        $sounds = collect($settings['sounds'])
            ->reject(fn (array $sound) => (bool) ($sound['is_default'] ?? false));
        $sound = $sounds->firstWhere('id', $id);

        if (! $sound) {
            abort(404);
        }

        Storage::disk('public')->delete($sound['path']);

        $remainingSounds = $sounds
            ->reject(fn (array $entry) => $entry['id'] === $id)
            ->map(fn (array $entry) => collect($entry)->only(['id', 'name', 'original_name', 'path', 'size', 'uploaded_at'])->all())
            ->values()
            ->all();
        $selectedId = $settings['selected_id'] === $id
            ? SystemSetting::DEFAULT_CLINIC_EMERGENCY_SOUND_ID
            : $settings['selected_id'];

        SystemSetting::setClinicEmergencySoundLibrary($remainingSounds, $selectedId);

        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => 'delete',
            'table_name' => 'system_settings',
            'description' => 'Deleted clinic emergency dashboard sound.',
        ]);

        return back()->with('success', 'Emergency sound deleted.');
    }

    // @function showEmergencySound: Ipinapakita ang emergency sound sa System Settings flow.
    // @useIn showEmergencySound: routes/web.php:457 (emergency-sounds.show)
    public function showEmergencySound(string $id)
    {
        abort_if($id === SystemSetting::DEFAULT_CLINIC_EMERGENCY_SOUND_ID, 404);

        $sound = collect(SystemSetting::clinicEmergencySoundSettings()['sounds'])
            ->firstWhere('id', $id);

        abort_if(! $sound || ! Storage::disk('public')->exists($sound['path']), 404);

        return Storage::disk('public')->response(
            $sound['path'],
            $sound['original_name'] ?: 'clinic-emergency-sound',
        );
    }
}
