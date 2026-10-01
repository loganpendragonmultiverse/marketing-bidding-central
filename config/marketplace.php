<?php

return [
    'currency' => 'USD',
    'minimum_bid_cents' => (int) env('MARKETPLACE_MINIMUM_BID_CENTS', 500),
    'minimum_top_up_cents' => (int) env('MARKETPLACE_MINIMUM_TOP_UP_CENTS', 100),
    'market_open' => (bool) env('MARKETPLACE_OPEN', false),
    'admin_email' => env('MARKETPLACE_ADMIN_EMAIL'),
    'admin_password_hash' => env('MARKETPLACE_ADMIN_PASSWORD_HASH'),
    'owner_token_hours' => (int) env('MARKETPLACE_OWNER_TOKEN_HOURS', 8760),
    'analytics_salt' => env('MARKETPLACE_ANALYTICS_SALT', env('APP_KEY')),
    'legal_updated_at' => env('MARKETPLACE_LEGAL_UPDATED_AT'),
    'legal_ready' => (bool) env('MARKETPLACE_LEGAL_READY', false),
    'version' => '1.0.0',
    'metadata' => [
        'max_bytes' => 524288,
        'timeout_seconds' => 8,
        'max_redirects' => 3,
    ],
];
