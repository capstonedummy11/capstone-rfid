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

    public const FACE_RECOGNITION_ENABLED = 'feature.face_recognition_enabled';

    public const ONLINE_CLASS_FACE_RECOGNITION_DEFAULT = 'online_class.face_recognition_enabled_by_default';

    public const PANEL_PIN_HASH = 'panel.pin_hash';

    public const PANEL_DEVICE_LABEL = 'panel.device_label';

    public const ATTENDANCE_ABSENT_DEFAULT_DAYS = 'attendance.absent_default_days';

    public const SECURITY_QUESTIONS = 'auth.security_questions';

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

    public static function featureFlags(): array
    {
        return [
            'borrowing_enabled' => static::boolean(static::BORROWING_ENABLED, false),
            'inventory_enabled' => static::boolean(static::INVENTORY_ENABLED, false),
            'face_recognition_enabled' => static::boolean(static::FACE_RECOGNITION_ENABLED, true),
            'online_class_face_recognition_default' => static::boolean(static::ONLINE_CLASS_FACE_RECOGNITION_DEFAULT, true),
        ];
    }

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

    public static function setArray(string $key, array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => json_encode(array_values($value)),
                'type' => 'array',
            ],
        );
    }
}
