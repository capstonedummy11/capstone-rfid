<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MinimalSeeder extends Seeder
{
    /**
     * Seed only the records required to start a clean installation.
     *
     * This seeder intentionally does not call DatabaseSeeder or any demo
     * seeders. Run it with:
     * php artisan db:seed --class=MinimalSeeder
     */
    public function run(): void
    {
        $rootAdmin = User::query()->firstOrNew([
            'email' => env('MINIMAL_ROOT_ADMIN_EMAIL', 'root.admin@sample.com'),
        ]);

        if (! $rootAdmin->exists) {
            $rootAdmin->password = env('MINIMAL_ROOT_ADMIN_PASSWORD', 'change-me-now');
            $rootAdmin->must_change_password = true;
        }

        $rootAdmin->fill([
            'name' => env('MINIMAL_ROOT_ADMIN_NAME', 'Root Admin'),
            'role' => 'admin',
            'is_root_admin' => true,
        ]);
        $rootAdmin->save();

        $this->seedCurrentAcademicYear($rootAdmin);

        $this->createSetting(SystemSetting::BORROWING_ENABLED, false, 'boolean');
        $this->createSetting(SystemSetting::INVENTORY_ENABLED, false, 'boolean');
        $this->createSetting(SystemSetting::PARENT_PORTAL_ENABLED, false, 'boolean');
        $this->createSetting(SystemSetting::PARENT_EXCUSE_LETTERS_ENABLED, false, 'boolean');
        $this->createSetting(SystemSetting::FACE_RECOGNITION_ENABLED, true, 'boolean');
        $this->createSetting(SystemSetting::DEMO_ATTENDANCE_PANEL_ENABLED, false, 'boolean');
        $this->createSetting(
            SystemSetting::ONLINE_CLASS_FACE_RECOGNITION_DEFAULT,
            true,
            'boolean',
        );
        $this->createSetting(
            SystemSetting::ATTENDANCE_ABSENT_DEFAULT_DAYS,
            15,
            'integer',
        );
        $this->createSetting(
            SystemSetting::ATTENDANCE_LATE_THRESHOLD_MINUTES,
            15,
            'integer',
        );
        $this->createSetting(
            SystemSetting::SECURITY_QUESTIONS,
            SystemSetting::DEFAULT_SECURITY_QUESTIONS,
            'array',
        );

        $this->createSetting(
            SystemSetting::PANEL_PIN_HASH,
            Hash::make((string) env('PANEL_PIN', '1234')),
            'string',
        );
        $this->createSetting(
            SystemSetting::PANEL_DEVICE_LABEL,
            'Attendance Console',
            'string',
        );
    }

    private function createSetting(string $key, mixed $value, string $type): void
    {
        SystemSetting::query()->firstOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'type' => $type,
            ],
        );
    }

    private function seedCurrentAcademicYear(User $rootAdmin): void
    {
        $today = now();
        $startYear = $today->month >= 6 ? $today->year : $today->year - 1;
        $endYear = $startYear + 1;
        $name = env('MINIMAL_ACADEMIC_YEAR', "{$startYear}-{$endYear}");

        $academicYear = AcademicYear::query()->updateOrCreate(
            ['name' => $name],
            [
                'starts_on' => env('MINIMAL_ACADEMIC_YEAR_START', "{$startYear}-06-01"),
                'ends_on' => env('MINIMAL_ACADEMIC_YEAR_END', "{$endYear}-03-31"),
                'status' => AcademicYear::STATUS_ACTIVE,
                'active_semester' => '1st Semester',
                'activated_at' => now(),
                'activated_by_user_id' => $rootAdmin->user_id,
            ],
        );

        AcademicYear::query()
            ->where('status', AcademicYear::STATUS_ACTIVE)
            ->whereKeyNot($academicYear->academic_year_id)
            ->update([
                'status' => AcademicYear::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => $rootAdmin->user_id,
            ]);
    }
}
