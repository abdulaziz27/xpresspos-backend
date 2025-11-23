<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Collection;

class DailyCashReportExport implements WithMultipleSheets
{
    public function __construct(
        private array $reports // Array of ['store' => Store, 'data' => array]
    ) {}

    /**
     * Return array of sheets for the export.
     */
    public function sheets(): array
    {
        $sheets = [];
        
        // First sheet: Combined summary for all stores
        $sheets[] = new CashSummarySheet($this->reports);
        
        // Add combined sheets for sessions, payment methods, and expenses
        $sheets[] = new CashSessionsSheet($this->reports);
        $sheets[] = new PaymentMethodsSheet($this->reports);
        $sheets[] = new ExpensesSheet($this->reports);
        
        return $sheets;
    }
}

// Cash Summary Sheet - Combined for all stores
class CashSummarySheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private array $reports // Array of ['store' => Store, 'data' => array]
    ) {}

    public function collection()
    {
        $data = [];
        
        // Header row
        $data[] = [
            'Nama Toko',
            'Total Pendapatan',
            'Total Pengeluaran',
            'Net Cash Flow',
            'Pendapatan Tunai',
            'Pendapatan Non-Tunai',
            'Total Sesi Kas',
            'Sesi Terbuka',
            'Sesi Tertutup',
            'Total Saldo Awal',
            'Total Saldo Akhir',
            'Total Penjualan Tunai',
            'Total Pengeluaran Tunai',
            'Total Selisih'
        ];
        
        // Initialize totals
        $totals = [
            'total_revenue' => 0,
            'total_expenses' => 0,
            'net_cash_flow' => 0,
            'cash_revenue' => 0,
            'non_cash_revenue' => 0,
            'total_sessions' => 0,
            'open_sessions' => 0,
            'closed_sessions' => 0,
            'total_opening_balance' => 0,
            'total_closing_balance' => 0,
            'total_cash_sales' => 0,
            'total_cash_expenses' => 0,
            'total_variance' => 0,
        ];
        
        // Data rows for each store
        foreach ($this->reports as $reportItem) {
            $store = $reportItem['store'];
            $summary = $reportItem['data']['summary'] ?? [];
            $cashSessions = $reportItem['data']['cash_sessions'] ?? [];
            
            $data[] = [
                $store->name,
                'Rp ' . number_format($summary['total_revenue'] ?? 0, 0, ',', '.'),
                'Rp ' . number_format($summary['total_expenses'] ?? 0, 0, ',', '.'),
                'Rp ' . number_format($summary['net_cash_flow'] ?? 0, 0, ',', '.'),
                'Rp ' . number_format($summary['cash_revenue'] ?? 0, 0, ',', '.'),
                'Rp ' . number_format($summary['non_cash_revenue'] ?? 0, 0, ',', '.'),
                $cashSessions['total_sessions'] ?? 0,
                $cashSessions['open_sessions'] ?? 0,
                $cashSessions['closed_sessions'] ?? 0,
                'Rp ' . number_format($cashSessions['total_opening_balance'] ?? 0, 0, ',', '.'),
                'Rp ' . number_format($cashSessions['total_closing_balance'] ?? 0, 0, ',', '.'),
                'Rp ' . number_format($cashSessions['total_cash_sales'] ?? 0, 0, ',', '.'),
                'Rp ' . number_format($cashSessions['total_cash_expenses'] ?? 0, 0, ',', '.'),
                'Rp ' . number_format($cashSessions['total_variance'] ?? 0, 0, ',', '.'),
            ];
            
            // Accumulate totals
            $totals['total_revenue'] += $summary['total_revenue'] ?? 0;
            $totals['total_expenses'] += $summary['total_expenses'] ?? 0;
            $totals['net_cash_flow'] += $summary['net_cash_flow'] ?? 0;
            $totals['cash_revenue'] += $summary['cash_revenue'] ?? 0;
            $totals['non_cash_revenue'] += $summary['non_cash_revenue'] ?? 0;
            $totals['total_sessions'] += $cashSessions['total_sessions'] ?? 0;
            $totals['open_sessions'] += $cashSessions['open_sessions'] ?? 0;
            $totals['closed_sessions'] += $cashSessions['closed_sessions'] ?? 0;
            $totals['total_opening_balance'] += $cashSessions['total_opening_balance'] ?? 0;
            $totals['total_closing_balance'] += $cashSessions['total_closing_balance'] ?? 0;
            $totals['total_cash_sales'] += $cashSessions['total_cash_sales'] ?? 0;
            $totals['total_cash_expenses'] += $cashSessions['total_cash_expenses'] ?? 0;
            $totals['total_variance'] += $cashSessions['total_variance'] ?? 0;
        }
        
        // Empty row before total
        $data[] = array_fill(0, 14, '');
        
        // Total row
        $data[] = [
            'TOTAL',
            'Rp ' . number_format($totals['total_revenue'], 0, ',', '.'),
            'Rp ' . number_format($totals['total_expenses'], 0, ',', '.'),
            'Rp ' . number_format($totals['net_cash_flow'], 0, ',', '.'),
            'Rp ' . number_format($totals['cash_revenue'], 0, ',', '.'),
            'Rp ' . number_format($totals['non_cash_revenue'], 0, ',', '.'),
            $totals['total_sessions'],
            $totals['open_sessions'],
            $totals['closed_sessions'],
            'Rp ' . number_format($totals['total_opening_balance'], 0, ',', '.'),
            'Rp ' . number_format($totals['total_closing_balance'], 0, ',', '.'),
            'Rp ' . number_format($totals['total_cash_sales'], 0, ',', '.'),
            'Rp ' . number_format($totals['total_cash_expenses'], 0, ',', '.'),
            'Rp ' . number_format($totals['total_variance'], 0, ',', '.'),
        ];
        
        return collect($data);
    }

    public function headings(): array
    {
        return []; // Headings are included in collection data
    }

    public function title(): string
    {
        return 'Ringkasan Kas';
    }

    public function styles(Worksheet $sheet)
    {
        $totalRow = count($this->reports) + 3; // Header + stores + empty + total
        
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            $totalRow => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '70AD47']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            'A:N' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]],
            'B:B' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'C:C' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'D:D' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'E:E' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'F:F' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'G:G' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'H:H' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'I:I' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'J:J' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'K:K' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'L:L' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'M:M' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'N:N' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
        ];
    }
}

