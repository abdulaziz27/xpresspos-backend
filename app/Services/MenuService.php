<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class MenuService
{
    public static function getMenuItemsForRole(): array
    {
        $user = Auth::user();
        
        if (!$user) {
            return [];
        }

        $menuItems = [];

        // System Admin Menu
        if ($user->hasRole('admin_sistem')) {
            $menuItems = [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'icon' => 'heroicon-o-home',
                    'url' => '/system-admin',
                    'active' => request()->is('system-admin'),
                ],
                'stores' => [
                    'label' => 'Stores',
                    'icon' => 'heroicon-o-building-storefront',
                    'url' => '/system-admin/stores',
                    'active' => request()->is('system-admin/stores*'),
                ],
                'subscriptions' => [
                    'label' => 'Subscriptions',
                    'icon' => 'heroicon-o-credit-card',
                    'url' => '/system-admin/subscriptions',
                    'active' => request()->is('system-admin/subscriptions*'),
                ],
                'users' => [
                    'label' => 'All Users',
                    'icon' => 'heroicon-o-users',
                    'url' => '/system-admin/global-users',
                    'active' => request()->is('system-admin/global-users*'),
                ],
            ];
        }
        // Store Owner Menu
        elseif ($user->hasRole('owner')) {
            $menuItems = [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'icon' => 'heroicon-o-home',
                    'url' => '/admin',
                    'active' => request()->is('admin') && !request()->is('admin/*'),
                ],
                'pos' => [
                    'label' => 'Point of Sale',
                    'icon' => 'heroicon-o-calculator',
                    'url' => '/admin/pos-workflow',
                    'active' => request()->is('admin/pos-workflow'),
                ],
                'orders' => [
                    'label' => 'Orders',
                    'icon' => 'heroicon-o-shopping-bag',
                    'url' => '/admin/orders',
                    'active' => request()->is('admin/orders*'),
                ],
                'products' => [
                    'label' => 'Products',
                    'icon' => 'heroicon-o-cube',
                    'url' => '/admin/products',
                    'active' => request()->is('admin/products*'),
                ],
                'staff' => [
                    'label' => 'Staff',
                    'icon' => 'heroicon-o-users',
                    'url' => '/admin/users',
                    'active' => request()->is('admin/users*'),
                ],
                'inventory' => [
                    'label' => 'Inventory',
                    'icon' => 'heroicon-o-archive-box',
                    'url' => '/admin/inventory-dashboard',
                    'active' => request()->is('admin/inventory*'),
                    'visible' => $user->store->subscription->plan->hasFeature('inventory_tracking'),
                ],
                'reports' => [
                    'label' => 'Reports',
                    'icon' => 'heroicon-o-chart-bar',
                    'url' => '/admin/reports',
                    'active' => request()->is('admin/reports*'),
                ],
            ];
        }
        // Manager Menu
        elseif ($user->hasRole('manager')) {
            $menuItems = [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'icon' => 'heroicon-o-home',
                    'url' => '/admin',
                    'active' => request()->is('admin') && !request()->is('admin/*'),
                ],
                'pos' => [
                    'label' => 'Point of Sale',
                    'icon' => 'heroicon-o-calculator',
                    'url' => '/admin/pos-workflow',
                    'active' => request()->is('admin/pos-workflow'),
                ],
                'orders' => [
                    'label' => 'Orders',
                    'icon' => 'heroicon-o-shopping-bag',
                    'url' => '/admin/orders',
                    'active' => request()->is('admin/orders*'),
                ],
                'products' => [
                    'label' => 'Products',
                    'icon' => 'heroicon-o-cube',
                    'url' => '/admin/products',
                    'active' => request()->is('admin/products*'),
                ],
                'inventory' => [
                    'label' => 'Inventory',
                    'icon' => 'heroicon-o-archive-box',
                    'url' => '/admin/inventory-dashboard',
                    'active' => request()->is('admin/inventory*'),
                    'visible' => $user->store->subscription->plan->hasFeature('inventory_tracking'),
                ],
                'reports' => [
                    'label' => 'Reports',
                    'icon' => 'heroicon-o-chart-bar',
                    'url' => '/admin/reports',
                    'active' => request()->is('admin/reports*'),
                ],
            ];
        }
        // Cashier Menu
        elseif ($user->hasRole('cashier')) {
            $menuItems = [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'icon' => 'heroicon-o-home',
                    'url' => '/admin',
                    'active' => request()->is('admin') && !request()->is('admin/*'),
                ],
                'pos' => [
                    'label' => 'Point of Sale',
                    'icon' => 'heroicon-o-calculator',
                    'url' => '/admin/pos-workflow',
                    'active' => request()->is('admin/pos-workflow'),
                ],
                'orders' => [
                    'label' => 'Orders',
                    'icon' => 'heroicon-o-shopping-bag',
                    'url' => '/admin/orders',
                    'active' => request()->is('admin/orders*'),
                ],
                'members' => [
                    'label' => 'Members',
                    'icon' => 'heroicon-o-user-group',
                    'url' => '/admin/members',
                    'active' => request()->is('admin/members*'),
                ],
            ];
        }

        // Filter out invisible items
        return array_filter($menuItems, function ($item) {
            return !isset($item['visible']) || $item['visible'] === true;
        });
    }

    public static function getQuickActionsForRole(): array
    {
        $user = Auth::user();
        
        if (!$user) {
            return [];
        }

        $quickActions = [];

        if ($user->hasRole('cashier')) {
            $quickActions = [
                [
                    'label' => 'New Order',
                    'icon' => 'heroicon-o-plus',
                    'url' => '/admin/pos-workflow',
                    'color' => 'success',
                ],
                [
                    'label' => 'Open Orders',
                    'icon' => 'heroicon-o-clock',
                    'url' => '/admin/orders?status=open',
                    'color' => 'warning',
                ],
            ];
        } elseif ($user->hasAnyRole(['owner', 'manager'])) {
            $quickActions = [
                [
                    'label' => 'New Order',
                    'icon' => 'heroicon-o-plus',
                    'url' => '/admin/pos-workflow',
                    'color' => 'success',
                ],
                [
                    'label' => 'Reports',
                    'icon' => 'heroicon-o-chart-bar',
                    'url' => '/admin/reports',
                    'color' => 'info',
                ],
            ];
        }

        return $quickActions;
    }
}