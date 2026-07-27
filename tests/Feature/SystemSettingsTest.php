<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can enable demo attendance panel and configure demo rfids', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'borrowing_enabled' => false,
            'inventory_enabled' => false,
            'face_recognition_enabled' => false,
            'online_class_face_recognition_default' => false,
            'demo_attendance_panel_enabled' => true,
            'demo_attendance_panel_rfids' => [
                'professor_tap' => 'PROF-ONE',
                'student_tap' => 'STUDENT-ONE',
                'second_student_tap' => 'STUDENT-TWO',
                'second_professor_tap' => 'PROF-TWO',
            ],
            'late_threshold_minutes' => 15,
            'security_questions' => SystemSetting::DEFAULT_SECURITY_QUESTIONS,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SystemSetting::demoAttendancePanelSettings())->toMatchArray([
        'enabled' => true,
        'rfids' => [
            'professor_tap' => 'PROF-ONE',
            'student_tap' => 'STUDENT-ONE',
            'second_student_tap' => 'STUDENT-TWO',
            'second_professor_tap' => 'PROF-TWO',
        ],
    ]);
});
