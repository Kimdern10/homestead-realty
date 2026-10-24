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

    'rentcast' => [
        'key' => env('RENTCAST_API_KEY'),
        'url' => env('RENTCAST_API_URL', 'https://api.rentcast.io/v1'),
        // Homes asked for per request. One request counts against the monthly allowance whatever the number.
        'limit' => (int) env('RENTCAST_LIMIT', 50),
        // How long a ZIP's results are reused before asking RentCast again.
        'cache_hours' => (int) env('RENTCAST_CACHE_HOURS', 72),
        // Stop sending requests after this many in a month (the free plan includes 50).
        'monthly_limit' => (int) env('RENTCAST_MONTHLY_LIMIT', 50),
    ],

];
