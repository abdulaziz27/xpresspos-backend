<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Jobs\GenerateMonthlyReportJob;
use App\Services\Reporting\MonthlyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MonthlyReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test store and user
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create([
            'store_id' => $this->store->id,
        ]);
        
        // Create and assign owner role
        $ownerRole = \Spatie\Permission\Models\Role::create(['name' => 'owner']);
        $this->user->assignRole($ownerRole);
        
        // Create subscription
        $plan = Plan::factory()->create([
            'name' => 'Pro Test Monthly',
            'slug' => 'pro-test-monthly',
            'features' => ['advanced_reports', 'monthly_reports'],
        ]);
        
        Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_queue_monthly_report_job()
    {
        Queue::fake();

        $reportMonth = now()->subMonth();
        
        GenerateMonthlyReportJob::dispatch($this->store->id, $reportMonth);

        Queue::assertPushed(GenerateMonthlyReportJob::class, function ($job) use ($reportMonth) {
            return $job->storeId === $this->store->id && 
                   $job->reportMonth->format('Y-m') === $reportMonth->format('Y-m');
        });
    }

    public function test_can_generate_comprehensive_monthly_report()
    {
        // Create test data for the previous month
        $reportMonth = now()->subMonth();
        $startDate = $reportMonth->copy()->startOfMonth();
        $endDate = $reportMonth->copy()->endOfMonth();
        
        // Create orders for the report month
        Order::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
            'completed_at' => $startDate->copy()->addDays(rand(1, 28)),
            'total_amount' => 100.00,
        ]);

        $monthlyReportService = app(MonthlyReportService::class);
        
        $report = $monthlyReportService->generateComprehensiveMonthlyReport(
            $this->store,
            $reportMonth
        );

        $this->assertIsArray($report);
        $this->assertArrayHasKey('store', $report);
        $this->assertArrayHasKey('report_period', $report);
        $this->assertArrayHasKey('executive_summary', $report);
        $this->assertArrayHasKey('financial_performance', $report);
        $this->assertArrayHasKey('sales_analysis', $report);
        $this->assertArrayHasKey('product_performance', $report);
        $this->assertArrayHasKey('customer_analytics', $report);
        $this->assertArrayHasKey('business_insights', $report);
        $this->assertArrayHasKey('key_performance_indicators', $report);
        $this->assertArrayHasKey('recommendations', $report);
        
        // Verify store information
        $this->assertEquals($this->store->id, $report['store']['id']);
        $this->assertEquals($this->store->name, $report['store']['name']);
        
        // Verify report period
        $this->assertEquals($reportMonth->format('F Y'), $report['report_period']['month']);
        $this->assertEquals($startDate->toDateString(), $report['report_period']['start_date']);
        $this->assertEquals($endDate->toDateString(), $report['report_period']['end_date']);
    }

    public function test_can_generate_monthly_report_pdf()
    {
        Storage::fake('local');
        
        $reportMonth = now()->subMonth();
        $monthlyReportService = app(MonthlyReportService::class);
        
        // Generate sample report data
        $reportData = [
            'store' => ['id' => $this->store->id, 'name' => $this->store->name],
            'report_period' => [
                'month' => $reportMonth->format('F Y'),
                'start_date' => $reportMonth->startOfMonth()->toDateString(),
                'end_date' => $reportMonth->endOfMonth()->toDateString(),
            ],
            'executive_summary' => [
                'revenue' => ['current' => 1000, 'previous' => 800, 'growth' => 25],
                'orders' => ['current' => 50, 'previous' => 40, 'growth' => 25, 'average_order_value' => 20],
                'profit' => ['current' => 200, 'previous' => 160, 'growth' => 25, 'margin' => 20],
                'expenses' => ['current' => 800, 'previous' => 640, 'growth' => 25],
                'highlights' => ['Test highlight'],
            ],
            'key_performance_indicators' => [
                'revenue_per_day' => 32.26,
                'orders_per_day' => 1.6,
                'customer_retention_rate' => 75,
                'revenue_per_customer' => 100,
            ],
            'product_performance' => ['products' => []],
            'customer_analytics' => [
                'summary' => [
                    'unique_customers' => 25,
                    'member_percentage' => 60,
                    'average_order_value' => 20,
                ]
            ],
            'recommendations' => [],
            'business_insights' => [],
            'generated_at' => now(),
        ];
        
        $pdfPath = $monthlyReportService->generateMonthlyReportPdf(
            $this->store,
            $reportData,
            $reportMonth
        );

        $this->assertNotEmpty($pdfPath);
        Storage::assertExists($pdfPath);
        
        // Verify file naming convention
        $expectedFileName = "monthly-report-{$this->store->id}-{$reportMonth->format('Y-m')}.pdf";
        $this->assertStringContainsString($expectedFileName, $pdfPath);
    }

    public function test_can_send_monthly_report_email()
    {
        Mail::fake();
        Storage::fake('local');
        
        $reportMonth = now()->subMonth();
        $monthlyReportService = app(MonthlyReportService::class);
        
        // Create a fake PDF file
        $pdfPath = "reports/monthly/test-report.pdf";
        Storage::put($pdfPath, 'fake pdf content');
        
        $reportData = [
            'executive_summary' => [
                'revenue' => ['current' => 1000, 'growth' => 25],
                'orders' => ['current' => 50, 'growth' => 25],
                'profit' => ['current' => 200, 'growth' => 25],
            ],
            'key_performance_indicators' => [
                'revenue_per_day' => 32.26,
                'orders_per_day' => 1.6,
                'customer_retention_rate' => 75,
            ],
            'recommendations' => [],
            'generated_at' => now(),
        ];
        
        $monthlyReportService->sendMonthlyReportEmail(
            $this->store,
            $reportData,
            $pdfPath,
            $reportMonth
        );

        Mail::assertSent(\App\Mail\MonthlyReportReady::class, function ($mail) {
            return $mail->store->id === $this->store->id;
        });
    }

    public function test_monthly_report_command_queues_jobs_for_all_stores()
    {
        Queue::fake();
        
        // Create additional stores
        $store2 = Store::factory()->create(['status' => 'active']);
        $store3 = Store::factory()->create(['status' => 'inactive']); // Inactive store
        
        $this->artisan('reports:generate-monthly')
            ->expectsOutput('Generating monthly reports for ' . now()->subMonth()->format('F Y') . '...')
            ->assertExitCode(0);

        // Should queue jobs for active stores only
        Queue::assertPushed(GenerateMonthlyReportJob::class, 2); // 2 active stores
    }

    public function test_monthly_report_command_can_target_specific_store()
    {
        Queue::fake();
        
        $this->artisan('reports:generate-monthly', ['--store' => $this->store->id])
            ->assertExitCode(0);

        Queue::assertPushed(GenerateMonthlyReportJob::class, function ($job) {
            return $job->storeId === $this->store->id;
        });
    }

    public function test_monthly_report_command_can_specify_month()
    {
        Queue::fake();
        
        $targetMonth = '2024-01';
        
        $this->artisan('reports:generate-monthly', [
            '--store' => $this->store->id,
            '--month' => $targetMonth
        ])->assertExitCode(0);

        Queue::assertPushed(GenerateMonthlyReportJob::class, function ($job) use ($targetMonth) {
            return $job->storeId === $this->store->id && 
                   $job->reportMonth->format('Y-m') === $targetMonth;
        });
    }

    public function test_monthly_report_job_handles_failure_gracefully()
    {
        Mail::fake();
        
        // Mock a service that will throw an exception
        $this->mock(MonthlyReportService::class, function ($mock) {
            $mock->shouldReceive('generateComprehensiveMonthlyReport')
                 ->andThrow(new \Exception('Test error'));
        });

        $job = new GenerateMonthlyReportJob($this->store->id, now()->subMonth());
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test error');
        
        $job->handle(
            app(\App\Services\Reporting\ReportService::class),
            app(MonthlyReportService::class)
        );
    }

    public function test_executive_summary_calculates_growth_correctly()
    {
        // Use a fixed date for consistent testing
        $reportMonth = Carbon::create(2024, 8, 1); // August 2024
        $monthlyReportService = app(MonthlyReportService::class);
        
        // Create orders for the report month (3 orders)
        $currentOrders = Order::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
            'completed_at' => $reportMonth->copy()->addDays(15), // August 16, 2024
            'total_amount' => 100.00,
        ]);
        
        // Create payments for report month orders
        foreach ($currentOrders as $order) {
            \App\Models\Payment::factory()->create([
                'store_id' => $this->store->id,
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'status' => 'completed',
                'created_at' => $order->completed_at,
            ]);
        }
        
        // Create orders for the month before the report month (2 orders)
        $prevMonth = $reportMonth->copy()->subMonth(); // July 2024
        $prevOrders = Order::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
            'completed_at' => $prevMonth->copy()->addDays(15), // July 16, 2024
            'total_amount' => 100.00,
        ]);
        
        // Create payments for previous month orders
        foreach ($prevOrders as $order) {
            \App\Models\Payment::factory()->create([
                'store_id' => $this->store->id,
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'status' => 'completed',
                'created_at' => $order->completed_at,
            ]);
        }

        $report = $monthlyReportService->generateComprehensiveMonthlyReport(
            $this->store,
            $reportMonth
        );

        $executiveSummary = $report['executive_summary'];
        

        
        // Should show 50% growth (3 vs 2 orders, 300 vs 200 revenue)
        $this->assertEquals(50, $executiveSummary['revenue']['growth']);
        $this->assertEquals(50, $executiveSummary['orders']['growth']);
        $this->assertEquals(300, $executiveSummary['revenue']['current']);
        $this->assertEquals(200, $executiveSummary['revenue']['previous']);
    }

    public function test_kpis_are_calculated_correctly()
    {
        // Use a fixed date for consistent testing
        $reportMonth = Carbon::create(2024, 8, 1); // August 2024
        $monthlyReportService = app(MonthlyReportService::class);
        
        // Create 10 orders with total revenue of 1000
        $orders = Order::factory()->count(10)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
            'completed_at' => $reportMonth->copy()->addDays(15), // August 16, 2024
            'total_amount' => 100.00,
        ]);
        
        // Create payments for the orders
        foreach ($orders as $order) {
            \App\Models\Payment::factory()->create([
                'store_id' => $this->store->id,
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'status' => 'completed',
                'created_at' => $order->completed_at,
            ]);
        }

        $report = $monthlyReportService->generateComprehensiveMonthlyReport(
            $this->store,
            $reportMonth
        );

        $kpis = $report['key_performance_indicators'];
        $daysInMonth = $reportMonth->daysInMonth;
        
        // Revenue per day should be 1000 / days_in_month
        $expectedRevenuePerDay = 1000 / $daysInMonth;
        $this->assertEquals($expectedRevenuePerDay, $kpis['revenue_per_day']);
        
        // Orders per day should be 10 / days_in_month
        $expectedOrdersPerDay = 10 / $daysInMonth;
        $this->assertEquals($expectedOrdersPerDay, $kpis['orders_per_day']);
        
        // Average order value should be 100
        $this->assertEquals(100, $kpis['average_order_value']);
    }
}
