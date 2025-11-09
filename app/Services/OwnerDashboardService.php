<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OwnerDashboardService
{
    public function summaryFor(?User $user): array
    {
        if (! $user) {
            return $this->emptySummary();
        }

        $storeId = $user->currentStoreId();
        
        if (! $storeId) {
            return $this->emptySummary();
        }
        $now = CarbonImmutable::now();

        $paymentsQuery = Payment::query()
            ->where('store_id', $storeId)
            ->completed();

        $totalRevenue = (float) (clone $paymentsQuery)->sum('amount');
        $todayRevenue = (float) (clone $paymentsQuery)->whereDate('processed_at', $now->toDateString())->sum('amount');
        $monthRevenue = (float) (clone $paymentsQuery)->whereBetween('processed_at', [$now->startOfMonth(), $now->endOfMonth()])->sum('amount');

        $ordersQuery = Order::query()
            ->where('store_id', $storeId)
            ->completed();

        $totalOrders = (clone $ordersQuery)->count();
        $todayOrders = (clone $ordersQuery)->whereDate('created_at', $now->toDateString())->count();
        $monthOrders = (clone $ordersQuery)->whereBetween('created_at', [$now->startOfMonth(), $now->endOfMonth()])->count();
        $averageOrderValue = (float) (clone $ordersQuery)->avg('total_amount');

        $activeMembers = Member::query()->where('store_id', $storeId)->active()->count();
        $lowStock = Product::query()->where('store_id', $storeId)->lowStock()->count();

        return [
            'revenue' => [
                'total' => $totalRevenue,
                'today' => $todayRevenue,
                'month' => $monthRevenue,
            ],
            'orders' => [
                'total' => $totalOrders,
                'today' => $todayOrders,
                'month' => $monthOrders,
                'average_value' => $averageOrderValue,
            ],
            'customers' => [
                'active_members' => $activeMembers,
            ],
            'inventory' => [
                'low_stock' => $lowStock,
            ],
            'top_products' => $this->topProducts($storeId),
            'recent_payments' => $this->recentPayments($storeId),
            'trends' => [
                'revenue' => $this->revenueTrend($storeId),
                'orders' => $this->orderTrend($storeId),
            ],
        ];
    }

    private function topProducts(string $storeId): Collection
    {
        $thirtyDaysAgo = CarbonImmutable::now()->subDays(30);

        return OrderItem::query()
            ->where('store_id', $storeId)
            ->whereHas('order', function ($query) use ($thirtyDaysAgo) {
                $query->completed()->where('created_at', '>=', $thirtyDaysAgo);
            })
            ->selectRaw('product_id, product_name, SUM(quantity) as total_quantity, SUM(total_price) as total_revenue')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'total_quantity' => (int) $row->total_quantity,
                    'total_revenue' => (float) $row->total_revenue,
                ];
            });
    }

    private function recentPayments(string $storeId): Collection
    {
        return Payment::query()
            ->where('store_id', $storeId)
            ->completed()
            ->latest('processed_at')
            ->take(5)
            ->get(['id', 'payment_method', 'amount', 'processed_at'])
            ->map(function (Payment $payment) {
                return [
                    'id' => $payment->id,
                    'payment_method' => $payment->payment_method,
                    'amount' => (float) $payment->amount,
                    'processed_at' => optional($payment->processed_at)->toDateTimeString(),
                ];
            });
    }

    private function revenueTrend(string $storeId, int $days = 14): Collection
    {
        $end = CarbonImmutable::now();
        $start = $end->subDays($days - 1)->startOfDay();

        $raw = Payment::query()
            ->where('store_id', $storeId)
            ->completed()
            ->whereBetween('processed_at', [$start, $end])
            ->selectRaw('DATE(processed_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->fillMissingDays($raw, $start, $end)
            ->map(function ($amount, $date) {
                return [
                    'date' => $date,
                    'total' => (float) $amount,
                ];
            })
            ->values();
    }

    private function orderTrend(string $storeId, int $days = 14): Collection
    {
        $end = CarbonImmutable::now();
        $start = $end->subDays($days - 1)->startOfDay();

        $raw = Order::query()
            ->where('store_id', $storeId)
            ->completed()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->fillMissingDays($raw, $start, $end)
            ->map(function ($count, $date) {
                return [
                    'date' => $date,
                    'total' => (int) $count,
                ];
            })
            ->values();
    }

    private function fillMissingDays(Collection $raw, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $days = collect();

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $key = $date->format('Y-m-d');
            $days[$key] = (float) ($raw[$key] ?? 0);
        }

        return $days;
    }

    private function emptySummary(): array
    {
        return [
            'revenue' => ['total' => 0, 'today' => 0, 'month' => 0],
            'orders' => ['total' => 0, 'today' => 0, 'month' => 0, 'average_value' => 0],
            'customers' => ['active_members' => 0],
            'inventory' => ['low_stock' => 0],
            'top_products' => Collection::make(),
            'recent_payments' => Collection::make(),
            'trends' => [
                'revenue' => Collection::make(),
                'orders' => Collection::make(),
            ],
        ];
    }
}
