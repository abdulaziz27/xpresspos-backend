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
            ['email' => 'admin@xpresspos.id'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Assign admin_sistem role (global role without tenant_id)
        // Note: No team context needed for global roles
        $adminSistemRole = \Spatie\Permission\Models\Role::where('name', 'admin_sistem')
            ->whereNull('tenant_id')
            ->first();
        if ($adminSistemRole) {
            // Reset team context for global role
            setPermissionsTeamId(null);
            
            if (!$admin->hasRole($adminSistemRole)) {
                $admin->assignRole($adminSistemRole);
                $this->command->info("✅ Assigned admin_sistem role to {$admin->email}");
            } else {
                $this->command->info("✅ Admin role already assigned for user {$admin->email}");
            }
        } else {
            $this->command->error("❌ Admin_sistem role not found. Please ensure PermissionsAndRolesSeeder ran successfully.");
        }

        // Get primary store ID and tenant ID
        $primaryStoreId = Store::value('id');
        $primaryTenantId = \App\Models\Tenant::value('id');
        
        if (!$primaryStoreId) {
            $this->command->error('No store found! Please run StoreSeeder first.');
            return;
        }

        if (!$primaryTenantId) {
            $this->command->error('No tenant found! Please run StoreSeeder first.');
            return;
        }

        // Create Store Owner user
        $owner = User::firstOrCreate(
            ['email' => 'owner@xpresspos.id'],
            [
                'name' => 'Store Owner',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // CRITICAL: Always update password even for existing users
        // This ensures password is correct regardless of which seeder ran first
        $owner->update(['password' => Hash::make('password123')]);
        $this->command->info("Updated {$owner->email}: password set to 'password123'");

        // CRITICAL: Create user_tenant_access for owner
        $exists = \DB::table('user_tenant_access')
            ->where('user_id', $owner->id)
            ->where('tenant_id', $primaryTenantId)
            ->exists();

        if (!$exists) {
            \DB::table('user_tenant_access')->insert([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'user_id' => $owner->id,
                'tenant_id' => $primaryTenantId,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info("✅ Created user_tenant_access for owner@xpresspos.id → tenant {$primaryTenantId}");
        } else {
            \DB::table('user_tenant_access')
                ->where('user_id', $owner->id)
                ->where('tenant_id', $primaryTenantId)
                ->update([
                    'role' => 'owner',
                    'updated_at' => now(),
                ]);
            $this->command->info("✅ Updated user_tenant_access for owner@xpresspos.id → tenant {$primaryTenantId}");
        }

        // Assign owner role for the specific tenant
        $ownerRole = \Spatie\Permission\Models\Role::where('name', 'owner')
            ->where('tenant_id', $primaryTenantId)
            ->first();
        
        if ($ownerRole) {
            // CRITICAL: Always set team context BEFORE any role operation
            setPermissionsTeamId($primaryTenantId);
            
            // Check if role already assigned (idempotent)
            if (!$owner->hasRole($ownerRole)) {
            // Force remove any existing role assignments for this user in this tenant
            // to ensure clean state
            $owner->roles()->wherePivot('tenant_id', $primaryTenantId)->detach();
            
            // Assign role fresh
            $owner->assignRole($ownerRole);
            
            // Verify assignment was successful
            $owner->refresh(); // Refresh to clear any cache
            setPermissionsTeamId($primaryTenantId); // Set context again after refresh
            
            if ($owner->hasRole('owner')) {
                $this->command->info("✅ Owner role successfully assigned to {$owner->email}");
            } else {
                $this->command->error("❌ Failed to assign owner role to {$owner->email}");
                
                // Debug: Show what roles the user actually has
                $this->command->warn("Current roles for user: " . $owner->getRoleNames()->implode(', '));
                }
            } else {
                $this->command->info("✅ Owner role already assigned to {$owner->email}");
            }
        } else {
            $this->command->error("❌ Owner role not found for tenant {$primaryTenantId}. Please ensure PermissionsAndRolesSeeder ran successfully.");
        }

        // Ensure owner user has store context
        $this->assignUserToStore($owner, $primaryStoreId, 'owner');

        $this->command->info('Filament users created successfully!');
        $this->command->info('Admin: admin@xpresspos.id / password123');
        $this->command->info('Owner: owner@xpresspos.id / password123');
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
