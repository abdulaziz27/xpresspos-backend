<?php

namespace App\Filament\Owner\Pages\Reports;

use App\Filament\Owner\Pages\Concerns\HasLocalReportFilterForm;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;
use BackedEnum;
use UnitEnum;

class CashFlowReport extends Page
{
    use HasLocalReportFilterForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Laporan Kas Harian';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.owner.pages.reports.cash-flow-report';

    public function mount(): void
    {
        $this->initializeLocalFilters();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Owner\Pages\Reports\Widgets\CashFlowFilterWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Owner\Pages\Reports\Widgets\CashFlowSummaryCards::class,
            \App\Filament\Owner\Pages\Reports\Widgets\CashReceiptsTable::class,
            \App\Filament\Owner\Pages\Reports\Widgets\CashRefundsTable::class,
            \App\Filament\Owner\Pages\Reports\Widgets\CashExpensesTable::class,
        ];
    }

    #[On('cash-flow-filter-updated')]
    public function handleFilterUpdated(): void
    {
        // Reload filters from session
        $this->initializeLocalFilters();
    }
}


