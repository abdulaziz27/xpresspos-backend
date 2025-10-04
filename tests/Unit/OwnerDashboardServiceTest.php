<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\OwnerDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_revenue_and_order_metrics_for_store_owner(): void
    {
        $store = Store::factory()->create();
        $owner = User::factory()->create([
            'store_id' => $store->id,
        ]);

        $this->actingAs($owner);

        $category = Category::factory()->create([
            'store_id' => $store->id,
            'name' => 'Beverage',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'price' => 50000,
            'status' => true,
            'track_inventory' => false,
            'name' => 'Iced Latte',
            'sku' => 'LATTE-001',
        ]);

        $order = Order::factory()->completed()->create([
            'store_id' => $store->id,
            'total_amount' => 150000,
        ]);

        OrderItem::factory()->forOrder($order)->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 50000,
            'quantity' => 3,
            'total_price' => 150000,
        ]);

        Payment::factory()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'status' => 'completed',
            'amount' => 150000,
            'processed_at' => now(),
            'payment_method' => 'qris',
        ]);

        Member::factory()->create([
            'store_id' => $store->id,
            'is_active' => true,
        ]);

        $service = new OwnerDashboardService();
        $summary = $service->summaryFor($owner);

        $this->assertSame(150000.0, $summary['revenue']['total']);
        $this->assertGreaterThanOrEqual(150000.0, $summary['revenue']['month']);
        $this->assertSame(1, $summary['orders']['total']);
        $this->assertSame(150000.0, $summary['orders']['average_value']);
        $this->assertEquals(1, $summary['customers']['active_members']);
        $this->assertNotEmpty($summary['top_products']);
        $this->assertNotEmpty($summary['trends']['revenue']);
        $this->assertNotEmpty($summary['trends']['orders']);
    }
}
