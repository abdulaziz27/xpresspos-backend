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

class SalesReportExport implements WithMultipleSheets
{
    public function __construct(
        private array $reports // Array of ['store' => Store, 'data' => array]
    ) {}

    /**
     * Return array of sheets for the export.
     */
    public function sheets(): array
    {
        return [
            new SalesSummarySheet($this->reports),
            new SalesTimelineSheet($this->reports),
            new SalesPaymentMethodSheet($this->reports),
            new SalesTopProductsSheet($this->reports),
        ];
    }
}

// Sales Summary Sheet - Combined for all stores
class SalesSummarySheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
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
            'Total Pesanan',
            'Total Pendapatan',
            'Total Item',
            'Rata-rata Nilai Pesanan',
            'Pelanggan Unik'
        ];
        
        // Initialize totals
        $totals = [
            'total_orders' => 0,
            'total_revenue' => 0,
            'total_items' => 0,
            'total_customers' => 0,
        ];
        
        // Data rows for each store
        foreach ($this->reports as $reportItem) {
            $store = $reportItem['store'];
            $summary = $reportItem['data']['summary'] ?? [];
            
            $totalOrders = $summary['total_orders'] ?? 0;
            $totalRevenue = $summary['total_revenue'] ?? 0;
            $totalItems = $summary['total_items'] ?? 0;
            $avgOrderValue = $summary['average_order_value'] ?? 0;
            $uniqueCustomers = $summary['unique_customers'] ?? 0;
            
            // Accumulate totals
            $totals['total_orders'] += $totalOrders;
            $totals['total_revenue'] += $totalRevenue;
            $totals['total_items'] += $totalItems;
            $totals['total_customers'] += $uniqueCustomers;
            
            $data[] = [
                $store->name,
                $totalOrders,
                'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                $totalItems,
                'Rp ' . number_format($avgOrderValue, 0, ',', '.'),
                $uniqueCustomers
            ];
        }
        
        // Empty row before total
        $data[] = ['', '', '', '', '', ''];
        
        // Calculate average AOV from totals (total revenue / total orders)
        $avgAOV = $totals['total_orders'] > 0 
            ? $totals['total_revenue'] / $totals['total_orders'] 
            : 0;
        
        // Total row
        $data[] = [
            'TOTAL',
            $totals['total_orders'],
            'Rp ' . number_format($totals['total_revenue'], 0, ',', '.'),
            $totals['total_items'],
            'Rp ' . number_format($avgAOV, 0, ',', '.'),
            $totals['total_customers']
        ];
        
        return collect($data);
    }

    public function headings(): array
    {
        // Headings are included in collection data
        return [];
    }

    public function title(): string
    {
        return 'Ringkasan Penjualan';
    }

    public function styles(Worksheet $sheet)
    {
        // Calculate total row: 1 (header) + count(reports) + 1 (empty) + 1 (total) = count + 3
        $totalRow = count($this->reports) + 3;
        
        return [
            // Header row
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Total row
            $totalRow => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '70AD47']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // All cells alignment
            'A:F' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]],
            // Numeric columns alignment
            'B:B' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'C:C' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'D:D' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'E:E' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'F:F' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
        ];
    }
}

// Sales Timeline Sheet - Combined for all stores
class SalesTimelineSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
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
            'Tanggal',
            'Nama Toko',
            'Jumlah Pesanan',
            'Pendapatan',
            'Jumlah Item',
            'Jumlah Pelanggan'
        ];
        
        // Collect all timeline data grouped by date
        $timelineByDate = [];
        $totals = [
            'orders' => 0,
            'revenue' => 0,
            'items' => 0,
            'customers' => 0,
        ];
        
        foreach ($this->reports as $reportItem) {
            $store = $reportItem['store'];
            $timeline = $reportItem['data']['timeline'] ?? [];
            
            foreach ($timeline as $date => $dateData) {
                if (!isset($timelineByDate[$date])) {
                    $timelineByDate[$date] = [];
                }
                
                $timelineByDate[$date][] = [
                    'store' => $store->name,
                    'orders' => $dateData['orders'] ?? 0,
                    'revenue' => $dateData['revenue'] ?? 0,
                    'items' => $dateData['items'] ?? 0,
                    'customers' => $dateData['customers'] ?? 0,
                ];
                
                // Accumulate totals
                $totals['orders'] += $dateData['orders'] ?? 0;
                $totals['revenue'] += $dateData['revenue'] ?? 0;
                $totals['items'] += $dateData['items'] ?? 0;
                $totals['customers'] += $dateData['customers'] ?? 0;
            }
        }
        
        // Sort by date
        ksort($timelineByDate);
        
        // Add data rows
        foreach ($timelineByDate as $date => $stores) {
            foreach ($stores as $storeData) {
                $data[] = [
                    $date,
                    $storeData['store'],
                    $storeData['orders'],
                    'Rp ' . number_format($storeData['revenue'], 0, ',', '.'),
                    $storeData['items'],
                    $storeData['customers'],
                ];
                $this->dataRowCount++;
            }
        }
        
        // Empty row before total
        $data[] = ['', '', '', '', '', ''];
        
        // Total row
        $data[] = [
            'TOTAL',
            '',
            $totals['orders'],
            'Rp ' . number_format($totals['revenue'], 0, ',', '.'),
            $totals['items'],
            $totals['customers']
        ];
        
        return collect($data);
    }

    public function headings(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Timeline Penjualan';
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
            'A:F' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]],
            'C:C' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'D:D' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'E:E' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'F:F' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
        ];
    }
}

// Payment Method Sheet - Combined for all stores
class SalesPaymentMethodSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
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
            $paymentMethods = $reportItem['data']['payment_methods'] ?? [];
            
            if (empty($paymentMethods)) {
                continue;
            }
            
            foreach ($paymentMethods as $key => $method) {
                $methodName = $key;
                if (is_array($method) && isset($method['method'])) {
                    $methodName = $method['method'];
                }
                
                $count = $method['count'] ?? 0;
                $amount = $method['amount'] ?? ($method['total_amount'] ?? 0);
                
                $totals['count'] += $count;
                $totals['amount'] += $amount;
                
                $data[] = [
                    $store->name,
                    ucfirst(str_replace('_', ' ', $methodName)),
                    $count,
                    'Rp ' . number_format($amount, 0, ',', '.'),
                ];
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
        return [];
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

// Top Products Sheet - Combined for all stores
class SalesTopProductsSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
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
            'Nama Produk',
            'Jumlah Terjual',
            'Pendapatan'
        ];
        
        // Collect all top products grouped by store
        $totals = [
            'quantity' => 0,
            'revenue' => 0,
        ];
        
        foreach ($this->reports as $reportItem) {
            $store = $reportItem['store'];
            $topProducts = $reportItem['data']['top_products'] ?? [];
            
            if (empty($topProducts)) {
                continue;
            }
            
            foreach ($topProducts as $product) {
                $quantity = $product['quantity'] ?? 0;
                $revenue = $product['revenue'] ?? 0;
                
                $totals['quantity'] += $quantity;
                $totals['revenue'] += $revenue;
                
                $data[] = [
                    $store->name,
                    $product['name'] ?? '',
                    $quantity,
                    'Rp ' . number_format($revenue, 0, ',', '.'),
                ];
                $this->dataRowCount++;
            }
        }
        
        // Empty row before total
        $data[] = ['', '', '', ''];
        
        // Total row
        $data[] = [
            'TOTAL',
            '',
            $totals['quantity'],
            'Rp ' . number_format($totals['revenue'], 0, ',', '.'),
        ];
        
        return collect($data);
    }

    public function headings(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Produk Terlaris';
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

