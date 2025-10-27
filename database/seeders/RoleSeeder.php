<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $roles = [
            'super-admin',
            'admin_sistem', 
            'owner',
            'cashier',
            'manager'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Create basic permissions
        $permissions = [
            'view-dashboard',
            'manage-products',
            'manage-orders',
            'manage-users',
            'manage-stores',
            'view-reports',
            'manage-settings'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $superAdmin = Role::findByName('super-admin');
        $superAdmin->givePermissionTo(Permission::all());

        $adminSistem = Role::findByName('admin_sistem');
        $adminSistem->givePermissionTo(['view-dashboard', 'manage-users', 'manage-stores', 'view-reports']);

        $owner = Role::findByName('owner');
        $owner->givePermissionTo(['view-dashboard', 'manage-products', 'manage-orders', 'view-reports', 'manage-settings']);

        $this->command->info('Roles and permissions created successfully!');
    }
}