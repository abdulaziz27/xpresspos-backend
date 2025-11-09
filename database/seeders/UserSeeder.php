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

        // Get first store for owner assignment
        $firstStore = \App\Models\Store::first();
        $storeId = $firstStore?->id;

        // Create Owner
        $owner = User::firstOrCreate(
            ['email' => 'owner@xpresspos.id'],
            [
                'name' => 'Store Owner',
                'password' => Hash::make('owner123'),
                'email_verified_at' => now(),
                'store_id' => $storeId, // Set store_id if store exists
            ]
        );

        // CRITICAL: Always ensure store_id is set, even for existing users
        if (!$owner->store_id && $storeId) {
            $owner->store_id = $storeId;
            $owner->save();
        }

        // CRITICAL: Set team context BEFORE assigning role
        if ($storeId) {
            // Find owner role for this specific store
            $ownerRole = \Spatie\Permission\Models\Role::where('name', 'owner')
                ->where('store_id', $storeId)
                ->first();
            
            if ($ownerRole) {
                setPermissionsTeamId($storeId);
                
                // Force remove any existing role assignments for this user in this store
                $owner->roles()->wherePivot('store_id', $storeId)->detach();
                
                // Assign role fresh
                $owner->assignRole($ownerRole);
                
                // Verify assignment
                $owner->refresh();
                setPermissionsTeamId($storeId);
                
                if ($owner->hasRole('owner')) {
                    $this->command->info("✅ Owner role successfully assigned to {$owner->email}");
                } else {
                    $this->command->error("❌ Failed to assign owner role to {$owner->email}");
                }
            } else {
                $this->command->warn("⚠️ Owner role not found for store ID: {$storeId}. Role will be assigned after PermissionsAndRolesSeeder runs.");
                // Fallback: assign role without team context (will be fixed by PermissionsAndRolesSeeder)
                $owner->assignRole('owner');
            }
        } else {
            $this->command->warn("⚠️ No store found. Owner role assignment skipped. Run StoreSeeder first.");
        }

        // Create Cashier
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@xpresspos.id'],
            [
                'name' => 'Cashier',
                'password' => Hash::make('cashier123'),
                'email_verified_at' => now(),
                'store_id' => $storeId, // Set store_id if store exists
            ]
        );

        // CRITICAL: Always ensure store_id is set, even for existing users
        if (!$cashier->store_id && $storeId) {
            $cashier->store_id = $storeId;
            $cashier->save();
        }

        // CRITICAL: Set team context BEFORE assigning role
        if ($storeId) {
            // Find cashier role for this specific store
            $cashierRole = \Spatie\Permission\Models\Role::where('name', 'cashier')
                ->where('store_id', $storeId)
                ->first();
            
            if ($cashierRole) {
                setPermissionsTeamId($storeId);
                
                // Force remove any existing role assignments for this user in this store
                $cashier->roles()->wherePivot('store_id', $storeId)->detach();
                
                // Assign role fresh
                $cashier->assignRole($cashierRole);
                
                // Verify assignment
                $cashier->refresh();
                setPermissionsTeamId($storeId);
                
                if ($cashier->hasRole('cashier')) {
                    $this->command->info("✅ Cashier role successfully assigned to {$cashier->email}");
                } else {
                    $this->command->error("❌ Failed to assign cashier role to {$cashier->email}");
                }
            } else {
                $this->command->warn("⚠️ Cashier role not found for store ID: {$storeId}. Role will be assigned after PermissionsAndRolesSeeder runs.");
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