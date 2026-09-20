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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        // Comma-separated fallback chain, tried in order. Empty = AiService::DEFAULT_MODELS.
        'models' => env('GROQ_MODELS'),
    ],

    'python' => [
        'path' => env('PYTHON_PATH', 'python'),
    ],

    // Handed to every Python subprocess (vnai reads it from the ENVIRONMENT, not from .env): without it the
    // scripts run as an anonymous "Guest" = 20 vnstock requests per minute, shared by the whole app.
    'vnstock' => [
        'api_key' => env('VNSTOCK_API_KEY'),
    ],

];
