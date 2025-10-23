<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class FnBAnalyticsService
{
    /**
     * Get F&B specific sales analytics
     */
    public function getSalesAnalytics(string $period = 'today'): array
    {
        $dateRange = $this->getDateRange($period);
        
        return [
            'summary' => $this->getSalesSummary($dateRange),
            'top_products' => $this->getTopProducts($dateRange),
            'popular_variants' => $this->getPopularVariants($dateRange),
            'hourly_sales' => $this->getHourlySales($dateRange),
            'category_performance' => $this->getCategoryPerformance($dateRange),
        ];
    }

    /**
     * Get profit analysis for F&B
     */
    public function getProfitAnalysis(string $period = 'today'): array
    {
        $dateRange = $this->getDateRange($period);
        
        $products = OrderItem::whereBetween('created_at', $dateRange)
            ->with('product')
            ->get()
            ->groupBy('product_id');

        $analysis = [];
        foreach ($products as $productId => $items) {
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

        // Sort by profit descending
        usort($analysis, fn($a, $b) => $b['profit'] <=> $a['profit']);

        return $analysis;
    }

    /**
     * Get F&B specific recommendations
     */
    public function getRecommendations(): array
    {
        $recommendations = [];
        
        // Low margin products
        $lowMarginProducts = $this->getLowMarginProducts();
        if (!empty($lowMarginProducts)) {
            $recommendations[] = [
                'type' => 'low_margin',
                'title' => 'Low Profit Margin Alert',
                'message' => 'These products have profit margin below 30%',
                'data' => $lowMarginProducts,
                'action' => 'Consider increasing price or reducing cost'
            ];
        }

        // Slow moving inventory
        $slowMoving = $this->getSlowMovingProducts();
        if (!empty($slowMoving)) {
            $recommendations[] = [
                'type' => 'slow_moving',
                'title' => 'Slow Moving Products',
                'message' => 'These products haven\'t sold in the last 7 days',
                'data' => $slowMoving,
                'action' => 'Consider promotion or menu update'
            ];
        }

        // Popular variants to promote
        $popularVariants = $this->getPopularVariants(['today']);
        if (!empty($popularVariants)) {
            $recommendations[] = [
                'type' => 'popular_variants',
                'title' => 'Trending Variants',
                'message' => 'These variants are selling well today',
                'data' => array_slice($popularVariants, 0, 3),
                'action' => 'Consider featuring these variants'
            ];
        }

        return $recommendations;
    }

    private function getSalesSummary(array $dateRange): array
    {
        $orders = Order::whereBetween('created_at', $dateRange)
            ->where('status', 'completed')
            ->get();

        return [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total_amount'),
            'average_order_value' => $orders->count() > 0 ? $orders->sum('total_amount') / $orders->count() : 0,
            'total_items_sold' => OrderItem::whereBetween('created_at', $dateRange)->sum('quantity'),
        ];
    }

    private function getTopProducts(array $dateRange, int $limit = 10): array
    {
        return OrderItem::whereBetween('created_at', $dateRange)
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

    private function getHourlySales(array $dateRange): array
    {
        $sales = Order::whereBetween('created_at', $dateRange)
            ->where('status', 'completed')
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

    private function getCategoryPerformance(array $dateRange): array
    {
        return OrderItem::whereBetween('created_at', $dateRange)
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name as category_name', DB::raw('SUM(order_items.quantity) as total_sold'), DB::raw('SUM(order_items.total_price) as revenue'))
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('revenue', 'desc')
            ->get()
            ->toArray();
    }

    private function getLowMarginProducts(): array
    {
        return Product::whereRaw('(price - cost_price) / price < 0.3')
            ->where('cost_price', '>', 0)
            ->select('name', 'price', 'cost_price', DB::raw('((price - cost_price) / price * 100) as margin'))
            ->get()
            ->toArray();
    }

    private function getSlowMovingProducts(): array
    {
        $sevenDaysAgo = now()->subDays(7);
        
        return Product::whereDoesntHave('orderItems', function ($query) use ($sevenDaysAgo) {
            $query->where('created_at', '>=', $sevenDaysAgo);
        })
        ->where('status', true)
        ->select('id', 'name', 'price', 'stock')
        ->get()
        ->toArray();
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