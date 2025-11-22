<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class NavigationService
{
    public static function getNavigationGroupsForUser(): array
    {
        $user = Auth::user();
        
        if (!$user) {
            return [];
        }

        // System admin gets all groups
        if ($user->hasRole('admin_sistem')) {
            return [
                'SaaS Management',
                'System Monitoring',
                'User Management',
                'Analytics',
            ];
        }

        // Store owner gets full store management
        if ($user->hasRole('owner')) {
            return [
                'Store Management',
                'Sales & Orders',
                'Inventory',
                'Customers',
                'Reports',
                'Settings',
            ];
        }

        // Manager gets operational groups
        if ($user->hasRole('manager')) {
            return [
                'Sales & Orders',
                'Inventory',
                'Customers',
                'Reports',
            ];
        }

        // Cashier gets basic POS groups
        if ($user->hasRole('cashier')) {
            return [
                'Sales & Orders',
                'Customers',
            ];
        }

        return [];
    }

    public static function canAccessResource(string $resource): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        // System admin can access everything
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Define resource permissions
        $resourcePermissions = [
            'UserResource' => ['owner'],
            'ProductResource' => ['owner', 'manager'],
            'CategoryResource' => ['owner', 'manager'],
            'OrderResource' => ['owner', 'manager', 'cashier'],
            'MemberResource' => ['owner', 'manager', 'cashier'],
            'TableResource' => ['owner', 'manager', 'cashier'],
            'CashSessionResource' => ['owner', 'manager', 'cashier'],
            'InventoryResource' => ['owner', 'manager'],
            'ReportResource' => ['owner', 'manager'],
            'LandingSubscriptionResource' => ['admin_sistem'],
            'PlanResource' => ['admin_sistem'],
            'SubscriptionResource' => ['admin_sistem'],
        ];

        $resourceName = class_basename($resource);
        $allowedRoles = $resourcePermissions[$resourceName] ?? [];

        return $user->hasAnyRole($allowedRoles);
    }

    public static function getWidgetsForUser(): array
    {
        $user = Auth::user();
        
        if (!$user) {
            return [];
        }

        // System admin widgets
        if ($user->hasRole('admin_sistem')) {
            return [
                'SaasMetricsWidget',
                'StoreOverviewWidget',
                'SubscriptionStatsWidget',
                'SystemHealthWidget',
            ];
        }

        // Store owner widgets
        if ($user->hasRole('owner')) {
            return [
                'StoreStatsWidget',
                'SalesOverviewWidget',
                'RecentOrdersWidget',
                'TopProductsWidget',
            ];
        }

        // Manager widgets
        if ($user->hasRole('manager')) {
            return [
                'StoreStatsWidget',
                'SalesOverviewWidget',
                'RecentOrdersWidget',
            ];
        }

        // Cashier widgets
        if ($user->hasRole('cashier')) {
            return [
                'StoreStatsWidget',
                'RecentOrdersWidget',
            ];
        }

        return [];
    }
}
