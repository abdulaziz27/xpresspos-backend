<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('old_price', 10, 2);
            $table->decimal('new_price', 10, 2);
            $table->decimal('old_cost_price', 10, 2)->nullable();
            $table->decimal('new_cost_price', 10, 2)->nullable();
            $table->uuid('changed_by');
            $table->string('reason')->nullable();
            $table->timestamp('effective_date');
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
            $table->index('effective_date');
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
    }
};
