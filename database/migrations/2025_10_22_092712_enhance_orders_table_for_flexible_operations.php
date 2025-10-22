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
        Schema::table('orders', function (Blueprint $table) {
            // Customer information
            $table->string('customer_name')->nullable()->after('member_id');
            $table->enum('customer_type', ['member', 'guest', 'walk_in'])->default('walk_in')->after('customer_name');
            
            // Operation modes
            $table->enum('operation_mode', ['dine_in', 'takeaway', 'delivery'])->default('dine_in')->after('customer_type');
            $table->enum('payment_mode', ['direct', 'open_bill'])->default('direct')->after('operation_mode');
            
            // Currency support
            $table->string('currency', 3)->default('IDR')->after('total_amount');
            
            // Indexes for better performance
            $table->index(['store_id', 'customer_type']);
            $table->index(['store_id', 'operation_mode']);
            $table->index(['store_id', 'payment_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'payment_mode']);
            $table->dropIndex(['store_id', 'operation_mode']);
            $table->dropIndex(['store_id', 'customer_type']);
            
            $table->dropColumn([
                'customer_name',
                'customer_type', 
                'operation_mode',
                'payment_mode',
                'currency'
            ]);
        });
    }
};
