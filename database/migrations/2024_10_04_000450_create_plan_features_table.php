<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Domain A3: Plan Features Normalization
     * Replaces JSON features column with queryable table structure.
     * Feature codes: MAX_BRANCH, MAX_PRODUCTS, ALLOW_LOYALTY, etc.
     */
    public function up(): void
    {
        if (!Schema::hasTable('plan_features')) {
            Schema::create('plan_features', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('plan_id');
                $table->string('feature_code')->comment('e.g., MAX_BRANCH, MAX_PRODUCTS, ALLOW_LOYALTY');
                $table->string('limit_value')->nullable()->comment('Numeric/string value; NULL = unlimited');
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();

                // Foreign key
                $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');

                // Unique constraint: one feature per plan
                $table->unique(['plan_id', 'feature_code'], 'uk_plan_features_plan_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
