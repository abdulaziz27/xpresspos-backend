<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage_roles',

            // Product management
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.manage_categories',

            // Discount management
            'discounts.view',
            'discounts.create',
            'discounts.update',
            'discounts.delete',

            // Order management
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
            'orders.refund',
            'orders.void',

            // Payment management
            'payments.view',
            'payments.create',
            'payments.update',
            'payments.delete',

            // Refund management
            'refunds.view',
            'refunds.create',
            'refunds.update',
            'refunds.delete',

            // Table management
            'tables.view',
            'tables.create',
            'tables.update',
            'tables.delete',

            // Member management
            'members.view',
            'members.create',
            'members.update',
            'members.delete',

            // Inventory management
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.reports',
            'inventory_items.view',
            'inventory_items.create',
            'inventory_items.update',
            'inventory_items.delete',
            'inventory_adjustments.view',
            'inventory_adjustments.create',
            'inventory_adjustments.update',
            'inventory_adjustments.delete',
            'inventory_transfers.view',
            'inventory_transfers.create',
            'inventory_transfers.update',
            'inventory_transfers.delete',
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.update',
            'purchase_orders.delete',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.delete',

            // Reports
            'reports.view',
            'reports.export',
            'reports.email',

            // Cash sessions
            'cash_sessions.open',
            'cash_sessions.close',
            'cash_sessions.view',
            'cash_sessions.create',
            'cash_sessions.update',
            'cash_sessions.delete',
            'cash_sessions.manage',

            // Expense management
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete',

            // Recipes
            'recipes.view',
            'recipes.create',
            'recipes.update',
            'recipes.delete',

            // Vouchers
            'vouchers.view',
            'vouchers.create',
            'vouchers.update',
            'vouchers.delete',

            // Promotions
            'promotions.view',
            'promotions.create',
            'promotions.update',
            'promotions.delete',

            // Stores
            'stores.view',
            'stores.create',
            'stores.update',
            'stores.delete',

            // Subscription management (system admin only)
            'subscription.view',
            'subscription.manage',

            // System management (system admin only)
            'system.backup',
            'system.maintenance',
            'system.logs',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // System Admin - Global access to everything
        $adminSistem = Role::create(['name' => 'admin_sistem']);
        $adminSistem->givePermissionTo(Permission::all());

        // Store Owner - Full access to their store
        $owner = Role::create(['name' => 'owner']);
        $owner->givePermissionTo([
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.manage_roles',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.manage_categories',
            'discounts.view', 'discounts.create', 'discounts.update', 'discounts.delete',
            'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'orders.refund', 'orders.void',
            'payments.view', 'payments.create', 'payments.update', 'payments.delete',
            'refunds.view', 'refunds.create', 'refunds.update', 'refunds.delete',
            'tables.view', 'tables.create', 'tables.update', 'tables.delete',
            'members.view', 'members.create', 'members.update', 'members.delete',
            'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.reports',
            'inventory_items.view', 'inventory_items.create', 'inventory_items.update', 'inventory_items.delete',
            'inventory_adjustments.view', 'inventory_adjustments.create', 'inventory_adjustments.update', 'inventory_adjustments.delete',
            'inventory_transfers.view', 'inventory_transfers.create', 'inventory_transfers.update', 'inventory_transfers.delete',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'reports.view', 'reports.export', 'reports.email',
            'cash_sessions.open', 'cash_sessions.close', 'cash_sessions.view', 'cash_sessions.create', 'cash_sessions.update', 'cash_sessions.delete', 'cash_sessions.manage',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
            'recipes.view', 'recipes.create', 'recipes.update', 'recipes.delete',
            'vouchers.view', 'vouchers.create', 'vouchers.update', 'vouchers.delete',
            'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete',
            'stores.view', 'stores.create', 'stores.update', 'stores.delete',
            'subscription.view',
        ]);

        // Manager - Operational management
        $manager = Role::create(['name' => 'manager']);
        $manager->givePermissionTo([
            'products.view', 'products.create', 'products.update', 'products.manage_categories',
            'discounts.view', 'discounts.create', 'discounts.update', 'discounts.delete',
            'orders.view', 'orders.create', 'orders.update', 'orders.refund',
            'payments.view', 'payments.create', 'payments.update',
            'refunds.view', 'refunds.create', 'refunds.update',
            'tables.view', 'tables.update',
            'members.view', 'members.create', 'members.update',
            'inventory.view', 'inventory.adjust', 'inventory.reports',
            'inventory_items.view', 'inventory_items.create', 'inventory_items.update',
            'inventory_adjustments.view', 'inventory_adjustments.create', 'inventory_adjustments.update',
            'inventory_transfers.view', 'inventory_transfers.create', 'inventory_transfers.update',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update',
            'suppliers.view', 'suppliers.create', 'suppliers.update',
            'reports.view', 'reports.export',
            'cash_sessions.open', 'cash_sessions.close', 'cash_sessions.view', 'cash_sessions.create', 'cash_sessions.update',
            'expenses.view', 'expenses.create', 'expenses.update',
            'recipes.view', 'recipes.create', 'recipes.update',
            'vouchers.view', 'vouchers.create', 'vouchers.update',
            'promotions.view', 'promotions.create', 'promotions.update',
            'stores.view',
        ]);

        // Cashier - POS operations only
        $cashier = Role::create(['name' => 'cashier']);
        $cashier->givePermissionTo([
            'products.view',
            'discounts.view',
            'orders.view', 'orders.create', 'orders.update',
            'payments.view', 'payments.create',
            'tables.view', 'tables.update',
            'members.view',
            'cash_sessions.open', 'cash_sessions.close', 'cash_sessions.view', 'cash_sessions.create',
            'expenses.view', 'expenses.create',
            'vouchers.view',
            'promotions.view',
        ]);
    }
}
