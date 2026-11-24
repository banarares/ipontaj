<?php

return [
    'indexable' => env('SEO_INDEXABLE', false),
    'url' => env('SEO_CANONICAL_URL', env('APP_URL', 'https://ipontaj.ro')),
    'google_verification' => env('GOOGLE_SITE_VERIFICATION'),
];
