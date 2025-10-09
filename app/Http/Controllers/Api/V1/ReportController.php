<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    /**
     * Get sales report.
     */
    public function sales(Request $request): JsonResponse
    {
        // Set default dates if not provided
        $request->merge([
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
        ]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'sometimes|in:day,week,month',
            'outlet_id' => 'sometimes|uuid|exists:outlets,id',
            'user_id' => 'sometimes|uuid|exists:users,id',
            'category_id' => 'sometimes|uuid|exists:categories,id',
        ]);

        $cacheKey = $this->generateCacheKey('sales', $request->all());

        $report = Cache::remember($cacheKey, 300, function () use ($request) {
            return $this->reportService->generateSalesReport(
                startDate: Carbon::parse($request->start_date),
                endDate: Carbon::parse($request->end_date),
                groupBy: $request->group_by ?? 'day',
                filters: $request->only(['outlet_id', 'user_id', 'category_id'])
            );
        });

        return response()->json([
            'success' => true,
            'data' => $report,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Get inventory report.
     */
    public function inventory(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'sometimes|uuid|exists:categories,id',
            'low_stock_only' => 'sometimes|boolean',
            'include_movements' => 'sometimes|boolean',
        ]);

        $cacheKey = $this->generateCacheKey('inventory', $request->all());

        $report = Cache::remember($cacheKey, 600, function () use ($request) {
            return $this->reportService->generateInventoryReport(
                filters: $request->only(['category_id', 'low_stock_only']),
                includeMovements: $request->boolean('include_movements', false)
            );
        });

        return response()->json([
            'success' => true,
            'data' => $report,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Get cash flow report.
     */
    public function cashFlow(Request $request): JsonResponse
    {
        // Set default dates if not provided
        $request->merge([
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
        ]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'include_sessions' => 'sometimes|boolean',
        ]);

        $cacheKey = $this->generateCacheKey('cash_flow', $request->all());

        $report = Cache::remember($cacheKey, 300, function () use ($request) {
            return $this->reportService->generateCashFlowReport(
                startDate: Carbon::parse($request->start_date),
                endDate: Carbon::parse($request->end_date),
                includeSessions: $request->boolean('include_sessions', false)
            );
        });

        return response()->json([
            'success' => true,
            'data' => $report,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Get product performance report.
     */
    public function productPerformance(Request $request): JsonResponse
    {
        // Set default dates if not provided
        $request->merge([
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
        ]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|in:quantity,revenue,profit',
        ]);

        $cacheKey = $this->generateCacheKey('product_performance', $request->all());

        $report = Cache::remember($cacheKey, 600, function () use ($request) {
            return $this->reportService->generateProductPerformanceReport(
                startDate: Carbon::parse($request->start_date),
                endDate: Carbon::parse($request->end_date),
                limit: $request->integer('limit', 20),
                sortBy: $request->string('sort_by', 'revenue')
            );
        });

        return response()->json([
            'success' => true,
            'data' => $report,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Get customer analytics report.
     */
    public function customerAnalytics(Request $request): JsonResponse
    {
        // Set default dates if not provided
        $request->merge([
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
        ]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'include_loyalty' => 'sometimes|boolean',
        ]);

        $cacheKey = $this->generateCacheKey('customer_analytics', $request->all());

        $report = Cache::remember($cacheKey, 600, function () use ($request) {
            return $this->reportService->generateCustomerAnalyticsReport(
                startDate: Carbon::parse($request->start_date),
                endDate: Carbon::parse($request->end_date),
                includeLoyalty: $request->boolean('include_loyalty', false)
            );
        });

        return response()->json([
            'success' => true,
            'data' => $report,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Export report to PDF or Excel.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'report_type' => 'required|in:sales,inventory,cash_flow,product_performance,customer_analytics',
            'format' => 'required|in:pdf,excel',
            'start_date' => 'required_if:report_type,sales,cash_flow,product_performance,customer_analytics|date',
            'end_date' => 'required_if:report_type,sales,cash_flow,product_performance,customer_analytics|date|after_or_equal:start_date',
        ]);

        // Check plan limits for export functionality
        $store = request()->user()->store;
        if ($store->hasExceededTransactionQuota() && !$store->activeSubscription->plan->hasFeature('report_export')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUOTA_EXCEEDED_PREMIUM_BLOCKED',
                    'message' => 'Report export is limited when transaction quota is exceeded. Please upgrade your plan.',
                ]
            ], 403);
        }

        $exportJob = $this->reportService->exportReport(
            reportType: $request->report_type,
            format: $request->format,
            parameters: $request->all(),
            userId: auth()->id()
        );

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => 'queued',
                'message' => 'Export job queued successfully. You will be notified when ready.',
            ]
        ]);
    }

    /**
     * Get dashboard summary.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|in:today,week,month,year',
        ]);

        $period = $request->string('period', 'today');
        $cacheKey = $this->generateCacheKey('dashboard', ['period' => $period]);

        $summary = Cache::remember($cacheKey, 300, function () use ($period) {
            return $this->reportService->generateDashboardSummary($period);
        });

        return response()->json([
            'success' => true,
            'data' => $summary,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Get sales trend analysis with forecasting.
     */
    public function salesTrends(Request $request): JsonResponse
    {
        // Set default dates if not provided
        $request->merge([
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
        ]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'sometimes|in:day,week,month',
        ]);

        $cacheKey = $this->generateCacheKey('sales_trends', $request->all());

        $analysis = Cache::remember($cacheKey, 600, function () use ($request) {
            return $this->reportService->generateSalesTrendAnalysis(
                startDate: Carbon::parse($request->start_date),
                endDate: Carbon::parse($request->end_date),
                groupBy: $request->group_by ?? 'day'
            );
        });

        return response()->json([
            'success' => true,
            'data' => $analysis,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Get product analytics with ABC analysis.
     */
    public function productAnalytics(Request $request): JsonResponse
    {
        // Set default dates if not provided
        $request->merge([
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
        ]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $cacheKey = $this->generateCacheKey('product_analytics', $request->all());

        $analysis = Cache::remember($cacheKey, 900, function () use ($request) {
            return $this->reportService->generateProductAnalytics(
                startDate: Carbon::parse($request->start_date),
                endDate: Carbon::parse($request->end_date)
            );
        });

        return response()->json([
            'success' => true,
            'data' => $analysis,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Get customer behavior analytics.
     */
    public function customerBehavior(Request $request): JsonResponse
    {
        // Ensure user is authenticated
        $user = auth()->user() ?? request()->user();
        if (!$user || !$user->store_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'User authentication required'
                ]
            ], 401);
        }

        // Set default dates if not provided
        $request->merge([
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
        ]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $cacheKey = $this->generateCacheKey('customer_behavior', $request->all());

        $analysis = Cache::remember($cacheKey, 900, function () use ($request) {
            return $this->reportService->generateCustomerBehaviorAnalytics(
                startDate: Carbon::parse($request->start_date),
                endDate: Carbon::parse($request->end_date)
            );
        });

        return response()->json([
            'success' => true,
            'data' => $analysis,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Get profitability analysis.
     */
    public function profitabilityAnalysis(Request $request): JsonResponse
    {
        // Set default dates if not provided
        $request->merge([
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
        ]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $cacheKey = $this->generateCacheKey('profitability_analysis', $request->all());

        $analysis = Cache::remember($cacheKey, 900, function () use ($request) {
            return $this->reportService->generateProfitabilityAnalysis(
                startDate: Carbon::parse($request->start_date),
                endDate: Carbon::parse($request->end_date)
            );
        });

        return response()->json([
            'success' => true,
            'data' => $analysis,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Get operational efficiency metrics.
     */
    public function operationalEfficiency(Request $request): JsonResponse
    {
        // Set default dates if not provided
        $request->merge([
            'start_date' => $request->input('start_date', now()->subDays(30)->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
        ]);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $cacheKey = $this->generateCacheKey('operational_efficiency', $request->all());

        $analysis = Cache::remember($cacheKey, 600, function () use ($request) {
            return $this->reportService->generateOperationalEfficiencyMetrics(
                startDate: Carbon::parse($request->start_date),
                endDate: Carbon::parse($request->end_date)
            );
        });

        return response()->json([
            'success' => true,
            'data' => $analysis,
            'meta' => [
                'cached' => true,
                'generated_at' => now(),
            ]
        ]);
    }

    /**
     * Generate cache key for reports.
     */
    private function generateCacheKey(string $reportType, array $parameters): string
    {
        $user = auth()->user() ?? request()->user();
        $storeId = $user?->store_id ?? 'no-store';
        $paramHash = md5(serialize($parameters));

        return "report:{$storeId}:{$reportType}:{$paramHash}";
    }
}
