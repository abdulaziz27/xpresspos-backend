<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('midtrans_customer_id')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('store_id');
            $table->index('midtrans_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
