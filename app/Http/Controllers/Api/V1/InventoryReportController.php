<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Services\CogsService;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use App\Models\CogsHistory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class InventoryReportController extends Controller
{
    protected InventoryService $inventoryService;
    protected CogsService $cogsService;

    public function __construct(InventoryService $inventoryService, CogsService $cogsService)
    {
        $this->inventoryService = $inventoryService;
        $this->cogsService = $cogsService;

        // Apply plan gate middleware for Pro/Enterprise features
        // $this->middleware('plan.gate:inventory_tracking');
    }

    /**
     * Get current stock levels report.
     */
    public function stockLevels(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'low_stock_only' => 'nullable|boolean',
            'out_of_stock_only' => 'nullable|boolean',
        ]);

        $query = StockLevel::with(['product' => function ($q) {
            $q->select('id', 'name', 'sku', 'category_id', 'min_stock_level', 'price')
                ->with('category:id,name');
        }])
            ->whereHas('product', function ($q) {
                $q->where('track_inventory', true);
            });

        // Filter by category
        if ($request->filled('category_id')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        // Filter by low stock
        if ($request->boolean('low_stock_only')) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('stock_levels.current_stock', '<=', 'products.min_stock_level');
            });
        }

        // Filter by out of stock
        if ($request->boolean('out_of_stock_only')) {
            $query->where('available_stock', '<=', 0);
        }

        $stockLevels = $query->orderBy('current_stock')->get();

        // Calculate summary statistics
        $summary = [
            'total_products' => $stockLevels->count(),
            'total_stock_value' => $stockLevels->sum('total_value'),
            'low_stock_count' => $stockLevels->filter(function ($level) {
                return $level->isLowStock();
            })->count(),
            'out_of_stock_count' => $stockLevels->filter(function ($level) {
                return $level->isOutOfStock();
            })->count(),
            'total_items' => $stockLevels->sum('current_stock'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'stock_levels' => $stockLevels,
                'summary' => $summary,
            ],
            'message' => 'Stock levels report generated successfully'
        ]);
    }

    /**
     * Get inventory movements report.
     */
    public function movements(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'product_id' => 'nullable|exists:products,id',
            'type' => 'nullable|in:sale,purchase,adjustment_in,adjustment_out,transfer_in,transfer_out,return,waste',
            'direction' => 'nullable|in:in,out',
        ]);

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : Carbon::now()->endOfMonth();

        $query = InventoryMovement::with(['product:id,name,sku', 'user:id,name'])
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        // Apply filters
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('direction')) {
            if ($request->direction === 'in') {
                $query->stockIn();
            } else {
                $query->stockOut();
            }
        }

        $movements = $query->orderByDesc('created_at')->get();

        // Get movement summary
        $summary = $this->inventoryService->getMovementSummary($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => [
                'movements' => $movements,
                'summary' => $summary,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
            ],
            'message' => 'Inventory movements report generated successfully'
        ]);
    }

    /**
     * Get inventory valuation report.
     */
    public function valuation(Request $request): JsonResponse
    {
        $request->validate([
            'method' => 'nullable|in:current,fifo,lifo,comparison',
        ]);

        $method = $request->input('method', 'current');

        if ($method === 'comparison') {
            $valuation = $this->cogsService->getInventoryValuationComparison();
        } else {
            $valuation = $this->inventoryService->getInventoryValuation();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'valuation' => $valuation,
                'method' => $method,
                'generated_at' => now(),
            ],
            'message' => 'Inventory valuation report generated successfully'
        ]);
    }

    /**
     * Get COGS analysis report.
     */
    public function cogsAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $cogsSummary = $this->cogsService->getCogsSummary($dateFrom, $dateTo);
        $profitAnalysis = $this->cogsService->getProfitMarginAnalysis($dateFrom, $dateTo);

        return response()->json([
            'success' => true,
            'data' => [
                'cogs_summary' => $cogsSummary,
                'profit_analysis' => $profitAnalysis,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
            ],
            'message' => 'COGS analysis report generated successfully'
        ]);
    }

    /**
     * Get stock aging report.
     */
    public function stockAging(Request $request): JsonResponse
    {
        $query = "
            SELECT
                p.id,
                p.name,
                p.sku,
                sl.current_stock,
                sl.average_cost,
                sl.total_value,
                sl.last_movement_at,
                (julianday('now') - julianday(sl.last_movement_at)) as days_since_last_movement,
                CASE
                    WHEN (julianday('now') - julianday(sl.last_movement_at)) <= 30 THEN 'Fresh (0-30 days)'
                    WHEN (julianday('now') - julianday(sl.last_movement_at)) <= 60 THEN 'Good (31-60 days)'
                    WHEN (julianday('now') - julianday(sl.last_movement_at)) <= 90 THEN 'Aging (61-90 days)'
                    WHEN (julianday('now') - julianday(sl.last_movement_at)) <= 180 THEN 'Old (91-180 days)'
                    ELSE 'Very Old (180+ days)'
                END as aging_category
            FROM products p
            INNER JOIN stock_levels sl ON p.id = sl.product_id
            WHERE p.track_inventory = 1
            AND p.store_id = ?
            AND sl.current_stock > 0
            ORDER BY days_since_last_movement DESC
        ";

        $results = collect(\DB::select($query, [auth()->user()->store_id]));

        // Group by aging category
        $agingGroups = $results->groupBy('aging_category')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_stock' => $group->sum('current_stock'),
                'total_value' => $group->sum('total_value'),
                'products' => $group->values(),
            ];
        });

        $summary = [
            'total_products' => $results->count(),
            'total_stock_value' => $results->sum('total_value'),
            'average_age_days' => $results->avg('days_since_last_movement'),
            'oldest_stock_days' => $results->max('days_since_last_movement'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'aging_groups' => $agingGroups,
                'summary' => $summary,
                'generated_at' => now(),
            ],
            'message' => 'Stock aging report generated successfully'
        ]);
    }

    /**
     * Get stock turnover report.
     */
    public function stockTurnover(Request $request): JsonResponse
    {
        $request->validate([
            'period_months' => 'nullable|integer|min:1|max:12',
        ]);

        $periodMonths = $request->input('period_months', 12);
        $dateFrom = Carbon::now()->subMonths($periodMonths);
        $dateTo = Carbon::now();

        $query = "
            SELECT
                p.id,
                p.name,
                p.sku,
                sl.current_stock,
                sl.average_cost,
                COALESCE(SUM(CASE WHEN im.type = 'sale' THEN im.quantity ELSE 0 END), 0) as total_sold,
                COALESCE(AVG(sl.current_stock), 0) as average_stock,
                CASE
                    WHEN AVG(sl.current_stock) > 0
                    THEN SUM(CASE WHEN im.type = 'sale' THEN im.quantity ELSE 0 END) / AVG(sl.current_stock)
                    ELSE 0
                END as turnover_ratio,
                CASE
                    WHEN SUM(CASE WHEN im.type = 'sale' THEN im.quantity ELSE 0 END) > 0
                    THEN (365 * AVG(sl.current_stock)) / SUM(CASE WHEN im.type = 'sale' THEN im.quantity ELSE 0 END)
                    ELSE 0
                END as days_of_supply
            FROM products p
            INNER JOIN stock_levels sl ON p.id = sl.product_id
            LEFT JOIN inventory_movements im ON p.id = im.product_id
                AND im.created_at BETWEEN ? AND ?
            WHERE p.track_inventory = 1
            AND p.store_id = ?
            GROUP BY p.id, p.name, p.sku, sl.current_stock, sl.average_cost
            ORDER BY turnover_ratio DESC
        ";

        $results = collect(\DB::select($query, [$dateFrom, $dateTo, auth()->user()->store_id]));

        // Categorize turnover performance
        $turnoverCategories = $results->groupBy(function ($item) {
            if ($item->turnover_ratio >= 12) return 'Excellent (12+ turns/year)';
            if ($item->turnover_ratio >= 6) return 'Good (6-12 turns/year)';
            if ($item->turnover_ratio >= 3) return 'Average (3-6 turns/year)';
            if ($item->turnover_ratio >= 1) return 'Slow (1-3 turns/year)';
            return 'Very Slow (<1 turn/year)';
        })->map(function ($group) {
            return [
                'count' => $group->count(),
                'products' => $group->values(),
            ];
        });

        $summary = [
            'total_products' => $results->count(),
            'average_turnover_ratio' => $results->avg('turnover_ratio'),
            'average_days_of_supply' => $results->avg('days_of_supply'),
            'period_months' => $periodMonths,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'turnover_analysis' => $results,
                'turnover_categories' => $turnoverCategories,
                'summary' => $summary,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'months' => $periodMonths,
                ],
            ],
            'message' => 'Stock turnover report generated successfully'
        ]);
    }

    /**
     * Export inventory report to Excel/CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'report_type' => 'required|in:stock_levels,movements,valuation,cogs_analysis,stock_aging,stock_turnover',
            'format' => 'nullable|in:excel,csv',
        ]);

        // This would be implemented with Laravel Excel or similar package
        // For now, return a placeholder response

        return response()->json([
            'success' => false,
            'message' => 'Export functionality will be implemented in a future update',
            'data' => [
                'report_type' => $request->report_type,
                'format' => $request->input('format', 'excel'),
            ]
        ], 501);
    }
}
