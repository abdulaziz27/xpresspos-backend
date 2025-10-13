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

            // Reports
            'reports.view',
            'reports.export',
            'reports.email',

            // Cash sessions
            'cash_sessions.open',
            'cash_sessions.close',
            'cash_sessions.view',
            'cash_sessions.manage',

            // Expense management
            'expenses.view',
            'expenses.create',
            'expenses.update',
            'expenses.delete',

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
            'reports.view', 'reports.export', 'reports.email',
            'cash_sessions.open', 'cash_sessions.close', 'cash_sessions.view', 'cash_sessions.manage',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
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
            'reports.view', 'reports.export',
            'cash_sessions.open', 'cash_sessions.close', 'cash_sessions.view',
            'expenses.view', 'expenses.create', 'expenses.update',
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
            'cash_sessions.open', 'cash_sessions.close', 'cash_sessions.view',
            'expenses.view', 'expenses.create',
        ]);
    }
}
