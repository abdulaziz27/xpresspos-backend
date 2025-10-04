<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_performances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->integer('orders_processed')->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->integer('refunds_processed')->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->integer('hours_worked')->default(0);
            $table->decimal('sales_per_hour', 10, 2)->default(0);
            $table->integer('customer_interactions')->default(0);
            $table->decimal('customer_satisfaction_score', 3, 2)->nullable();
            $table->json('additional_metrics')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'user_id', 'date']);
            $table->index(['store_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_performances');
    }
};