// Cash Sessions Sheet - Combined for all stores
class CashSessionsSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    private int $dataRowCount = 0;
    
    public function __construct(
        private array $reports // Array of ['store' => Store, 'data' => array]
    ) {}

    public function collection()
    {
        $data = [];
        
        // Header row
        $data[] = [
            'Nama Toko',
            'Tanggal Buka',
            'Tanggal Tutup',
            'Kasir',
            'Status',
            'Saldo Awal',
            'Saldo Akhir',
            'Saldo Diharapkan',
            'Penjualan Tunai',
            'Pengeluaran Tunai',
            'Selisih'
        ];
        
        // Initialize totals
        $totals = [
            'opening_balance' => 0,
            'closing_balance' => 0,
            'expected_balance' => 0,
            'cash_sales' => 0,
            'cash_expenses' => 0,
            'variance' => 0,
        ];
        
        // Collect all sessions from all stores
        foreach ($this->reports as $reportItem) {
            $store = $reportItem['store'];
            $sessions = $reportItem['data']['cash_sessions']['sessions'] ?? [];
            
            if (empty($sessions)) {
                continue;
            }
            
            foreach ($sessions as $session) {
                // Handle user name extraction
                $userName = 'N/A';
                if (isset($session['user'])) {
                    if (is_array($session['user'])) {
                        $userName = $session['user']['name'] ?? 'N/A';
                    } elseif (is_object($session['user'])) {
                        $userName = $session['user']->name ?? 'N/A';
                    }
                }
                
                // Handle date formatting
                $openedAt = '';
                if (isset($session['opened_at'])) {
                    if (is_string($session['opened_at'])) {
                        $openedAt = date('Y-m-d H:i:s', strtotime($session['opened_at']));
                    } elseif (is_object($session['opened_at']) && method_exists($session['opened_at'], 'format')) {
                        $openedAt = $session['opened_at']->format('Y-m-d H:i:s');
                    }
                }
                
                $closedAt = 'Masih Terbuka';
                if (isset($session['closed_at'])) {
                    if (is_string($session['closed_at'])) {
                        $closedAt = date('Y-m-d H:i:s', strtotime($session['closed_at']));
                    } elseif (is_object($session['closed_at']) && method_exists($session['closed_at'], 'format')) {
                        $closedAt = $session['closed_at']->format('Y-m-d H:i:s');
                    }
                }
                
                $openingBalance = $session['opening_balance'] ?? 0;
                $closingBalance = $session['closing_balance'] ?? 0;
                $expectedBalance = $session['expected_balance'] ?? 0;
                $cashSales = $session['cash_sales'] ?? 0;
                $cashExpenses = $session['cash_expenses'] ?? 0;
                $variance = $session['variance'] ?? 0;
                
                $data[] = [
                    $store->name,
                    $openedAt,
                    $closedAt,
                    $userName,
                    ucfirst($session['status'] ?? ''),
                    'Rp ' . number_format($openingBalance, 0, ',', '.'),
                    'Rp ' . number_format($closingBalance, 0, ',', '.'),
                    'Rp ' . number_format($expectedBalance, 0, ',', '.'),
                    'Rp ' . number_format($cashSales, 0, ',', '.'),
                    'Rp ' . number_format($cashExpenses, 0, ',', '.'),
                    'Rp ' . number_format($variance, 0, ',', '.'),
                ];
                
                // Accumulate totals
                $totals['opening_balance'] += $openingBalance;
                $totals['closing_balance'] += $closingBalance;
                $totals['expected_balance'] += $expectedBalance;
                $totals['cash_sales'] += $cashSales;
                $totals['cash_expenses'] += $cashExpenses;
                $totals['variance'] += $variance;
                
                $this->dataRowCount++;
            }
        }
        
        // Empty row before total
        $data[] = array_fill(0, 11, '');
        
        // Total row
        $data[] = [
            'TOTAL',
            '',
            '',
            '',
            '',
            'Rp ' . number_format($totals['opening_balance'], 0, ',', '.'),
            'Rp ' . number_format($totals['closing_balance'], 0, ',', '.'),
            'Rp ' . number_format($totals['expected_balance'], 0, ',', '.'),
            'Rp ' . number_format($totals['cash_sales'], 0, ',', '.'),
            'Rp ' . number_format($totals['cash_expenses'], 0, ',', '.'),
            'Rp ' . number_format($totals['variance'], 0, ',', '.'),
        ];
        
        return collect($data);
    }

    public function headings(): array
    {
        return []; // Headings are included in collection data
    }

    public function title(): string
    {
        return 'Sesi Kas';
    }

    public function styles(Worksheet $sheet)
    {
        // Calculate total row: 1 (header) + dataRowCount + 1 (empty) + 1 (total)
        $totalRow = 1 + $this->dataRowCount + 2;
        
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            $totalRow => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '70AD47']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            'A:K' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]],
            'F:F' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'G:G' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'H:H' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'I:I' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'J:J' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'K:K' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
        ];
    }
}

