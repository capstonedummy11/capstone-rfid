<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\AwsFaceRecognitionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemSettingsController
{
    public function edit()
    {
        $faceAvailability = (new AwsFaceRecognitionService)->availability();

        return Inertia::render('Auth/Admin/SystemSettings', [
            'featureSettings' => SystemSetting::featureFlags(),
            'faceRecognitionAvailability' => $faceAvailability,
            'attendanceSettings' => [
                'absent_default_days' => SystemSetting::integer(SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, 15),
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
            'online_class_face_recognition_default' => ['required', 'boolean'],
            'absent_default_days' => ['nullable', 'integer', 'min:1', 'max:365'],
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
        SystemSetting::setBoolean(SystemSetting::ONLINE_CLASS_FACE_RECOGNITION_DEFAULT, (bool) $validated['online_class_face_recognition_default']);
        if (array_key_exists('absent_default_days', $validated) && $validated['absent_default_days'] !== null) {
            SystemSetting::setInteger(SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS, (int) $validated['absent_default_days']);
        }
        if (array_key_exists('security_questions', $validated) && $validated['security_questions'] !== null) {
            $questions = collect($validated['security_questions'])
                ->map(fn ($question) => trim((string) $question))
                ->filter()
                ->unique()
                ->values()
                ->all();

            SystemSetting::setArray(SystemSetting::SECURITY_QUESTIONS, $questions);
        }

        return back()->with('success', $warning ?? 'System settings updated.');
    }
}
