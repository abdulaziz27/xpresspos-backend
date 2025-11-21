<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@xpresspos.id'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('super_admin');

        // Create Admin Sistem
        $adminSistem = User::firstOrCreate(
            ['email' => 'admin.sistem@xpresspos.id'],
            [
                'name' => 'Admin Sistem',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
        $adminSistem->assignRole('admin_sistem');

        // Get first store and tenant for owner assignment
        $firstStore = \App\Models\Store::first();
        $storeId = $firstStore?->id;
        $firstTenant = \App\Models\Tenant::first();
        $tenantId = $firstTenant?->id;

        // Create Owner
        $owner = User::firstOrCreate(
            ['email' => 'owner@xpresspos.id'],
            [
                'name' => 'Store Owner',
                'password' => Hash::make('owner123'),
                'email_verified_at' => now(),
            ]
        );

        // CRITICAL: Create store_user_assignment for owner
        if ($storeId) {
            \App\Models\StoreUserAssignment::updateOrCreate(
                [
                    'store_id' => $storeId,
                    'user_id' => $owner->id,
                ],
                [
                    'assignment_role' => 'owner',
                    'is_primary' => true,
                ]
            );
        }

        // CRITICAL: Create user_tenant_access for owner
        if ($tenantId) {
            $exists = \DB::table('user_tenant_access')
                ->where('user_id', $owner->id)
                ->where('tenant_id', $tenantId)
                ->exists();

            if (!$exists) {
                \DB::table('user_tenant_access')->insert([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'user_id' => $owner->id,
                    'tenant_id' => $tenantId,
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("✅ Created user_tenant_access for owner@xpresspos.id → tenant {$tenantId}");
            } else {
                \DB::table('user_tenant_access')
                    ->where('user_id', $owner->id)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'role' => 'owner',
                        'updated_at' => now(),
                    ]);
                $this->command->info("✅ Updated user_tenant_access for owner@xpresspos.id → tenant {$tenantId}");
            }
        }

        // CRITICAL: Set team context BEFORE assigning role
        if ($storeId && $tenantId) {
            // Find owner role for this specific tenant
            $ownerRole = \Spatie\Permission\Models\Role::where('name', 'owner')
                ->where('tenant_id', $tenantId)
                ->first();
            
            if ($ownerRole) {
                setPermissionsTeamId($tenantId);
                
                // Force remove any existing role assignments for this user in this tenant
                $owner->roles()->wherePivot('tenant_id', $tenantId)->detach();
                
                // Assign role fresh
                $owner->assignRole($ownerRole);
                
                // Verify assignment
                $owner->refresh();
                setPermissionsTeamId($tenantId);
                
                if ($owner->hasRole('owner')) {
                    $this->command->info("✅ Owner role successfully assigned to {$owner->email}");
                } else {
                    $this->command->error("❌ Failed to assign owner role to {$owner->email}");
                }
            } else {
                $this->command->warn("⚠️ Owner role not found for tenant ID: {$tenantId}. Role will be assigned after PermissionsAndRolesSeeder runs.");
                // Fallback: assign role without team context (will be fixed by PermissionsAndRolesSeeder)
                $owner->assignRole('owner');
            }
        } else {
            $this->command->warn("⚠️ No store or tenant found. Owner role assignment skipped. Run StoreSeeder first.");
        }

        // Create Cashier
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@xpresspos.id'],
            [
                'name' => 'Cashier',
                'password' => Hash::make('cashier123'),
                'email_verified_at' => now(),
            ]
        );

        // CRITICAL: Create store_user_assignment for cashier
        // Note: assignment_role uses 'staff' (AssignmentRoleEnum doesn't have 'cashier')
        // but Spatie Permission role is 'cashier' for proper permissions
        if ($storeId) {
            \App\Models\StoreUserAssignment::updateOrCreate(
                [
                    'store_id' => $storeId,
                    'user_id' => $cashier->id,
                ],
                [
                    'assignment_role' => 'staff',
                    'is_primary' => false,
                ]
            );
        }

        // CRITICAL: Create user_tenant_access for cashier (staff role)
        if ($tenantId) {
            $exists = \DB::table('user_tenant_access')
                ->where('user_id', $cashier->id)
                ->where('tenant_id', $tenantId)
                ->exists();

            if (!$exists) {
                \DB::table('user_tenant_access')->insert([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'user_id' => $cashier->id,
                    'tenant_id' => $tenantId,
                    'role' => 'staff',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("✅ Created user_tenant_access for cashier@xpresspos.id → tenant {$tenantId}");
            } else {
                \DB::table('user_tenant_access')
                    ->where('user_id', $cashier->id)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'role' => 'staff',
                        'updated_at' => now(),
                    ]);
                $this->command->info("✅ Updated user_tenant_access for cashier@xpresspos.id → tenant {$tenantId}");
            }
        }

        // CRITICAL: Set team context BEFORE assigning role
        if ($storeId && $tenantId) {
            // Find cashier role for this specific tenant
            $cashierRole = \Spatie\Permission\Models\Role::where('name', 'cashier')
                ->where('tenant_id', $tenantId)
                ->first();
            
            if ($cashierRole) {
                setPermissionsTeamId($tenantId);
                
                // Force remove any existing role assignments for this user in this tenant
                $cashier->roles()->wherePivot('tenant_id', $tenantId)->detach();
                
                // Assign role fresh
                $cashier->assignRole($cashierRole);
                
                // Verify assignment
                $cashier->refresh();
                setPermissionsTeamId($tenantId);
                
                if ($cashier->hasRole('cashier')) {
                    $this->command->info("✅ Cashier role successfully assigned to {$cashier->email}");
                } else {
                    $this->command->error("❌ Failed to assign cashier role to {$cashier->email}");
                }
            } else {
                $this->command->warn("⚠️ Cashier role not found for tenant ID: {$tenantId}. Role will be assigned after PermissionsAndRolesSeeder runs.");
                // Fallback: assign role without team context (will be fixed by PermissionsAndRolesSeeder)
                $cashier->assignRole('cashier');
            }
        } else {
            $this->command->warn("⚠️ No store found. Cashier role assignment skipped. Run StoreSeeder first.");
        }

        $this->command->info('Users created successfully!');
        $this->command->info('Super Admin: admin@xpresspos.id / admin123');
        $this->command->info('Admin Sistem: admin.sistem@xpresspos.id / admin123');
        $this->command->info('Owner: owner@xpresspos.id / owner123');
        $this->command->info('Cashier: cashier@xpresspos.id / cashier123');
    }
}