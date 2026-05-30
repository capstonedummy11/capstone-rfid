<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemSettingsController
{
    public function edit()
    {
        return Inertia::render('Auth/Admin/SystemSettings', [
            'featureSettings' => SystemSetting::featureFlags(),
            'title' => 'Settings',
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'borrowing_enabled' => ['required', 'boolean'],
            'inventory_enabled' => ['required', 'boolean'],
            'face_recognition_enabled' => ['required', 'boolean'],
        ]);

        SystemSetting::setBoolean(SystemSetting::BORROWING_ENABLED, (bool) $validated['borrowing_enabled']);
        SystemSetting::setBoolean(SystemSetting::INVENTORY_ENABLED, (bool) $validated['inventory_enabled']);
        SystemSetting::setBoolean(SystemSetting::FACE_RECOGNITION_ENABLED, (bool) $validated['face_recognition_enabled']);

        return back()->with('success', 'System settings updated.');
    }
}
