<?php

return [
    'postmark' => ['key' => env('POSTMARK_API_KEY')],
    'resend' => ['key' => env('RESEND_API_KEY')],
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
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'snap_url' => env(
            'MIDTRANS_SNAP_URL',
            env('MIDTRANS_IS_PRODUCTION', false)
                ? 'https://app.midtrans.com/snap/v1/transactions'
                : 'https://app.sandbox.midtrans.com/snap/v1/transactions'
        ),
    ],
    'cargotrace_geocoding' => [
        'endpoint' => env('CARGOTRACE_GEOCODING_ENDPOINT', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('CARGOTRACE_GEOCODING_USER_AGENT', 'CargoTrace/1.0 (set CARGOTRACE_CONTACT_EMAIL for production use)'),
    ],
];
