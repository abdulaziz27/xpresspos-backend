<?php

namespace Database\Seeders;

use App\Models\AddOn;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AddOnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $addOns = [
            [
                'code' => 'ADDON_TRANSACTIONS',
                'name' => 'Tambahan Transaksi',
                'description' => 'Tambahan limit transaksi per bulan',
                'feature_code' => 'MAX_TRANSACTIONS_PER_MONTH',
                'quantity' => 1000, // +1000 transaksi per bulan
                'price_monthly' => 50000, // Rp 50.000/bulan
                'price_annual' => 500000, // Rp 500.000/tahun (2 bulan gratis)
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'ADDON_STAFF',
                'name' => 'Tambahan Staff',
                'description' => 'Tambahan limit jumlah staff',
                'feature_code' => 'MAX_STAFF',
                'quantity' => 5, // +5 staff
                'price_monthly' => 25000, // Rp 25.000/bulan
                'price_annual' => 250000, // Rp 250.000/tahun (2 bulan gratis)
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'ADDON_STORES',
                'name' => 'Tambahan Toko',
                'description' => 'Tambahan limit jumlah toko/cabang',
                'feature_code' => 'MAX_STORES',
                'quantity' => 1, // +1 toko
                'price_monthly' => 100000, // Rp 100.000/bulan
                'price_annual' => 1000000, // Rp 1.000.000/tahun (2 bulan gratis)
                'is_active' => true,
                'sort_order' => 30,
            ],
        ];

        foreach ($addOns as $addOn) {
            AddOn::updateOrCreate(
                ['code' => $addOn['code']],
                $addOn
            );
        }

        $this->command->info('Add-ons seeded successfully!');
    }
}
