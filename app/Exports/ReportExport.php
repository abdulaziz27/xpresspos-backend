<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Illuminate\Support\Collection;

class ReportExport implements WithMultipleSheets
{
    public function __construct(
        private string $reportType,
        private array $data
    ) {}

    /**
     * Return array of sheets for the export.
     */
    public function sheets(): array
    {
        return match ($this->reportType) {
            'sales' => $this->createSalesSheets(),
            'inventory' => $this->createInventorySheets(),
            'cash_flow' => $this->createCashFlowSheets(),
            'product_performance' => $this->createProductPerformanceSheets(),
            'customer_analytics' => $this->createCustomerAnalyticsSheets(),
            default => [$this->createGenericSheet()]
        };
    }

    /**
     * Create sheets for sales report.
     */
    private function createSalesSheets(): array
    {
        return [
            new SalesSummarySheet($this->data),
            new SalesTimelineSheet($this->data),
            new TopProductsSheet($this->data),
        ];
    }

    /**
     * Create sheets for inventory report.
     */
    private function createInventorySheets(): array
    {
        return [
            new InventorySheet($this->data),
        ];
    }

    /**
     * Create sheets for cash flow report.
     */
    private function createCashFlowSheets(): array
    {
        return [
            new CashFlowSummarySheet($this->data),
            new PaymentMethodsSheet($this->data),
            new ExpenseCategoriesSheet($this->data),
        ];
    }

    /**
     * Create sheets for product performance report.
     */
    private function createProductPerformanceSheets(): array
    {
        return [
            new ProductPerformanceSheet($this->data),
        ];
    }

    /**
     * Create sheets for customer analytics report.
     */
    private function createCustomerAnalyticsSheets(): array
    {
        return [
            new CustomerAnalyticsSheet($this->data),
            new TopCustomersSheet($this->data),
        ];
    }

    /**
     * Create generic sheet for unsupported report types.
     */
    private function createGenericSheet(): object
    {
        return new class($this->data) implements FromCollection, WithTitle {
            public function __construct(private array $data) {}
            
            public function collection()
            {
                return collect($this->data);
            }
            
            public function title(): string
            {
                return 'Report Data';
            }
        };
    }
}

// Sales Summary Sheet
class SalesSummarySheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        $summary = $this->data['summary'];
        
        return collect([
            ['Total Orders', $summary['total_orders']],
            ['Total Revenue', number_format($summary['total_revenue'], 2)],
            ['Total Items', $summary['total_items']],
            ['Average Order Value', number_format($summary['average_order_value'], 2)],
            ['Unique Customers', $summary['unique_customers']],
        ]);
    }

    public function headings(): array
    {
        return ['Metric', 'Value'];
    }

    public function title(): string
    {
        return 'Sales Summary';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A:B' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]],
        ];
    }
}