// Payment Methods Sheet - Combined for all stores
class PaymentMethodsSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    private int $dataRowCount = 0;
    
    public function __construct(
        private array $reports // Array of ['store' => Store, 'data' => array]
    ) {}

    public function collection()
    {
        $data = [];
        
        // Header row
        $data[] = [
            'Nama Toko',
            'Metode Pembayaran',
            'Jumlah Transaksi',
            'Total Amount'
        ];
        
        // Collect all payment methods grouped by store
        $totals = [
            'count' => 0,
            'amount' => 0,
        ];
        
        foreach ($this->reports as $reportItem) {
            $store = $reportItem['store'];
            $paymentMethods = $reportItem['data']['payments_by_method'] ?? [];
            
            if (empty($paymentMethods)) {
                continue;
            }
            
            foreach ($paymentMethods as $method) {
                $count = $method['count'] ?? 0;
                $amount = $method['total'] ?? 0;
                
                $data[] = [
                    $store->name,
                    ucfirst(str_replace('_', ' ', $method['payment_method'] ?? '')),
                    $count,
                    'Rp ' . number_format($amount, 0, ',', '.'),
                ];
                
                $totals['count'] += $count;
                $totals['amount'] += $amount;
                $this->dataRowCount++;
            }
        }
        
        // Empty row before total
        $data[] = ['', '', '', ''];
        
        // Total row
        $data[] = [
            'TOTAL',
            '',
            $totals['count'],
            'Rp ' . number_format($totals['amount'], 0, ',', '.'),
        ];
        
        return collect($data);
    }

    public function headings(): array
    {
        return []; // Headings are included in collection data
    }

    public function title(): string
    {
        return 'Metode Pembayaran';
    }

    public function styles(Worksheet $sheet)
    {
        // Calculate total row: 1 (header) + dataRowCount + 1 (empty) + 1 (total)
        $totalRow = 1 + $this->dataRowCount + 2;
        
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            $totalRow => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '70AD47']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            'A:D' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]],
            'C:C' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'D:D' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
        ];
    }
}

