<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductOptionRequest;
use App\Http\Requests\UpdateProductOptionRequest;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductOptionController extends Controller
{
    /**
     * Display a listing of product options.
     */
    public function index(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $options = ProductOption::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $options,
            'message' => 'Product options retrieved successfully',
        ]);
    }

    /**
     * Store a newly created product option.
     */
    public function store(StoreProductOptionRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $option = $product->options()->create([
            'store_id' => auth()->user()->store_id,
            'name' => $request->name,
            'value' => $request->value,
            'price_adjustment' => $request->price_adjustment ?? 0,
            'is_active' => $request->is_active ?? true,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => $option,
            'message' => 'Product option created successfully',
        ], 201);
    }

    /**
     * Display the specified product option.
     */
    public function show(Product $product, ProductOption $option): JsonResponse
    {
        $this->authorize('view', $product);

        // Ensure the option belongs to the product
        if ($option->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Product option not found for this product',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $option,
            'message' => 'Product option retrieved successfully',
        ]);
    }

    /**
     * Update the specified product option.
     */
    public function update(UpdateProductOptionRequest $request, Product $product, ProductOption $option): JsonResponse
    {
        $this->authorize('update', $product);

        // Ensure the option belongs to the product
        if ($option->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Product option not found for this product',
            ], 404);
        }

        $option->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $option->fresh(),
            'message' => 'Product option updated successfully',
        ]);
    }

    /**
     * Remove the specified product option.
     */
    public function destroy(Product $product, ProductOption $option): JsonResponse
    {
        $this->authorize('update', $product);

        // Ensure the option belongs to the product
        if ($option->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Product option not found for this product',
            ], 404);
        }

        $option->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product option deleted successfully',
        ]);
    }

    /**
     * Calculate total price for product with selected options.
     */
    public function calculatePrice(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $request->validate([
            'options' => 'array',
            'options.*' => 'exists:product_options,id',
        ]);

        $basePrice = $product->price;
        $totalAdjustment = 0;
        $selectedOptions = [];

        if ($request->has('options')) {
            $options = ProductOption::withoutGlobalScopes()
                ->whereIn('id', $request->options)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->get();

            foreach ($options as $option) {
                $totalAdjustment += $option->price_adjustment;
                $selectedOptions[] = [
                    'id' => $option->id,
                    'name' => $option->name,
                    'value' => $option->value,
                    'price_adjustment' => $option->price_adjustment,
                ];
            }
        }

        $totalPrice = $basePrice + $totalAdjustment;

        return response()->json([
            'success' => true,
            'data' => [
                'base_price' => $basePrice,
                'total_adjustment' => $totalAdjustment,
                'total_price' => $totalPrice,
                'selected_options' => $selectedOptions,
            ],
            'message' => 'Price calculated successfully',
        ]);
    }

    /**
     * Get available option groups for a product.
     */
    public function groups(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $options = ProductOption::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('name');

        $groups = $options->map(function ($groupOptions, $groupName) {
            return [
                'name' => $groupName,
                'options' => $groupOptions->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'value' => $option->value,
                        'price_adjustment' => $option->price_adjustment,
                        'sort_order' => $option->sort_order,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $groups,
            'message' => 'Product option groups retrieved successfully',
        ]);
    }
}