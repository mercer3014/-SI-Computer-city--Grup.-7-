<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'brevo' => [
        'key' => env('BREVO_KEY'),
    ],

    'stripe' => [
        'public' => env('STRIPE_PUBLIC_KEY'),
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'bob'),
    ],

    // CU22: asistente virtual con la API de Grok (xAI). La clave solo se lee de XAI_API_KEY.
    'xai' => [
        'key' => env('XAI_API_KEY'),
        'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
        'model' => env('XAI_MODEL') ?: 'grok-4',
        'timeout' => (int) env('XAI_TIMEOUT', 20),
        'simulado' => (bool) env('ASISTENTE_SIMULADO', false),
        'limite_por_minuto' => (int) env('ASISTENTE_LIMITE_POR_MINUTO', 10),
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

];
