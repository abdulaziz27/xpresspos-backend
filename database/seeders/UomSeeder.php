<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $uoms = [
            ['code' => 'kg', 'name' => 'Kilogram', 'description' => 'Unit of mass'],
            ['code' => 'g', 'name' => 'Gram', 'description' => 'Unit of mass'],
            ['code' => 'mg', 'name' => 'Milligram', 'description' => 'Unit of mass'],
            ['code' => 'l', 'name' => 'Liter', 'description' => 'Unit of volume'],
            ['code' => 'ml', 'name' => 'Milliliter', 'description' => 'Unit of volume'],
            ['code' => 'pcs', 'name' => 'Pieces', 'description' => 'Unit of count'],
            ['code' => 'dozen', 'name' => 'Dozen', 'description' => 'Unit of count (12 pieces)'],
            ['code' => 'box', 'name' => 'Box', 'description' => 'Unit of packaging'],
            ['code' => 'carton', 'name' => 'Carton', 'description' => 'Unit of packaging'],
        ];

        foreach ($uoms as $uom) {
            $existing = DB::table('uoms')->where('code', $uom['code'])->first();
            
            if (!$existing) {
                DB::table('uoms')->insert([
                    'id' => (string) Str::uuid(),
                    'code' => $uom['code'],
                    'name' => $uom['name'],
                    'description' => $uom['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('UOMs seeded successfully.');
    }
}
