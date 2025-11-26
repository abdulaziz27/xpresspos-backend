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
        Schema::table('add_on_payments', function (Blueprint $table) {
            $table->string('invoice_url')->nullable()->after('external_id');
            $table->timestamp('last_reminder_sent_at')->nullable()->after('expires_at');
            $table->unsignedInteger('reminder_count')->default(0)->after('last_reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('add_on_payments', function (Blueprint $table) {
            $table->dropColumn(['invoice_url', 'last_reminder_sent_at', 'reminder_count']);
        });
    }
};
