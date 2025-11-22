<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationProvisioningService
{
    /**
     * Auto-provision tenant, store, and access for a newly registered user.
     * This is idempotent: if user already has a tenant, do nothing.
     */
    public function provisionFor(User $user): void
    {
        // Guard: if user already has a tenant, skip provisioning
        if ($user->tenants()->exists()) {
            return;
        }

        DB::transaction(function () use ($user) {
            // 1. Create default tenant
            $tenant = Tenant::create([
                'id'     => (string) Str::uuid(),
                'name'   => $user->name . "'s Business",
                'email'  => $user->email,
                'phone'  => null,
                'status' => 'active',
            ]);

            // 2. Create default store
            $store = Store::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name'      => 'Main Store',
                'code'      => Str::upper(Str::random(8)),
                'email'     => $user->email,
                'phone'     => null,
                'address'   => null,
                'city'      => null,
                'country'   => 'ID',
                'timezone'  => 'Asia/Jakarta',
                'currency'  => 'IDR',
                'status'    => 'active',
            ]);

            // 3. Create user_tenant_access (owner role)
            DB::table('user_tenant_access')->insert([
                'id'         => (string) Str::uuid(),
                'user_id'    => $user->id,
                'tenant_id'  => $tenant->id,
                'role'       => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4. Create store_user_assignment (owner + primary)
            StoreUserAssignment::create([
                'id'              => (string) Str::uuid(),
                'store_id'        => $store->id,
                'user_id'         => $user->id,
                'assignment_role' => 'owner',
                'is_primary'      => true,
            ]);

        // 5. Update user's store_id for legacy compatibility
        $user->update(['store_id' => $store->id]);

        // 6. Mark email as verified for auto-provisioned users
        // Note: In production, you might want to implement proper email verification
        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }
        });
    }
}

