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
        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation')->index(); // created, updated, deleted, etc.
            $table->string('entity_type')->index(); // subscription_payment, store_payment, api_key, etc.
            $table->unsignedBigInteger('entity_id')->index(); // ID of the entity being audited
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_email')->nullable();
            $table->string('ip_address')->index();
            $table->text('user_agent')->nullable();
            $table->longText('old_data')->nullable(); // JSON of old values
            $table->longText('new_data')->nullable(); // JSON of new values
            $table->longText('changes')->nullable(); // JSON of specific changes
            $table->string('request_id')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->timestamp('created_at')->index();

            // Indexes for performance
            $table->index(['entity_type', 'entity_id']);
            $table->index(['operation', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            
            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
    }
};
