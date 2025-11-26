<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Routing Paths & Domains
    |--------------------------------------------------------------------------
    |
    | Path-based routing is now used for the Filament panels. Keep the URL and
    | path fragments centralized here for consistency. Legacy keys (`owner`,
    | `admin`) remain for backward compatibility but now return full URLs.
    |
    */

    // Main landing domain
    'main' => env('MAIN_DOMAIN', 'xpresspos.id'),

    // Panel URLs (path-based)
    'owner_url' => env('OWNER_URL', '/owner'),
    'admin_url' => env('ADMIN_URL', env('APP_URL') . '/admin'),

    // Backward compatibility: previously used as domains
    'owner' => env('OWNER_URL', env('APP_URL') . '/owner'),
    'admin' => env('ADMIN_URL', env('APP_URL') . '/admin'),

    // Path segments (without leading slash)
    'owner_path' => trim(parse_url(env('OWNER_URL', env('APP_URL') . '/owner'), PHP_URL_PATH) ?: 'owner', '/'),
    'admin_path' => trim(parse_url(env('ADMIN_URL', env('APP_URL') . '/admin'), PHP_URL_PATH) ?: 'admin', '/'),

    // API domain (still subdomain-based)
    'api' => env('API_DOMAIN', 'api.xpresspos.id'),

    // Development domains (for local testing)
    'local' => [
        'main' => env('LOCAL_MAIN_DOMAIN', 'xpresspos.test'),
    ],
];
