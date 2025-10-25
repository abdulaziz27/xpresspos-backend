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
        'trial_days' => env('SUBSCRIPTION_TRIAL_DAYS', 14),
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
        'success_redirect_url' => env('APP_URL') . '/subscription/payment/success',
        'failure_redirect_url' => env('APP_URL') . '/subscription/payment/failed',
    ],
];