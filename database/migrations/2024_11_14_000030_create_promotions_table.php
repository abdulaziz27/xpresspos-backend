<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates promotions table for flexible promotion system.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36); // NOT NULL - promotion must belong to tenant
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->onDelete('cascade'); // Nullable for tenant-wide promotions
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['AUTOMATIC', 'CODED']);
            $table->string('code')->nullable()->unique();
            $table->boolean('stackable')->default(false);
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->integer('priority')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indexes for active promotions query
            $table->index(['tenant_id', 'status', 'starts_at', 'ends_at'], 'idx_promotions_tenant_status_dates');
            $table->index(['store_id', 'status', 'starts_at', 'ends_at'], 'idx_promotions_store_status_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
