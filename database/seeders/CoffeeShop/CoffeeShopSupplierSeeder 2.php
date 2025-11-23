<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Store;

class CoffeeShopSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic suppliers for coffee shop.
     */
    public function run(): void
    {
        $store = Store::first();
        if (!$store) {
            $this->command->error('No store found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $store->tenant_id;

        $suppliers = [
            [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => 'PT Kopi Nusantara',
                'email' => 'order@kopinusantara.com',
                'phone' => '+62811111111',
                'address' => 'Jl. Raya Bogor Km 30, Cibinong, Bogor',
                'tax_id' => '01.234.567.8-901.000',
                'bank_account' => 'BCA 1234567890',
                'status' => 'active',
                'metadata' => [
                    'contact_person' => 'Budi Santoso',
                    'payment_terms' => 'Net 30',
                    'note' => 'Supplier utama untuk coffee beans',
                ],
            ],
            [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => 'CV Susu Segar',
                'email' => 'sales@sususegar.co.id',
                'phone' => '+62812222222',
                'address' => 'Jl. Raya Puncak, Cisarua, Bogor',
                'tax_id' => '02.345.678.9-012.000',
                'bank_account' => 'Mandiri 0987654321',
                'status' => 'active',
                'metadata' => [
                    'contact_person' => 'Siti Nurhaliza',
                    'payment_terms' => 'Cash on Delivery',
                    'note' => 'Supplier untuk dairy products',
                ],
            ],
            [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => 'PT Gula Sejahtera',
                'email' => 'info@gulasejahtera.com',
                'phone' => '+62813333333',
                'address' => 'Jl. Gatot Subroto No. 123, Jakarta Selatan',
                'tax_id' => '03.456.789.0-123.000',
                'bank_account' => 'BNI 5555555555',
                'status' => 'active',
                'metadata' => [
                    'contact_person' => 'Ahmad Yani',
                    'payment_terms' => 'Net 14',
                    'note' => 'Supplier untuk sweeteners dan syrups',
                ],
            ],
            [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => 'Tea House Import',
                'email' => 'order@teahouseimport.com',
                'phone' => '+62814444444',
                'address' => 'Jl. Kemang Raya No. 45, Jakarta Selatan',
                'tax_id' => '04.567.890.1-234.000',
                'bank_account' => 'BRI 7777777777',
                'status' => 'active',
                'metadata' => [
                    'contact_person' => 'Lisa Sari',
                    'payment_terms' => 'Net 30',
                    'note' => 'Supplier untuk tea leaves',
                ],
            ],
            [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => 'Kemasan Nusantara',
                'email' => 'sales@kemasannusantara.co.id',
                'phone' => '+62815555555',
                'address' => 'Jl. Industri No. 88, Tangerang',
                'tax_id' => '05.678.901.2-345.000',
                'bank_account' => 'CIMB 9999999999',
                'status' => 'active',
                'metadata' => [
                    'contact_person' => 'Dedi Kurniawan',
                    'payment_terms' => 'Net 30',
                    'note' => 'Supplier untuk packaging materials',
                ],
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => $supplierData['name'],
                ],
                $supplierData
            );
        }

        $this->command->info('✅ Coffee shop suppliers created successfully!');
    }
}

