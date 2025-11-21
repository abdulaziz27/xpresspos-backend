<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('member_number');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->integer('loyalty_points')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->integer('visit_count')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->foreignUuid('tier_id')->nullable()->constrained('member_tiers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'member_number']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'store_id']);
            $table->index('loyalty_points');
            $table->index('tier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
