<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'compreface' => [
        'url' => env('COMPREFACE_URL', 'http://localhost:8000'),
        'api_key' => env('COMPREFACE_API_KEY', ''),
    ],

    'aws_rekognition' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'token' => env('AWS_SESSION_TOKEN'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'similarity_threshold' => env('AWS_REKOGNITION_SIMILARITY_THRESHOLD', 90),
        'liveness' => [
            'enabled' => env('AWS_REKOGNITION_LIVENESS_ENABLED', false),
            'region' => env('AWS_REKOGNITION_LIVENESS_REGION', 'us-east-1'),
            'identity_pool_id' => env('VITE_AWS_COGNITO_IDENTITY_POOL_ID'),
            'confidence_threshold' => env('AWS_REKOGNITION_LIVENESS_CONFIDENCE_THRESHOLD', 90),
        ],
    ],

    'semaphore' => [
        'key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME'),
        'endpoint' => env('SEMAPHORE_ENDPOINT', 'https://api.semaphore.co/api/v4/messages'),
        'account_endpoint' => env('SEMAPHORE_ACCOUNT_ENDPOINT', 'https://api.semaphore.co/api/v4/account'),
        'enabled' => env('SEMAPHORE_ENABLED', true),
    ],

    'philsms' => [
        'token' => env('PHILSMS_API_TOKEN'),
        'sender_id' => env('PHILSMS_SENDER_ID', 'PhilSMS'),
        'enabled' => env('PHILSMS_ENABLED', true),
        'endpoint' => env('PHILSMS_ENDPOINT', 'https://dashboard.philsms.com/api/v3/sms/send'),
    ],

    'iprog' => [
        'token' => env('IPROG_SMS_API_TOKEN'),
        'endpoint' => env('IPROG_SMS_ENDPOINT', 'https://www.iprogsms.com/api/v1/sms_messages'),
        'balance_endpoint' => env('IPROG_SMS_BALANCE_ENDPOINT', 'https://www.iprogsms.com/api/v1/account/sms_credits'),
        'enabled' => env('IPROG_SMS_ENABLED', true),
    ],

];