// Sales Timeline Sheet
class SalesTimelineSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        $timeline = $this->data['timeline'];
        
        return collect($timeline)->map(function ($data, $date) {
            return [
                'date' => $date,
                'orders' => $data['orders'],
                'revenue' => number_format($data['revenue'], 2),
                'items' => $data['items'],
                'customers' => $data['customers'],
            ];
        })->values();
    }

    public function headings(): array
    {
        return ['Date', 'Orders', 'Revenue', 'Items', 'Customers'];
    }

    public function title(): string
    {
        return 'Sales Timeline';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Top Products Sheet
class TopProductsSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        return collect($this->data['top_products'])->map(function ($product) {
            return [
                'name' => $product['name'],
                'quantity' => $product['quantity'],
                'revenue' => number_format($product['revenue'], 2),
            ];
        });
    }

    public function headings(): array
    {
        return ['Product Name', 'Quantity Sold', 'Revenue'];
    }

    public function title(): string
    {
        return 'Top Products';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Inventory Sheet
class InventorySheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        return collect($this->data['products'])->map(function ($product) {
            return [
                'name' => $product['name'],
                'sku' => $product['sku'],
                'category' => $product['category'],
                'current_stock' => $product['current_stock'],
                'min_stock_level' => $product['min_stock_level'],
                'cost_price' => number_format($product['cost_price'], 2),
                'selling_price' => number_format($product['selling_price'], 2),
                'stock_value' => number_format($product['stock_value'], 2),
                'status' => ucfirst(str_replace('_', ' ', $product['status'])),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Product Name', 'SKU', 'Category', 'Current Stock', 
            'Min Stock Level', 'Cost Price', 'Selling Price', 
            'Stock Value', 'Status'
        ];
    }

    public function title(): string
    {
        return 'Inventory Report';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Cash Flow Summary Sheet
class CashFlowSummarySheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        $summary = $this->data['summary'];
        
        return collect([
            ['Total Revenue', number_format($summary['total_revenue'], 2)],
            ['Total Expenses', number_format($summary['total_expenses'], 2)],
            ['Net Cash Flow', number_format($summary['net_cash_flow'], 2)],
            ['Transaction Count', $summary['transaction_count']],
            ['Expense Count', $summary['expense_count']],
            ['Average Transaction', number_format($summary['average_transaction'], 2)],
            ['Average Expense', number_format($summary['average_expense'], 2)],
        ]);
    }

    public function headings(): array
    {
        return ['Metric', 'Value'];
    }

    public function title(): string
    {
        return 'Cash Flow Summary';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Payment Methods Sheet
class PaymentMethodsSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        return collect($this->data['payment_methods'])->map(function ($data, $method) {
            return [
                'method' => ucfirst($method),
                'count' => $data['count'],
                'total_amount' => number_format($data['total_amount'], 2),
                'average_amount' => number_format($data['average_amount'], 2),
            ];
        })->values();
    }

    public function headings(): array
    {
        return ['Payment Method', 'Count', 'Total Amount', 'Average Amount'];
    }

    public function title(): string
    {
        return 'Payment Methods';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Expense Categories Sheet
class ExpenseCategoriesSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        return collect($this->data['expense_categories'])->map(function ($data, $category) {
            return [
                'category' => ucfirst($category),
                'count' => $data['count'],
                'total_amount' => number_format($data['total_amount'], 2),
                'average_amount' => number_format($data['average_amount'], 2),
            ];
        })->values();
    }

    public function headings(): array
    {
        return ['Expense Category', 'Count', 'Total Amount', 'Average Amount'];
    }

    public function title(): string
    {
        return 'Expense Categories';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Product Performance Sheet
class ProductPerformanceSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        return collect($this->data['products'])->map(function ($product) {
            return [
                'name' => $product['name'],
                'sku' => $product['sku'],
                'category' => $product['category'],
                'quantity_sold' => $product['quantity_sold'],
                'revenue' => number_format($product['revenue'], 2),
                'profit' => number_format($product['profit'], 2),
                'profit_margin' => $product['profit_margin'] . '%',
                'order_count' => $product['order_count'],
                'average_price' => number_format($product['average_price'], 2),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Product Name', 'SKU', 'Category', 'Quantity Sold', 
            'Revenue', 'Profit', 'Profit Margin', 'Order Count', 'Average Price'
        ];
    }

    public function title(): string
    {
        return 'Product Performance';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Customer Analytics Sheet
class CustomerAnalyticsSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        $summary = $this->data['summary'];
        
        return collect([
            ['Total Orders', $summary['total_orders']],
            ['Unique Customers', $summary['unique_customers']],
            ['Guest Orders', $summary['guest_orders']],
            ['Member Orders', $summary['member_orders']],
            ['Member Percentage', $summary['member_percentage'] . '%'],
            ['Total Revenue', number_format($summary['total_revenue'], 2)],
            ['Average Order Value', number_format($summary['average_order_value'], 2)],
        ]);
    }

    public function headings(): array
    {
        return ['Metric', 'Value'];
    }

    public function title(): string
    {
        return 'Customer Analytics';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Top Customers Sheet
class TopCustomersSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(private array $data) {}

    public function collection()
    {
        return collect($this->data['top_customers'])->map(function ($customer) {
            return [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'order_count' => $customer->order_count,
                'total_spent' => number_format($customer->total_spent, 2),
                'average_order' => number_format($customer->average_order, 2),
                'last_order_date' => $customer->last_order_date,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Customer Name', 'Email', 'Phone', 'Order Count', 
            'Total Spent', 'Average Order', 'Last Order Date'
        ];
    }

    public function title(): string
    {
        return 'Top Customers';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}