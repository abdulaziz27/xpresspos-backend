<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FilamentUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure dependencies exist first
        $this->ensureDependencies();
        
        // Create System Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@xpresspos.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Assign admin_sistem role (global role without store_id)
        // Note: No team context needed for global roles
        $adminSistemRole = \Spatie\Permission\Models\Role::where('name', 'admin_sistem')
            ->whereNull('store_id')
            ->first();
        if ($adminSistemRole) {
            if (!$admin->hasRole($adminSistemRole)) {
                $admin->assignRole($adminSistemRole);
                $this->command->info("✅ Assigned admin_sistem role to {$admin->email}");
            } else {
                $this->command->info("✅ Admin role already assigned for user {$admin->email}");
            }
        } else {
            $this->command->error("❌ Admin_sistem role not found. Please ensure PermissionsAndRolesSeeder ran successfully.");
        }

        // Get primary store ID
        $primaryStoreId = Store::value('id');
        if (!$primaryStoreId) {
            $this->command->error('No store found! Please run StoreSeeder first.');
            return;
        }

        // Create Store Owner user
        $owner = User::firstOrCreate(
            ['email' => 'owner@xpresspos.com'],
            [
                'name' => 'Store Owner',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'store_id' => $primaryStoreId,
            ]
        );

        // Assign owner role for the specific store
        $ownerRole = \Spatie\Permission\Models\Role::where('name', 'owner')
            ->where('store_id', $primaryStoreId)
            ->first();
        
        if ($ownerRole) {
            // CRITICAL: Set team context BEFORE checking hasRole()
            // This ensures hasRole() check uses correct team context
            setPermissionsTeamId($primaryStoreId);
            
            // Check if role already assigned with proper team context
            if (!$owner->hasRole($ownerRole)) {
                // Assign role with team context already set
                $owner->assignRole($ownerRole);
                
                // Verify assignment was successful
                $owner->refresh(); // Refresh to clear any cache
                setPermissionsTeamId($primaryStoreId);
                
                if (!$owner->hasRole('owner')) {
                    $this->command->warn("⚠️  Warning: Role assignment may have failed. User ID: {$owner->id}, Store ID: {$primaryStoreId}");
                }
            } else {
                $this->command->info("✅ Owner role already assigned for user {$owner->email}");
            }
        } else {
            $this->command->error("❌ Owner role not found for store {$primaryStoreId}. Please ensure PermissionsAndRolesSeeder ran successfully.");
        }

        // Ensure owner user has store context
        $this->assignUserToStore($owner, $primaryStoreId, 'owner');

        $this->command->info('Filament users created successfully!');
        $this->command->info('Admin: admin@xpresspos.com / password123');
        $this->command->info('Owner: owner@xpresspos.com / password123');
    }

    private function assignUserToStore(User $user, string $storeId, string $role): void
    {
        StoreUserAssignment::updateOrCreate(
            [
                'store_id' => $storeId,
                'user_id' => $user->id,
            ],
            [
                'assignment_role' => $role,
                'is_primary' => true,
            ]
        );
    }
    
    private function ensureDependencies(): void
    {
        // Check if we have stores
        if (!Store::exists()) {
            $this->command->info('No stores found. Creating default store...');
            $this->call(StoreSeeder::class);
        }
        
        // Check if we have roles and permissions
        $hasRoles = \Spatie\Permission\Models\Role::exists();
        $hasPermissions = \Spatie\Permission\Models\Permission::exists();
        
        if (!$hasRoles || !$hasPermissions) {
            $this->command->info('Roles and permissions not found. Creating them...');
            $this->call(PermissionsAndRolesSeeder::class);
        }
        
        $this->command->info('✅ All dependencies are ready!');
    }
}
