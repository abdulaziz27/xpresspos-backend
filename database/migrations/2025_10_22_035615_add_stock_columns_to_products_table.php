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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock')->default(0)->after('track_inventory');
            $table->integer('min_stock_level')->default(0)->after('stock');
            
            // Add index for low stock queries
            $table->index(['track_inventory', 'stock', 'min_stock_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['track_inventory', 'stock', 'min_stock_level']);
            $table->dropColumn(['stock', 'min_stock_level']);
        });
    }
};
