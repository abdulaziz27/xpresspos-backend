<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('landing_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('landing_subscriptions', 'business_name')) {
                $table->string('business_name')->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('landing_subscriptions', 'business_type')) {
                $table->string('business_type')->nullable()->after('business_name');
            }
            
            if (!Schema::hasColumn('landing_subscriptions', 'plan_id')) {
                $table->string('plan_id')->nullable()->after('plan');
            }
            
            if (!Schema::hasColumn('landing_subscriptions', 'billing_cycle')) {
                $table->string('billing_cycle')->nullable()->after('plan_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['business_name', 'business_type', 'plan_id', 'billing_cycle']);
        });
    }
};