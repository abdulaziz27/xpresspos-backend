<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename table for better naming consistency
        Schema::rename('product_options', 'product_variants');
    }

    public function down(): void
    {
        Schema::rename('product_variants', 'product_options');
    }
};