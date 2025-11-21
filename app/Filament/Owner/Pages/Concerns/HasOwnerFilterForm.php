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

trait HasOwnerFilterForm
{
    use HasFiltersForm {
        HasFiltersForm::updatedFilters as baseUpdatedFilters;
    }

    protected function initializeOwnerFilters(): void
    {
        if (blank($this->filters)) {
            $this->filters = $this->getDefaultFilters();

            if (method_exists($this, 'getFiltersForm')) {
                $this->getFiltersForm()->fill($this->filters);
            }

            $this->persistFiltersState();
        }

        $this->syncGlobalFilterService();
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
        $this->syncGlobalFilterService();

        if (method_exists($this, 'loadReportData')) {
            $this->loadReportData();
        }
    }

    protected function persistFiltersState(): void
    {
        if (! $this->persistsFiltersInSession()) {
            return;
        }

        session()->put(
            $this->getFiltersSessionKey(),
            $this->filters,
        );
    }

    protected function getDefaultFilters(): array
    {
        /** @var GlobalFilterService $service */
        $service = app(GlobalFilterService::class);
        $defaults = $service->getFilterState();

        if (! ($defaults['tenant_id'] ?? null)) {
            $tenantId = Auth::user()?->currentTenant()?->id;
            if ($tenantId) {
                $defaults['tenant_id'] = $tenantId;
                $service->setTenant($tenantId);
            }
        }

        return $defaults;
    }

    protected function syncGlobalFilterService(): void
    {
        app(GlobalFilterService::class)->syncFromDashboardFilters($this->filters ?? []);
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


