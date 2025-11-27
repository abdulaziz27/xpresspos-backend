<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use App\Services\StoreContext;
use App\Traits\ChecksPlanLimits;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    use ChecksPlanLimits;

    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;

        // Apply plan gate middleware for Pro/Enterprise features
        // $this->middleware('plan.gate:inventory_tracking')->except(['index', 'show']);
    }

    /**
     * Display current stock levels.
     * 
     * NOTE: Now returns stock levels per inventory_item (not per product).
     * Stock is tracked per inventory_item per store.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InventoryItem::class);

        $query = StockLevel::with(['inventoryItem:id,name,sku,track_stock,min_stock_level']);

        // Filter by low stock
        if ($request->boolean('low_stock')) {
            $query->whereHas('inventoryItem', function ($q) {
                $q->where('track_stock', true);
            })->whereColumn('stock_levels.current_stock', '<=', 'stock_levels.min_stock_level');
        }

        // Filter by out of stock
        if ($request->boolean('out_of_stock')) {
            $query->where('available_stock', '<=', 0);
        }

        // Search by inventory item name or SKU
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('inventoryItem', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by inventory_item_id
        if ($request->filled('inventory_item_id')) {
            $query->where('inventory_item_id', $request->inventory_item_id);
        }

        $stockLevels = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $stockLevels,
            'message' => 'Stock levels retrieved successfully'
        ]);
    }

    /**
     * Display stock level for specific inventory item.
     * 
     * NOTE: Now accepts inventory_item_id (not product_id).
     * Stock is tracked per inventory_item per store.
     * 
     * @param string $inventoryItemId The inventory item ID (UUID)
     */
    public function show(string $inventoryItemId): JsonResponse
    {
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

        $storeId = StoreContext::instance()->current($user);
        if (!$storeId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_CONTEXT_MISSING',
                    'message' => 'User missing store context',
                    'debug' => [
                        'user_id' => $user->id,
                    ]
                ]
            ], 400);
        }

        $inventoryItem = InventoryItem::where('track_stock', true)
            ->findOrFail($inventoryItemId);
        
        $this->authorize('view', $inventoryItem);
        
        $stockLevel = StockLevel::getOrCreateForInventoryItem($inventoryItemId, $storeId);

        // Get recent movements (InventoryMovement is store-scoped)
        $recentMovements = InventoryMovement::where('inventory_item_id', $inventoryItemId)
            ->where('store_id', $storeId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'inventory_item' => $inventoryItem,
                'stock_level' => $stockLevel,
                'recent_movements' => $recentMovements,
                'is_low_stock' => $stockLevel->isLowStock(),
                'is_out_of_stock' => $stockLevel->isOutOfStock(),
            ],
            'message' => 'Stock level retrieved successfully'
        ]);
    }

    /**
     * Create manual stock adjustment.
     * 
     * NOTE: Now accepts inventory_item_id (not product_id).
     * Stock adjustments are per inventory_item per store.
     * 
     * REQUIRES: Pro/Enterprise plan (ALLOW_INVENTORY feature)
     */
    public function adjust(Request $request): JsonResponse
    {
        // Check if tenant has inventory feature enabled
        if (!$this->hasFeature(null, 'ALLOW_INVENTORY')) {
            return $this->featureNotAvailableResponse('Inventory Management', 'Pro');
        }

        $this->authorize('create', \App\Models\InventoryAdjustment::class);

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

        $storeId = StoreContext::instance()->current($user);
        if (!$storeId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_CONTEXT_MISSING',
                    'message' => 'User missing store context'
                ]
            ], 401);
        }

        $request->validate([
            'inventory_item_id' => 'required|string|exists:inventory_items,id',
            'quantity' => 'required|numeric|min:0.001',
            'unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $inventoryItem = InventoryItem::where('track_stock', true)
            ->findOrFail($request->inventory_item_id);

        $result = $this->inventoryService->adjustStock(
            $inventoryItem->id,
            $request->quantity,
            $request->reason,
            $request->unit_cost,
            $request->notes
        );

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Stock adjustment completed successfully'
        ]);
    }

    /**
     * Get inventory movements with filters.
     * 
     * NOTE: Now filters by inventory_item_id (not product_id).
     * Movements are tracked per inventory_item per store.
     */
    public function movements(Request $request): JsonResponse
    {
        $query = InventoryMovement::with(['inventoryItem:id,name,sku', 'user:id,name']);

        // Filter by inventory item
        if ($request->filled('inventory_item_id')) {
            $query->where('inventory_item_id', $request->inventory_item_id);
        }

        // Filter by movement type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by stock in/out
        if ($request->filled('direction')) {
            if ($request->direction === 'in') {
                $query->stockIn();
            } elseif ($request->direction === 'out') {
                $query->stockOut();
            }
        }

        $movements = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $movements,
            'message' => 'Inventory movements retrieved successfully'
        ]);
    }

    /**
     * Transfer stock between outlets (Enterprise feature).
     * 
     * NOTE: Now accepts inventory_item_id (not product_id).
     */
    public function transfer(Request $request): JsonResponse
    {
        // This would be implemented for multi-outlet transfers in Enterprise plan
        $request->validate([
            'inventory_item_id' => 'required|string|exists:inventory_items,id',
            'from_outlet_id' => 'required|uuid|exists:outlets,id',
            'to_outlet_id' => 'required|uuid|exists:outlets,id|different:from_outlet_id',
            'quantity' => 'required|numeric|min:0.001',
            'notes' => 'nullable|string|max:1000',
        ]);

        // For now, return not implemented as multi-outlet is Enterprise feature
        return response()->json([
            'success' => false,
            'message' => 'Stock transfer between outlets requires Enterprise plan'
        ], 403);
    }

    /**
     * Get inventory levels summary.
     * 
     * NOTE: Now returns summary per inventory_item (not per product).
     */
    public function levels(Request $request): JsonResponse
    {
        $stockLevels = StockLevel::with(['inventoryItem:id,name,sku,track_stock,min_stock_level'])
            ->whereHas('inventoryItem', function ($q) {
                $q->where('track_stock', true);
            })
            ->get();

        $summary = [
            'total_items' => $stockLevels->count(),
            'total_stock_value' => $stockLevels->sum('total_value'),
            'low_stock_count' => $stockLevels->filter(function ($level) {
                return $level->isLowStock();
            })->count(),
            'out_of_stock_count' => $stockLevels->where('current_stock', '<=', 0)->count(),
            'available_stock_count' => $stockLevels->where('available_stock', '>', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'stock_levels' => $stockLevels,
            ],
            'message' => 'Inventory levels retrieved successfully'
        ]);
    }

    /**
     * Get low stock alerts.
     * 
     * NOTE: Now returns low stock alerts per inventory_item (not per product).
     */
    public function lowStockAlerts(): JsonResponse
    {
        $lowStockItems = StockLevel::with(['inventoryItem:id,name,sku,min_stock_level'])
            ->whereHas('inventoryItem', function ($q) {
                $q->where('track_stock', true);
            })
            ->get()
            ->filter(function ($level) {
                return $level->isLowStock();
            });

        return response()->json([
            'success' => true,
            'data' => [
                'low_stock_count' => $lowStockItems->count(),
                'inventory_items' => $lowStockItems->values(),
            ],
            'message' => 'Low stock alerts retrieved successfully'
        ]);
    }

    /**
     * Create a new inventory movement.
     * 
     * NOTE: Now accepts inventory_item_id (not product_id).
     * Movements are tracked per inventory_item per store.
     * 
     * REQUIRES: Pro/Enterprise plan (ALLOW_INVENTORY feature)
     */
    public function createMovement(Request $request): JsonResponse
    {
        // Check if tenant has inventory feature enabled
        if (!$this->hasFeature(null, 'ALLOW_INVENTORY')) {
            return $this->featureNotAvailableResponse('Inventory Management', 'Pro');
        }

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

        $storeId = StoreContext::instance()->current($user);
        if (!$storeId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_CONTEXT_MISSING',
                    'message' => 'User missing store context'
                ]
            ], 401);
        }

        $request->validate([
            'inventory_item_id' => 'required|string|exists:inventory_items,id',
            'type' => 'required|string|in:sale,purchase,adjustment_in,adjustment_out,transfer_in,transfer_out,waste,return',
            'quantity' => 'required|numeric|min:0.001',
            'unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $inventoryItem = InventoryItem::where('track_stock', true)
            ->findOrFail($request->inventory_item_id);

        try {
            $movement = InventoryMovement::createMovement(
                $inventoryItem->id,
                $request->type,
                (float) $request->quantity,
                $request->unit_cost,
                $request->reason,
                null,
                null,
                $request->notes
            );

            // Update stock level (StockLevel is store-scoped)
            $stockLevel = StockLevel::getOrCreateForInventoryItem($inventoryItem->id, $storeId);
            $stockLevel->updateFromMovement($movement);

            return response()->json([
                'success' => true,
                'data' => [
                    'movement' => $movement,
                    'stock_level' => $stockLevel->fresh(),
                ],
                'message' => 'Inventory movement created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MOVEMENT_CREATION_FAILED',
                    'message' => 'Failed to create inventory movement',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
    }
}
