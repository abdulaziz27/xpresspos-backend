<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Reporting\ReportService;
use App\Services\Reporting\ReportExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

class ExportReportJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $reportType,
        public string $format,
        public array $parameters,
        public string $userId
    ) {
        $this->onQueue('reports');
    }

    /**
     * Execute the job.
     */
    public function handle(ReportService $reportService, ReportExportService $exportService): void
    {
        try {
            $user = User::findOrFail($this->userId);
            
            // Generate report data
            $reportData = $this->generateReportData($reportService);
            
            // Export to specified format
            $filePath = $exportService->export(
                reportType: $this->reportType,
                format: $this->format,
                data: $reportData,
                parameters: $this->parameters
            );
            
            // Send email notification with download link
            $this->sendExportNotification($user, $filePath);
            
        } catch (Exception $e) {
            // Log error and notify user of failure
            logger()->error('Report export failed', [
                'report_type' => $this->reportType,
                'format' => $this->format,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            
            $this->sendFailureNotification($e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Generate report data based on type.
     */
    private function generateReportData(ReportService $reportService): array
    {
        return match ($this->reportType) {
            'sales' => $reportService->generateSalesReport(
                startDate: Carbon::parse($this->parameters['start_date']),
                endDate: Carbon::parse($this->parameters['end_date']),
                groupBy: $this->parameters['group_by'] ?? 'day',
                filters: array_intersect_key($this->parameters, array_flip(['outlet_id', 'user_id', 'category_id']))
            ),
            'inventory' => $reportService->generateInventoryReport(
                filters: array_intersect_key($this->parameters, array_flip(['category_id', 'low_stock_only'])),
                includeMovements: $this->parameters['include_movements'] ?? false
            ),
            'cash_flow' => $reportService->generateCashFlowReport(
                startDate: Carbon::parse($this->parameters['start_date']),
                endDate: Carbon::parse($this->parameters['end_date']),
                includeSessions: $this->parameters['include_sessions'] ?? false
            ),
            'product_performance' => $reportService->generateProductPerformanceReport(
                startDate: Carbon::parse($this->parameters['start_date']),
                endDate: Carbon::parse($this->parameters['end_date']),
                limit: $this->parameters['limit'] ?? 20,
                sortBy: $this->parameters['sort_by'] ?? 'revenue'
            ),
            'customer_analytics' => $reportService->generateCustomerAnalyticsReport(
                startDate: Carbon::parse($this->parameters['start_date']),
                endDate: Carbon::parse($this->parameters['end_date']),
                includeLoyalty: $this->parameters['include_loyalty'] ?? false
            ),
            default => throw new Exception("Unsupported report type: {$this->reportType}")
        };
    }

    /**
     * Send export success notification to user.
     */
    private function sendExportNotification(User $user, string $filePath): void
    {
        $downloadUrl = Storage::url($filePath);
        $fileName = basename($filePath);
        
        // Send email with download link
        Mail::to($user->email)->send(new \App\Mail\ReportExportReady(
            reportType: $this->reportType,
            format: $this->format,
            fileName: $fileName,
            downloadUrl: $downloadUrl,
            expiresAt: now()->addDays(7) // Link expires in 7 days
        ));
    }

    /**
     * Send failure notification to user.
     */
    private function sendFailureNotification(string $errorMessage): void
    {
        try {
            $user = User::findOrFail($this->userId);
            
            Mail::to($user->email)->send(new \App\Mail\ReportExportFailed(
                reportType: $this->reportType,
                format: $this->format,
                errorMessage: $errorMessage
            ));
        } catch (Exception $e) {
            logger()->error('Failed to send export failure notification', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Exception $exception): void
    {
        logger()->error('Report export job failed permanently', [
            'report_type' => $this->reportType,
            'format' => $this->format,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
        
        $this->sendFailureNotification($exception->getMessage());
    }
}