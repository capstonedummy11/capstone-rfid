<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $primaryKey = 'system_setting_id';

    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    public const BORROWING_ENABLED = 'feature.borrowing_enabled';

    public const INVENTORY_ENABLED = 'feature.inventory_enabled';

    public const PARENT_PORTAL_ENABLED = 'feature.parent_portal_enabled';

    public const PARENT_EXCUSE_LETTERS_ENABLED = 'feature.parent_excuse_letters_enabled';

    public const FACE_RECOGNITION_ENABLED = 'feature.face_recognition_enabled';

    public const DEMO_ATTENDANCE_PANEL_ENABLED = 'feature.demo_attendance_panel_enabled';

    public const DEMO_ATTENDANCE_PANEL_RFIDS = 'attendance.demo_panel_rfids';

    public const DEFAULT_DEMO_ATTENDANCE_PANEL_RFIDS = [
        'professor_tap' => 'RFID-INSTRUCTOR-SAMPLE',
        'student_tap' => 'RFID-STUDENT-1101',
        'second_student_tap' => 'RFID-STUDENT-1102',
        'second_professor_tap' => 'RFID-INSTRUCTOR-SAMPLE',
    ];

    public const ONLINE_CLASS_FACE_RECOGNITION_DEFAULT = 'online_class.face_recognition_enabled_by_default';

    public const ONLINE_CLASSES_ENABLED = 'feature.online_classes_enabled';

    public const PANEL_PIN_HASH = 'panel.pin_hash';

    public const PANEL_DEVICE_LABEL = 'panel.device_label';

    public const ATTENDANCE_ABSENT_DEFAULT_DAYS = 'attendance.absent_default_days';

    public const ATTENDANCE_LATE_THRESHOLD_MINUTES = 'attendance.late_threshold_minutes';

    public const SECURITY_QUESTIONS = 'auth.security_questions';

    public const CLINIC_EMERGENCY_SOUND_LIBRARY = 'clinic.emergency_sound_library';

    public const SMS_SEMAPHORE_AVAILABLE = 'sms.semaphore_available';

    public const SMS_IPROG_AVAILABLE = 'sms.iprog_available';

    public const SMS_PRIMARY_PROVIDER = 'sms.primary_provider';

    public const DEFAULT_CLINIC_EMERGENCY_SOUND_ID = 'default';

    public const SMS_PROVIDER_NAMES = ['semaphore', 'iprog'];

    public const DEFAULT_SECURITY_QUESTIONS = [
        'What was the name of your first school?',
        'What is your mother\'s maiden name?',
        'What was the name of your first pet?',
        'In what city were you born?',
        'What was the model of your first car?',
        'What is the name of the street where you grew up?',
        'What was your childhood nickname?',
        'What is the name of your favorite teacher?',
    ];

    // @function featureFlags: Kinukuha ang feature flags result para sa System Setting.
    // @useIn featureFlags: app/Services/ExcuseLetterPdfService.php
    public static function featureFlags(): array
    {
        $parentPortalEnabled = static::boolean(static::PARENT_PORTAL_ENABLED, false);

        return [
            'borrowing_enabled' => static::boolean(static::BORROWING_ENABLED, false),
            'inventory_enabled' => static::boolean(static::INVENTORY_ENABLED, false),
            'parent_portal_enabled' => $parentPortalEnabled,
            'parent_excuse_letters_enabled' => $parentPortalEnabled
                && static::boolean(static::PARENT_EXCUSE_LETTERS_ENABLED, false),
            'face_recognition_enabled' => static::boolean(static::FACE_RECOGNITION_ENABLED, true),
            'demo_attendance_panel_enabled' => static::boolean(static::DEMO_ATTENDANCE_PANEL_ENABLED, false),
            'online_class_face_recognition_default' => static::boolean(static::ONLINE_CLASS_FACE_RECOGNITION_DEFAULT, true),
            'online_classes_enabled' => static::boolean(static::ONLINE_CLASSES_ENABLED, true),
        ];
    }

    // @function smsProviderSettings: Kinukuha ang sms provider settings result para sa System Setting.
    // @useIn smsProviderSettings: app/Services/SmsService.php
    public static function smsProviderSettings(): array
    {
        $availability = [
            'semaphore' => static::boolean(static::SMS_SEMAPHORE_AVAILABLE, false),
            'iprog' => static::boolean(static::SMS_IPROG_AVAILABLE, false),
        ];
        $savedPrimary = static::string(static::SMS_PRIMARY_PROVIDER, '');
        $primary = in_array($savedPrimary, static::SMS_PROVIDER_NAMES, true)
            && ($availability[$savedPrimary] ?? false)
            ? $savedPrimary
            : collect($availability)
                ->filter()
                ->keys()
                ->first();

        return [
            'providers' => [
                'semaphore' => [
                    'label' => 'Semaphore',
                    'available' => $availability['semaphore'],
                ],
                'iprog' => [
                    'label' => 'IPROG SMS',
                    'available' => $availability['iprog'],
                ],
            ],
            'primary' => $primary,
        ];
    }

    // @function demoAttendancePanelSettings: Kinukuha ang demo attendance panel settings result para sa System Setting.
    // @useIn demoAttendancePanelSettings: app/Http/Controllers/SystemSettingsController.php
    public static function demoAttendancePanelSettings(): array
    {
        $rfids = static::array(static::DEMO_ATTENDANCE_PANEL_RFIDS, static::DEFAULT_DEMO_ATTENDANCE_PANEL_RFIDS);

        return [
            'enabled' => static::boolean(static::DEMO_ATTENDANCE_PANEL_ENABLED, false),
            'rfids' => collect(static::DEFAULT_DEMO_ATTENDANCE_PANEL_RFIDS)
                ->mapWithKeys(fn (string $default, string $key) => [
                    $key => trim((string) ($rfids[$key] ?? $default)),
                ])
                ->all(),
        ];
    }

    // @function boolean: Sinusuri ang boolean condition para sa System Setting.
    // @useIn boolean: SystemSetting::featureFlags (app/Models/SystemSetting.php)
    public static function boolean(string $key, bool $default = false): bool
    {
        if (! Schema::hasTable('system_settings')) {
            return $default;
        }

        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = json_decode((string) $setting->value, true);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    // @function string: Binubuo ang string string para sa System Setting.
    // @useIn string: SystemSetting::smsProviderSettings (app/Models/SystemSetting.php)
    public static function string(string $key, string $default = ''): string
    {
        if (! Schema::hasTable('system_settings')) {
            return $default;
        }

        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = json_decode((string) $setting->value, true);

        return is_string($value) ? $value : $default;
    }

    // @function integer: Kinukuha ang integer result para sa System Setting.
    // @useIn integer: app/Http/Controllers/SystemSettingsController.php
    public static function integer(string $key, int $default = 0): int
    {
        if (! Schema::hasTable('system_settings')) {
            return $default;
        }

        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = json_decode((string) $setting->value, true);

        return is_numeric($value) ? (int) $value : $default;
    }

    // @function array: Kinukuha ang array result para sa System Setting.
    // @useIn array: SystemSetting::demoAttendancePanelSettings (app/Models/SystemSetting.php)
    public static function array(string $key, array $default = []): array
    {
        if (! Schema::hasTable('system_settings')) {
            return $default;
        }

        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = json_decode((string) $setting->value, true);

        return is_array($value) ? $value : $default;
    }

    // @function securityQuestions: Kinukuha ang security questions result para sa System Setting.
    // @useIn securityQuestions: app/Http/Controllers/SystemSettingsController.php
    public static function securityQuestions(): array
    {
        $questions = collect(static::array(static::SECURITY_QUESTIONS, static::DEFAULT_SECURITY_QUESTIONS))
            ->map(fn ($question) => trim((string) $question))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return count($questions) >= 3 ? $questions : static::DEFAULT_SECURITY_QUESTIONS;
    }

    // @function clinicEmergencySoundSettings: Kinukuha ang clinic emergency sound settings result para sa System Setting.
    // @useIn clinicEmergencySoundSettings: app/Http/Controllers/SystemSettingsController.php
    public static function clinicEmergencySoundSettings(): array
    {
        $library = static::array(static::CLINIC_EMERGENCY_SOUND_LIBRARY, []);
        $uploadedSounds = collect($library['sounds'] ?? [])
            ->filter(fn ($sound) => is_array($sound) && ! empty($sound['id']) && ! empty($sound['path']))
            ->map(fn (array $sound) => [
                'id' => (string) $sound['id'],
                'name' => trim((string) ($sound['name'] ?? 'Emergency Sound')),
                'original_name' => trim((string) ($sound['original_name'] ?? '')),
                'path' => (string) $sound['path'],
                'size' => (int) ($sound['size'] ?? 0),
                'uploaded_at' => (string) ($sound['uploaded_at'] ?? ''),
            ])
            ->values()
            ->all();

        $selectedId = (string) ($library['selected_id'] ?? static::DEFAULT_CLINIC_EMERGENCY_SOUND_ID);
        $knownIds = collect($uploadedSounds)
            ->pluck('id')
            ->push(static::DEFAULT_CLINIC_EMERGENCY_SOUND_ID)
            ->all();

        if (! in_array($selectedId, $knownIds, true)) {
            $selectedId = static::DEFAULT_CLINIC_EMERGENCY_SOUND_ID;
        }

        $sounds = collect([
            [
                'id' => static::DEFAULT_CLINIC_EMERGENCY_SOUND_ID,
                'name' => 'Default Emergency Alert',
                'original_name' => 'emergency-alert.mp3',
                'url' => '/sound/emergency-alert.mp3',
                'size' => 0,
                'uploaded_at' => '',
                'is_default' => true,
            ],
        ])
            ->merge(collect($uploadedSounds)->map(fn (array $sound) => [
                ...$sound,
                'url' => route('clinic.emergency-sounds.show', ['id' => $sound['id']]),
                'is_default' => false,
            ]))
            ->values()
            ->all();

        $selected = collect($sounds)->firstWhere('id', $selectedId) ?? $sounds[0];

        return [
            'selected_id' => $selectedId,
            'selected_url' => $selected['url'],
            'sounds' => $sounds,
        ];
    }

    // @function setClinicEmergencySoundLibrary: Sine-set ang clinic emergency sound library sa System Setting flow.
    // @useIn setClinicEmergencySoundLibrary: app/Http/Controllers/SystemSettingsController.php
    public static function setClinicEmergencySoundLibrary(array $sounds, string $selectedId): void
    {
        static::setArray(static::CLINIC_EMERGENCY_SOUND_LIBRARY, [
            'selected_id' => $selectedId,
            'sounds' => array_values($sounds),
        ]);
    }

    // @function setBoolean: Sine-set ang boolean sa System Setting flow.
    // @useIn setBoolean: app/Http/Controllers/SystemSettingsController.php
    public static function setBoolean(string $key, bool $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'type' => 'boolean',
            ],
        );
    }

    // @function setString: Sine-set ang string sa System Setting flow.
    // @useIn setString: app/Http/Controllers/SystemSettingsController.php
    public static function setString(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'type' => 'string',
            ],
        );
    }

    // @function setInteger: Sine-set ang integer sa System Setting flow.
    // @useIn setInteger: app/Http/Controllers/SystemSettingsController.php
    public static function setInteger(string $key, int $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'type' => 'integer',
            ],
        );
    }

    // @function setArray: Sine-set ang array sa System Setting flow.
    // @useIn setArray: SystemSetting::setClinicEmergencySoundLibrary (app/Models/SystemSetting.php)
    public static function setArray(string $key, array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'type' => 'array',
            ],
        );
    }
}
