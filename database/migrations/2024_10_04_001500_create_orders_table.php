<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->enum('customer_type', ['member', 'guest', 'walk_in'])->default('walk_in');
            $table->enum('operation_mode', ['dine_in', 'takeaway', 'delivery'])->default('dine_in');
            $table->enum('payment_mode', ['direct', 'open_bill'])->default('direct');
            $table->foreignUuid('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->enum('status', ['draft', 'open', 'completed', 'cancelled'])->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'created_at']);
            $table->index('status');
            $table->index(['store_id', 'customer_type']);
            $table->index(['store_id', 'operation_mode']);
            $table->index(['store_id', 'payment_mode']);
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->foreign('current_order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropForeign(['current_order_id']);
        });

        Schema::dropIfExists('orders');
    }
};
