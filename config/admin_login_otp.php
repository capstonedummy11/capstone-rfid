<?php

return [
    'expires_minutes' => (int) env('ADMIN_LOGIN_OTP_EXPIRES_MINUTES', 10),
    'resend_seconds' => (int) env('ADMIN_LOGIN_OTP_RESEND_SECONDS', 60),
    'max_attempts' => (int) env('ADMIN_LOGIN_OTP_MAX_ATTEMPTS', 5),
];
