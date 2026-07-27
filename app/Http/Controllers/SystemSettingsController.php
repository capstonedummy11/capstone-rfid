<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Services\AwsFaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SystemSettingsController
{
    public function edit()
    {
        $faceAvailability = (new AwsFaceRecognitionService)->availability();

        return Inertia::render('Auth/Admin/SystemSettings', [
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
            'title' => 'Settings',
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'borrowing_enabled' => ['required', 'boolean'],
            'inventory_enabled' => ['required', 'boolean'],
            'face_recognition_enabled' => ['required', 'boolean'],
            'demo_attendance_panel_enabled' => ['required', 'boolean'],
            'demo_attendance_panel_rfids' => ['required', 'array'],
            'demo_attendance_panel_rfids.professor_tap' => ['nullable', 'string', 'max:255'],
            'demo_attendance_panel_rfids.student_tap' => ['nullable', 'string', 'max:255'],
            'demo_attendance_panel_rfids.second_student_tap' => ['nullable', 'string', 'max:255'],
            'demo_attendance_panel_rfids.second_professor_tap' => ['nullable', 'string', 'max:255'],
            'online_class_face_recognition_default' => ['required', 'boolean'],
            'absent_default_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'late_threshold_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'security_questions' => ['nullable', 'array', 'min:3', 'max:20'],
            'security_questions.*' => ['required_with:security_questions', 'string', 'min:8', 'max:255', 'distinct'],
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

        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'table_name' => 'system_settings',
            'description' => $warning ?? 'Updated system settings.',
        ]);

        return back()->with('success', $warning ?? 'System settings updated.');
    }
}
