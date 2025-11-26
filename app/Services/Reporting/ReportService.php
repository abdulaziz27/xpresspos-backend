<?php

namespace App\Services\Reporting;

use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Member;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Jobs\ExportReportJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Generate sales report with various groupings and filters.
     */
    public function generateSalesReport(
        Carbon $startDate,
        Carbon $endDate,
        string $groupBy = 'day',
        array $filters = []
    ): array {
        $query = Order::withoutGlobalScopes()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->with(['items.product', 'payments', 'user']);

        // Apply filters
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (!empty($filters['outlet_id'])) {
            $query->where('outlet_id', $filters['outlet_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereHas('items.product', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        $orders = $query->get();

        // Group data by specified period
        $groupedData = $this->groupOrdersByPeriod($orders, $groupBy);

        // Calculate summary metrics
        $summary = $this->calculateSalesSummary($orders);

        // Get payment method breakdown
        $paymentBreakdown = $this->getPaymentMethodBreakdown($orders);

        // Get top products
        $topProducts = $this->getTopProducts($orders, 10);

        return [
            'summary' => $summary,
            'timeline' => $groupedData,
            'payment_methods' => $paymentBreakdown,
            'top_products' => $topProducts,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'group_by' => $groupBy,
            ],
            'filters' => $filters,
        ];
    }

    /**
     * Generate inventory report with stock levels and movements.
     */
    public function generateInventoryReport(array $filters = [], bool $includeMovements = false): array
    {
        $query = Product::query()
            ->where('track_inventory', true)
            ->with(['category', 'stockLevel']);

        // Apply filters
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['low_stock_only'])) {
            $query->lowStock();
        }

        $products = $query->get();

        $inventoryData = $products->map(function ($product) use ($includeMovements) {
            $data = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category->name ?? 'Uncategorized',
                'current_stock' => $product->stock,
                'min_stock_level' => $product->min_stock_level,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->price,
                'stock_value' => $product->stock * $product->cost_price,
                'status' => $this->getStockStatus($product),
            ];

            if ($includeMovements) {
                $data['recent_movements'] = $this->getRecentMovements($product->id, 10);
            }

            return $data;
        });

        // Calculate summary
        $summary = [
            'total_products' => $products->count(),
            'low_stock_products' => $products->filter(fn($p) => $p->isLowStock())->count(),
            'out_of_stock_products' => $products->filter(fn($p) => $p->isOutOfStock())->count(),
            'total_stock_value' => $inventoryData->sum('stock_value'),
            'average_stock_level' => $products->avg('stock'),
        ];

        return [
            'summary' => $summary,
            'products' => $inventoryData->values()->toArray(),
            'filters' => $filters,
        ];
    }

    /**
     * Generate cash flow report with sessions and payment methods.
     */
    public function generateCashFlowReport(
        Carbon $startDate,
        Carbon $endDate,
        bool $includeSessions = false
    ): array {
        // Get payments in date range
        $payments = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['order'])
            ->get();

        // Get expenses in date range
        $expenses = Expense::query()
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->with(['user', 'cashSession'])
            ->get();

        // Group by payment method
        $paymentsByMethod = $payments->groupBy('payment_method')->map(function ($methodPayments) {
            return [
                'count' => $methodPayments->count(),
                'total_amount' => $methodPayments->sum('amount'),
                'average_amount' => $methodPayments->avg('amount'),
            ];
        });

        // Group expenses by category
        $expensesByCategory = $expenses->groupBy('category')->map(function ($categoryExpenses) {
            return [
                'count' => $categoryExpenses->count(),
                'total_amount' => $categoryExpenses->sum('amount'),
                'average_amount' => $categoryExpenses->avg('amount'),
            ];
        });

        // Calculate daily cash flow
        $dailyCashFlow = $this->calculateDailyCashFlow($payments, $expenses, $startDate, $endDate);

        $summary = [
            'total_revenue' => $payments->sum('amount'),
            'total_expenses' => $expenses->sum('amount'),
            'net_cash_flow' => $payments->sum('amount') - $expenses->sum('amount'),
            'transaction_count' => $payments->count(),
            'expense_count' => $expenses->count(),
            'average_transaction' => $payments->avg('amount'),
            'average_expense' => $expenses->avg('amount'),
        ];

        $result = [
            'summary' => $summary,
            'daily_flow' => $dailyCashFlow,
            'payment_methods' => $paymentsByMethod,
            'expense_categories' => $expensesByCategory,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ];

        if ($includeSessions) {
            $result['cash_sessions'] = $this->getCashSessionsInPeriod($startDate, $endDate);
        }

        return $result;
    }

    /**
     * Generate product performance report.
     */
    public function generateProductPerformanceReport(
        Carbon $startDate,
        Carbon $endDate,
        int $limit = 20,
        string $sortBy = 'revenue'
    ): array {
        $productStats = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.status', 'completed')
            ->where('orders.store_id', (auth()->user() ?? request()->user())?->store_id)
            ->whereBetween('orders.completed_at', [$startDate, $endDate])
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.price',
                'products.cost_price',
                'categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count'),
                DB::raw('AVG(order_items.unit_price) as average_price'),
            ])
            ->groupBy([
                'products.id',
                'products.name',
                'products.sku',
                'products.price',
                'products.cost_price',
                'categories.name'
            ])
            ->get()
            ->map(function ($item) {
                $profit = $item->total_revenue - ($item->total_quantity * $item->cost_price);
                $profitMargin = $item->total_revenue > 0 ? ($profit / $item->total_revenue) * 100 : 0;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'category' => $item->category_name,
                    'quantity_sold' => (int) $item->total_quantity,
                    'revenue' => (float) $item->total_revenue,
                    'profit' => $profit,
                    'profit_margin' => round($profitMargin, 2),
                    'order_count' => (int) $item->order_count,
                    'average_price' => (float) $item->average_price,
                    'current_price' => (float) $item->price,
                    'cost_price' => (float) $item->cost_price,
                ];
            });

        // Sort by specified criteria
        $sortedProducts = $productStats->sortByDesc($sortBy)->take($limit)->values();

        // Calculate summary
        $summary = [
            'total_products_sold' => $productStats->count(),
            'total_quantity' => $productStats->sum('quantity_sold'),
            'total_revenue' => $productStats->sum('revenue'),
            'total_profit' => $productStats->sum('profit'),
            'average_profit_margin' => $productStats->avg('profit_margin'),
        ];

        return [
            'summary' => $summary,
            'products' => $sortedProducts->toArray(),
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'sort_by' => $sortBy,
            'limit' => $limit,
        ];
    }

    /**
     * Generate customer analytics report.
     */
    public function generateCustomerAnalyticsReport(
        Carbon $startDate,
        Carbon $endDate,
        bool $includeLoyalty = false
    ): array {
        // Get customer statistics
        $customerStats = DB::table('orders')
            ->leftJoin('members', 'orders.member_id', '=', 'members.id')
            ->where('orders.status', 'completed')
            ->where('orders.store_id', (auth()->user() ?? request()->user())?->store_id)
            ->whereBetween('orders.completed_at', [$startDate, $endDate])
            ->select([
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('COUNT(DISTINCT orders.member_id) as unique_customers'),
                DB::raw('COUNT(CASE WHEN orders.member_id IS NULL THEN 1 END) as guest_orders'),
                DB::raw('COUNT(CASE WHEN orders.member_id IS NOT NULL THEN 1 END) as member_orders'),
                DB::raw('SUM(orders.total_amount) as total_revenue'),
                DB::raw('AVG(orders.total_amount) as average_order_value'),
            ])
            ->first();

        // Get top customers
        $topCustomers = DB::table('orders')
            ->join('members', 'orders.member_id', '=', 'members.id')
            ->where('orders.status', 'completed')
            ->where('orders.store_id', (auth()->user() ?? request()->user())?->store_id)
            ->whereBetween('orders.completed_at', [$startDate, $endDate])
            ->select([
                'members.id',
                'members.name',
                'members.email',
                'members.phone',
                DB::raw('COUNT(orders.id) as order_count'),
                DB::raw('SUM(orders.total_amount) as total_spent'),
                DB::raw('AVG(orders.total_amount) as average_order'),
                DB::raw('MAX(orders.completed_at) as last_order_date'),
            ])
            ->groupBy(['members.id', 'members.name', 'members.email', 'members.phone'])
            ->orderByDesc('total_spent')
            ->limit(20)
            ->get();

        // Calculate customer segments
        $segments = $this->calculateCustomerSegments($startDate, $endDate);

        $result = [
            'summary' => [
                'total_orders' => (int) $customerStats->total_orders,
                'unique_customers' => (int) $customerStats->unique_customers,
                'guest_orders' => (int) $customerStats->guest_orders,
                'member_orders' => (int) $customerStats->member_orders,
                'member_percentage' => $customerStats->total_orders > 0
                    ? round(($customerStats->member_orders / $customerStats->total_orders) * 100, 2)
                    : 0,
                'total_revenue' => (float) $customerStats->total_revenue,
                'average_order_value' => (float) $customerStats->average_order_value,
            ],
            'top_customers' => $topCustomers->toArray(),
            'segments' => $segments,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ];

        if ($includeLoyalty) {
            $result['loyalty_stats'] = $this->getLoyaltyStats($startDate, $endDate);
        }

        return $result;
    }

    /**
     * Generate dashboard summary for different periods.
     */
    public function generateDashboardSummary(string $period = 'today'): array
    {
        [$startDate, $endDate] = $this->getPeriodDates($period);

        // Get basic metrics
        $orders = Order::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get();

        $payments = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        // Calculate metrics
        $revenue = $payments->sum('amount');
        $totalExpenses = $expenses->sum('amount');
        $profit = $revenue - $totalExpenses;
        $orderCount = $orders->count();
        $averageOrderValue = $orderCount > 0 ? $revenue / $orderCount : 0;

        // Get comparison with previous period
        $previousPeriod = $this->getPreviousPeriodData($period);

        return [
            'current_period' => [
                'revenue' => $revenue,
                'profit' => $profit,
                'expenses' => $totalExpenses,
                'orders' => $orderCount,
                'average_order_value' => $averageOrderValue,
                'customers' => $orders->whereNotNull('member_id')->unique('member_id')->count(),
            ],
            'previous_period' => $previousPeriod,
            'growth' => $this->calculateGrowthMetrics($revenue, $profit, $orderCount, $previousPeriod),
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Generate sales trend analysis with forecasting.
     */
    public function generateSalesTrendAnalysis(
        Carbon $startDate,
        Carbon $endDate,
        string $groupBy = 'day'
    ): array {
        $orders = Order::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get();

        // Group sales by period
        $salesData = $this->groupOrdersByPeriod($orders, $groupBy);

        // Calculate trends
        $trends = $this->calculateTrends($salesData);

        // Generate forecast for next periods
        $forecast = $this->generateForecast($salesData, 7); // Next 7 periods

        // Calculate seasonality patterns
        $seasonality = $this->analyzeSeasonality($orders, $groupBy);

        return [
            'historical_data' => $salesData,
            'trends' => $trends,
            'forecast' => $forecast,
            'seasonality' => $seasonality,
            'insights' => $this->generateTrendInsights($trends, $salesData),
        ];
    }

    /**
     * Generate product performance analytics with ABC analysis.
     */
    public function generateProductAnalytics(
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $productStats = $this->getProductPerformanceData($startDate, $endDate);

        // ABC Analysis (80/20 rule)
        $abcAnalysis = $this->performAbcAnalysis($productStats);

        // Product lifecycle analysis
        $lifecycleAnalysis = $this->analyzeProductLifecycle($productStats);

        // Cross-selling analysis
        $crossSellingData = $this->analyzeCrossSelling($startDate, $endDate);

        // Price elasticity analysis
        $priceElasticity = $this->analyzePriceElasticity($startDate, $endDate);

        return [
            'abc_analysis' => $abcAnalysis,
            'lifecycle_analysis' => $lifecycleAnalysis,
            'cross_selling' => $crossSellingData,
            'price_elasticity' => $priceElasticity,
            'recommendations' => $this->generateProductRecommendations($abcAnalysis, $lifecycleAnalysis),
        ];
    }

    /**
     * Generate customer behavior analytics with segmentation.
     */
    public function generateCustomerBehaviorAnalytics(
        Carbon $startDate,
        Carbon $endDate
    ): array {
        // RFM Analysis (Recency, Frequency, Monetary)
        $rfmAnalysis = $this->performRfmAnalysis($startDate, $endDate);

        // Customer lifetime value
        $clvAnalysis = $this->calculateCustomerLifetimeValue($startDate, $endDate);

        // Churn prediction
        $churnAnalysis = $this->analyzeCustomerChurn($startDate, $endDate);

        // Purchase patterns
        $purchasePatterns = $this->analyzePurchasePatterns($startDate, $endDate);

        // Customer journey analysis
        $journeyAnalysis = $this->analyzeCustomerJourney($startDate, $endDate);

        return [
            'rfm_analysis' => $rfmAnalysis,
            'customer_lifetime_value' => $clvAnalysis,
            'churn_analysis' => $churnAnalysis,
            'purchase_patterns' => $purchasePatterns,
            'customer_journey' => $journeyAnalysis,
            'segments' => $this->generateCustomerSegments($rfmAnalysis),
        ];
    }

    /**
     * Generate profitability analysis.
     */
    public function generateProfitabilityAnalysis(
        Carbon $startDate,
        Carbon $endDate
    ): array {
        // Gross margin analysis
        $grossMarginAnalysis = $this->analyzeGrossMargins($startDate, $endDate);

        // Cost analysis
        $costAnalysis = $this->analyzeCosts($startDate, $endDate);

        // Profit center analysis
        $profitCenterAnalysis = $this->analyzeProfitCenters($startDate, $endDate);

        // Break-even analysis
        $breakEvenAnalysis = $this->calculateBreakEvenPoints($startDate, $endDate);

        return [
            'gross_margins' => $grossMarginAnalysis,
            'cost_analysis' => $costAnalysis,
            'profit_centers' => $profitCenterAnalysis,
            'break_even' => $breakEvenAnalysis,
            'profitability_trends' => $this->analyzeProfitabilityTrends($startDate, $endDate),
        ];
    }

    /**
     * Generate operational efficiency metrics.
     */
    public function generateOperationalEfficiencyMetrics(
        Carbon $startDate,
        Carbon $endDate
    ): array {
        // Staff performance metrics
        $staffPerformance = $this->analyzeStaffPerformance($startDate, $endDate);

        // Peak hours analysis
        $peakHoursAnalysis = $this->analyzePeakHours($startDate, $endDate);

        // Table turnover analysis
        $tableTurnover = $this->analyzeTableTurnover($startDate, $endDate);

        // Service efficiency metrics
        $serviceEfficiency = $this->analyzeServiceEfficiency($startDate, $endDate);

        return [
            'staff_performance' => $staffPerformance,
            'peak_hours' => $peakHoursAnalysis,
            'table_turnover' => $tableTurnover,
            'service_efficiency' => $serviceEfficiency,
            'efficiency_recommendations' => $this->generateEfficiencyRecommendations($staffPerformance, $peakHoursAnalysis),
        ];
    }

    /**
     * Queue report export job.
     */
    public function exportReport(
        string $reportType,
        string $format,
        array $parameters,
        string $userId
    ): void {
        ExportReportJob::dispatch($reportType, $format, $parameters, $userId);
    }

    // Private helper methods...

    private function groupOrdersByPeriod(Collection $orders, string $groupBy): array
    {
        return $orders->groupBy(function ($order) use ($groupBy) {
            $date = Carbon::parse($order->completed_at);
            return match ($groupBy) {
                'week' => $date->startOfWeek()->toDateString(),
                'month' => $date->format('Y-m'),
                default => $date->toDateString(),
            };
        })->map(function ($periodOrders) {
            return [
                'orders' => $periodOrders->count(),
                'revenue' => $periodOrders->sum('total_amount'),
                'items' => $periodOrders->sum('total_items'),
                'customers' => $periodOrders->whereNotNull('member_id')->unique('member_id')->count(),
            ];
        })->toArray();
    }

    private function calculateSalesSummary(Collection $orders): array
    {
        return [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total_amount'),
            'total_items' => $orders->sum('total_items'),
            'average_order_value' => $orders->avg('total_amount'),
            'unique_customers' => $orders->whereNotNull('member_id')->unique('member_id')->count(),
        ];
    }

    private function getPaymentMethodBreakdown(Collection $orders): array
    {
        $payments = $orders->flatMap->payments;

        return $payments->groupBy('payment_method')->map(function ($methodPayments) {
            return [
                'count' => $methodPayments->count(),
                'amount' => $methodPayments->sum('amount'),
                'percentage' => 0, // Will be calculated after all methods
            ];
        })->toArray();
    }

    private function getTopProducts(Collection $orders, int $limit): array
    {
        $items = $orders->flatMap->items;

        return $items->groupBy('product_id')
            ->map(function ($productItems) {
                $product = $productItems->first()->product;
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $productItems->sum('quantity'),
                    'revenue' => $productItems->sum('total_price'),
                ];
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->toArray();
    }

    private function getStockStatus(Product $product): string
    {
        if ($product->isOutOfStock()) {
            return 'out_of_stock';
        }

        if ($product->isLowStock()) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * @deprecated This method uses product_id for inventory_movements which is no longer valid.
     * Use inventory_item_id instead.
     */
    private function getRecentMovements(string $productId, int $limit): array
    {
        throw new \Exception(
            'ReportService::getRecentMovements() with product_id is deprecated. ' .
            'Product-based inventory reports are deprecated due to inventory refactor. ' .
            'Use inventory-item-based reports instead.'
        );
    }

    /**
     * Get recent movements for an inventory item.
     */
    private function getRecentMovementsForInventoryItem(string $inventoryItemId, int $limit): array
    {
        return InventoryMovement::where('inventory_item_id', $inventoryItemId)
            ->with(['user'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($movement) {
                return [
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'reason' => $movement->reason,
                    'user' => $movement->user->name ?? 'System',
                    'date' => $movement->created_at->toDateTimeString(),
                ];
            })
            ->toArray();
    }

    private function calculateDailyCashFlow(
        Collection $payments,
        Collection $expenses,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $dailyFlow = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayPayments = $payments->filter(function ($payment) use ($current) {
                return Carbon::parse($payment->created_at)->isSameDay($current);
            });

            $dayExpenses = $expenses->filter(function ($expense) use ($current) {
                return Carbon::parse($expense->expense_date)->isSameDay($current);
            });

            $dailyFlow[$current->toDateString()] = [
                'revenue' => $dayPayments->sum('amount'),
                'expenses' => $dayExpenses->sum('amount'),
                'net_flow' => $dayPayments->sum('amount') - $dayExpenses->sum('amount'),
            ];

            $current->addDay();
        }

        return $dailyFlow;
    }

    private function getCashSessionsInPeriod(Carbon $startDate, Carbon $endDate): array
    {
        return CashSession::whereBetween('opened_at', [$startDate, $endDate])
            ->with(['user'])
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'user' => $session->user->name,
                    'opening_balance' => $session->opening_balance,
                    'closing_balance' => $session->closing_balance,
                    'expected_balance' => $session->expected_balance,
                    'variance' => $session->variance,
                    'status' => $session->status,
                    'opened_at' => $session->opened_at,
                    'closed_at' => $session->closed_at,
                ];
            })
            ->toArray();
    }

    private function calculateCustomerSegments(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation for customer segmentation
        return [
            'new_customers' => 0,
            'returning_customers' => 0,
            'vip_customers' => 0,
        ];
    }

    private function getLoyaltyStats(Carbon $startDate, Carbon $endDate): array
    {
        // Implementation for loyalty statistics
        return [
            'points_earned' => 0,
            'points_redeemed' => 0,
            'active_members' => 0,
        ];
    }

    private function getPeriodDates(string $period): array
    {
        return match ($period) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function getPreviousPeriodData(string $period): array
    {
        // Implementation for previous period comparison
        return [
            'revenue' => 0,
            'profit' => 0,
            'orders' => 0,
        ];
    }

    private function calculateGrowthMetrics(
        float $currentRevenue,
        float $currentProfit,
        int $currentOrders,
        array $previousPeriod
    ): array {
        return [
            'revenue_growth' => $this->calculateGrowthPercentage($currentRevenue, $previousPeriod['revenue']),
            'profit_growth' => $this->calculateGrowthPercentage($currentProfit, $previousPeriod['profit']),
            'orders_growth' => $this->calculateGrowthPercentage($currentOrders, $previousPeriod['orders']),
        ];
    }

    private function calculateGrowthPercentage(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    // Business Intelligence Methods

    private function calculateTrends(array $salesData): array
    {
        if (empty($salesData)) {
            return [
                'trend' => 'insufficient_data',
                'slope' => 0,
                'correlation' => 0,
                'intercept' => 0,
                'strength' => 'none'
            ];
        }

        $values = array_values(array_map(fn($data) => $data['revenue'], $salesData));
        $count = count($values);

        if ($count < 2) {
            return [
                'trend' => 'insufficient_data',
                'slope' => 0,
                'correlation' => 0,
                'intercept' => 0,
                'strength' => 'none'
            ];
        }

        // Calculate linear regression
        $x = range(1, $count);
        $sumX = array_sum($x);
        $sumY = array_sum($values);
        $sumXY = array_sum(array_map(fn($i) => $x[$i] * $values[$i], range(0, $count - 1)));
        $sumX2 = array_sum(array_map(fn($val) => $val * $val, $x));

        $denominator = ($count * $sumX2 - $sumX * $sumX);
        $slope = $denominator != 0 ? ($count * $sumXY - $sumX * $sumY) / $denominator : 0;
        $intercept = $count > 0 ? ($sumY - $slope * $sumX) / $count : 0;

        // Calculate correlation coefficient
        $meanX = $sumX / $count;
        $meanY = $sumY / $count;

        $numerator = array_sum(array_map(fn($i) => ($x[$i] - $meanX) * ($values[$i] - $meanY), range(0, $count - 1)));
        $denomX = sqrt(array_sum(array_map(fn($val) => pow($val - $meanX, 2), $x)));
        $denomY = sqrt(array_sum(array_map(fn($val) => pow($val - $meanY, 2), $values)));

        $correlation = ($denomX * $denomY != 0) ? $numerator / ($denomX * $denomY) : 0;

        return [
            'trend' => $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable'),
            'slope' => round($slope, 2),
            'intercept' => round($intercept, 2),
            'correlation' => round($correlation, 3),
            'strength' => abs($correlation) > 0.7 ? 'strong' : (abs($correlation) > 0.3 ? 'moderate' : 'weak'),
        ];
    }

    private function generateForecast(array $salesData, int $periods): array
    {
        $values = array_values(array_map(fn($data) => $data['revenue'], $salesData));
        $count = count($values);

        if ($count < 3) {
            return [];
        }

        // Simple moving average forecast
        $windowSize = min(3, $count);
        $recentValues = array_slice($values, -$windowSize);
        $average = array_sum($recentValues) / $windowSize;

        // Calculate trend from recent data
        $recentSalesData = array_slice($salesData, -$windowSize);
        $recentTrend = $this->calculateTrends($recentSalesData);

        $forecast = [];
        for ($i = 1; $i <= $periods; $i++) {
            $forecastValue = $average + ($recentTrend['slope'] * $i);
            $forecast[] = [
                'period' => $i,
                'forecast_revenue' => max(0, round($forecastValue, 2)),
                'confidence' => max(0.1, 1 - ($i * 0.1)), // Decreasing confidence
            ];
        }

        return $forecast;
    }

    private function analyzeSeasonality(Collection $orders, string $groupBy): array
    {
        $seasonalData = [];

        foreach ($orders as $order) {
            $date = Carbon::parse($order->completed_at);
            $key = match ($groupBy) {
                'day' => $date->format('H'), // Hour of day
                'week' => $date->format('N'), // Day of week
                'month' => $date->format('j'), // Day of month
                default => $date->format('H'),
            };

            if (!isset($seasonalData[$key])) {
                $seasonalData[$key] = ['count' => 0, 'revenue' => 0];
            }

            $seasonalData[$key]['count']++;
            $seasonalData[$key]['revenue'] += $order->total_amount;
        }

        // Calculate averages and identify patterns
        $totalRevenue = array_sum(array_column($seasonalData, 'revenue'));
        $avgRevenue = count($seasonalData) > 0 ? $totalRevenue / count($seasonalData) : 0;

        foreach ($seasonalData as $key => &$data) {
            $data['avg_revenue'] = $data['count'] > 0 ? $data['revenue'] / $data['count'] : 0;
            $data['variance_from_avg'] = $avgRevenue > 0 ? (($data['revenue'] - $avgRevenue) / $avgRevenue) * 100 : 0;
        }

        return $seasonalData;
    }

    private function generateTrendInsights(array $trends, array $salesData): array
    {
        $insights = [];

        if ($trends['trend'] === 'increasing' && $trends['strength'] === 'strong') {
            $insights[] = 'Strong upward sales trend detected. Consider increasing inventory and staff during peak periods.';
        } elseif ($trends['trend'] === 'decreasing' && $trends['strength'] === 'strong') {
            $insights[] = 'Declining sales trend detected. Review marketing strategies and customer feedback.';
        }

        if (empty($salesData)) {
            $insights[] = 'Insufficient data for trend analysis. Consider expanding the date range or generating more sales activity.';
            return $insights;
        }

        $lastEntry = end($salesData);
        $recentRevenue = $lastEntry ? $lastEntry['revenue'] : 0;
        $totalRevenue = array_sum(array_column($salesData, 'revenue'));
        $avgRevenue = count($salesData) > 0 ? $totalRevenue / count($salesData) : 0;

        if ($avgRevenue > 0) {
            if ($recentRevenue > $avgRevenue * 1.2) {
                $insights[] = 'Recent performance is significantly above average. Analyze successful factors for replication.';
            } elseif ($recentRevenue < $avgRevenue * 0.8) {
                $insights[] = 'Recent performance is below average. Consider promotional activities or operational improvements.';
            }
        }

        return $insights;
    }

    private function getProductPerformanceData(Carbon $startDate, Carbon $endDate): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.status', 'completed')
            ->where('orders.store_id', (auth()->user() ?? request()->user())?->store_id)
            ->whereBetween('orders.completed_at', [$startDate, $endDate])
            ->select([
                'products.id',
                'products.name',
                'products.cost_price',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as order_frequency'),
            ])
            ->groupBy(['products.id', 'products.name', 'products.cost_price'])
            ->get();
    }

    private function performAbcAnalysis(Collection $productStats): array
    {
        $sortedProducts = $productStats->sortByDesc('total_revenue');
        $totalRevenue = $sortedProducts->sum('total_revenue');

        $cumulativeRevenue = 0;
        $categories = ['A' => [], 'B' => [], 'C' => []];

        foreach ($sortedProducts as $product) {
            $cumulativeRevenue += $product->total_revenue;
            $cumulativePercentage = ($cumulativeRevenue / $totalRevenue) * 100;

            if ($cumulativePercentage <= 80) {
                $categories['A'][] = $product;
            } elseif ($cumulativePercentage <= 95) {
                $categories['B'][] = $product;
            } else {
                $categories['C'][] = $product;
            }
        }

        return [
            'categories' => $categories,
            'summary' => [
                'A_products' => count($categories['A']),
                'B_products' => count($categories['B']),
                'C_products' => count($categories['C']),
                'A_revenue_percentage' => 80,
                'B_revenue_percentage' => 15,
                'C_revenue_percentage' => 5,
            ],
        ];
    }

    private function analyzeProductLifecycle(Collection $productStats): array
    {
        $lifecycle = [];

        foreach ($productStats as $product) {
            $revenuePerOrder = $product->order_frequency > 0 ? $product->total_revenue / $product->order_frequency : 0;
            $profit = $product->total_revenue - ($product->total_quantity * $product->cost_price);
            $profitMargin = $product->total_revenue > 0 ? ($profit / $product->total_revenue) * 100 : 0;

            // Classify product lifecycle stage
            $stage = 'mature';
            if ($product->order_frequency < 5) {
                $stage = 'introduction';
            } elseif ($profitMargin > 30 && $product->order_frequency > 20) {
                $stage = 'growth';
            } elseif ($profitMargin < 10) {
                $stage = 'decline';
            }

            $lifecycle[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'stage' => $stage,
                'revenue_per_order' => round($revenuePerOrder, 2),
                'profit_margin' => round($profitMargin, 2),
                'order_frequency' => $product->order_frequency,
            ];
        }

        return $lifecycle;
    }

    private function analyzeCrossSelling(Carbon $startDate, Carbon $endDate): array
    {
        // Get orders with multiple items
        $crossSellingData = DB::table('orders')
            ->join('order_items as oi1', 'orders.id', '=', 'oi1.order_id')
            ->join('order_items as oi2', function ($join) {
                $join->on('orders.id', '=', 'oi2.order_id')
                    ->where('oi1.product_id', '!=', DB::raw('oi2.product_id'));
            })
            ->join('products as p1', 'oi1.product_id', '=', 'p1.id')
            ->join('products as p2', 'oi2.product_id', '=', 'p2.id')
            ->where('orders.status', 'completed')
            ->where('orders.store_id', (auth()->user() ?? request()->user())?->store_id)
            ->whereBetween('orders.completed_at', [$startDate, $endDate])
            ->select([
                'p1.name as product_1',
                'p2.name as product_2',
                DB::raw('COUNT(*) as frequency'),
            ])
            ->groupBy(['p1.name', 'p2.name'])
            ->orderByDesc('frequency')
            ->limit(20)
            ->get();

        return $crossSellingData->toArray();
    }

    private function analyzePriceElasticity(Carbon $startDate, Carbon $endDate): array
    {
        // This is a simplified price elasticity analysis
        // In a real implementation, you'd need historical price changes
        return [
            'analysis' => 'Price elasticity analysis requires historical price change data',
            'recommendation' => 'Implement A/B testing for price optimization',
        ];
    }

    private function generateProductRecommendations(array $abcAnalysis, array $lifecycleAnalysis): array
    {
        $recommendations = [];

        // ABC Analysis recommendations
        if (count($abcAnalysis['categories']['A']) > 0) {
            $recommendations[] = 'Focus inventory management on Category A products (80% of revenue)';
        }

        if (count($abcAnalysis['categories']['C']) > count($abcAnalysis['categories']['A']) * 2) {
            $recommendations[] = 'Consider discontinuing some Category C products to reduce complexity';
        }

        // Lifecycle recommendations
        $introductionProducts = array_filter($lifecycleAnalysis, fn($p) => $p['stage'] === 'introduction');
        $declineProducts = array_filter($lifecycleAnalysis, fn($p) => $p['stage'] === 'decline');

        if (count($introductionProducts) > 0) {
            $recommendations[] = 'Increase marketing for ' . count($introductionProducts) . ' products in introduction stage';
        }

        if (count($declineProducts) > 0) {
            $recommendations[] = 'Review pricing or consider phasing out ' . count($declineProducts) . ' declining products';
        }

        return $recommendations;
    }

    private function performRfmAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $user = auth()->user() ?? request()->user();
        $storeId = $user?->store_id;

        if (!$storeId) {
            return [
                'rfm_analysis' => [],
                'customer_lifetime_value' => [],
                'churn_analysis' => [],
                'purchase_patterns' => [],
                'customer_journey' => [],
                'segments' => [],
            ];
        }

        $customerData = DB::table('orders')
            ->join('members', 'orders.member_id', '=', 'members.id')
            ->where('orders.status', 'completed')
            ->where('orders.store_id', $storeId)
            ->whereBetween('orders.completed_at', [$startDate, $endDate])
            ->select([
                'members.id',
                'members.name',
                DB::raw('MAX(orders.completed_at) as last_order_date'),
                DB::raw('COUNT(orders.id) as frequency'),
                DB::raw('SUM(orders.total_amount) as monetary'),
            ])
            ->groupBy(['members.id', 'members.name'])
            ->get();

        $rfmScores = [];
        $now = now();

        foreach ($customerData as $customer) {
            $recency = $now->diffInDays(Carbon::parse($customer->last_order_date));

            // Score calculation (1-5 scale)
            $recencyScore = $recency <= 30 ? 5 : ($recency <= 60 ? 4 : ($recency <= 90 ? 3 : ($recency <= 180 ? 2 : 1)));
            $frequencyScore = $customer->frequency >= 10 ? 5 : ($customer->frequency >= 5 ? 4 : ($customer->frequency >= 3 ? 3 : ($customer->frequency >= 2 ? 2 : 1)));
            $monetaryScore = $customer->monetary >= 1000 ? 5 : ($customer->monetary >= 500 ? 4 : ($customer->monetary >= 200 ? 3 : ($customer->monetary >= 100 ? 2 : 1)));

            $segment = $this->determineRfmSegment($recencyScore, $frequencyScore, $monetaryScore);

            $rfmScores[] = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'recency' => $recency,
                'frequency' => $customer->frequency,
                'monetary' => $customer->monetary,
                'recency_score' => $recencyScore,
                'frequency_score' => $frequencyScore,
                'monetary_score' => $monetaryScore,
                'rfm_score' => $recencyScore . $frequencyScore . $monetaryScore,
                'segment' => $segment,
            ];
        }

        return $rfmScores;
    }

    private function determineRfmSegment(int $r, int $f, int $m): string
    {
        if ($r >= 4 && $f >= 4 && $m >= 4) return 'Champions';
        if ($r >= 3 && $f >= 3 && $m >= 4) return 'Loyal Customers';
        if ($r >= 4 && $f <= 2 && $m >= 3) return 'Potential Loyalists';
        if ($r >= 4 && $f <= 2 && $m <= 2) return 'New Customers';
        if ($r >= 3 && $f >= 3 && $m <= 3) return 'Promising';
        if ($r <= 2 && $f >= 3 && $m >= 3) return 'Need Attention';
        if ($r <= 2 && $f <= 2 && $m >= 4) return 'About to Sleep';
        if ($r <= 2 && $f >= 3 && $m <= 2) return 'At Risk';
        if ($r <= 1 && $f <= 2 && $m <= 2) return 'Lost';

        return 'Others';
    }

    private function calculateCustomerLifetimeValue(Carbon $startDate, Carbon $endDate): array
    {
        // Simplified CLV calculation
        $user = auth()->user() ?? request()->user();
        $storeId = $user?->store_id;

        if (!$storeId) {
            return [];
        }

        $customerMetrics = DB::table('orders')
            ->join('members', 'orders.member_id', '=', 'members.id')
            ->where('orders.status', 'completed')
            ->where('orders.store_id', $storeId)
            ->select([
                'members.id',
                'members.name',
                DB::raw('AVG(orders.total_amount) as avg_order_value'),
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('DATEDIFF(MAX(orders.completed_at), MIN(orders.completed_at)) as customer_lifespan_days'),
                DB::raw('SUM(orders.total_amount) as total_spent'),
            ])
            ->groupBy(['members.id', 'members.name'])
            ->having('total_orders', '>', 1)
            ->get();

        $clvData = [];
        foreach ($customerMetrics as $customer) {
            $purchaseFrequency = $customer->customer_lifespan_days > 0 ?
                $customer->total_orders / ($customer->customer_lifespan_days / 365) : 0;

            $clv = $customer->avg_order_value * $purchaseFrequency * 2; // Assuming 2-year lifespan

            $clvData[] = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'avg_order_value' => round($customer->avg_order_value, 2),
                'purchase_frequency' => round($purchaseFrequency, 2),
                'customer_lifespan_days' => $customer->customer_lifespan_days,
                'estimated_clv' => round($clv, 2),
                'total_spent' => $customer->total_spent,
            ];
        }

        return $clvData;
    }

    private function analyzeCustomerChurn(Carbon $startDate, Carbon $endDate): array
    {
        // Simple churn analysis based on last order date
        $churnThreshold = 90; // days

        $user = auth()->user() ?? request()->user();
        $storeId = $user?->store_id;

        if (!$storeId) {
            return [
                'customers' => [],
                'churn_rate' => 0,
                'at_risk_customers' => 0,
            ];
        }

        $customerActivity = DB::table('members')
            ->leftJoin('orders', function ($join) {
                $join->on('members.id', '=', 'orders.member_id')
                    ->where('orders.status', 'completed');
            })
            ->where('members.store_id', $storeId)
            ->select([
                'members.id',
                'members.name',
                'members.created_at as registration_date',
                DB::raw('MAX(orders.completed_at) as last_order_date'),
                DB::raw('COUNT(orders.id) as total_orders'),
            ])
            ->groupBy(['members.id', 'members.name', 'members.created_at'])
            ->get();

        $churnAnalysis = [
            'active' => 0,
            'at_risk' => 0,
            'churned' => 0,
            'customers' => [],
        ];

        $now = now();
        foreach ($customerActivity as $customer) {
            $daysSinceLastOrder = $customer->last_order_date ?
                $now->diffInDays(Carbon::parse($customer->last_order_date)) :
                $now->diffInDays(Carbon::parse($customer->registration_date));

            $status = 'active';
            if ($daysSinceLastOrder > $churnThreshold * 2) {
                $status = 'churned';
                $churnAnalysis['churned']++;
            } elseif ($daysSinceLastOrder > $churnThreshold) {
                $status = 'at_risk';
                $churnAnalysis['at_risk']++;
            } else {
                $churnAnalysis['active']++;
            }

            $churnAnalysis['customers'][] = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'days_since_last_order' => $daysSinceLastOrder,
                'total_orders' => $customer->total_orders,
                'status' => $status,
            ];
        }

        return $churnAnalysis;
    }

    private function analyzePurchasePatterns(Carbon $startDate, Carbon $endDate): array
    {
        $user = auth()->user() ?? request()->user();
        $storeId = $user?->store_id;

        if (!$storeId) {
            return [
                'hourly_patterns' => [],
                'daily_patterns' => [],
            ];
        }

        // Analyze purchase patterns by time
        $hourlyPatterns = DB::table('orders')
            ->where('status', 'completed')
            ->where('store_id', $storeId)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->select([
                DB::raw('HOUR(completed_at) as hour'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('AVG(total_amount) as avg_order_value'),
            ])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $dailyPatterns = DB::table('orders')
            ->where('status', 'completed')
            ->where('store_id', $storeId)
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->select([
                DB::raw('DAYOFWEEK(completed_at) as day_of_week'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('AVG(total_amount) as avg_order_value'),
            ])
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get();

        return [
            'hourly_patterns' => $hourlyPatterns->toArray(),
            'daily_patterns' => $dailyPatterns->toArray(),
        ];
    }

    private function analyzeCustomerJourney(Carbon $startDate, Carbon $endDate): array
    {
        // Simplified customer journey analysis
        return [
            'analysis' => 'Customer journey analysis requires detailed interaction tracking',
            'recommendation' => 'Implement customer touchpoint tracking for detailed journey analysis',
        ];
    }

    private function generateCustomerSegments(array $rfmAnalysis): array
    {
        $segments = [];
        foreach ($rfmAnalysis as $customer) {
            $segment = $customer['segment'];
            if (!isset($segments[$segment])) {
                $segments[$segment] = [
                    'count' => 0,
                    'total_monetary' => 0,
                    'avg_frequency' => 0,
                    'customers' => [],
                ];
            }

            $segments[$segment]['count']++;
            $segments[$segment]['total_monetary'] += $customer['monetary'];
            $segments[$segment]['avg_frequency'] += $customer['frequency'];
            $segments[$segment]['customers'][] = $customer;
        }

        // Calculate averages
        foreach ($segments as $segment => &$data) {
            $data['avg_monetary'] = $data['count'] > 0 ? $data['total_monetary'] / $data['count'] : 0;
            $data['avg_frequency'] = $data['count'] > 0 ? $data['avg_frequency'] / $data['count'] : 0;
        }

        return $segments;
    }

    // Placeholder methods for other BI features
    private function analyzeGrossMargins(Carbon $startDate, Carbon $endDate): array
    {
        return ['message' => 'Gross margin analysis implementation pending'];
    }

    private function analyzeCosts(Carbon $startDate, Carbon $endDate): array
    {
        return ['message' => 'Cost analysis implementation pending'];
    }

    private function analyzeProfitCenters(Carbon $startDate, Carbon $endDate): array
    {
        return ['message' => 'Profit center analysis implementation pending'];
    }

    private function calculateBreakEvenPoints(Carbon $startDate, Carbon $endDate): array
    {
        return ['message' => 'Break-even analysis implementation pending'];
    }

    private function analyzeProfitabilityTrends(Carbon $startDate, Carbon $endDate): array
    {
        return ['message' => 'Profitability trends analysis implementation pending'];
    }

    private function analyzeStaffPerformance(Carbon $startDate, Carbon $endDate): array
    {
        return ['message' => 'Staff performance analysis implementation pending'];
    }

    private function analyzePeakHours(Carbon $startDate, Carbon $endDate): array
    {
        return ['message' => 'Peak hours analysis implementation pending'];
    }

    private function analyzeTableTurnover(Carbon $startDate, Carbon $endDate): array
    {
        return ['message' => 'Table turnover analysis implementation pending'];
    }

    private function analyzeServiceEfficiency(Carbon $startDate, Carbon $endDate): array
    {
        return ['message' => 'Service efficiency analysis implementation pending'];
    }

    private function generateEfficiencyRecommendations(array $staffPerformance, array $peakHoursAnalysis): array
    {
        return ['message' => 'Efficiency recommendations implementation pending'];
    }

    /**
     * Generate sales recap grouped by payment method and operation mode.
     */
    public function generateSalesRecap(Carbon $startDate, Carbon $endDate): array
    {
        $storeId = request()->user()->store_id;

        // Get payment method breakdown
        $paymentMethods = Payment::where('payments.store_id', $storeId)
            ->where('payments.status', 'completed')
            ->whereBetween('payments.created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'payment_method' => $item->payment_method,
                    'count' => (int) $item->count,
                    'total_amount' => (float) $item->total_amount,
                ];
            });

        // Get operation mode breakdown
        $operationModes = Order::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select('operation_mode', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total_amount'))
            ->groupBy('operation_mode')
            ->get()
            ->map(function ($item) {
                return [
                    'operation_mode' => $item->operation_mode,
                    'count' => (int) $item->count,
                    'total_amount' => (float) $item->total_amount,
                ];
            });

        // Calculate totals
        $totalCash = $paymentMethods->where('payment_method', 'cash')->sum('total_amount');
        $totalNonCash = $paymentMethods->whereNotIn('payment_method', ['cash'])->sum('total_amount');
        $totalTransactions = $paymentMethods->sum('count');
        $grandTotal = $paymentMethods->sum('total_amount');

        return [
            'payment_methods' => $paymentMethods->values()->toArray(),
            'operation_modes' => $operationModes->values()->toArray(),
            'totals' => [
                'total_transactions' => $totalTransactions,
                'total_cash' => $totalCash,
                'total_non_cash' => $totalNonCash,
                'grand_total' => $grandTotal,
            ],
        ];
    }

    /**
     * Generate best sellers report (products and categories).
     */
    public function generateBestSellersReport(Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        $storeId = request()->user()->store_id;

        // Get best selling products
        $products = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.completed_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.sku',
                'products.image',
                'categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as total_quantity_sold'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count')
            )
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.image', 'categories.name')
            ->orderByDesc('total_quantity_sold')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'sku' => $item->sku,
                    'category_name' => $item->category_name,
                    'total_quantity_sold' => (int) $item->total_quantity_sold,
                    'total_revenue' => (float) $item->total_revenue,
                    'order_count' => (int) $item->order_count,
                    'image' => $item->image,
                ];
            });

        // Get best selling categories
        $categories = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.completed_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select(
                'categories.id as category_id',
                'categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as total_quantity_sold'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_quantity_sold')
            ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category_id,
                    'category_name' => $item->category_name,
                    'total_quantity_sold' => (int) $item->total_quantity_sold,
                    'total_revenue' => (float) $item->total_revenue,
                    'order_count' => (int) $item->order_count,
                ];
            });

        return [
            'products' => $products->toArray(),
            'categories' => $categories->toArray(),
        ];
    }

    /**
     * Generate sales summary with profit calculations.
     */
    public function generateSalesSummaryReport(Carbon $startDate, Carbon $endDate): array
    {
        $storeId = request()->user()->store_id;

        // Get completed orders in date range
        $orders = Order::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->get();

        // Calculate basic metrics
        $grossSales = $orders->sum('subtotal');
        $totalDiscount = $orders->sum('discount_amount');
        $netSales = $grossSales - $totalDiscount;
        $totalRevenue = $orders->sum('total_amount');
        $totalTax = $orders->sum('tax_amount');
        $totalServiceCharge = $orders->sum('service_charge');
        $totalTransactions = $orders->count();

        // Calculate cost of goods sold (COGS) and profit
        $totalCost = 0;
        $orderIds = $orders->pluck('id');

        if ($orderIds->isNotEmpty()) {
            $totalCost = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->whereIn('order_items.order_id', $orderIds)
                ->sum(DB::raw('order_items.quantity * COALESCE(products.cost_price, 0)'));
        }

        $grossProfit = $netSales - $totalCost;
        
        // Get expenses in the same period
        $totalExpenses = Expense::where('store_id', $storeId)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        $netProfit = $grossProfit - $totalExpenses;
        $grossProfitMargin = $grossSales > 0 ? ($grossProfit / $grossSales) * 100 : 0;

        // Daily statistics for charting
        $dailyStats = Order::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select(
                DB::raw('DATE(completed_at) as date'),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total_sales' => (float) $item->total_sales,
                    'transaction_count' => (int) $item->transaction_count,
                ];
            });

        return [
            'gross_sales' => (float) $grossSales,
            'net_sales' => (float) $netSales,
            'gross_profit' => (float) $grossProfit,
            'net_profit' => (float) $netProfit,
            'total_transactions' => $totalTransactions,
            'gross_profit_margin' => round($grossProfitMargin, 2),
            'total_revenue' => (float) $totalRevenue,
            'total_cost' => (float) $totalCost,
            'total_tax' => (float) $totalTax,
            'total_discount' => (float) $totalDiscount,
            'total_service_charge' => (float) $totalServiceCharge,
            'daily_statistics' => $dailyStats->toArray(),
        ];
    }
}
