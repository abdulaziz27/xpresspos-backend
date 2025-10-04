<?php

namespace App\Services\Reporting;

use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Member;
use App\Mail\MonthlyReportReady;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class MonthlyReportService
{
    public function __construct(
        private ReportService $reportService
    ) {}

    /**
     * Generate comprehensive monthly report for a store.
     */
    public function generateComprehensiveMonthlyReport(
        Store $store,
        Carbon $reportMonth
    ): array {
        $startDate = $reportMonth->copy()->startOfMonth();
        $endDate = $reportMonth->copy()->endOfMonth();
        
        // Get previous month for comparison
        $prevStartDate = $reportMonth->copy()->subMonth()->startOfMonth();
        $prevEndDate = $reportMonth->copy()->subMonth()->endOfMonth();

        // Generate all report sections
        $executiveSummary = $this->generateExecutiveSummary($store, $startDate, $endDate, $prevStartDate, $prevEndDate);
        $financialPerformance = $this->generateFinancialPerformance($store, $startDate, $endDate);
        $salesAnalysis = $this->generateSalesAnalysis($store, $startDate, $endDate);
        $productPerformance = $this->generateProductPerformance($store, $startDate, $endDate);
        $customerAnalytics = $this->generateCustomerAnalytics($store, $startDate, $endDate);
        $kpis = $this->calculateKeyPerformanceIndicators($store, $startDate, $endDate);
        $businessInsights = $this->generateBusinessInsights($store, $startDate, $endDate);
        $recommendations = $this->generateRecommendations($store, $executiveSummary, $productPerformance, $customerAnalytics);

        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'email' => $store->email,
            ],
            'report_period' => [
                'month' => $reportMonth->format('F Y'),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'days_in_month' => $reportMonth->daysInMonth,
            ],
            'executive_summary' => $executiveSummary,
            'financial_performance' => $financialPerformance,
            'sales_analysis' => $salesAnalysis,
            'product_performance' => $productPerformance,
            'customer_analytics' => $customerAnalytics,
            'key_performance_indicators' => $kpis,
            'business_insights' => $businessInsights,
            'recommendations' => $recommendations,
            'generated_at' => now(),
        ];
    }

    /**
     * Generate PDF report from report data.
     */
    public function generateMonthlyReportPdf(
        Store $store,
        array $reportData,
        Carbon $reportMonth
    ): string {
        $fileName = "monthly-report-{$store->id}-{$reportMonth->format('Y-m')}.pdf";
        $filePath = "reports/monthly/{$fileName}";

        // Generate PDF using the Blade template
        $pdf = Pdf::loadView('reports.pdf.monthly-report', [
            'reportData' => $reportData,
            'store' => $store,
            'reportMonth' => $reportMonth,
        ]);

        // Configure PDF settings
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'sans-serif',
        ]);

        // Save PDF to storage
        Storage::put($filePath, $pdf->output());

        return $filePath;
    }

    /**
     * Send monthly report email to store owner.
     */
    public function sendMonthlyReportEmail(
        Store $store,
        array $reportData,
        string $pdfPath,
        Carbon $reportMonth
    ): void {
        // Get store owner
        $storeOwner = $store->users()->whereHas('roles', function ($query) {
            $query->where('name', 'owner');
        })->first();

        if (!$storeOwner) {
            throw new \Exception("No store owner found for store: {$store->name}");
        }

        // Send email with PDF attachment
        Mail::to($storeOwner->email)->send(
            new MonthlyReportReady(
                store: $store,
                reportData: $reportData,
                pdfPath: $pdfPath,
                reportMonth: $reportMonth
            )
        );
    }

    /**
     * Generate executive summary with key metrics and growth.
     */
    private function generateExecutiveSummary(
        Store $store,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $prevStartDate,
        Carbon $prevEndDate
    ): array {
        // Current month metrics
        $currentOrders = Order::withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get();

        $currentPayments = Payment::whereHas('order', function ($query) use ($store) {
                $query->withoutGlobalScopes()->where('store_id', $store->id);
            })
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $currentExpenses = Expense::withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        // Previous month metrics
        $prevOrders = Order::withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$prevStartDate, $prevEndDate])
            ->get();

        $prevPayments = Payment::whereHas('order', function ($query) use ($store) {
                $query->withoutGlobalScopes()->where('store_id', $store->id);
            })
            ->where('status', 'completed')
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->get();

        $prevExpenses = Expense::withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->whereBetween('expense_date', [$prevStartDate, $prevEndDate])
            ->get();

        // Calculate metrics
        $currentRevenue = $currentPayments->sum('amount');
        $prevRevenue = $prevPayments->sum('amount');
        $currentOrderCount = $currentOrders->count();
        $prevOrderCount = $prevOrders->count();
        $currentExpenseTotal = $currentExpenses->sum('amount');
        $prevExpenseTotal = $prevExpenses->sum('amount');
        $currentProfit = $currentRevenue - $currentExpenseTotal;
        $prevProfit = $prevRevenue - $prevExpenseTotal;

        return [
            'revenue' => [
                'current' => $currentRevenue,
                'previous' => $prevRevenue,
                'growth' => $this->calculateGrowthPercentage($currentRevenue, $prevRevenue),
            ],
            'orders' => [
                'current' => $currentOrderCount,
                'previous' => $prevOrderCount,
                'growth' => $this->calculateGrowthPercentage($currentOrderCount, $prevOrderCount),
                'average_order_value' => $currentOrderCount > 0 ? $currentRevenue / $currentOrderCount : 0,
            ],
            'profit' => [
                'current' => $currentProfit,
                'previous' => $prevProfit,
                'growth' => $this->calculateGrowthPercentage($currentProfit, $prevProfit),
                'margin' => $currentRevenue > 0 ? ($currentProfit / $currentRevenue) * 100 : 0,
            ],
            'expenses' => [
                'current' => $currentExpenseTotal,
                'previous' => $prevExpenseTotal,
                'growth' => $this->calculateGrowthPercentage($currentExpenseTotal, $prevExpenseTotal),
            ],
            'highlights' => $this->generateHighlights($currentRevenue, $prevRevenue, $currentOrderCount, $prevOrderCount),
        ];
    }

    /**
     * Generate financial performance analysis.
     */
    private function generateFinancialPerformance(
        Store $store,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        return $this->reportService->generateCashFlowReport($startDate, $endDate, true);
    }

    /**
     * Generate sales analysis.
     */
    private function generateSalesAnalysis(
        Store $store,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        return $this->reportService->generateSalesReport($startDate, $endDate, 'day');
    }

    /**
     * Generate product performance analysis.
     */
    private function generateProductPerformance(
        Store $store,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        return $this->reportService->generateProductPerformanceReport($startDate, $endDate, 20, 'revenue');
    }

    /**
     * Generate customer analytics.
     */
    private function generateCustomerAnalytics(
        Store $store,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        return $this->reportService->generateCustomerAnalyticsReport($startDate, $endDate, true);
    }

    /**
     * Calculate key performance indicators.
     */
    private function calculateKeyPerformanceIndicators(
        Store $store,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $orders = Order::withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get();

        $payments = Payment::whereHas('order', function ($query) use ($store) {
                $query->withoutGlobalScopes()->where('store_id', $store->id);
            })
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $daysInMonth = $startDate->daysInMonth;
        $totalRevenue = $payments->sum('amount');
        $totalOrders = $orders->count();
        $uniqueCustomers = $orders->whereNotNull('member_id')->unique('member_id')->count();

        // Calculate customer retention (customers who made purchases in both current and previous month)
        $prevMonth = $startDate->copy()->subMonth();
        $prevCustomers = Order::withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$prevMonth->startOfMonth(), $prevMonth->endOfMonth()])
            ->whereNotNull('member_id')
            ->pluck('member_id')
            ->unique();

        $currentCustomers = $orders->whereNotNull('member_id')->pluck('member_id')->unique();
        $retainedCustomers = $currentCustomers->intersect($prevCustomers)->count();
        $retentionRate = $prevCustomers->count() > 0 ? ($retainedCustomers / $prevCustomers->count()) * 100 : 0;

        return [
            'revenue_per_day' => $daysInMonth > 0 ? $totalRevenue / $daysInMonth : 0,
            'orders_per_day' => $daysInMonth > 0 ? $totalOrders / $daysInMonth : 0,
            'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
            'customer_retention_rate' => round($retentionRate, 2),
            'revenue_per_customer' => $uniqueCustomers > 0 ? $totalRevenue / $uniqueCustomers : 0,
            'unique_customers' => $uniqueCustomers,
            'total_transactions' => $totalOrders,
        ];
    }

    /**
     * Generate business insights.
     */
    private function generateBusinessInsights(
        Store $store,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $insights = [];

        // Peak sales day analysis
        $dailySales = Order::withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->selectRaw('DATE(completed_at) as sale_date, COUNT(*) as order_count, SUM(total_amount) as daily_revenue')
            ->groupBy('sale_date')
            ->orderByDesc('daily_revenue')
            ->first();

        if ($dailySales) {
            $insights[] = [
                'type' => 'peak_day',
                'title' => 'Best Sales Day',
                'description' => "Your best sales day was " . Carbon::parse($dailySales->sale_date)->format('F j') . 
                               " with {$dailySales->order_count} orders and $" . number_format($dailySales->daily_revenue, 2) . " in revenue.",
            ];
        }

        // Product performance insight
        $topProduct = Product::withoutGlobalScopes()
            ->where('store_id', $store->id)
            ->withSum(['orderItems' => function ($query) use ($startDate, $endDate) {
                $query->whereHas('order', function ($q) use ($startDate, $endDate) {
                    $q->withoutGlobalScopes()
                      ->where('status', 'completed')
                      ->whereBetween('completed_at', [$startDate, $endDate]);
                });
            }], 'quantity')
            ->orderByDesc('order_items_sum_quantity')
            ->first();

        if ($topProduct && $topProduct->order_items_sum_quantity > 0) {
            $insights[] = [
                'type' => 'top_product',
                'title' => 'Top Selling Product',
                'description' => "'{$topProduct->name}' was your best seller with {$topProduct->order_items_sum_quantity} units sold.",
            ];
        }

        return $insights;
    }

    /**
     * Generate actionable recommendations.
     */
    private function generateRecommendations(
        Store $store,
        array $executiveSummary,
        array $productPerformance,
        array $customerAnalytics
    ): array {
        $recommendations = [];

        // Revenue growth recommendation
        if ($executiveSummary['revenue']['growth'] < 0) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'revenue',
                'title' => 'Address Revenue Decline',
                'description' => 'Revenue decreased by ' . abs($executiveSummary['revenue']['growth']) . '% this month. Consider reviewing pricing strategy, promoting high-margin products, or launching marketing campaigns.',
                'action_items' => [
                    'Review and optimize pricing for top products',
                    'Launch targeted promotions for slow-moving inventory',
                    'Analyze customer feedback for service improvements',
                ],
            ];
        } elseif ($executiveSummary['revenue']['growth'] > 20) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'growth',
                'title' => 'Capitalize on Growth',
                'description' => 'Excellent revenue growth of ' . $executiveSummary['revenue']['growth'] . '%! Consider expanding successful strategies.',
                'action_items' => [
                    'Analyze what drove this growth and replicate it',
                    'Consider increasing inventory for top-performing products',
                    'Explore opportunities to expand operating hours or services',
                ],
            ];
        }

        // Customer retention recommendation
        $retentionRate = $customerAnalytics['summary']['member_percentage'] ?? 0;
        if ($retentionRate < 50) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'customer_retention',
                'title' => 'Improve Customer Loyalty',
                'description' => 'Only ' . $retentionRate . '% of orders are from members. Focus on building customer loyalty.',
                'action_items' => [
                    'Enhance loyalty program benefits',
                    'Implement personalized marketing campaigns',
                    'Collect customer feedback to improve experience',
                ],
            ];
        }

        // Product performance recommendation
        if (!empty($productPerformance['products'])) {
            $lowPerformers = array_filter($productPerformance['products'], function ($product) {
                return $product['quantity_sold'] < 5; // Less than 5 units sold
            });

            if (count($lowPerformers) > 0) {
                $recommendations[] = [
                    'priority' => 'low',
                    'category' => 'inventory',
                    'title' => 'Optimize Product Mix',
                    'description' => count($lowPerformers) . ' products sold fewer than 5 units this month.',
                    'action_items' => [
                        'Review slow-moving products for discontinuation',
                        'Consider bundling slow movers with popular items',
                        'Adjust inventory levels for underperforming products',
                    ],
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Generate highlights for executive summary.
     */
    private function generateHighlights(
        float $currentRevenue,
        float $prevRevenue,
        int $currentOrders,
        int $prevOrders
    ): array {
        $highlights = [];

        if ($currentRevenue > $prevRevenue) {
            $growth = $this->calculateGrowthPercentage($currentRevenue, $prevRevenue);
            $highlights[] = "Revenue increased by {$growth}% compared to last month";
        }

        if ($currentOrders > $prevOrders) {
            $growth = $this->calculateGrowthPercentage($currentOrders, $prevOrders);
            $highlights[] = "Order volume grew by {$growth}% from previous month";
        }

        if (empty($highlights)) {
            $highlights[] = "Focus on growth strategies to improve performance next month";
        }

        return $highlights;
    }

    /**
     * Calculate growth percentage.
     */
    private function calculateGrowthPercentage(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}