<?php

$integerInRange = static function (mixed $value, int $default, int $minimum, int $maximum): int {
    if (! is_numeric($value)) {
        return $default;
    }

    $value = (int) $value;

    return $value >= $minimum && $value <= $maximum ? $value : $default;
};

$roles = static fn (mixed $value, array $default): array => array_values(array_unique(array_filter(
    array_map(static fn ($role) => strtolower(trim((string) $role)), explode(',', (string) $value)),
    static fn ($role) => $role !== '',
))) ?: $default;

return [
    'idle_minutes' => $integerInRange(env('IDLE_TIMEOUT_MINUTES', 15), 15, 1, 1440),
    'warning_seconds' => $integerInRange(env('IDLE_WARNING_COUNTDOWN_SECONDS', 30), 30, 5, 300),
    'activity_ping_seconds' => $integerInRange(env('IDLE_ACTIVITY_PING_SECONDS', 60), 60, 10, 300),
    'enabled_roles' => ['student', 'parent', 'admin', 'instructor'],
    'exempt_roles' => $roles(env('IDLE_TIMEOUT_EXEMPT_ROLES', 'clinic,console'), ['clinic', 'console']),
    'background_routes' => [
        'messages.unread-status',
        'messages.poll',
        'student-parent.notifications.poll',
    ],
];
