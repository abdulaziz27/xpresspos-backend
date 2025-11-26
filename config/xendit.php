<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Xendit Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Xendit payment gateway integration
    |
    */

    'api_key' => env('XENDIT_API_KEY'),
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
    'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    'is_production' => env('XENDIT_IS_PRODUCTION', false),
    
    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    */
    
    'currency' => env('PAYMENT_CURRENCY', 'IDR'),
    'invoice_expiry_hours' => env('PAYMENT_EXPIRY_HOURS', 24),
    'grace_period_days' => env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 3),
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    
    'webhook_endpoints' => [
        'invoice' => env('APP_URL') . '/api/webhooks/xendit/invoice',
        'recurring' => env('APP_URL') . '/api/webhooks/xendit/recurring',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    */
    
    'payment_methods' => [
        'bank_transfer' => [
            'BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'BSI'
        ],
        'e_wallet' => [
            'OVO', 'DANA', 'LINKAJA', 'SHOPEEPAY'
        ],
        'retail_outlet' => [
            'ALFAMART', 'INDOMARET'
        ],
        'qr_code' => [
            'QRIS'
        ],
        'credit_card' => [
            'VISA', 'MASTERCARD', 'JCB', 'AMEX'
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Subscription Configuration
    |--------------------------------------------------------------------------
    */
    
    'subscription' => [
        'trial_days' => env('SUBSCRIPTION_TRIAL_DAYS', 30),
        'reminder_days' => env('SUBSCRIPTION_REMINDER_DAYS', 7),
        'retry_attempts' => env('SUBSCRIPTION_RETRY_ATTEMPTS', 3),
        'retry_interval_hours' => env('SUBSCRIPTION_RETRY_INTERVAL_HOURS', 24),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Invoice Configuration
    |--------------------------------------------------------------------------
    */
    
    'invoice' => [
        'prefix' => env('INVOICE_PREFIX', 'XPOS'),
        'success_redirect_url' => env('APP_URL') . '/payment/success',
        'failure_redirect_url' => env('APP_URL') . '/payment/failed',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Add-on Billing Configuration
    |--------------------------------------------------------------------------
    */
    
    'addon' => [
        'reminder_hours' => env('ADDON_INVOICE_REMINDER_HOURS', 48),
        'reminder_cooldown_hours' => env('ADDON_INVOICE_REMINDER_COOLDOWN_HOURS', 12),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    
    'security' => [
        // Rate limiting for webhook endpoints
        'rate_limit' => [
            'max_attempts' => env('XENDIT_WEBHOOK_RATE_LIMIT', 60), // requests per minute
            'decay_minutes' => env('XENDIT_WEBHOOK_RATE_DECAY', 1),
        ],
        
        // IP whitelist for webhook endpoints (empty array allows all IPs)
        'ip_whitelist' => array_filter(explode(',', env('XENDIT_WEBHOOK_IP_WHITELIST', ''))),
        
        // Maximum payload size for webhooks (in bytes)
        'max_payload_size' => env('XENDIT_MAX_PAYLOAD_SIZE', 1024 * 1024), // 1MB
        
        // Security alert thresholds
        'alert_thresholds' => [
            'invalid_signature' => [
                'max_events' => 5,
                'time_window' => 300, // 5 minutes
            ],
            'rate_limit_exceeded' => [
                'max_events' => 3,
                'time_window' => 600, // 10 minutes
            ],
            'ip_not_whitelisted' => [
                'max_events' => 10,
                'time_window' => 3600, // 1 hour
            ],
            'replay_attack_detected' => [
                'max_events' => 3,
                'time_window' => 300, // 5 minutes
            ],
        ],
        
        // Administrator notification settings
        'admin_email' => env('XENDIT_SECURITY_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),
        'notification_channels' => ['email', 'log'], // Available: email, log, slack
        
        // Automatic IP blocking settings
        'auto_block' => [
            'enabled' => env('XENDIT_AUTO_BLOCK_ENABLED', true),
            'threshold_events' => 10, // Block after this many security events
            'threshold_window' => 3600, // Within this time window (seconds)
            'block_duration' => 3600, // Block for this duration (seconds)
        ],
        
        // Encryption settings for sensitive data
        'encryption' => [
            'algorithm' => 'AES-256-CBC',
            'key_rotation_days' => env('XENDIT_KEY_ROTATION_DAYS', 90),
        ],
    ],
];