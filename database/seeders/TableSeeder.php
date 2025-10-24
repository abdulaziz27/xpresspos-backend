<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $storeId = config('demo.store_id') ?? \App\Models\Store::first()->id;

        $tables = [
            // Basic tables
            [
                'store_id' => $storeId,
                'name' => 'Table 1',
                'table_number' => 'T001',
                'capacity' => 2,
                'location' => 'indoor',
                'notes' => 'Window side seating',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Table 2',
                'table_number' => 'T002',
                'capacity' => 4,
                'location' => 'indoor',
                'notes' => 'Center area, good for families',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Table 3',
                'table_number' => 'T003',
                'capacity' => 4,
                'location' => 'indoor',
                'notes' => 'Corner table',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Table 4',
                'table_number' => 'T004',
                'capacity' => 6,
                'location' => 'vip',
                'notes' => 'VIP area with premium seating',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Table 5',
                'table_number' => 'T005',
                'capacity' => 2,
                'location' => 'bar',
                'notes' => 'Bar counter seating',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Table 6',
                'table_number' => 'T006',
                'capacity' => 8,
                'location' => 'vip',
                'notes' => 'Private room for large groups',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Outdoor 1',
                'table_number' => 'OUT01',
                'capacity' => 4,
                'location' => 'terrace',
                'notes' => 'Terrace with city view',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Outdoor 2',
                'table_number' => 'OUT02',
                'capacity' => 6,
                'location' => 'outdoor',
                'notes' => 'Garden seating area',
                'is_active' => true,
                'status' => 'available',
            ],
            // Additional tables from EnhancedTableSeeder
            [
                'store_id' => $storeId,
                'name' => 'Table 7',
                'table_number' => 'T007',
                'capacity' => 2,
                'location' => 'indoor',
                'notes' => 'Near the entrance',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Table 8',
                'table_number' => 'T008',
                'capacity' => 4,
                'location' => 'indoor',
                'notes' => 'Center area, good for families',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Table 9',
                'table_number' => 'T009',
                'capacity' => 6,
                'location' => 'indoor',
                'notes' => 'Large table for groups',
                'is_active' => true,
                'status' => 'occupied',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Table 10',
                'table_number' => 'T010',
                'capacity' => 2,
                'location' => 'indoor',
                'notes' => 'Quiet corner table',
                'is_active' => true,
                'status' => 'reserved',
            ],
            // VIP Section
            [
                'store_id' => $storeId,
                'name' => 'VIP 1',
                'table_number' => 'VIP01',
                'capacity' => 4,
                'location' => 'vip',
                'notes' => 'Premium seating with privacy',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'VIP 2',
                'table_number' => 'VIP02',
                'capacity' => 6,
                'location' => 'vip',
                'notes' => 'Large VIP table with sofa seating',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'VIP 3',
                'table_number' => 'VIP03',
                'capacity' => 8,
                'location' => 'vip',
                'notes' => 'Executive meeting table',
                'is_active' => true,
                'status' => 'maintenance',
            ],
            // Bar Area
            [
                'store_id' => $storeId,
                'name' => 'Bar 1',
                'table_number' => 'BAR01',
                'capacity' => 2,
                'location' => 'bar',
                'notes' => 'High table with bar stools',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Bar 2',
                'table_number' => 'BAR02',
                'capacity' => 2,
                'location' => 'bar',
                'notes' => 'Counter seating',
                'is_active' => true,
                'status' => 'occupied',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Bar 3',
                'table_number' => 'BAR03',
                'capacity' => 3,
                'location' => 'bar',
                'notes' => 'Corner bar table',
                'is_active' => true,
                'status' => 'available',
            ],
            // Outdoor/Terrace
            [
                'store_id' => $storeId,
                'name' => 'Terrace 1',
                'table_number' => 'TER01',
                'capacity' => 4,
                'location' => 'terrace',
                'notes' => 'Beautiful city view',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Terrace 2',
                'table_number' => 'TER02',
                'capacity' => 6,
                'location' => 'terrace',
                'notes' => 'Perfect for sunset dining',
                'is_active' => true,
                'status' => 'maintenance',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Garden 1',
                'table_number' => 'GAR01',
                'capacity' => 4,
                'location' => 'outdoor',
                'notes' => 'Surrounded by plants',
                'is_active' => true,
                'status' => 'available',
            ],
            [
                'store_id' => $storeId,
                'name' => 'Garden 2',
                'table_number' => 'GAR02',
                'capacity' => 8,
                'location' => 'outdoor',
                'notes' => 'Large outdoor table for events',
                'is_active' => true,
                'status' => 'reserved',
            ],
            // Some inactive tables for testing
            [
                'store_id' => $storeId,
                'name' => 'Storage Table',
                'table_number' => 'STO01',
                'capacity' => 4,
                'location' => 'other',
                'notes' => 'Currently in storage',
                'is_active' => false,
                'status' => 'maintenance',
            ],
        ];

        foreach ($tables as $table) {
            Table::withoutGlobalScopes()->updateOrCreate(
                [
                    'store_id' => $table['store_id'],
                    'table_number' => $table['table_number']
                ],
                $table
            );
        }

        $this->command->info('Created ' . count($tables) . ' tables (basic + enhanced)');
    }
}