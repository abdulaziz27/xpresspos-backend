<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::with(['category:id,name', 'variants']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('is_favorite')) {
            $query->where('is_favorite', $request->boolean('is_favorite'));
        }

        if ($request->filled('track_inventory')) {
            $query->where('track_inventory', $request->boolean('track_inventory'));
        }

        if ($request->filled('low_stock')) {
            $query->lowStock();
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'sort_order');
        $sortDirection = $request->input('sort_direction', 'asc');

        if (in_array($sortBy, ['name', 'price', 'stock', 'sort_order', 'created_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('sort_order', 'asc');
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $products = $query->paginate($perPage);

        // Get store ID for stock calculation
        $user = $request->user();
        $storeId = null;
        if ($user) {
            $store = $user->store();
            $storeId = $store?->id;
        }

        // Add stock quantity to each product
        $productsData = $products->map(function ($product) use ($storeId) {
            $productArray = $product->toArray();
            $productArray['stock'] = $product->getAvailableStock($storeId);
            return $productArray;
        });

        return response()->json([
            'success' => true,
            'data' => $productsData->values()->all(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = Product::create([
            'category_id' => $request->input('category_id'),
            'name' => $request->input('name'),
            'sku' => $request->input('sku'),
            'description' => $request->input('description'),
            'image' => $request->input('image'),
            'price' => $request->input('price'),
            'cost_price' => $request->input('cost_price'),
            'track_inventory' => $request->input('track_inventory', false),
            'status' => $request->input('status', true),
            'is_favorite' => $request->input('is_favorite', false),
            'sort_order' => $request->input('sort_order', 0)
        ]);

        $product->load(['category:id,name', 'variants']);

        // Get store ID for stock calculation
        $user = $request->user();
        $storeId = null;
        if ($user) {
            $store = $user->store();
            $storeId = $store?->id;
        }

        // Add stock quantity to product data
        $productData = $product->toArray();
        $productData['stock'] = $product->getAvailableStock($storeId);

        return response()->json([
            'success' => true,
            'data' => $productData,
            'message' => 'Product created successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $product = Product::with(['category:id,name', 'variants', 'priceHistory' => function ($query) {
            $query->with('changedBy:id,name')->latest()->limit(10);
        }])->findOrFail($id);

        $this->authorize('view', $product);

        // Get store ID for stock calculation
        $user = $request->user();
        $storeId = null;
        if ($user) {
            $store = $user->store();
            $storeId = $store?->id;
        }

        // Add stock quantity to product data
        $productData = $product->toArray();
        $productData['stock'] = $product->getAvailableStock($storeId);

        return response()->json([
            'success' => true,
            'data' => $productData,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);

        $validator = Validator::make($request->all(), [
            'category_id' => [
                'sometimes',
                'exists:categories,id',
            ],
            'name' => 'sometimes|required|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->where(function ($query) use ($product) {
                    $user = request()->user();
                    $tenantId = $user->currentTenant()?->id;
                    if ($tenantId) {
                        $query->where('tenant_id', $tenantId);
                    }
                })->ignore($product->id)
            ],
            'description' => 'nullable|string|max:2000',
            'image' => 'nullable|string|max:255',
            'price' => 'sometimes|required|numeric|min:0|max:999999.99',
            'cost_price' => 'nullable|numeric|min:0|max:999999.99',
            'track_inventory' => 'boolean',
            'status' => 'boolean',
            'is_favorite' => 'boolean',
            'sort_order' => 'integer|min:0',
            'price_change_reason' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        // Check if price is changing and record history
        $newPrice = $request->input('price');
        $newCostPrice = $request->input('cost_price');
        $priceChangeReason = $request->input('price_change_reason');

        if ($product->price != $newPrice || ($newCostPrice !== null && $product->cost_price != $newCostPrice)) {
            $product->recordPriceChange($newPrice, $newCostPrice, $priceChangeReason);
        }

        $updateData = [];

        if ($request->has('category_id')) {
            $updateData['category_id'] = $request->input('category_id');
        }
        if ($request->has('name')) {
            $updateData['name'] = $request->input('name');
        }
        if ($request->has('sku')) {
            $updateData['sku'] = $request->input('sku');
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->input('description');
        }
        if ($request->has('image')) {
            $updateData['image'] = $request->input('image');
        }
        if ($request->has('price')) {
            $updateData['price'] = $newPrice;
        }
        if ($request->has('cost_price')) {
            $updateData['cost_price'] = $newCostPrice;
        }
        if ($request->has('track_inventory')) {
            $updateData['track_inventory'] = $request->input('track_inventory');
        }
        if ($request->has('status')) {
            $updateData['status'] = $request->input('status');
        }
        if ($request->has('is_favorite')) {
            $updateData['is_favorite'] = $request->input('is_favorite');
        }
        if ($request->has('sort_order')) {
            $updateData['sort_order'] = $request->input('sort_order');
        }

        $product->update($updateData);

        $product->load(['category:id,name', 'variants']);

        return response()->json([
            'success' => true,
            'data' => $product->fresh(['category:id,name', 'variants']),
            'message' => 'Product updated successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('delete', $product);

        // Check if product has order items
        if ($product->orderItems()->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PRODUCT_HAS_ORDERS',
                    'message' => 'Cannot delete product that has been ordered. Consider archiving instead.',
                    'details' => [
                        'orders_count' => $product->orderItems()->count()
                    ]
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Delete related records first to avoid foreign key constraints
            // Delete recipe items that use this product as ingredient
            \DB::table('recipe_items')->where('ingredient_product_id', $id)->delete();

            // Delete recipes that use this product
            $product->recipes()->delete();

            // Delete product variants
            $product->variants()->delete();

            // Delete inventory movements
            $product->inventoryMovements()->delete();

            // Delete stock level
            $product->stockLevel()?->delete();

            // Delete price history
            $product->priceHistory()->delete();

            // Finally delete the product
            $product->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => 'Failed to delete product: ' . $e->getMessage(),
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Archive the specified product.
     */
    public function archive(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);

        $product->archive();

        return response()->json([
            'success' => true,
            'data' => $product->fresh(),
            'message' => 'Product archived successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Restore the archived product.
     */
    public function restore(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);

        $product->restore();

        return response()->json([
            'success' => true,
            'data' => $product->fresh(),
            'message' => 'Product restored successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Upload product image.
     */
    public function uploadImage(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        // Delete old image if exists
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        // Store new image
        $imagePath = $request->file('image')->store('products', 'public');

        $product->update(['image' => $imagePath]);

        return response()->json([
            'success' => true,
            'data' => [
                'image_path' => $imagePath,
                'image_url' => Storage::disk('public')->url($imagePath)
            ],
            'message' => 'Product image uploaded successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Get product price history.
     */
    public function priceHistory(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('view', $product);

        $history = $product->priceHistory()
            ->with('changedBy:id,name')
            ->orderBy('effective_date', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Search products for POS.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Search query is required.',
                    'details' => $validator->errors()
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        $search = $request->input('q');

        $products = Product::with(['category:id,name', 'variants'])
            ->active()
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        // Get store ID for stock calculation
        $user = $request->user();
        $storeId = null;
        if ($user) {
            $store = $user->store();
            $storeId = $store?->id;
        }

        // Add stock quantity to each product
        $productsData = $products->map(function ($product) use ($storeId) {
            $productArray = $product->toArray();
            $productArray['stock'] = $product->getAvailableStock($storeId);
            return $productArray;
        });

        return response()->json([
            'success' => true,
            'data' => $productsData->values()->all(),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }
}
