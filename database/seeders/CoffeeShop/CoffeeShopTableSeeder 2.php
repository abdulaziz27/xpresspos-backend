<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Table;
use App\Models\Store;

class CoffeeShopTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic tables for coffee shop dine-in.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;
        $stores = Store::where('tenant_id', $tenantId)->get();

        if ($stores->isEmpty()) {
            $this->command->error('No stores found. Make sure StoreSeeder runs first.');
            return;
        }

        // Base table configuration (will be replicated per store)
        $baseTables = [
            // Indoor tables
            [
                'tenant_id' => $tenantId,
                'table_number' => '01',
                'name' => 'Table 1',
                'capacity' => 2,
                'status' => 'available',
                'location' => 'Indoor - Near Window',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '02',
                'name' => 'Table 2',
                'capacity' => 2,
                'status' => 'available',
                'location' => 'Indoor - Near Window',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '03',
                'name' => 'Table 3',
                'capacity' => 4,
                'status' => 'available',
                'location' => 'Indoor - Center',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '04',
                'name' => 'Table 4',
                'capacity' => 4,
                'status' => 'available',
                'location' => 'Indoor - Center',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '05',
                'name' => 'Table 5',
                'capacity' => 4,
                'status' => 'available',
                'location' => 'Indoor - Center',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '06',
                'name' => 'Table 6',
                'capacity' => 6,
                'status' => 'available',
                'location' => 'Indoor - Corner',
                'is_active' => true,
            ],
            
            // Outdoor tables
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '07',
                'name' => 'Table 7',
                'capacity' => 2,
                'status' => 'available',
                'location' => 'Outdoor - Patio',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '08',
                'name' => 'Table 8',
                'capacity' => 2,
                'status' => 'available',
                'location' => 'Outdoor - Patio',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '09',
                'name' => 'Table 9',
                'capacity' => 4,
                'status' => 'available',
                'location' => 'Outdoor - Patio',
                'is_active' => true,
            ],
            
            // Bar seating
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '10',
                'name' => 'Bar Seat 1',
                'capacity' => 1,
                'status' => 'available',
                'location' => 'Bar Counter',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '11',
                'name' => 'Bar Seat 2',
                'capacity' => 1,
                'status' => 'available',
                'location' => 'Bar Counter',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenantId,
                
                'table_number' => '12',
                'name' => 'Bar Seat 3',
                'capacity' => 1,
                'status' => 'available',
                'location' => 'Bar Counter',
                'is_active' => true,
            ],
        ];

        // Create tables for each store
        foreach ($stores as $store) {
            foreach ($baseTables as $tableData) {
                Table::query()->withoutGlobalScopes()->firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'table_number' => $tableData['table_number'],
                    ],
                    array_merge($tableData, [
                        'tenant_id' => $tenantId,
                        'store_id' => $store->id,
                    ])
                );
            }
        }

        $this->command->info("✅ Coffee shop tables created successfully for {$stores->count()} stores!");
    }
}

