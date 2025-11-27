<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function __construct()
    {
        // Apply plan gate middleware for Pro/Enterprise features
        // $this->middleware('plan.gate:inventory_tracking'); // Commented out until middleware is implemented
    }

    /**
     * Display a listing of recipes.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Recipe::class);

        $query = Recipe::with(['product:id,name,sku', 'items.ingredient:id,name,sku'])
            ->where('is_active', true);

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Search by name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $recipes = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $recipes,
            'message' => 'Recipes retrieved successfully'
        ]);
    }

    /**
     * Store a newly created recipe.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Recipe::class);

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'yield_quantity' => 'required|numeric|min:0.01',
            'yield_unit' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.ingredient_product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        return DB::transaction(function () use ($request, $user) {
            // Create recipe
            $recipe = Recipe::create([
                'store_id' => $user->store_id,
                'product_id' => $request->product_id,
                'name' => $request->name,
                'description' => $request->description,
                'yield_quantity' => $request->yield_quantity,
                'yield_unit' => $request->yield_unit,
                'is_active' => true,
            ]);

            // Create recipe items
            foreach ($request->items as $itemData) {
                RecipeItem::create([
                    'store_id' => $user->store_id,
                    'recipe_id' => $recipe->id,
                    'ingredient_product_id' => $itemData['ingredient_product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'],
                    'unit_cost' => $itemData['unit_cost'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            // Load relationships for response
            $recipe->load(['product:id,name,sku', 'items.ingredient:id,name,sku']);

            return response()->json([
                'success' => true,
                'data' => $recipe,
                'message' => 'Recipe created successfully'
            ], 201);
        });
    }

    /**
     * Display the specified recipe.
     */
    public function show(Recipe $recipe): JsonResponse
    {
        $this->authorize('view', $recipe);
        
        $recipe->load(['product:id,name,sku', 'items.ingredient:id,name,sku,cost_price']);

        return response()->json([
            'success' => true,
            'data' => $recipe,
            'message' => 'Recipe retrieved successfully'
        ]);
    }

    /**
     * Update the specified recipe.
     */
    public function update(Request $request, Recipe $recipe): JsonResponse
    {
        $this->authorize('update', $recipe);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'yield_quantity' => 'sometimes|numeric|min:0.01',
            'yield_unit' => 'sometimes|string|max:50',
            'is_active' => 'sometimes|boolean',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'sometimes|exists:recipe_items,id',
            'items.*.ingredient_product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User is not authenticated'
                ]
            ], 401);
        }

        return DB::transaction(function () use ($request, $recipe, $user) {
            // Update recipe
            $recipe->update($request->only([
                'name',
                'description',
                'yield_quantity',
                'yield_unit',
                'is_active'
            ]));

            // Update recipe items if provided
            if ($request->has('items')) {
                // Delete existing items
                $recipe->items()->delete();

                // Create new items
                foreach ($request->items as $itemData) {
                    RecipeItem::create([
                        'store_id' => $user->store_id,
                        'recipe_id' => $recipe->id,
                        'ingredient_product_id' => $itemData['ingredient_product_id'],
                        'quantity' => $itemData['quantity'],
                        'unit' => $itemData['unit'],
                        'unit_cost' => $itemData['unit_cost'],
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }
            }

            // Load relationships for response
            $recipe->load(['product:id,name,sku', 'items.ingredient:id,name,sku']);

            return response()->json([
                'success' => true,
                'data' => $recipe,
                'message' => 'Recipe updated successfully'
            ]);
        });
    }

    /**
     * Remove the specified recipe.
     */
    public function destroy(Recipe $recipe): JsonResponse
    {
        $this->authorize('delete', $recipe);
        
        $recipe->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Recipe deactivated successfully'
        ]);
    }

    /**
     * Calculate recipe cost based on current ingredient prices.
     */
    public function calculateCost(Recipe $recipe): JsonResponse
    {
        $recipe->load(['items.ingredient:id,name,sku,cost_price']);

        $totalCost = 0;
        $costBreakdown = [];

        foreach ($recipe->items as $item) {
            $currentCost = $item->ingredient->cost_price ?? $item->unit_cost;
            $itemTotalCost = $item->quantity * $currentCost;
            $totalCost += $itemTotalCost;

            $costBreakdown[] = [
                'ingredient_id' => $item->ingredient->id,
                'ingredient_name' => $item->ingredient->name,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'original_unit_cost' => $item->unit_cost,
                'current_unit_cost' => $currentCost,
                'total_cost' => $itemTotalCost,
                'cost_variance' => $currentCost - $item->unit_cost,
            ];
        }

        $costPerUnit = $recipe->yield_quantity > 0 ? $totalCost / $recipe->yield_quantity : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'recipe' => $recipe,
                'total_cost' => $totalCost,
                'cost_per_unit' => $costPerUnit,
                'original_total_cost' => $recipe->total_cost,
                'original_cost_per_unit' => $recipe->cost_per_unit,
                'cost_variance' => $totalCost - $recipe->total_cost,
                'cost_breakdown' => $costBreakdown,
            ],
            'message' => 'Recipe cost calculated successfully'
        ]);
    }

    /**
     * Update recipe costs based on current ingredient prices.
     */
    public function updateCosts(Recipe $recipe): JsonResponse
    {
        $recipe->recalculateCosts();
        $recipe->refresh();

        return response()->json([
            'success' => true,
            'data' => $recipe,
            'message' => 'Recipe costs updated successfully'
        ]);
    }

    /**
     * Get available ingredients for recipes.
     */
    public function availableIngredients(Request $request): JsonResponse
    {
        $query = Product::select('id', 'name', 'sku', 'cost_price')
            ->where('status', true);

        // Search by name or SKU
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $ingredients = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $ingredients,
            'message' => 'Available ingredients retrieved successfully'
        ]);
    }
}
