<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SmsActivityLogger
{
    public static function record(Request $request, string $action, array $details, bool $success = true): void
    {
        try {
            ActivityLog::query()->create([
                'event_id' => (string) Str::uuid(),
                'user_id' => $request->user()?->user_id,
                'user_name' => $request->user()?->name,
                'user_role' => $request->user()?->role,
                'action' => $action,
                'table_name' => str_starts_with($action, 'emergency_text') ? 'emergency_alerts' : 'system_settings',
                'module' => 'sms',
                'outcome' => $success ? 'success' : 'failure',
                'severity' => $success ? 'info' : 'warning',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'route_name' => $request->route()?->getName(),
                'http_method' => $request->method(),
                'description' => json_encode($details, JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable $exception) {
            try {
                report($exception);
            } catch (\Throwable) {
                // An audit outage must not affect delivery.
            }
        }
    }
}
