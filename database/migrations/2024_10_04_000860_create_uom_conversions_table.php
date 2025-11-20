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
        if (!Schema::hasTable('uom_conversions')) {
            Schema::create('uom_conversions', function (Blueprint $table) {
                $table->id();
                $table->string('from_uom_id', 36);
                $table->string('to_uom_id', 36);
                $table->decimal('multiplier', 18, 6);
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('from_uom_id')->references('id')->on('uoms')->onDelete('cascade');
                $table->foreign('to_uom_id')->references('id')->on('uoms')->onDelete('cascade');
                
                // Unique constraint to prevent duplicate conversions
                $table->unique(['from_uom_id', 'to_uom_id'], 'uk_uom_conv_from_to');
                
                // Indexes
                $table->index('from_uom_id');
                $table->index('to_uom_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uom_conversions');
    }
};
