<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportExport;

class ReportExportService
{
    /**
     * Export report data to specified format.
     */
    public function export(
        string $reportType,
        string $format,
        array $data,
        array $parameters
    ): string {
        $fileName = $this->generateFileName($reportType, $format, $parameters);
        
        return match ($format) {
            'pdf' => $this->exportToPdf($reportType, $data, $fileName),
            'excel' => $this->exportToExcel($reportType, $data, $fileName),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}")
        };
    }

    /**
     * Export report to PDF format.
     */
    private function exportToPdf(string $reportType, array $data, string $fileName): string
    {
        $view = $this->getReportView($reportType);
        
        $pdf = Pdf::loadView($view, [
            'data' => $data,
            'reportType' => $reportType,
            'generatedAt' => now(),
            'storeName' => auth()->user()->store->name ?? 'POS Xpress Store',
        ]);
        
        // Configure PDF settings
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'sans-serif',
        ]);
        
        $filePath = "reports/pdf/{$fileName}";
        Storage::put($filePath, $pdf->output());
        
        return $filePath;
    }

    /**
     * Export report to Excel format.
     */
    private function exportToExcel(string $reportType, array $data, string $fileName): string
    {
        $filePath = "reports/excel/{$fileName}";
        
        Excel::store(
            new ReportExport($reportType, $data),
            $filePath,
            'local'
        );
        
        return $filePath;
    }

    /**
     * Generate unique filename for export.
     */
    private function generateFileName(string $reportType, string $format, array $parameters): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $storeId = auth()->user()->store_id;
        $extension = $format === 'excel' ? 'xlsx' : $format;
        
        // Add date range to filename if available
        $dateRange = '';
        if (isset($parameters['start_date']) && isset($parameters['end_date'])) {
            $start = Carbon::parse($parameters['start_date'])->format('Y-m-d');
            $end = Carbon::parse($parameters['end_date'])->format('Y-m-d');
            $dateRange = "_{$start}_to_{$end}";
        }
        
        return "{$reportType}_report{$dateRange}_{$storeId}_{$timestamp}.{$extension}";
    }

    /**
     * Get the appropriate view for report type.
     */
    private function getReportView(string $reportType): string
    {
        return match ($reportType) {
            'sales' => 'reports.pdf.sales',
            'inventory' => 'reports.pdf.inventory',
            'cash_flow' => 'reports.pdf.cash-flow',
            'product_performance' => 'reports.pdf.product-performance',
            'customer_analytics' => 'reports.pdf.customer-analytics',
            default => 'reports.pdf.generic'
        };
    }
}