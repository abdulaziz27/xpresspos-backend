<?php

namespace App\Filament\Owner\Pages;

use App\Models\Store;
use App\Services\GlobalFilterService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OwnerDashboard extends BaseDashboard
{
    use HasFiltersForm {
        HasFiltersForm::updatedFilters as baseUpdatedFilters;
    }

    protected static ?string $navigationLabel = 'Dashboard';

    public function mount(): void
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

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 1,
            'lg' => 12,
            'xl' => 12,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Filter Global Dashboard')
                    ->description('Pilih tenant, cabang, dan periode data untuk seluruh widget.')
                    ->columnSpan('full')
                    ->schema([
                        Select::make('tenant_id')
                            ->label('Tenant')
                            ->placeholder('Semua Tenant')
                            ->options(fn () => $this->getTenantOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (callable $set): void {
                                $set('store_id', null);
                            }),
                        Select::make('store_id')
                            ->label('Cabang Toko')
                            ->placeholder('Semua Cabang')
                            ->options(fn (callable $get) => $this->getStoreOptions($get('tenant_id')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn (callable $get): bool => blank($get('tenant_id'))),
                        Select::make('date_preset')
                            ->label('Periode')
                            ->placeholder('Semua Periode')
                            ->options(fn () => $this->getDatePresetOptions())
                            ->live(),
                        DatePicker::make('date_start')
                            ->label('Dari Tanggal')
                            ->placeholder('Pilih tanggal mulai')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->live()
                            ->visible(fn (callable $get): bool => $get('date_preset') === 'custom')
                            ->maxDate(fn (callable $get) => $get('date_end')),
                        DatePicker::make('date_end')
                            ->label('Sampai Tanggal')
                            ->placeholder('Pilih tanggal akhir')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->live()
                            ->visible(fn (callable $get): bool => $get('date_preset') === 'custom')
                            ->after('date_start'),
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

    protected function syncGlobalFilterService(): void
    {
        /** @var GlobalFilterService $service */
        $service = app(GlobalFilterService::class);
        $service->syncFromDashboardFilters($this->filters ?? []);
    }

    protected function getDefaultFilters(): array
    {
        /** @var GlobalFilterService $service */
        $service = app(GlobalFilterService::class);

        $defaults = $service->getFilterState();

        if (! $defaults['tenant_id']) {
            $defaults['tenant_id'] = auth()->user()?->currentTenant()?->id;

            if ($defaults['tenant_id']) {
                $service->setTenant($defaults['tenant_id']);
            }
        }

        return $defaults;
    }

    protected function getTenantOptions(): array
    {
        $user = auth()->user();

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
