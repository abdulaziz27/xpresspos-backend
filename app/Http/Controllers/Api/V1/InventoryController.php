<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
        
        // Apply plan gate middleware for Pro/Enterprise features
        $this->middleware('plan.gate:inventory_tracking')->except(['index', 'show']);
    }

    /**
     * Display current stock levels.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockLevel::with(['product:id,name,sku,track_inventory,min_stock_level'])
            ->whereHas('product', function ($q) {
                $q->where('track_inventory', true);
            });

        // Filter by low stock
        if ($request->boolean('low_stock')) {
            $query->whereHas('product', function ($q) use ($query) {
                $q->whereColumn('stock_levels.current_stock', '<=', 'products.min_stock_level');
            });
        }

        // Filter by out of stock
        if ($request->boolean('out_of_stock')) {
            $query->where('available_stock', '<=', 0);
        }

        // Search by product name or SKU
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $stockLevels = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $stockLevels,
            'message' => 'Stock levels retrieved successfully'
        ]);
    }

    /**
     * Display stock level for specific product.
     */
    public function show(string $productId): JsonResponse
    {
        $product = Product::where('track_inventory', true)->findOrFail($productId);
        $stockLevel = StockLevel::getOrCreateForProduct($productId);
        
        // Get recent movements
        $recentMovements = InventoryMovement::where('product_id', $productId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
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
     */
    public function adjust(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|not_in:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $product = Product::where('track_inventory', true)->findOrFail($request->product_id);
        
        $result = $this->inventoryService->adjustStock(
            $product->id,
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
     */
    public function movements(Request $request): JsonResponse
    {
        $query = InventoryMovement::with(['product:id,name,sku', 'user:id,name']);

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
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
     */
    public function transfer(Request $request): JsonResponse
    {
        // This would be implemented for multi-outlet transfers in Enterprise plan
        $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'from_outlet_id' => 'required|uuid|exists:outlets,id',
            'to_outlet_id' => 'required|uuid|exists:outlets,id|different:from_outlet_id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        // For now, return not implemented as multi-outlet is Enterprise feature
        return response()->json([
            'success' => false,
            'message' => 'Stock transfer between outlets requires Enterprise plan'
        ], 403);
    }

    /**
     * Get low stock alerts.
     */
    public function lowStockAlerts(): JsonResponse
    {
        $lowStockProducts = StockLevel::with(['product:id,name,sku,min_stock_level'])
            ->whereHas('product', function ($q) {
                $q->where('track_inventory', true)
                  ->whereColumn('stock_levels.current_stock', '<=', 'products.min_stock_level');
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'low_stock_count' => $lowStockProducts->count(),
                'products' => $lowStockProducts,
            ],
            'message' => 'Low stock alerts retrieved successfully'
        ]);
    }
}