// Expenses Sheet - Combined for all stores
class ExpensesSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    private int $dataRowCount = 0;
    
    public function __construct(
        private array $reports // Array of ['store' => Store, 'data' => array]
    ) {}

    public function collection()
    {
        $data = [];
        
        // Header row
        $data[] = [
            'Nama Toko',
            'Kategori Pengeluaran',
            'Jumlah Transaksi',
            'Total Amount'
        ];
        
        // Collect all expenses grouped by store
        $totals = [
            'count' => 0,
            'amount' => 0,
        ];
        
        foreach ($this->reports as $reportItem) {
            $store = $reportItem['store'];
            $expenses = $reportItem['data']['expenses_by_category'] ?? [];
            
            if (empty($expenses)) {
                continue;
            }
            
            foreach ($expenses as $expense) {
                $count = $expense['count'] ?? 0;
                $amount = $expense['total'] ?? 0;
                
                $data[] = [
                    $store->name,
                    ucfirst(str_replace('_', ' ', $expense['category'] ?? '')),
                    $count,
                    'Rp ' . number_format($amount, 0, ',', '.'),
                ];
                
                $totals['count'] += $count;
                $totals['amount'] += $amount;
                $this->dataRowCount++;
            }
        }
        
        // Empty row before total
        $data[] = ['', '', '', ''];
        
        // Total row
        $data[] = [
            'TOTAL',
            '',
            $totals['count'],
            'Rp ' . number_format($totals['amount'], 0, ',', '.'),
        ];
        
        return collect($data);
    }

    public function headings(): array
    {
        return []; // Headings are included in collection data
    }

    public function title(): string
    {
        return 'Pengeluaran';
    }

    public function styles(Worksheet $sheet)
    {
        // Calculate total row: 1 (header) + dataRowCount + 1 (empty) + 1 (total)
        $totalRow = 1 + $this->dataRowCount + 2;
        
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            $totalRow => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '70AD47']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            'A:D' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]],
            'C:C' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'D:D' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
        ];
    }
}

