<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Store;

class PermissionsAndRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Merge permissions from old seeder with new categorized permissions
        $legacyPermissions = [
            // User management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage_roles',

            // Product management (will be merged with new ones)
            'products.manage_categories',

            // Order management (will be merged with new ones)
            'orders.delete',
            'orders.refund',
            'orders.void',

            // Payment management (will be merged with new ones)
            'payments.update',
            'payments.delete',

            // Refund management
            'refunds.view',
            'refunds.create',
            'refunds.update',
            'refunds.delete',

            // Table management (will be merged with new ones)
            'tables.create',
            'tables.delete',

            // Member management (will be merged with new ones)
            'members.delete',

            // Inventory management (will be merged with new ones)
            'inventory.adjust',
            'inventory.transfer',

            // Reports (will be merged with new ones)
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

        // Define all permissions by category (new system)
        $categorizedPermissions = [
            'products' => [
                'products.view' => 'Lihat Produk',
                'products.create' => 'Tambah Produk',
                'products.update' => 'Edit Produk',
                'products.delete' => 'Hapus Produk',
                'products.manage_categories' => 'Kelola Kategori',
            ],
            'orders' => [
                'orders.view' => 'Lihat Pesanan',
                'orders.create' => 'Buat Pesanan',
                'orders.update' => 'Edit Pesanan',
                'orders.delete' => 'Hapus Pesanan',
                'orders.cancel' => 'Batalkan Pesanan',
                'orders.complete' => 'Selesaikan Pesanan',
                'orders.refund' => 'Refund Pesanan',
                'orders.void' => 'Void Pesanan',
            ],
            'inventory' => [
                'inventory.view' => 'Lihat Stok',
                'inventory.update' => 'Update Stok',
                'inventory.adjust' => 'Sesuaikan Stok',
                'inventory.transfer' => 'Transfer Stok',
                'inventory.reports' => 'Laporan Stok',
            ],
            'reports' => [
                'reports.view' => 'Lihat Laporan',
                'reports.sales' => 'Laporan Penjualan',
                'reports.financial' => 'Laporan Keuangan',
                'reports.analytics' => 'Analytics',
                'reports.export' => 'Export Laporan',
                'reports.email' => 'Email Laporan',
            ],
            'staff' => [
                'staff.view' => 'Lihat Staff',
                'staff.create' => 'Tambah Staff',
                'staff.update' => 'Edit Staff',
                'staff.manage' => 'Kelola Staff',
                'staff.permissions' => 'Kelola Permission Staff',
            ],
            'members' => [
                'members.view' => 'Lihat Member',
                'members.create' => 'Tambah Member',
                'members.update' => 'Edit Member',
                'members.delete' => 'Hapus Member',
            ],
            'tables' => [
                'tables.view' => 'Lihat Meja',
                'tables.create' => 'Tambah Meja',
                'tables.update' => 'Update Meja',
                'tables.delete' => 'Hapus Meja',
                'tables.manage' => 'Kelola Meja',
                'tables.occupy' => 'Atur Okupansi Meja',
            ],
            'payments' => [
                'payments.view' => 'Lihat Pembayaran',
                'payments.create' => 'Proses Pembayaran',
                'payments.update' => 'Update Pembayaran',
                'payments.delete' => 'Hapus Pembayaran',
                'payments.refund' => 'Refund',
                'payments.view_history' => 'Lihat Riwayat Pembayaran',
            ],
            'categories' => [
                'categories.view' => 'Lihat Kategori',
                'categories.create' => 'Tambah Kategori',
                'categories.update' => 'Edit Kategori',
                'categories.delete' => 'Hapus Kategori',
            ],
            'discounts' => [
                'discounts.view' => 'Lihat Diskon',
                'discounts.create' => 'Tambah Diskon',
                'discounts.update' => 'Edit Diskon',
                'discounts.delete' => 'Hapus Diskon',
            ],
        ];

        // Create legacy permissions first
        foreach ($legacyPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Create categorized permissions
        foreach ($categorizedPermissions as $category => $categoryPermissions) {
            foreach ($categoryPermissions as $permission => $description) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }
        }

        // Define role permissions (compatible with legacy system)
        $rolePermissions = [
            'admin_sistem' => ['*'], // Global admin with all permissions
            'owner' => ['*'], // All permissions
            'admin' => [
                // Product management
                'products.*',
                // Order management  
                'orders.*',
                // Inventory management
                'inventory.*',
                // Reports
                'reports.*',
                // Staff management
                'staff.view',
                'staff.create', 
                'staff.update',
                // Member management
                'members.*',
                // Table management
                'tables.*',
                // Category management
                'categories.*',
                // Discount management
                'discounts.*',
                // Payment management
                'payments.*',
                // Cash sessions
                'cash_sessions.*',
                // Expenses
                'expenses.*',
                // User management
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'users.manage_roles',
                // Refunds
                'refunds.*',
            ],
            'manager' => [
                // Product management
                'products.view',
                'products.create',
                'products.update',
                'products.manage_categories',
                // Order management
                'orders.view',
                'orders.create',
                'orders.update',
                'orders.complete',
                'orders.refund',
                // Inventory management
                'inventory.view',
                'inventory.update',
                'inventory.adjust',
                'inventory.reports',
                // Reports
                'reports.view',
                'reports.export',
                // Staff management
                'staff.view',
                // Member management
                'members.*',
                // Table management
                'tables.view',
                'tables.update',
                // Category management
                'categories.view',
                // Discount management
                'discounts.view',
                'discounts.create',
                'discounts.update',
                'discounts.delete',
                // Payment management
                'payments.view',
                'payments.create',
                'payments.update',
                // Cash sessions
                'cash_sessions.open',
                'cash_sessions.close',
                'cash_sessions.view',
                // Expenses
                'expenses.view',
                'expenses.create',
                'expenses.update',
                // Refunds
                'refunds.view',
                'refunds.create',
                'refunds.update',
            ],
            'staff' => [
                // Product management
                'products.view',
                // Order management
                'orders.view',
                'orders.create',
                'orders.update',
                // Inventory management
                'inventory.view',
                // Member management
                'members.view',
                'members.create',
                // Table management
                'tables.view',
                'tables.update',
                // Payment management
                'payments.view',
                'payments.create',
                // Cash sessions
                'cash_sessions.open',
                'cash_sessions.close',
                'cash_sessions.view',
                // Expenses
                'expenses.view',
                'expenses.create',
            ],
            'cashier' => [
                // Product management
                'products.view',
                // Order management
                'orders.view',
                'orders.create',
                'orders.update',
                // Payment management
                'payments.view',
                'payments.create',
                // Table management
                'tables.view',
                'tables.update',
                // Member management
                'members.view',
                // Discount management
                'discounts.view',
                // Cash sessions
                'cash_sessions.open',
                'cash_sessions.close',
                'cash_sessions.view',
                // Expenses
                'expenses.view',
                'expenses.create',
            ],
        ];

        // Temporarily disable teams for admin_sistem role creation
        config(['permission.teams' => false]);
        
        // Create global admin_sistem role without store_id
        $adminSistemRole = Role::firstOrCreate([
            'name' => 'admin_sistem',
            'guard_name' => 'web',
        ]);
        $adminSistemRole->syncPermissions(Permission::all());
        
        // Re-enable teams for other roles
        config(['permission.teams' => true]);

        // Create roles and assign permissions for each store
        $stores = Store::all();
        
        foreach ($stores as $store) {
            foreach ($rolePermissions as $roleName => $rolePerms) {
                // Skip admin_sistem as it's already created globally
                if ($roleName === 'admin_sistem') {
                    continue;
                }

                // Create role for this store
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'store_id' => $store->id,
                ]);

                // Assign permissions to role
                $permissionsToAssign = [];
                
                foreach ($rolePerms as $perm) {
                    if ($perm === '*') {
                        // Owner gets all permissions
                        $permissionsToAssign = Permission::all()->pluck('name')->toArray();
                        break;
                    } elseif (str_ends_with($perm, '.*')) {
                        // Wildcard permissions (e.g., 'products.*')
                        $prefix = str_replace('.*', '', $perm);
                        $wildcardPerms = Permission::where('name', 'like', $prefix . '.%')->pluck('name')->toArray();
                        $permissionsToAssign = array_merge($permissionsToAssign, $wildcardPerms);
                    } else {
                        // Specific permission
                        $permissionsToAssign[] = $perm;
                    }
                }

                $role->syncPermissions(array_unique($permissionsToAssign));
            }
        }

        $this->command->info('Permissions and roles created successfully for all stores!');
    }
}