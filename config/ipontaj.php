<?php

return [
    'billing_enabled' => env('BILLING_ENABLED', false),
    'self_hosted_seat_limit' => (int) env('SELF_HOSTED_SEAT_LIMIT', 15),
    'demo' => [
        'session_minutes' => (int) env('DEMO_SESSION_MINUTES', 60),
        'max_workspaces' => (int) env('DEMO_MAX_WORKSPACES', 20),
    ],
    'plans' => ['free' => ['seats' => 5, 'price' => 0], 'team' => ['seats' => 15, 'price' => 49]],
    'stripe_team_price' => env('STRIPE_TEAM_PRICE'),
    'stripe_portal_configuration' => env('STRIPE_PORTAL_CONFIGURATION'),
];
