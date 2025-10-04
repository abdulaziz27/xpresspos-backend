<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('feature_type');
            $table->integer('current_usage')->default(0);
            $table->integer('annual_quota')->nullable();
            $table->date('subscription_year_start');
            $table->date('subscription_year_end');
            $table->boolean('soft_cap_triggered')->default(false);
            $table->timestamp('soft_cap_triggered_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'feature_type']);
            $table->index('soft_cap_triggered');
            $table->index('subscription_year_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_usage');
    }
};
