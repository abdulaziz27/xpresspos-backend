<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('gateway');
            $table->string('gateway_id');
            $table->enum('type', ['card', 'bank_account', 'bank_transfer', 'digital_wallet', 'va', 'qris', 'other'])->default('card');
            $table->string('last_four', 4)->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->index(['gateway', 'gateway_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
