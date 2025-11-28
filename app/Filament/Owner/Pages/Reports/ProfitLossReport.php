<?php

namespace App\Filament\Owner\Pages\Reports;

use App\Filament\Owner\Pages\Concerns\HasLocalReportFilterForm;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;
use App\Filament\Traits\HasPlanBasedNavigation;
use BackedEnum;
use UnitEnum;

class ProfitLossReport extends Page
{
    use HasLocalReportFilterForm;
    use HasPlanBasedNavigation;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Laporan Laba Rugi';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

    protected static ?int $navigationSort = 15;

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasPlanFeature('ALLOW_ADVANCED_REPORTS');
    }

    protected string $view = 'filament.owner.pages.reports.profit-loss-report';

    public function mount(): void
    {
        if (!static::hasPlanFeature('ALLOW_ADVANCED_REPORTS')) {
            abort(403, 'Upgrade plan required.');
        }
        $this->initializeLocalFilters();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Owner\Pages\Reports\Widgets\ProfitLossFilterWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Owner\Pages\Reports\Widgets\ProfitLossSummaryCard::class,
            \App\Filament\Owner\Pages\Reports\Widgets\ProfitLossDetailTable::class,
        ];
    }

    #[On('profit-loss-filter-updated')]
    public function handleFilterUpdated(): void
    {
        // Reload filters from session
        $this->initializeLocalFilters();
    }

    public function updatedFilters(): void
    {
        parent::updatedFilters();
        
        // Dispatch event specific to profit loss report
        $this->dispatch('profit-loss-filter-updated');
    }
}

