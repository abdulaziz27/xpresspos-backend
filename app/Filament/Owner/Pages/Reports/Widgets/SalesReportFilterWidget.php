<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Store;
use App\Services\GlobalFilterService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;
use Filament\Actions\Action;

class SalesReportFilterWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static ?int $sort = -9999; // Always at top

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.owner.pages.reports.widgets.sales-report-filter-widget';

    public ?array $data = [];

    protected function getSessionKey(): string
    {
        // Use same session key as SalesReport page
        // SalesReport uses class_basename which is 'SalesReport'
        // So the key is 'local_filter.salesreport.filters'
        return 'local_filter.salesreport.filters';
    }

    public function mount(): void
    {
        // Load filters from session (same as page)
        $filters = Session::get($this->getSessionKey(), $this->getDefaultFilters());
        $this->data = $filters;
        $this->form->fill($filters);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns([
                'md' => 2,
                'xl' => 4,
            ])
            ->components([
                Select::make('tenant_id')
                    ->label('Tenant')
                    ->placeholder('Pilih tenant')
                    ->options(fn () => $this->getTenantOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('store_id', null)),
                Select::make('store_id')
                    ->label('Cabang')
                    ->placeholder('Semua cabang')
                    ->options(fn (callable $get) => $this->getStoreOptions($get('tenant_id')))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disabled(fn (callable $get): bool => blank($get('tenant_id'))),
                Select::make('date_preset')
                    ->label('Periode')
                    ->options($this->getDatePresetOptions())
                    ->default('this_month')
                    ->live(),
                DatePicker::make('date_start')
                    ->label('Dari tanggal')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn (callable $get): bool => $get('date_preset') === 'custom')
                    ->maxDate(fn (callable $get) => $get('date_end')),
                DatePicker::make('date_end')
                    ->label('Sampai tanggal')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn (callable $get): bool => $get('date_preset') === 'custom')
                    ->minDate(fn (callable $get) => $get('date_start')),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('export')
                ->label('Export ke Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->outlined()
                ->url(fn () => $this->getExportUrl())
                ->openUrlInNewTab(),
        ];
    }
    
    public function getExportUrl(): string
    {
        $filters = Session::get($this->getSessionKey(), $this->getDefaultFilters());
        $globalService = app(GlobalFilterService::class);
        
        // Get date range
        $datePreset = $filters['date_preset'] ?? 'this_month';
        $range = $globalService->getDateRangeForPreset($datePreset);
        
        $startDate = $filters['date_start'] ?? $range['start']->toDateString();
        $endDate = $filters['date_end'] ?? $range['end']->toDateString();
        
        // Build export URL with store_id and tenant_id
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        
        if (!empty($filters['tenant_id'])) {
            $params['tenant_id'] = $filters['tenant_id'];
        }
        
        if (!empty($filters['store_id'])) {
            $params['store_id'] = $filters['store_id'];
        }
        
        return route('api.v1.reports.sales.export', $params);
    }

    public function updatedData(): void
    {
        // Get current form state
        $filters = $this->form->getState();
        
        // Persist to session
        Session::put($this->getSessionKey(), $filters);
        
        // Dispatch event to update page and all widgets
        $this->dispatch('sales-report-filter-updated');
    }

    #[On('sales-report-filter-updated')]
    public function refreshForm(): void
    {
        // Reload form from session
        $filters = Session::get($this->getSessionKey(), $this->getDefaultFilters());
        $this->form->fill($filters);
    }

    protected function getDefaultFilters(): array
    {
        $user = Auth::user();
        $tenantId = $user?->currentTenant()?->id;
        $globalService = app(GlobalFilterService::class);
        $preset = 'this_month';
        $range = $globalService->getDateRangeForPreset($preset);

        return [
            'tenant_id' => $tenantId,
            'store_id' => null,
            'date_preset' => $preset,
            'date_start' => $range['start']->toDateString(),
            'date_end' => $range['end']->toDateString(),
        ];
    }

    protected function getTenantOptions(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        return $user->tenants()
            ->select('tenants.id', 'tenants.name')
            ->orderBy('tenants.name')
            ->pluck('tenants.name', 'tenants.id')
            ->toArray();
    }

    protected function getStoreOptions(?string $tenantId): array
    {
        if (! $tenantId) {
            return [];
        }

        return Store::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getDatePresetOptions(): array
    {
        return [
            'today' => 'Hari Ini',
            'yesterday' => 'Kemarin',
            'this_week' => 'Minggu Ini',
            'last_week' => 'Minggu Lalu',
            'this_month' => 'Bulan Ini',
            'last_month' => 'Bulan Lalu',
            'this_year' => 'Tahun Ini',
            'custom' => 'Custom',
        ];
    }
}

