<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Services\StoreContext;

class FnBAnalyticsService
{
    private ?string $storeId;

    public function __construct(private ?StoreContext $storeContext = null)
    {
        $this->storeContext = $this->storeContext ?? StoreContext::instance();
        $this->storeId = $this->storeContext->current(auth()->user());
    }

    public function getSalesAnalytics(string $period = 'today'): array
    {
        return $this->getSalesAnalyticsForStores($this->resolveStoreIds(), $period);
    }

    public function getSalesAnalyticsForStores(array $storeIds, string $period = 'today', ?array $customRange = null): array
    {
        $dateRange = $customRange ?? $this->getDateRange($period);
        $storeIds = $this->resolveStoreIds($storeIds);

        return [
            'summary' => $this->getSalesSummary($dateRange, $storeIds),
            'top_products' => $this->getTopProducts($dateRange, $storeIds),
            'popular_variants' => $this->getPopularVariants($dateRange),
            'hourly_sales' => $this->getHourlySales($dateRange, $storeIds),
            'category_performance' => $this->getCategoryPerformance($dateRange, $storeIds),
        ];
    }

    public function getProfitAnalysis(string $period = 'today'): array
    {
        return $this->getProfitAnalysisForStores($this->resolveStoreIds(), $period);
    }

    public function getProfitAnalysisForStores(array $storeIds, string $period = 'today', ?array $customRange = null): array
    {
        $dateRange = $customRange ?? $this->getDateRange($period);
        $storeIds = $this->resolveStoreIds($storeIds);

        if (empty($storeIds)) {
            return [];
        }

        $products = OrderItem::whereBetween('order_items.created_at', $dateRange)
            ->whereIn('order_items.store_id', $storeIds)
            ->with('product')
            ->get()
            ->groupBy('product_id');

        $analysis = [];
        foreach ($products as $items) {
            $product = $items->first()->product;
            $totalSold = $items->sum('quantity');
            $revenue = $items->sum('total_price');
            $cost = $totalSold * $product->cost_price;
            $profit = $revenue - $cost;
            $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

            $analysis[] = [
                'product_name' => $product->name,
                'quantity_sold' => $totalSold,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'margin_percent' => round($margin, 2),
                'profit_per_item' => $totalSold > 0 ? $profit / $totalSold : 0,
            ];
        }

        usort($analysis, fn($a, $b) => $b['profit'] <=> $a['profit']);

        return $analysis;
    }

    public function getRecommendations(): array
    {
        return $this->getRecommendationsForStores($this->resolveStoreIds());
    }

    public function getRecommendationsForStores(array $storeIds): array
    {
        $storeIds = $this->resolveStoreIds($storeIds);
        $recommendations = [];
        
        $lowMarginProducts = $this->getLowMarginProducts($storeIds);
        if (! empty($lowMarginProducts)) {
            $recommendations[] = [
                'type' => 'low_margin',
                'title' => 'Low Profit Margin Alert',
                'message' => 'These products have profit margin below 30%',
                'data' => $lowMarginProducts,
                'action' => 'Consider increasing price or reducing cost',
            ];
        }

        $slowMoving = $this->getSlowMovingProducts($storeIds);
        if (! empty($slowMoving)) {
            $recommendations[] = [
                'type' => 'slow_moving',
                'title' => 'Slow Moving Products',
                'message' => 'These products haven\'t sold in the last 7 days',
                'data' => $slowMoving,
                'action' => 'Consider promotion or menu update',
            ];
        }

        $popularVariants = $this->getPopularVariants(['today']);
        if (! empty($popularVariants)) {
            $recommendations[] = [
                'type' => 'popular_variants',
                'title' => 'Trending Variants',
                'message' => 'These variants are selling well today',
                'data' => array_slice($popularVariants, 0, 3),
                'action' => 'Consider featuring these variants',
            ];
        }

        return $recommendations;
    }

