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
        if (!Schema::hasTable('uoms')) {
            Schema::create('uoms', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->string('description', 255)->nullable();
                $table->timestamps();
                
                // Indexes
                $table->index('code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uoms');
    }
};
