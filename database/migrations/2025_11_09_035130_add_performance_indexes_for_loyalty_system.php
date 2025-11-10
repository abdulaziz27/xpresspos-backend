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
        // Orders table - optimize queries for member orders and status filtering
        Schema::table('orders', function (Blueprint $table) {
            // For queries: WHERE member_id = ? AND status = ?
            $table->index(['member_id', 'status'], 'idx_orders_member_status');
            
            // For queries: WHERE store_id = ? AND status = ? AND created_at
            // Used in reporting and analytics
            $table->index(['store_id', 'status', 'created_at'], 'idx_orders_store_status_date');
            
            // For queries: WHERE member_id = ? ORDER BY created_at DESC
            $table->index(['member_id', 'created_at'], 'idx_orders_member_date');
        });

        // Members table - optimize tier lookups and store filtering
        Schema::table('members', function (Blueprint $table) {
            // For queries: WHERE store_id = ? AND tier_id = ?
            $table->index(['store_id', 'tier_id'], 'idx_members_store_tier');
            
            // For queries: WHERE store_id = ? AND is_active = ?
            $table->index(['store_id', 'is_active'], 'idx_members_store_active');
            
            // For sorting and filtering by loyalty points
            $table->index(['loyalty_points'], 'idx_members_loyalty_points');
        });

        // Loyalty Point Transactions - optimize history queries
        Schema::table('loyalty_point_transactions', function (Blueprint $table) {
            // For queries: WHERE member_id = ? ORDER BY created_at DESC
            $table->index(['member_id', 'created_at'], 'idx_loyalty_member_date');
            
            // For queries: WHERE member_id = ? AND type = ?
            $table->index(['member_id', 'type'], 'idx_loyalty_member_type');
            
            // For queries: WHERE store_id = ? AND created_at >= ?
            // Used in reporting
            $table->index(['store_id', 'created_at'], 'idx_loyalty_store_date');
            
            // For queries: WHERE order_id = ? (lookup transactions by order)
            $table->index(['order_id'], 'idx_loyalty_order');
        });

        // Member Tiers - optimize tier boundary lookups
        Schema::table('member_tiers', function (Blueprint $table) {
            // For queries: WHERE store_id = ? AND is_active = ? ORDER BY min_points
            $table->index(['store_id', 'is_active', 'min_points'], 'idx_tiers_store_active_points');
            
            // For queries: WHERE store_id = ? AND min_points <= ? AND max_points >= ?
            $table->index(['store_id', 'min_points', 'max_points'], 'idx_tiers_boundaries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_member_status');
            $table->dropIndex('idx_orders_store_status_date');
            $table->dropIndex('idx_orders_member_date');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('idx_members_store_tier');
            $table->dropIndex('idx_members_store_active');
            $table->dropIndex('idx_members_loyalty_points');
        });

        Schema::table('loyalty_point_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_loyalty_member_date');
            $table->dropIndex('idx_loyalty_member_type');
            $table->dropIndex('idx_loyalty_store_date');
            $table->dropIndex('idx_loyalty_order');
        });

        Schema::table('member_tiers', function (Blueprint $table) {
            $table->dropIndex('idx_tiers_store_active_points');
            $table->dropIndex('idx_tiers_boundaries');
        });
    }
};
