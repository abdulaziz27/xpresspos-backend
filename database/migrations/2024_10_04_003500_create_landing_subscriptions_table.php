<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('preferred_contact_method')->nullable();
            $table->text('notes')->nullable();
            $table->json('follow_up_logs')->nullable();
            $table->string('plan')->nullable();
            $table->string('status')->default('pending');
            $table->string('stage')->default('new');
            $table->json('meta')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('provisioned_store_id')->nullable();
            $table->unsignedBigInteger('provisioned_user_id')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->string('onboarding_url')->nullable();
            $table->timestamps();

            $table->foreign('provisioned_store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('provisioned_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('email');
            $table->index('status');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::table('landing_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropForeign(['provisioned_store_id']);
            $table->dropForeign(['provisioned_user_id']);
        });

        Schema::dropIfExists('landing_subscriptions');
    }
};
