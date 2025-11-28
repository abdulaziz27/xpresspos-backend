<?php

namespace App\Support;

class PlanFeatureResolver
{
    /**
     * Legacy feature name → canonical plan feature code.
     */
    protected const FEATURE_ALIASES = [
        'inventory_tracking' => 'ALLOW_INVENTORY',
        'inventory' => 'ALLOW_INVENTORY',
        'advanced_reports' => 'ALLOW_ADVANCED_REPORTS',
        'report_export' => 'ALLOW_REPORT_EXPORT',
        'monthly_email_reports' => 'ALLOW_MONTHLY_EMAIL_REPORTS',
        'advanced_analytics' => 'ALLOW_AI_ANALYTICS',
        'multi_outlet' => 'ALLOW_MULTI_STORE',
        'multi_store' => 'ALLOW_MULTI_STORE',
        'cogs_calculation' => 'ALLOW_COGS_CALCULATION',
        'api_access' => 'ALLOW_API_ACCESS',
        'table_management' => 'ALLOW_TABLE_MANAGEMENT',
        'payment_gateway' => 'ALLOW_PAYMENT_GATEWAY',
        'loyalty' => 'ALLOW_LOYALTY',
        'member_management' => 'ALLOW_MEMBER_MANAGEMENT',
        'customer_management' => 'ALLOW_CUSTOMER_MANAGEMENT',
        'pos' => 'ALLOW_POS',
        'priority_support' => 'ALLOW_PRIORITY_SUPPORT',
    ];

    /**
     * Legacy limit key → canonical plan feature code.
     */
    protected const LIMIT_ALIASES = [
        'products' => 'MAX_PRODUCTS',
        'product' => 'MAX_PRODUCTS',
        'staff' => 'MAX_STAFF',
        'users' => 'MAX_STAFF',
        'employees' => 'MAX_STAFF',
        'stores' => 'MAX_STORES',
        'outlets' => 'MAX_STORES',
        'branches' => 'MAX_STORES',
        'transactions' => 'MAX_TRANSACTIONS_PER_YEAR',
        'transactions_per_year' => 'MAX_TRANSACTIONS_PER_YEAR',
        'transactions_per_month' => 'MAX_TRANSACTIONS_PER_MONTH',
        'orders_per_month' => 'MAX_ORDERS_PER_MONTH',
    ];

    public static function normalizeFeatureCode(?string $feature): ?string
    {
        if (!$feature) {
            return null;
        }

        $upper = strtoupper($feature);

        if (str_starts_with($upper, 'ALLOW_') || str_starts_with($upper, 'MAX_')) {
            return $upper;
        }

        $lower = strtolower($feature);

        return self::FEATURE_ALIASES[$lower] ?? null;
    }

    public static function normalizeLimitCode(?string $key): ?string
    {
        if (!$key) {
            return null;
        }

        $upper = strtoupper($key);

        if (str_starts_with($upper, 'MAX_')) {
            return $upper;
        }

        $lower = strtolower($key);

        return self::LIMIT_ALIASES[$lower] ?? null;
    }
}

