<?php

namespace App\Filament\Owner\Pages\Concerns;

use App\Models\Store;
use App\Services\GlobalFilterService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

trait HasLocalReportFilterForm
{
    use HasFiltersForm {
        HasFiltersForm::updatedFilters as baseUpdatedFilters;
        HasFiltersForm::normalizeTableFilterValuesFromQueryString as normalizeTableFilterValuesFromQueryStringFilters;
    }

    /**
     * Get session key prefix for this page's filters
     * Override this in the page class to customize
     */
    protected function getLocalFilterSessionKeyPrefix(): string
    {
        // Default: use class name as prefix
        $className = class_basename(static::class);
        return 'local_filter.' . strtolower($className);
    }

    protected function initializeLocalFilters(): void
    {
        if (blank($this->filters)) {
            $this->filters = $this->getDefaultLocalFilters();

            if (method_exists($this, 'getFiltersForm')) {
                $this->getFiltersForm()->fill($this->filters);
            }

            $this->persistLocalFiltersState();
        }
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Filter Laporan')
                    ->schema([
                        Select::make('tenant_id')
                            ->label('Tenant')
                            ->placeholder('Pilih tenant')
                            ->options($this->getTenantOptions())
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
                    ])
                    ->columns([
                        'md' => 2,
                        'xl' => 4,
                    ]),
            ]);
    }

    public function updatedFilters(): void
    {
        $this->baseUpdatedFilters();
        $this->persistLocalFiltersState();

        if (method_exists($this, 'loadReportData')) {
            $this->loadReportData();
        }
    }

    protected function persistLocalFiltersState(): void
    {
        if (! $this->persistsFiltersInSession()) {
            return;
        }

        $key = $this->getLocalFilterSessionKey();
        Session::put($key, $this->filters);
    }

    protected function getLocalFilterSessionKey(): string
    {
        return $this->getLocalFilterSessionKeyPrefix() . '.filters';
    }

    protected function getDefaultLocalFilters(): array
    {
        // Try to load from session first
        $key = $this->getLocalFilterSessionKey();
        $saved = Session::get($key);

        if ($saved && is_array($saved)) {
            return $saved;
        }

        // Default: use current tenant and this month
        $tenantId = Auth::user()?->currentTenant()?->id;
        $globalService = app(GlobalFilterService::class);
        $preset = 'this_month';
        $range = $globalService->getDateRangeForPreset($preset);

        return [
            'tenant_id' => $tenantId,
            'store_id' => null, // All stores by default
            'date_preset' => $preset,
            'date_start' => $range['start']->toDateString(),
            'date_end' => $range['end']->toDateString(),
        ];
    }

    /**
     * Get current tenant ID from local filter
     */
    protected function getLocalTenantId(): ?string
    {
        return $this->filters['tenant_id'] ?? null;
    }

    /**
     * Get current store ID from local filter (null = all stores)
     */
    protected function getLocalStoreId(): ?string
    {
        return $this->filters['store_id'] ?? null;
    }

    /**
     * Get store IDs for current tenant (array of IDs, or empty for all)
     */
    protected function getLocalStoreIds(): array
    {
        $tenantId = $this->getLocalTenantId();
        
        if (!$tenantId) {
            return [];
        }

        $storeId = $this->getLocalStoreId();

        // If specific store selected, return only that store
        if ($storeId) {
            return [$storeId];
        }

        // Return all stores for tenant
        return Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get date range from local filter
     */
    protected function getLocalDateRange(): array
    {
        $preset = $this->filters['date_preset'] ?? 'this_month';
        $globalService = app(GlobalFilterService::class);

        if ($preset === 'custom') {
            $start = $this->filters['date_start'] ?? null;
            $end = $this->filters['date_end'] ?? null;

            if ($start && $end) {
                return [
                    'start' => Carbon::parse($start)->startOfDay(),
                    'end' => Carbon::parse($end)->endOfDay(),
                ];
            }
        }

        // Use preset
        return $globalService->getDateRangeForPreset($preset);
    }

    /**
     * Get filter summary for display
     */
    protected function getLocalFilterSummary(): array
    {
        $tenantId = $this->getLocalTenantId();
        $storeId = $this->getLocalStoreId();
        $range = $this->getLocalDateRange();
        $preset = $this->filters['date_preset'] ?? 'this_month';

        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;
        $store = $storeId ? Store::find($storeId) : null;
        $globalService = app(GlobalFilterService::class);

        return [
            'tenant' => $tenant?->name ?? 'N/A',
            'tenant_id' => $tenantId,
            'store' => $store?->name ?? 'Semua Cabang',
            'store_id' => $storeId,
            'date_start' => $range['start']->format('d M Y'),
            'date_end' => $range['end']->format('d M Y'),
            'date_preset' => $preset,
            'date_preset_label' => $globalService->getAvailableDatePresets()[$preset] ?? 'Custom',
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
        return app(GlobalFilterService::class)->getAvailableDatePresets();
    }
}

