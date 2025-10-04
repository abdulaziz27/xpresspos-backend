<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductOptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create owner role with all permissions
        $ownerRole = Role::create(['name' => 'owner', 'guard_name' => 'web']);
        $ownerRole->givePermissionTo($permissions);

        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        
        // Assign owner role to user for testing
        $this->user->assignRole('owner');
        
        $this->category = Category::factory()->create(['store_id' => $this->store->id]);
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_can_list_product_options()
    {
        // Create some options for the product
        ProductOption::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$this->product->id}/options");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'value',
                        'price_adjustment',
                        'is_active',
                        'sort_order',
                    ]
                ],
                'message'
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_product_option()
    {
        $optionData = [
            'name' => 'Size',
            'value' => 'Large',
            'price_adjustment' => 5000,
            'is_active' => true,
            'sort_order' => 1,
        ];

        $response = $this->postJson("/api/v1/products/{$this->product->id}/options", $optionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'value',
                    'price_adjustment',
                    'is_active',
                    'sort_order',
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => $optionData,
            ]);

        $this->assertDatabaseHas('product_options', [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'name' => 'Size',
            'value' => 'Large',
            'price_adjustment' => 5000,
        ]);
    }

    public function test_can_show_specific_product_option()
    {
        $option = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->getJson("/api/v1/products/{$this->product->id}/options/{$option->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'value' => $option->value,
                ],
            ]);
    }

    public function test_can_update_product_option()
    {
        $option = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
        ]);

        $updateData = [
            'name' => 'Updated Size',
            'value' => 'Extra Large',
            'price_adjustment' => 8000,
        ];

        $response = $this->putJson("/api/v1/products/{$this->product->id}/options/{$option->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $updateData,
            ]);

        $this->assertDatabaseHas('product_options', [
            'id' => $option->id,
            'name' => 'Updated Size',
            'value' => 'Extra Large',
            'price_adjustment' => 8000,
        ]);
    }

    public function test_can_delete_product_option()
    {
        $option = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->deleteJson("/api/v1/products/{$this->product->id}/options/{$option->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product option deleted successfully',
            ]);

        $this->assertDatabaseMissing('product_options', [
            'id' => $option->id,
        ]);
    }

    public function test_can_calculate_price_with_options()
    {
        $option1 = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Size',
            'value' => 'Large',
            'price_adjustment' => 5000,
            'is_active' => true,
        ]);

        $option2 = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Temperature',
            'value' => 'Hot',
            'price_adjustment' => 0,
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/products/{$this->product->id}/calculate-price", [
            'options' => [$option1->id, $option2->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'base_price',
                    'total_adjustment',
                    'total_price',
                    'selected_options' => [
                        '*' => [
                            'id',
                            'name',
                            'value',
                            'price_adjustment',
                        ]
                    ]
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'base_price' => $this->product->price,
                    'total_adjustment' => 5000,
                    'total_price' => $this->product->price + 5000,
                ],
            ]);
    }

    public function test_can_get_option_groups()
    {
        // Create options with different groups
        ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Size',
            'value' => 'Small',
            'price_adjustment' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Size',
            'value' => 'Large',
            'price_adjustment' => 5000,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'name' => 'Temperature',
            'value' => 'Hot',
            'price_adjustment' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson("/api/v1/products/{$this->product->id}/option-groups");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'name',
                        'options' => [
                            '*' => [
                                'id',
                                'value',
                                'price_adjustment',
                                'sort_order',
                            ]
                        ]
                    ]
                ],
                'message'
            ])
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertCount(2, $data); // Size and Temperature groups

        // Find Size group
        $sizeGroup = collect($data)->firstWhere('name', 'Size');
        $this->assertNotNull($sizeGroup);
        $this->assertCount(2, $sizeGroup['options']); // Small and Large
    }

    public function test_validates_required_fields_when_creating_option()
    {
        $response = $this->postJson("/api/v1/products/{$this->product->id}/options", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'value']);
    }

    public function test_validates_price_adjustment_is_numeric()
    {
        $response = $this->postJson("/api/v1/products/{$this->product->id}/options", [
            'name' => 'Size',
            'value' => 'Large',
            'price_adjustment' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price_adjustment']);
    }

    public function test_cannot_access_option_from_different_product()
    {
        $otherProduct = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
        ]);

        $option = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $otherProduct->id,
        ]);

        $response = $this->getJson("/api/v1/products/{$this->product->id}/options/{$option->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Product option not found for this product',
            ]);
    }

    public function test_cannot_access_options_from_different_store()
    {
        $otherStore = Store::factory()->create();
        $otherUser = User::factory()->create(['store_id' => $otherStore->id]);
        
        // Assign owner role to other user as well
        $otherUser->assignRole('owner');
        
        Sanctum::actingAs($otherUser);

        // Try to access product from different store - should return 404 due to global scope
        // The product doesn't exist in the other user's store context
        $response = $this->getJson("/api/v1/products/{$this->product->id}/options");

        $response->assertStatus(404);
    }

    public function test_only_returns_active_options_in_listing()
    {
        ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'is_active' => true,
        ]);

        ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/products/{$this->product->id}/options");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_price_calculation_ignores_inactive_options()
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

        $response = $this->postJson("/api/v1/products/{$this->product->id}/calculate-price", [
            'options' => [$activeOption->id, $inactiveOption->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_adjustment' => 5000, // Only active option counted
                ],
            ]);

        $selectedOptions = $response->json('data.selected_options');
        $this->assertCount(1, $selectedOptions); // Only active option returned
    }
}