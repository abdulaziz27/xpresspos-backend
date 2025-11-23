<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UomConversionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get UOM IDs
        $kg = DB::table('uoms')->where('code', 'kg')->first();
        $g = DB::table('uoms')->where('code', 'g')->first();
        $mg = DB::table('uoms')->where('code', 'mg')->first();
        $l = DB::table('uoms')->where('code', 'l')->first();
        $ml = DB::table('uoms')->where('code', 'ml')->first();
        $dozen = DB::table('uoms')->where('code', 'dozen')->first();
        $pcs = DB::table('uoms')->where('code', 'pcs')->first();

        $conversions = [];

        // Mass conversions
        if ($kg && $g) {
            $conversions[] = [
                'from_uom_id' => $kg->id,
                'to_uom_id' => $g->id,
                'multiplier' => 1000, // 1 kg = 1000 g
            ];
        }

        if ($g && $mg) {
            $conversions[] = [
                'from_uom_id' => $g->id,
                'to_uom_id' => $mg->id,
                'multiplier' => 1000, // 1 g = 1000 mg
            ];
        }

        if ($kg && $mg) {
            $conversions[] = [
                'from_uom_id' => $kg->id,
                'to_uom_id' => $mg->id,
                'multiplier' => 1000000, // 1 kg = 1,000,000 mg
            ];
        }

        // Volume conversions
        if ($l && $ml) {
            $conversions[] = [
                'from_uom_id' => $l->id,
                'to_uom_id' => $ml->id,
                'multiplier' => 1000, // 1 L = 1000 mL
            ];
        }

        // Count conversions
        if ($dozen && $pcs) {
            $conversions[] = [
                'from_uom_id' => $dozen->id,
                'to_uom_id' => $pcs->id,
                'multiplier' => 12, // 1 dozen = 12 pcs
            ];
        }

        foreach ($conversions as $conversion) {
            $existing = DB::table('uom_conversions')
                ->where('from_uom_id', $conversion['from_uom_id'])
                ->where('to_uom_id', $conversion['to_uom_id'])
                ->first();
            
            if (!$existing) {
                DB::table('uom_conversions')->insert([
                    'from_uom_id' => $conversion['from_uom_id'],
                    'to_uom_id' => $conversion['to_uom_id'],
                    'multiplier' => $conversion['multiplier'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('UOM conversions seeded successfully.');
    }
}
