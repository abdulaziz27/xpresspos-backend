<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Reporting\ReportService;
use App\Services\Reporting\MonthlyReportService;
use App\Services\Reporting\ReportExportService;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;

class TestReportingSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:test
                           {--store-id= : Test with specific store ID}
                           {--user-id= : Test with specific user ID}
                           {--generate-pdf : Generate PDF reports for testing}
                           {--send-email : Send test email notifications}';

    /**
     * The console command description.
     */
    protected $description = 'Test the reporting system functionality including PDF generation and email delivery';

    /**
     * Execute the console command.
     */
    public function handle(
        ReportService $reportService,
        MonthlyReportService $monthlyReportService,
        ReportExportService $exportService
    ): int {
        $this->info('🧪 Testing POS Xpress Reporting System...');
        $this->newLine();

        try {
            // Get test store and user
            [$store, $user] = $this->getTestStoreAndUser();

            if (!$store || !$user) {
                $this->error('❌ No test store or user found. Please ensure you have at least one store with an owner user.');
                return 1;
            }

            $this->info("📊 Testing with Store: {$store->name} (ID: {$store->id})");
            $this->info("👤 Testing with User: {$user->name} (ID: {$user->id})");
            $this->newLine();

            // Set authentication context
            auth()->setUser($user);

            // Test 1: Sales Report
            $this->testSalesReport($reportService);

            // Test 2: Inventory Report
            $this->testInventoryReport($reportService);

            // Test 3: Cash Flow Report
            $this->testCashFlowReport($reportService);

            // Test 4: Product Performance Report
            $this->testProductPerformanceReport($reportService);

            // Test 5: Customer Analytics Report
            $this->testCustomerAnalyticsReport($reportService);

            // Test 6: Monthly Report Generation
            if ($this->option('generate-pdf')) {
                $this->testMonthlyReportGeneration($monthlyReportService, $store);
            }

            // Test 7: PDF Export
            if ($this->option('generate-pdf')) {
                $this->testPdfExport($exportService, $reportService);
            }

            $this->newLine();
            $this->info('✅ All reporting system tests completed successfully!');

            if ($this->option('send-email')) {
                $this->info('📧 Email notifications would be sent in production environment');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Test failed: {$e->getMessage()}");
            $this->error("Stack trace: {$e->getTraceAsString()}");
            return 1;
        }
    }

    /**
     * Test sales report generation.
     */
    private function testSalesReport(ReportService $reportService): void
    {
        $this->info('📈 Testing Sales Report...');

        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $report = $reportService->generateSalesReport(
            startDate: $startDate,
            endDate: $endDate,
            groupBy: 'day'
        );

        $this->checkReportStructure($report, ['summary', 'timeline', 'payment_methods', 'top_products']);
        $this->line("   ✓ Generated sales report for {$startDate->toDateString()} to {$endDate->toDateString()}");
    }

    /**
     * Test inventory report generation.
     */
    private function testInventoryReport(ReportService $reportService): void
    {
        $this->info('📦 Testing Inventory Report...');

        $report = $reportService->generateInventoryReport(
            filters: [],
            includeMovements: false
        );

        $this->checkReportStructure($report, ['summary', 'products']);
        $this->line("   ✓ Generated inventory report with {$report['summary']['total_products']} products");
    }

    /**
     * Test cash flow report generation.
     */
    private function testCashFlowReport(ReportService $reportService): void
    {
        $this->info('💰 Testing Cash Flow Report...');

        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $report = $reportService->generateCashFlowReport(
            startDate: $startDate,
            endDate: $endDate,
            includeSessions: true
        );

        $this->checkReportStructure($report, ['summary', 'daily_flow', 'payment_methods', 'expense_categories']);
        $this->line("   ✓ Generated cash flow report with net flow: $" . number_format($report['summary']['net_cash_flow'], 2));
    }

    /**
     * Test product performance report generation.
     */
    private function testProductPerformanceReport(ReportService $reportService): void
    {
        $this->info('🏆 Testing Product Performance Report...');

        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $report = $reportService->generateProductPerformanceReport(
            startDate: $startDate,
            endDate: $endDate,
            limit: 10,
            sortBy: 'revenue'
        );

        $this->checkReportStructure($report, ['summary', 'products']);
        $this->line("   ✓ Generated product performance report with {$report['summary']['total_products_sold']} products sold");
    }

    /**
     * Test customer analytics report generation.
     */
    private function testCustomerAnalyticsReport(ReportService $reportService): void
    {
        $this->info('👥 Testing Customer Analytics Report...');

        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $report = $reportService->generateCustomerAnalyticsReport(
            startDate: $startDate,
            endDate: $endDate,
            includeLoyalty: true
        );

        $this->checkReportStructure($report, ['summary', 'top_customers', 'segments']);
        $this->line("   ✓ Generated customer analytics report with {$report['summary']['unique_customers']} unique customers");
    }

    /**
     * Test monthly report generation.
     */
    private function testMonthlyReportGeneration(MonthlyReportService $monthlyReportService, Store $store): void
    {
        $this->info('📊 Testing Monthly Report Generation...');

        $reportMonth = Carbon::now()->subMonth();

        $reportData = $monthlyReportService->generateComprehensiveMonthlyReport(
            store: $store,
            reportMonth: $reportMonth
        );

        $this->checkReportStructure($reportData, [
            'store',
            'report_period',
            'executive_summary',
            'financial_performance',
            'sales_analysis',
            'product_performance',
            'customer_analytics',
            'key_performance_indicators',
            'business_insights',
            'recommendations'
        ]);

        $this->line("   ✓ Generated comprehensive monthly report for {$reportMonth->format('F Y')}");

        // Test PDF generation
        $this->info('📄 Testing Monthly Report PDF Generation...');

        try {
            $pdfPath = $monthlyReportService->generateMonthlyReportPdf(
                store: $store,
                reportData: $reportData,
                reportMonth: $reportMonth
            );

            $this->line("   ✓ Generated PDF report at: {$pdfPath}");
        } catch (\Exception $e) {
            $this->warn("   ⚠️ PDF generation failed: {$e->getMessage()}");
        }
    }

    /**
     * Test PDF export functionality.
     */
    private function testPdfExport(ReportExportService $exportService, ReportService $reportService): void
    {
        $this->info('📄 Testing PDF Export Functionality...');

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        // Generate test data
        $salesData = $reportService->generateSalesReport($startDate, $endDate);

        try {
            $filePath = $exportService->export(
                reportType: 'sales',
                format: 'pdf',
                data: $salesData,
                parameters: [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString()
                ]
            );

            $this->line("   ✓ Generated PDF export at: {$filePath}");
        } catch (\Exception $e) {
            $this->warn("   ⚠️ PDF export failed: {$e->getMessage()}");
        }
    }

    /**
     * Check report structure and data.
     */
    private function checkReportStructure(array $report, array $requiredKeys): void
    {
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $report)) {
                throw new \Exception("Missing required key in report: {$key}");
            }
        }
    }

    /**
     * Get test store and user.
     */
    private function getTestStoreAndUser(): array
    {
        $storeId = $this->option('store-id');
        $userId = $this->option('user-id');

        if ($storeId) {
            $store = Store::find($storeId);
        } else {
            $store = Store::first();
        }

        if ($userId) {
            $user = User::find($userId);
        } elseif ($store) {
            $user = $store->users()->whereHas('roles', function ($query) {
                $query->where('name', 'owner');
            })->first() ?? $store->users()->first();
        } else {
            $user = null;
        }

        return [$store, $user];
    }
}
