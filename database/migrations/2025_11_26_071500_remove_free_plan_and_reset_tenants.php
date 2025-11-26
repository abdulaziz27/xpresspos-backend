<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $freePlanId = DB::table('plans')->where('slug', 'free')->value('id');

        if ($freePlanId) {
            DB::table('tenants')
                ->where('plan_id', $freePlanId)
                ->update([
                    'plan_id' => null,
                    'status' => 'pending_plan',
                ]);

            DB::table('plan_features')->where('plan_id', $freePlanId)->delete();
            DB::table('plans')->where('id', $freePlanId)->delete();
        }
    }

    public function down(): void
    {
        // No-op: Free plan intentionally removed.
    }
};

