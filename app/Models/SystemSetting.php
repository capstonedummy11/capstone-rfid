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
    public const PANEL_PIN_HASH = 'panel.pin_hash';
    public const PANEL_DEVICE_LABEL = 'panel.device_label';

    public static function featureFlags(): array
    {
        return [
            'borrowing_enabled' => static::boolean(static::BORROWING_ENABLED, false),
            'inventory_enabled' => static::boolean(static::INVENTORY_ENABLED, false),
            'face_recognition_enabled' => static::boolean(static::FACE_RECOGNITION_ENABLED, true),
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
}
