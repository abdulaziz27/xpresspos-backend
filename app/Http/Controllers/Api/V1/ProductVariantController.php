<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\FnBVariantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProductVariantController extends Controller
{
    protected FnBVariantService $variantService;

    public function __construct(FnBVariantService $variantService)
    {
        $this->variantService = $variantService;
    }

    /**
     * Get variants for a specific product
     */
    public function index(string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $this->authorize('view', $product);

        $variants = $this->variantService->getVariantGroups($product);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'base_price' => $product->price,
                ],
                'variant_groups' => $variants
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Store a new variant for a product
     */
    public function store(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $this->authorize('update', $product);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'value' => 'required|string|max:100',
            'price_adjustment' => 'numeric|min:-999999.99|max:999999.99',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
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

        $variant = ProductVariant::create([
            'store_id' => $product->store_id,
            'product_id' => $product->id,
            'name' => $request->input('name'),
            'value' => $request->input('value'),
            'price_adjustment' => $request->input('price_adjustment', 0),
            'is_active' => $request->input('is_active', true),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return response()->json([
            'success' => true,
            'data' => $variant,
            'message' => 'Product variant created successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ], 201);
    }

    /**
     * Update a variant
     */
    public function update(Request $request, string $productId, string $variantId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $this->authorize('update', $product);

        $variant = ProductVariant::where('product_id', $productId)
            ->findOrFail($variantId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'value' => 'sometimes|required|string|max:100',
            'price_adjustment' => 'sometimes|numeric|min:-999999.99|max:999999.99',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
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

        $variant->update($request->only([
            'name', 'value', 'price_adjustment', 'is_active', 'sort_order'
        ]));

        return response()->json([
            'success' => true,
            'data' => $variant->fresh(),
            'message' => 'Product variant updated successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Delete a variant
     */
    public function destroy(string $productId, string $variantId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $this->authorize('update', $product);

        $variant = ProductVariant::where('product_id', $productId)
            ->findOrFail($variantId);

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product variant deleted successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Calculate price with selected variants (for POS)
     */
    public function calculatePrice(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $this->authorize('view', $product);

        $validator = Validator::make($request->all(), [
            'variant_ids' => 'array',
            'variant_ids.*' => 'exists:product_variants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid variant selection.',
                    'details' => $validator->errors()
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        $variantIds = $request->input('variant_ids', []);
        
        // Validate variant selection
        $errors = $this->variantService->validateVariantSelection($product, $variantIds);
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_VARIANT_SELECTION',
                    'message' => 'Invalid variant combination.',
                    'details' => $errors
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        $calculation = $this->variantService->calculateOrderTotal($product, $variantIds);

        return response()->json([
            'success' => true,
            'data' => $calculation,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Get F&B variant presets (common combinations)
     */
    public function getPresets(): JsonResponse
    {
        $presets = [
            'coffee_sizes' => [
                ['name' => 'Size', 'value' => 'Small', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Regular', 'price_adjustment' => 5000],
                ['name' => 'Size', 'value' => 'Large', 'price_adjustment' => 10000],
            ],
            'milk_options' => [
                ['name' => 'Milk', 'value' => 'Regular Milk', 'price_adjustment' => 0],
                ['name' => 'Milk', 'value' => 'Oat Milk', 'price_adjustment' => 8000],
                ['name' => 'Milk', 'value' => 'Almond Milk', 'price_adjustment' => 6000],
                ['name' => 'Milk', 'value' => 'Soy Milk', 'price_adjustment' => 5000],
            ],
            'sugar_levels' => [
                ['name' => 'Sugar', 'value' => 'No Sugar', 'price_adjustment' => 0],
                ['name' => 'Sugar', 'value' => 'Less Sugar', 'price_adjustment' => 0],
                ['name' => 'Sugar', 'value' => 'Regular Sugar', 'price_adjustment' => 0],
                ['name' => 'Sugar', 'value' => 'Extra Sweet', 'price_adjustment' => 2000],
            ],
            'temperature' => [
                ['name' => 'Temperature', 'value' => 'Hot', 'price_adjustment' => 0],
                ['name' => 'Temperature', 'value' => 'Iced', 'price_adjustment' => 3000],
            ],
            'spice_levels' => [
                ['name' => 'Spice Level', 'value' => 'Mild', 'price_adjustment' => 0],
                ['name' => 'Spice Level', 'value' => 'Medium', 'price_adjustment' => 0],
                ['name' => 'Spice Level', 'value' => 'Spicy', 'price_adjustment' => 2000],
                ['name' => 'Spice Level', 'value' => 'Extra Spicy', 'price_adjustment' => 5000],
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $presets,
            'message' => 'F&B variant presets for quick setup',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }
}