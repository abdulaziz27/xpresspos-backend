<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Reporting\ReportService;
use App\Services\Reporting\MonthlyReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Exception;

class GenerateMonthlyReportJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $storeId,
        public ?Carbon $reportMonth = null
    ) {
        $this->onQueue('reports');
        $this->reportMonth = $reportMonth ?? now()->subMonth();
    }

    /**
     * Execute the job.
     */
    public function handle(
        ReportService $reportService,
        MonthlyReportService $monthlyReportService
    ): void {
        try {
            $store = Store::findOrFail($this->storeId);
            
            // Set the store context for scoped queries
            auth()->setUser($store->users()->first());
            
            // Generate comprehensive monthly report
            $monthlyReport = $monthlyReportService->generateComprehensiveMonthlyReport(
                store: $store,
                reportMonth: $this->reportMonth
            );
            
            // Generate PDF report
            $pdfPath = $monthlyReportService->generateMonthlyReportPdf(
                store: $store,
                reportData: $monthlyReport,
                reportMonth: $this->reportMonth
            );
            
            // Send email with report
            $monthlyReportService->sendMonthlyReportEmail(
                store: $store,
                reportData: $monthlyReport,
                pdfPath: $pdfPath,
                reportMonth: $this->reportMonth
            );
            
            // Log successful generation
            logger()->info('Monthly report generated successfully', [
                'store_id' => $this->storeId,
                'report_month' => $this->reportMonth->format('Y-m'),
            ]);
            
        } catch (Exception $e) {
            logger()->error('Monthly report generation failed', [
                'store_id' => $this->storeId,
                'report_month' => $this->reportMonth->format('Y-m'),
                'error' => $e->getMessage(),
            ]);
            
            // Send failure notification
            $this->sendFailureNotification($store ?? null, $e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Send failure notification.
     */
    private function sendFailureNotification(?Store $store, string $errorMessage): void
    {
        if (!$store) {
            return;
        }

        try {
            $storeOwner = $store->users()->whereHas('roles', function ($query) {
                $query->where('name', 'owner');
            })->first();

            if ($storeOwner) {
                Mail::to($storeOwner->email)->send(
                    new \App\Mail\MonthlyReportFailed(
                        storeName: $store->name,
                        reportMonth: $this->reportMonth,
                        errorMessage: $errorMessage
                    )
                );
            }
        } catch (Exception $e) {
            logger()->error('Failed to send monthly report failure notification', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Exception $exception): void
    {
        logger()->error('Monthly report job failed permanently', [
            'store_id' => $this->storeId,
            'report_month' => $this->reportMonth->format('Y-m'),
            'error' => $exception->getMessage(),
        ]);
    }
}
