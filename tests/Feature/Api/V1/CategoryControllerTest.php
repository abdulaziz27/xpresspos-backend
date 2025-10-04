<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        $this->user->assignRole('owner');
        
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_categories()
    {
        Category::factory()->count(3)->create(['store_id' => $this->store->id]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'image',
                        'is_active',
                        'sort_order',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'timestamp',
                    'version'
                ]
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_categories_by_search()
    {
        Category::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'Beverages'
        ]);
        Category::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'Food'
        ]);

        $response = $this->getJson('/api/v1/categories?search=Bev');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Beverages', $response->json('data.0.name'));
    }

    public function test_can_filter_categories_by_active_status()
    {
        Category::factory()->create([
            'store_id' => $this->store->id,
            'is_active' => true
        ]);
        Category::factory()->create([
            'store_id' => $this->store->id,
            'is_active' => false
        ]);

        $response = $this->getJson('/api/v1/categories?is_active=1');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_active'));
    }

    public function test_can_create_category()
    {
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'Test description',
            'is_active' => true,
            'sort_order' => 10
        ];

        $response = $this->postJson('/api/v1/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'is_active',
                    'sort_order',
                    'store_id'
                ],
                'message',
                'meta'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('Test Category', $response->json('data.name'));
        $this->assertEquals('test-category', $response->json('data.slug'));
        $this->assertEquals($this->store->id, $response->json('data.store_id'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'store_id' => $this->store->id
        ]);
    }

    public function test_cannot_create_category_with_duplicate_name_in_same_store()
    {
        Category::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'Existing Category'
        ]);

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Existing Category'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_create_category_with_same_name_in_different_store()
    {
        $otherStore = Store::factory()->create();
        Category::factory()->create([
            'store_id' => $otherStore->id,
            'name' => 'Same Name'
        ]);

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Same Name'
        ]);

        $response->assertStatus(201);
        $this->assertEquals('Same Name', $response->json('data.name'));
    }

    public function test_validation_errors_on_create()
    {
        $response = $this->postJson('/api/v1/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_show_category()
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'image',
                    'is_active',
                    'sort_order'
                ],
                'meta'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($category->id, $response->json('data.id'));
    }

    public function test_cannot_show_category_from_different_store()
    {
        $otherStore = Store::factory()->create();
        $category = Category::factory()->create(['store_id' => $otherStore->id]);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(404);
    }

    public function test_can_update_category()
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);

        $updateData = [
            'name' => 'Updated Category',
            'description' => 'Updated description',
            'is_active' => false,
            'sort_order' => 20
        ];

        $response = $this->putJson("/api/v1/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'message',
                'meta'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('Updated Category', $response->json('data.name'));
        $this->assertEquals('updated-category', $response->json('data.slug'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category'
        ]);
    }

    public function test_cannot_update_category_from_different_store()
    {
        $otherStore = Store::factory()->create();
        $category = Category::factory()->create(['store_id' => $otherStore->id]);

        $response = $this->putJson("/api/v1/categories/{$category->id}", [
            'name' => 'Updated Name'
        ]);

        $response->assertStatus(404);
    }

    public function test_can_delete_category_without_products()
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'meta'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_products()
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);
        Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'details'
                ]
            ]);

        $this->assertFalse($response->json('success'));
        $this->assertEquals('CATEGORY_HAS_PRODUCTS', $response->json('error.code'));
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_from_different_store()
    {
        $otherStore = Store::factory()->create();
        $category = Category::factory()->create(['store_id' => $otherStore->id]);

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(404);
    }

    public function test_can_get_category_options()
    {
        Category::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'Active Category',
            'is_active' => true,
            'sort_order' => 1
        ]);
        Category::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'Inactive Category',
            'is_active' => false,
            'sort_order' => 2
        ]);

        $response = $this->getJson('/api/v1/categories-options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sort_order'
                    ]
                ],
                'meta'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(1, $response->json('data')); // Only active categories
        $this->assertEquals('Active Category', $response->json('data.0.name'));
    }

    public function test_unauthorized_user_cannot_access_categories()
    {
        $unauthorizedUser = User::factory()->create(['store_id' => $this->store->id]);
        // Don't assign any role to make user unauthorized
        
        Sanctum::actingAs($unauthorizedUser);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(403);
    }

    public function test_user_cannot_access_categories_from_different_store()
    {
        $otherStore = Store::factory()->create();
        Category::factory()->count(2)->create(['store_id' => $otherStore->id]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data')); // Should not see other store's categories
    }
}