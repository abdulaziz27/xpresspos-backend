<?php

namespace App\Filament\Owner\Pages\Reports;

use App\Filament\Owner\Pages\Concerns\HasLocalReportFilterForm;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;
use BackedEnum;
use UnitEnum;

class InventoryReport extends Page
{
    use HasLocalReportFilterForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Laporan Bahan Baku';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.owner.pages.reports.inventory-report';

    protected function getLocalFilterSessionKeyPrefix(): string
    {
        return 'local_filter.inventoryreport';
    }

    public function mount(): void
    {
        $this->initializeLocalFilters();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Owner\Pages\Reports\Widgets\InventoryReportFilterWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Owner\Pages\Reports\Widgets\TotalStockValueCard::class,
            \App\Filament\Owner\Pages\Reports\Widgets\InventoryOverviewTable::class,
            \App\Filament\Owner\Pages\Reports\Widgets\StockMovementTable::class,
            \App\Filament\Owner\Pages\Reports\Widgets\MaterialUsageChart::class,
        ];
    }

    #[On('inventory-report-filter-updated')]
    public function handleFilterUpdated(): void
    {
        // Reload filters from session
        $this->initializeLocalFilters();
    }

    public function updatedFilters(): void
    {
        parent::updatedFilters();
        
        // Dispatch event specific to inventory report
        $this->dispatch('inventory-report-filter-updated');
    }
}

