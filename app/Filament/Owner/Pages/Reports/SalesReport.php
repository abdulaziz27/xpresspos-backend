<?php

namespace App\Filament\Owner\Pages\Reports;

use App\Filament\Owner\Pages\Concerns\HasLocalReportFilterForm;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;
use BackedEnum;
use UnitEnum;

class SalesReport extends Page
{
    use HasLocalReportFilterForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Laporan Penjualan';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.owner.pages.reports.sales-report';


    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Owner\Pages\Reports\Widgets\SalesReportFilterWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Owner\Pages\Reports\Widgets\SalesTrendChart::class,
            \App\Filament\Owner\Pages\Reports\Widgets\SalesPerDayChart::class,
            \App\Filament\Owner\Pages\Reports\Widgets\SalesPerHourChart::class,
            \App\Filament\Owner\Pages\Reports\Widgets\SalesAndProductsPerDayChart::class,
            \App\Filament\Owner\Pages\Reports\Widgets\PaymentMethodPieChart::class,
            \App\Filament\Owner\Pages\Reports\Widgets\SalesSummaryCard::class,
            \App\Filament\Owner\Pages\Reports\Widgets\TransactionControlCard::class,
        ];
    }

    public function mount(): void
    {
        $this->initializeLocalFilters();
    }

    #[On('sales-report-filter-updated')]
    public function handleFilterUpdated(): void
    {
        // Reload filters from session
        $this->initializeLocalFilters();
    }
}


