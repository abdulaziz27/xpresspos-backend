<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('receipt_number')->nullable();
            $table->string('vendor')->nullable();
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'category']);
            $table->index(['store_id', 'expense_date']);
            $table->index('cash_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
