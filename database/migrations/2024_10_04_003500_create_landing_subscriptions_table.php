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
            
            // Authenticated checkout fields (untuk flow wajib login)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tenant_id', 36)->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            
            // Legacy fields (untuk backward compatibility dengan flow anonymous)
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('preferred_contact_method')->nullable();
            $table->text('notes')->nullable();
            $table->json('follow_up_logs')->nullable();
            $table->string('plan')->nullable(); // Legacy string plan name
            
            // Plan & billing
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('billing_cycle')->nullable(); // monthly/annual
            
            // Status & stage tracking
            $table->string('status')->default('pending');
            $table->string('stage')->default('new');
            
            // Payment tracking (denormalized dari subscription_payments)
            $table->string('xendit_invoice_id')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'expired', 'failed'])->default('pending')->nullable();
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Links ke entity yang dibuat setelah provisioning
            $table->uuid('subscription_id')->nullable();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            
            // Metadata & tracking
            $table->json('meta')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('provisioned_store_id')->nullable();
            $table->unsignedBigInteger('provisioned_user_id')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->string('onboarding_url')->nullable();
            
            // Business info (optional)
            $table->string('business_name')->nullable();
            $table->string('business_type')->nullable();
            
            // Upgrade/Downgrade tracking
            $table->boolean('is_upgrade')->default(false);
            $table->boolean('is_downgrade')->default(false);
            $table->foreignId('previous_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            
            $table->timestamps();

            $table->foreign('provisioned_store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('provisioned_user_id')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index('email');
            $table->index('status');
            $table->index('stage');
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'plan_id']);
            $table->index('xendit_invoice_id');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('landing_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropForeign(['provisioned_store_id']);
            $table->dropForeign(['provisioned_user_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['plan_id']);
            $table->dropForeign(['subscription_id']);
            $table->dropForeign(['previous_plan_id']);
        });

        Schema::dropIfExists('landing_subscriptions');
    }
};
