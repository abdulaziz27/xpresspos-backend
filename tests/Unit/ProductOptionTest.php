<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\Store;
use App\Models\Scopes\StoreScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOptionTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->category = Category::factory()->create(['store_id' => $this->store->id]);
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'price' => 10000,
        ]);
    }

    public function test_product_can_calculate_price_with_options()
    {
        $option1 = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'price_adjustment' => 5000,
            'is_active' => true,
        ]);

        $option2 = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'price_adjustment' => 2000,
            'is_active' => true,
        ]);

        $result = $this->product->calculatePriceWithOptions([$option1->id, $option2->id]);

        $this->assertEquals(10000, $result['base_price']);
        $this->assertEquals(7000, $result['total_adjustment']);
        $this->assertEquals(17000, $result['total_price']);
        $this->assertCount(2, $result['selected_options']);
    }

    public function test_product_can_calculate_price_without_options()
    {
        $result = $this->product->calculatePriceWithOptions([]);

        $this->assertEquals(10000, $result['base_price']);
        $this->assertEquals(0, $result['total_adjustment']);
        $this->assertEquals(10000, $result['total_price']);
        $this->assertEmpty($result['selected_options']);
    }

    public function test_product_ignores_inactive_options_in_price_calculation()
    {
        $activeOption = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'price_adjustment' => 5000,
            'is_active' => true,
        ]);

        $inactiveOption = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'price_adjustment' => 10000,
            'is_active' => false,
        ]);

        $result = $this->product->calculatePriceWithOptions([$activeOption->id, $inactiveOption->id]);

        $this->assertEquals(5000, $result['total_adjustment']);
        $this->assertEquals(15000, $result['total_price']);
        $this->assertCount(1, $result['selected_options']);
    }

    public function test_product_can_get_option_groups()
    {
        ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Size',
            'value' => 'Small',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Size',
            'value' => 'Large',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Temperature',
            'value' => 'Hot',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $groups = $this->product->getOptionGroups();

        $this->assertCount(2, $groups);
        
        $sizeGroup = collect($groups)->firstWhere('name', 'Size');
        $this->assertNotNull($sizeGroup);
        $this->assertCount(2, $sizeGroup['options']);

        $temperatureGroup = collect($groups)->firstWhere('name', 'Temperature');
        $this->assertNotNull($temperatureGroup);
        $this->assertCount(1, $temperatureGroup['options']);
    }

    public function test_product_validates_options_correctly()
    {
        $validOption = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'is_active' => true,
        ]);

        $inactiveOption = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'is_active' => false,
        ]);

        // Test valid options
        $errors = $this->product->validateOptions([$validOption->id]);
        $this->assertEmpty($errors);

        // Test invalid option ID
        $errors = $this->product->validateOptions(['invalid-id']);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid option IDs', $errors[0]);

        // Test inactive option
        $errors = $this->product->validateOptions([$inactiveOption->id]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid option IDs', $errors[0]);
    }

    public function test_product_validates_duplicate_option_groups()
    {
        $option1 = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Size',
            'value' => 'Small',
            'is_active' => true,
        ]);

        $option2 = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Size',
            'value' => 'Large',
            'is_active' => true,
        ]);

        $errors = $this->product->validateOptions([$option1->id, $option2->id]);
        
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Cannot select multiple values from option group: Size', $errors[0]);
    }

    public function test_product_option_affects_inventory()
    {
        $productWithInventory = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'track_inventory' => true,
        ]);

        $productWithoutInventory = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'track_inventory' => false,
        ]);

        $optionWithInventory = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $productWithInventory->id,
        ]);
        $optionWithInventory->load('product');

        $optionWithoutInventory = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $productWithoutInventory->id,
        ]);
        $optionWithoutInventory->load('product');

        $this->assertTrue($optionWithInventory->affectsInventory());
        $this->assertFalse($optionWithoutInventory->affectsInventory());
    }

    public function test_product_option_effective_price()
    {
        $option = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'price_adjustment' => 5000,
        ]);
        $option->load('product');

        $this->assertEquals(15000, $option->getEffectivePrice());
    }

    public function test_product_option_availability()
    {
        // Ensure product is active and has stock
        $this->product->update(['status' => true, 'stock' => 10]);

        $activeOption = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'is_active' => true,
        ]);

        $inactiveOption = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'is_active' => false,
        ]);

        $this->assertTrue($activeOption->isAvailable());
        $this->assertFalse($inactiveOption->isAvailable());
    }

    public function test_product_option_display_methods()
    {
        $option = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Size',
            'value' => 'Large',
            'price_adjustment' => 5000,
        ]);

        $this->assertEquals('Size: Large', $option->getDisplayName());
        $this->assertEquals('+5.000', $option->getPriceAdjustmentDisplay());

        $freeOption = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'price_adjustment' => 0,
        ]);

        $this->assertEquals('', $freeOption->getPriceAdjustmentDisplay());
    }
}