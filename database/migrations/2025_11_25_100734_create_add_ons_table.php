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
        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // 'ADDON_TRANSACTIONS', 'ADDON_STAFF', 'ADDON_STORES'
            $table->string('name'); // 'Tambahan Transaksi', 'Tambahan Staff', 'Tambahan Toko'
            $table->text('description')->nullable();
            $table->string('feature_code'); // 'MAX_TRANSACTIONS_PER_MONTH', 'MAX_STAFF', 'MAX_STORES'
            $table->integer('quantity'); // Jumlah limit yang ditambahkan (misal: 1000 transaksi, 5 staff, 1 toko)
            $table->decimal('price_monthly', 12, 2); // Harga per bulan
            $table->decimal('price_annual', 12, 2); // Harga per tahun (biasanya lebih murah)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('code');
            $table->index('feature_code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('add_ons');
    }
};
