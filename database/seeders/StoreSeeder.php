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
        $tenant = Tenant::firstOrCreate(
            ['email' => 'demo@xpresspos.id'],
            [
                'name' => 'Xpress Business',
                'email' => 'demo@xpresspos.id',
            'phone' => '+62812345678',
            'status' => 'active',
            'settings' => [
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                    'business_type' => 'coffee_shop',
                    'tax_id' => null,
                    'registration_number' => null,
            ],
            ]
        );

        $this->command->info("✅ Tenant ready: {$tenant->name} (ID: {$tenant->id})");

        // Create 3 stores/cabang untuk tenant ini
        $stores = [
            [
                'name' => 'Xpress Store Jakarta',
                'email' => 'jakarta@xpresspos.id',
                'phone' => '+62811111111',
                'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
                'code' => 'XP-JKT-001',
            ],
            [
                'name' => 'Xpress Pos Bandung',
                'email' => 'bandung@xpresspos.id',
                'phone' => '+62812222222',
                'address' => 'Jl. Dago No. 45, Bandung',
                'code' => 'XP-BDG-001',
            ],
            [
                'name' => 'Xpress Pos Purwokerto',
                'email' => 'purwokerto@xpresspos.id',
                'phone' => '+62813333333',
                'address' => 'Jl. Jenderal Sudirman No. 88, Purwokerto',
                'code' => 'XP-PWT-001',
            ],
        ];

        $createdStores = [];
        foreach ($stores as $storeData) {
            $store = Store::firstOrCreate(
                ['code' => $storeData['code']],
                array_merge($storeData, [
            'tenant_id' => $tenant->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'settings' => [
                'tax_rate' => 10,
                'service_charge_rate' => 5,
                        'tax_included' => false,
                        'website_url' => 'https://xpresspos.id',
                        'thank_you_message' => 'Terima kasih atas kunjungan Anda! Sampai jumpa lagi!',
                        'wifi_name' => 'XP-GUEST',
                        'wifi_password' => 'guest123',
                'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
            ],
            'status' => 'active',
                ])
            );

            // Ensure tenant_id is set even for existing stores
            if (!$store->tenant_id) {
                $store->update(['tenant_id' => $tenant->id]);
            }

            $createdStores[] = $store;
            $this->command->info("✅ Store ready: {$store->name} (ID: {$store->id}, Code: {$store->code})");
        }

        // Create subscription untuk tenant ini menggunakan Enterprise plan
        $enterprisePlan = Plan::where('slug', 'enterprise')->first();
        if ($enterprisePlan) {
            $tenant->update([
                'plan_id' => $enterprisePlan->id,
                'status' => 'active',
            ]);

            $subscription = Subscription::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $enterprisePlan->id,
                    'status' => 'active',
                ],
                [
                    'billing_cycle' => 'monthly',
                    'starts_at' => now(),
                    'ends_at' => now()->addMonth(),
                    'trial_ends_at' => now()->addDays((int) config('xendit.subscription.trial_days', 30)),
                    'amount' => $enterprisePlan->price,
                    'metadata' => [
                        'payment_method' => 'bank_transfer',
                        'auto_renew' => true,
                        'source' => 'seeder',
                    ],
                ]
            );

            $this->command->info("✅ Subscription ready for tenant {$tenant->id} with plan {$enterprisePlan->name}");
        } else {
            $this->command->warn("⚠️ Enterprise plan not found. Subscription not created.");
        }

        // Store the first store ID and tenant ID for use in other seeders (backward compatibility)
        $firstStore = $createdStores[0];
        config(['demo.store_id' => $firstStore->id]);
        config(['demo.tenant_id' => $tenant->id]);
        
        $this->command->info("✅ Created {$tenant->name} with " . count($createdStores) . " stores/cabang");
    }
}