    private function getSalesSummary(array $dateRange, array $storeIds = []): array
    {
        $orders = Order::query()
            ->whereBetween('created_at', $dateRange)
            ->where('status', 'completed')
            ->when(! empty($storeIds), fn($query) => $query->whereIn('store_id', $storeIds))
            ->get();

        return [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total_amount'),
            'average_order_value' => $orders->count() > 0 ? $orders->sum('total_amount') / $orders->count() : 0,
            'total_items_sold' => OrderItem::query()
                ->whereBetween('order_items.created_at', $dateRange)
                ->when(! empty($storeIds), fn($query) => $query->whereIn('order_items.store_id', $storeIds))
                ->sum('quantity'),
        ];
    }

    private function getTopProducts(array $dateRange, array $storeIds = [], int $limit = 10): array
    {
        return OrderItem::query()
            ->whereBetween('order_items.created_at', $dateRange)
            ->when(! empty($storeIds), fn($query) => $query->whereIn('order_items.store_id', $storeIds))
            ->select('product_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(total_price) as revenue'))
            ->with('product:id,name,price')
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'quantity_sold' => $item->total_sold,
                    'revenue' => $item->revenue,
                ];
            })
            ->toArray();
    }

    private function getPopularVariants(array $dateRange): array
    {
        // This would need to be implemented based on how variants are stored in order_items
        // For now, return empty array
        return [];
    }

    private function getHourlySales(array $dateRange, array $storeIds = []): array
    {
        $sales = Order::query()
            ->whereBetween('created_at', $dateRange)
            ->where('status', 'completed')
            ->when(! empty($storeIds), fn($query) => $query->whereIn('store_id', $storeIds))
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as orders'), DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $hourlySales = [];
        for ($i = 0; $i < 24; $i++) {
            $hourData = $sales->firstWhere('hour', $i);
            $hourlySales[] = [
                'hour' => $i,
                'time_label' => sprintf('%02d:00', $i),
                'orders' => $hourData ? $hourData->orders : 0,
                'revenue' => $hourData ? $hourData->revenue : 0,
            ];
        }

        return $hourlySales;
    }

    private function getCategoryPerformance(array $dateRange, array $storeIds = []): array
    {
        return OrderItem::query()
            ->whereBetween('order_items.created_at', $dateRange)
            ->when(! empty($storeIds), fn($query) => $query->whereIn('order_items.store_id', $storeIds))
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name as category_name', DB::raw('SUM(order_items.quantity) as total_sold'), DB::raw('SUM(order_items.total_price) as revenue'))
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('revenue', 'desc')
            ->get()
            ->toArray();
    }

    private function getLowMarginProducts(array $storeIds = []): array
    {
        $query = Product::query()
            ->whereRaw('(price - cost_price) / price < 0.3')
            ->where('cost_price', '>', 0)
            ->select('name', 'price', 'cost_price', DB::raw('((price - cost_price) / price * 100) as margin'));

        if (! empty($storeIds)) {
            $query->whereHas('orderItems', fn($orderItems) => $orderItems->whereIn('store_id', $storeIds));
        }

        return $query->limit(10)->get()->toArray();
    }

    private function getSlowMovingProducts(array $storeIds = []): array
    {
        $sevenDaysAgo = now()->subDays(7);
        
        $query = Product::query()
            ->where('status', true)
            ->whereDoesntHave('orderItems', function ($orderItems) use ($sevenDaysAgo, $storeIds) {
                $orderItems->where('order_items.created_at', '>=', $sevenDaysAgo);

                if (! empty($storeIds)) {
                    $orderItems->whereIn('order_items.store_id', $storeIds);
                }
            })
            ->select('id', 'name', 'price');

        return $query->limit(10)->get()->toArray();
    }

    private function resolveStoreIds(?array $storeIds = null): array
    {
        if (is_array($storeIds) && ! empty($storeIds)) {
            return array_values(array_filter($storeIds));
        }

        return $this->storeId ? [$this->storeId] : [];
    }

    private function getDateRange(string $period): array
    {
        return match($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }
}