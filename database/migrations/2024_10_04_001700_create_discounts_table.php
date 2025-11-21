<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 15, 2);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('expired_date')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'store_id']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
