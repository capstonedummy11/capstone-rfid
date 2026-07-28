<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('admin can upload select and delete clinic emergency dashboard sounds', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $clinic = User::factory()->create(['role' => 'clinic']);

    $this->actingAs($admin)
        ->post(route('admin.settings.emergency-sounds.store'), [
            'name' => 'Clinic Bell',
            'sound' => UploadedFile::fake()->create('clinic-alert.mp3', 64, 'audio/mpeg'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency sound uploaded and selected.');

    $library = SystemSetting::array(SystemSetting::CLINIC_EMERGENCY_SOUND_LIBRARY);
    $uploaded = $library['sounds'][0];

    Storage::disk('public')->assertExists($uploaded['path']);
    expect($library['selected_id'])->toBe($uploaded['id']);

    $this->actingAs($clinic)
        ->get(route('clinic.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('emergencySound.selected_id', $uploaded['id'])
            ->where('emergencySound.selected_url', route('clinic.emergency-sounds.show', ['id' => $uploaded['id']]))
        );

    $this->actingAs($admin)
        ->put(route('admin.settings.emergency-sounds.select', ['id' => SystemSetting::DEFAULT_CLINIC_EMERGENCY_SOUND_ID]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency sound selected.');

    expect(SystemSetting::clinicEmergencySoundSettings()['selected_id'])
        ->toBe(SystemSetting::DEFAULT_CLINIC_EMERGENCY_SOUND_ID);

    $this->actingAs($admin)
        ->delete(route('admin.settings.emergency-sounds.destroy', ['id' => $uploaded['id']]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Emergency sound deleted.');

    Storage::disk('public')->assertMissing($uploaded['path']);
    expect(SystemSetting::array(SystemSetting::CLINIC_EMERGENCY_SOUND_LIBRARY)['sounds'])->toBe([]);
});
