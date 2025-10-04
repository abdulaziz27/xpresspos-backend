<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo store
        $store = \App\Models\Store::create([
            'name' => 'Demo Coffee Shop',
            'email' => 'demo@posxpress.com',
            'phone' => '+62812345678',
            'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
            'settings' => [
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                'tax_rate' => 10,
                'service_charge_rate' => 5,
                'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
            ],
            'status' => 'active',
        ]);

        // Create subscription for the store
        $proPlan = \App\Models\Plan::where('slug', 'pro')->first();
        if ($proPlan) {
            \App\Models\Subscription::create([
                'store_id' => $store->id,
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
        }

        // Store the store ID for use in other seeders
        config(['demo.store_id' => $store->id]);
    }
}
