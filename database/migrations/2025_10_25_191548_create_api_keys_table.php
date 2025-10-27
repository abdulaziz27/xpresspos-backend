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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index(); // xendit, midtrans, etc.
            $table->string('environment')->default('production'); // production, sandbox
            $table->uuid('store_id')->nullable()->index(); // null for global keys
            $table->text('encrypted_key'); // Encrypted API key
            $table->string('key_hash'); // Hash for integrity verification
            $table->boolean('is_active')->default(true)->index();
            $table->integer('rotation_count')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['provider', 'environment', 'is_active']);
            $table->index(['store_id', 'provider', 'is_active']);
            $table->index(['expires_at', 'is_active']);
            
            // Foreign key constraint
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            
            // Unique constraint to prevent duplicate active keys
            $table->unique(['provider', 'environment', 'store_id', 'is_active'], 'unique_active_api_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
