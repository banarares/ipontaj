<?php

$proxyList = env('CLOUDFLARE_TRUSTED_PROXIES', '173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,141.101.64.0/18,108.162.192.0/18,190.93.240.0/20,188.114.96.0/20,197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,104.24.0.0/14,172.64.0.0/13,131.0.72.0/22,2400:cb00::/32,2606:4700::/32,2803:f800::/32,2405:b500::/32,2405:8100::/32,2a06:98c0::/29,2c0f:f248::/32');
$appUrl = env('APP_URL', 'http://localhost');
$hostname = is_string($appUrl) ? (parse_url($appUrl, PHP_URL_HOST) ?: 'localhost') : 'localhost';
$hostnames = env('TURNSTILE_HOSTNAMES', $hostname);

return [
    'enabled' => env('CLOUDFLARE_ENABLED', false),
    // Official ranges checked on 2026-11-24: https://www.cloudflare.com/ips/
    'trusted_proxies' => is_string($proxyList) ? array_filter(array_map('trim', explode(',', $proxyList))) : [],
    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'hostnames' => is_string($hostnames) ? array_filter(array_map('trim', explode(',', $hostnames))) : [],
    ],
    'analytics_token' => env('CLOUDFLARE_WEB_ANALYTICS_TOKEN'),
];
