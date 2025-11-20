<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Plan;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create tenant first (subscription per tenant, bukan per store)
        $tenant = Tenant::create([
            'name' => 'Demo Business',
            'email' => 'demo@posxpress.com',
            'phone' => '+62812345678',
            'status' => 'active',
            'settings' => [
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
            ],
        ]);

        $this->command->info("✅ Created tenant: {$tenant->name} (ID: {$tenant->id})");

        // Create demo store with tenant_id
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Coffee Shop',
            'email' => 'demo@posxpress.com',
            'phone' => '+62812345678',
            'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
            'code' => 'DEMO-001',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'settings' => [
                'tax_rate' => 10,
                'service_charge_rate' => 5,
                'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
            ],
            'status' => 'active',
        ]);

        $this->command->info("✅ Created store: {$store->name} (ID: {$store->id}, Tenant ID: {$store->tenant_id})");

        // Create subscription for the tenant (bukan store)
        $proPlan = Plan::where('slug', 'pro')->first();
        if ($proPlan) {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id, // Subscription per tenant, bukan store
                'plan_id' => $proPlan->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'amount' => $proPlan->price,
                'metadata' => [
                    'payment_method' => 'bank_transfer',
                    'auto_renew' => true,
                ],
            ]);

            $this->command->info("✅ Created subscription: {$subscription->id} for tenant {$tenant->id} with plan {$proPlan->name}");
        } else {
            $this->command->warn("⚠️ Pro plan not found. Subscription not created.");
        }

        // Store the store ID and tenant ID for use in other seeders
        config(['demo.store_id' => $store->id]);
        config(['demo.tenant_id' => $tenant->id]);
    }
}
