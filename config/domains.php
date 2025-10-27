<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the domain configuration for the XpressPOS application.
    | Different subdomains serve different purposes in the multi-tenant setup.
    |
    */

    // Main landing page domain
    'main' => env('MAIN_DOMAIN', 'xpresspos.id'),
    
    // Owner dashboard subdomain
    'owner' => env('OWNER_DOMAIN', 'dashboard.xpresspos.id'),
    
    // Admin panel subdomain  
    'admin' => env('ADMIN_DOMAIN', 'admin.xpresspos.id'),
    
    // API subdomain
    'api' => env('API_DOMAIN', 'api.xpresspos.id'),
    
    // Development domains (for local testing)
    'local' => [
        'main' => env('LOCAL_MAIN_DOMAIN', 'xpresspos.test'),
        'owner' => env('LOCAL_OWNER_DOMAIN', 'owner.xpresspos.test'),
        'admin' => env('LOCAL_ADMIN_DOMAIN', 'admin.xpresspos.test'),
        'api' => env('LOCAL_API_DOMAIN', 'api.xpresspos.test'),
    ],
];
