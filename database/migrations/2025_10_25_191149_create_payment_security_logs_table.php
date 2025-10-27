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
        Schema::create('payment_security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event')->index();
            $table->string('level')->default('info');
            $table->string('ip_address')->index();
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_email')->nullable();
            $table->json('context')->nullable();
            $table->json('headers')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['event', 'ip_address']);
            $table->index(['created_at', 'event']);
            $table->index(['user_id', 'created_at']);
            
            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_security_logs');
    }
};
