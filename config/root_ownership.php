<?php

return [
    'transfer_delay_days' => (int) env('ROOT_TRANSFER_DELAY_DAYS', 14),
    'transfer_cooldown_days' => (int) env('ROOT_TRANSFER_COOLDOWN_DAYS', 14),
    'override_delay_hours' => (int) env('ROOT_OVERRIDE_DELAY_HOURS', 24),
    'override_approval_window_hours' => (int) env('ROOT_OVERRIDE_APPROVAL_WINDOW_HOURS', 72),
    'override_required_approvals' => (int) env('ROOT_OVERRIDE_REQUIRED_APPROVALS', 2),
    'designated_approver_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ROOT_OVERRIDE_APPROVER_EMAILS', '')),
    ))),
    'rate_limit_per_minute' => (int) env('ROOT_OWNERSHIP_RATE_LIMIT', 5),
];
