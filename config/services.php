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

    'ai' => [
        'base_url' => env('AI_API_BASE_URL', 'http://127.0.0.1:5000/api/v1'),
        'timeout' => env('AI_API_TIMEOUT', 30),
        'retries' => env('AI_API_RETRIES', 2),
        'retry_sleep_ms' => env('AI_API_RETRY_SLEEP_MS', 250),
        'shared_secret' => env('AI_SERVICE_SHARED_SECRET', 'super-secret-key-123!'),
        'mock_enabled' => (bool) env('AI_API_MOCK_ENABLED', true),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'azure' => [
        'storage_name' => env('AZURE_STORAGE_NAME'),
        'storage_container' => env('AZURE_STORAGE_CONTAINER'),
        'storage_key' => env('AZURE_STORAGE_KEY'),
    ],

];
