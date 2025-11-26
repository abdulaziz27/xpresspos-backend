<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Domain A1: Tenancy Foundation
     * Creates the tenants table as the root entity for multi-tenant architecture.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->string('id', 36)->primary()->comment('UUID primary key');
                
                // Plans table is now created earlier (2024_10_02), so we can safely constrain here
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->json('settings')->nullable()->comment('Tenant-specific configuration');
                $table->string('status')->default('active')->comment('active/inactive/suspended');
                $table->timestamps();

                // Indexes for common queries
                $table->index(['status', 'created_at'], 'idx_tenants_status_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
