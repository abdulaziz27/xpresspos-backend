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
        // NOTE: Super Admin dan Admin Sistem hanya untuk tim developer XpressPos
        // Tidak dibuat di seeder demo untuk calon subscribers

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

        // CRITICAL: Create store_user_assignment for owner to ALL stores
        $stores = \App\Models\Store::where('tenant_id', $tenantId)->get();
        if ($stores->isNotEmpty()) {
            foreach ($stores as $index => $store) {
            \App\Models\StoreUserAssignment::updateOrCreate(
                [
                        'store_id' => $store->id,
                    'user_id' => $owner->id,
                ],
                [
                    'assignment_role' => 'owner',
                        'is_primary' => $index === 0, // First store is primary
                ]
            );
            }
            $this->command->info("✅ Owner assigned to " . $stores->count() . " stores");
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

        // Create Cashiers - dibagi ke beberapa stores (tidak semua)
        $stores = \App\Models\Store::where('tenant_id', $tenantId)->get();
        
        if ($stores->isNotEmpty()) {
            // Buat beberapa cashier dan distribusikan ke stores
            $cashiers = [
                [
                    'email' => 'cashier1@xpresspos.id',
                    'name' => 'Cashier Jakarta',
                    'password' => 'cashier123',
                ],
                [
                    'email' => 'cashier2@xpresspos.id',
                    'name' => 'Cashier Bandung',
                    'password' => 'cashier123',
                ],
                [
                    'email' => 'cashier3@xpresspos.id',
                    'name' => 'Cashier Purwokerto',
                    'password' => 'cashier123',
                ],
            ];
            
            foreach ($cashiers as $index => $cashierData) {
                if ($index >= $stores->count()) {
                    break; // Jangan buat lebih banyak cashier daripada stores
                }
                
        $cashier = User::firstOrCreate(
                    ['email' => $cashierData['email']],
            [
                        'name' => $cashierData['name'],
                        'password' => Hash::make($cashierData['password']),
                'email_verified_at' => now(),
            ]
        );

                // Assign cashier ke store yang sesuai (1 cashier per store)
                $store = $stores[$index];
                
            \App\Models\StoreUserAssignment::updateOrCreate(
                [
                        'store_id' => $store->id,
                    'user_id' => $cashier->id,
                ],
                [
                    'assignment_role' => 'staff',
                    'is_primary' => false,
                ]
            );

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
                        $this->command->info("✅ Created user_tenant_access for {$cashierData['email']} → tenant {$tenantId}");
            }
        }

        // CRITICAL: Set team context BEFORE assigning role
                if ($tenantId) {
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
                            $this->command->info("✅ Cashier role successfully assigned to {$cashierData['email']}");
                        }
                } else {
                        $this->command->warn("⚠️ Cashier role not found for tenant ID: {$tenantId}.");
                        $cashier->assignRole('cashier');
                    }
                }
            }
        }

        $this->command->info('Users created successfully!');
        $this->command->info('Owner: owner@xpresspos.id / owner123');
        $this->command->info('Cashier Jakarta: cashier1@xpresspos.id / cashier123');
        $this->command->info('Cashier Bandung: cashier2@xpresspos.id / cashier123');
        $this->command->info('Cashier Purwokerto: cashier3@xpresspos.id / cashier123');
    }
}