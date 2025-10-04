<?php

namespace Tests\Unit\Unit;

use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected User $user;
    protected Store $store;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);
        
        // Create test data
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        
        $category = Category::factory()->create(['store_id' => $this->store->id]);
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'track_inventory' => true,
            'min_stock_level' => 10,
        ]);

        // Authenticate user
        $this->actingAs($this->user);
    }

    public function test_can_adjust_stock_increase()
    {
        $result = $this->inventoryService->adjustStock(
            $this->product->id,
            50,
            'Initial stock',
            10.00,
            'Setting up initial inventory'
        );

        $this->assertArrayHasKey('movement', $result);
        $this->assertArrayHasKey('stock_level', $result);
        
        $this->assertEquals(50, $result['stock_level']->current_stock);
        $this->assertEquals(50, $result['stock_level']->available_stock);
        $this->assertEquals(10.00, $result['stock_level']->average_cost);
        
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => 'adjustment_in',
            'quantity' => 50,
        ]);
    }

    public function test_can_adjust_stock_decrease()
    {
        // First add some stock
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'current_stock' => 100,
            'available_stock' => 100,
            'average_cost' => 10.00,
        ]);

        $result = $this->inventoryService->adjustStock(
            $this->product->id,
            -20,
            'Damaged goods',
            null,
            'Items damaged during transport'
        );

        $this->assertEquals(80, $result['stock_level']->current_stock);
        $this->assertEquals(80, $result['stock_level']->available_stock);
        
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => 'adjustment_out',
            'quantity' => 20,
        ]);
    }

    public function test_can_process_sale()
    {
        // Set up initial stock
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'current_stock' => 100,
            'available_stock' => 100,
            'average_cost' => 10.00,
        ]);

        $result = $this->inventoryService->processSale(
            $this->product->id,
            5,
            null // Don't use order ID in unit test
        );

        $this->assertArrayHasKey('movement', $result);
        $this->assertArrayHasKey('stock_level', $result);
        $this->assertArrayHasKey('cogs', $result);
        
        $this->assertEquals(95, $result['stock_level']->current_stock);
        $this->assertEquals(95, $result['stock_level']->available_stock);
        
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => 'sale',
            'quantity' => 5,
        ]);
        
        $this->assertDatabaseHas('cogs_history', [
            'product_id' => $this->product->id,
            'quantity_sold' => 5,
        ]);
    }

    public function test_cannot_sell_more_than_available_stock()
    {
        // Set up limited stock
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'current_stock' => 5,
            'available_stock' => 5,
            'average_cost' => 10.00,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->inventoryService->processSale($this->product->id, 10);
    }

    public function test_can_process_purchase()
    {
        $result = $this->inventoryService->processPurchase(
            $this->product->id,
            100,
            12.00,
            'po-456',
            'Purchase order from supplier'
        );

        $this->assertArrayHasKey('movement', $result);
        $this->assertArrayHasKey('stock_level', $result);
        
        $this->assertEquals(100, $result['stock_level']->current_stock);
        $this->assertEquals(100, $result['stock_level']->available_stock);
        $this->assertEquals(12.00, $result['stock_level']->average_cost);
        
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'unit_cost' => 12.00,
        ]);
    }

    public function test_can_reserve_and_release_stock()
    {
        // Set up initial stock
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'current_stock' => 100,
            'available_stock' => 100,
            'average_cost' => 10.00,
        ]);

        $items = [
            ['product_id' => $this->product->id, 'quantity' => 10]
        ];

        // Test reservation
        $result = $this->inventoryService->reserveStock($items);
        
        $this->assertEmpty($result['errors']);
        $this->assertCount(1, $result['reservations']);
        
        $stockLevel = StockLevel::where('product_id', $this->product->id)->first();
        $this->assertEquals(100, $stockLevel->current_stock);
        $this->assertEquals(90, $stockLevel->available_stock);
        $this->assertEquals(10, $stockLevel->reserved_stock);

        // Test release
        $this->inventoryService->releaseReservedStock($items);
        
        $stockLevel->refresh();
        $this->assertEquals(100, $stockLevel->current_stock);
        $this->assertEquals(100, $stockLevel->available_stock);
        $this->assertEquals(0, $stockLevel->reserved_stock);
    }

    public function test_can_get_inventory_valuation()
    {
        // Create multiple products with stock
        $product2 = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => Category::factory()->create(['store_id' => $this->store->id])->id,
            'track_inventory' => true,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'current_stock' => 50,
            'average_cost' => 10.00,
            'total_value' => 500.00,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product2->id,
            'current_stock' => 30,
            'average_cost' => 15.00,
            'total_value' => 450.00,
        ]);

        $valuation = $this->inventoryService->getInventoryValuation();

        $this->assertEquals(950.00, $valuation['total_value']);
        $this->assertEquals(80, $valuation['total_items']);
        $this->assertEquals(2, $valuation['products_count']);
    }
}
