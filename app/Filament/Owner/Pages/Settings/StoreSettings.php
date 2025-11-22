<?php

namespace App\Filament\Owner\Pages\Settings;

use App\Models\Store;
use App\Services\GlobalFilterService;
use App\Services\StoreContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class StoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Pengaturan Toko';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.owner.pages.settings.store-settings';

    public ?array $data = [];

    public ?string $selectedStoreId = null;

    public function mount(): void
    {
        $this->loadStoreData();
    }

    protected function loadStoreData(): void
    {
        $store = $this->resolveStore();

        if (!$store) {
            Notification::make()
                ->title('Toko tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        // Update selectedStoreId
        $this->selectedStoreId = $store->id;

        // Merge store settings with defaults
        $this->data = array_merge(
            $this->defaultSettings(),
            $store->settings ?? []
        );

        // Fill form with store data
        $this->form->fill($this->data);
    }

    // Method ini sudah tidak diperlukan karena menggunakan afterStateUpdated di Select
    // public function updatedSelectedStoreId(): void
    // {
    //     if ($this->selectedStoreId) {
    //         $this->loadStoreData();
    //     }
    // }

    public function form(Schema $schema): Schema
    {
        $stores = $this->getAvailableStores();
        $hasMultipleStores = $stores->count() > 1;

        return $schema
            ->components([
                // Store selector (only show if tenant has multiple stores)
                // Note: storeSelector uses separate statePath, not part of form data
                ...($hasMultipleStores ? [
                    Section::make('Pilih Toko')
                        ->description('Pilih toko yang ingin Anda atur pengaturannya.')
                        ->schema([
                            Select::make('storeSelector')
                                ->label('Toko')
                                ->options($stores->pluck('name', 'id'))
                                ->default(fn () => $this->selectedStoreId ?? $stores->first()?->id)
                                ->required()
                                ->searchable()
                                ->live()
                                ->dehydrated(false) // Don't include in form state (data)
                                ->afterStateHydrated(function ($component) use ($stores) {
                                    // Ensure value is set from property page
                                    if (!$component->getState() && $this->selectedStoreId) {
                                        $component->state($this->selectedStoreId);
                                    } elseif (!$component->getState() && $stores->first()) {
                                        $component->state($stores->first()->id);
                                        $this->selectedStoreId = $stores->first()->id;
                                    }
                                })
                                ->afterStateUpdated(function ($state) {
                                    $this->selectedStoreId = $state;
                                    $this->loadStoreData();
                                }),
                        ])
                        ->collapsible(false),
                ] : []),

                Section::make('Tax & Service Charges')
                    ->description('Nilai default yang akan digunakan untuk menghitung order baru.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('tax_rate')
                                    ->label('Tax Rate (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->helperText('Persentase pajak standar yang akan diterapkan.')
                                    ->default(0),

                                TextInput::make('service_charge_rate')
                                    ->label('Service Charge (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->helperText('Persentase service charge standar yang akan diterapkan.')
                                    ->default(0),
                            ]),

                        Toggle::make('tax_included')
                            ->label('Harga sudah termasuk pajak')
                            ->default(false)
                            ->helperText('Aktifkan bila harga jual sudah termasuk pajak.'),
                    ]),

                Section::make('Nota & Komunikasi')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('website_url')
                                    ->label('Website Toko')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://tokokamu.com'),

                                TextInput::make('thank_you_message')
                                    ->label('Ucapan Terima Kasih')
                                    ->maxLength(255)
                                    ->placeholder('Terima kasih, sampai jumpa lagi!'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('wifi_name')
                                    ->label('Nama WiFi (SSID)')
                                    ->maxLength(100)
                                    ->placeholder('XPRESS-GUEST'),

                                TextInput::make('wifi_password')
                                    ->label('Password WiFi')
                                    ->maxLength(100)
                                    ->placeholder('passwordwifi'),
                            ]),

                        Textarea::make('receipt_footer')
                            ->label('Catatan Footer Nota')
                            ->rows(3)
                            ->placeholder('Terima kasih atas kunjungan Anda!')
                            ->maxLength(500),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Pengaturan')
                ->action('save')
                ->icon(Heroicon::OutlinedCheck)
                ->color('primary'),
        ];
    }

    public function save(): void
    {
        $store = $this->resolveStore();

        if (!$store) {
            Notification::make()
                ->title('Toko tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        $state = $this->form->getState();

        // Note: selectedStoreId is excluded from form state via dehydrated(false)
        // so it won't be in $state array

        $store->update([
            'settings' => array_merge($this->defaultSettings(), $state),
        ]);

        Notification::make()
            ->title('Pengaturan toko berhasil disimpan.')
            ->success()
            ->send();
    }

    protected function resolveStore(): ?Store
    {
        // If store selector is used and a store is selected
        if ($this->selectedStoreId) {
            $store = Store::find($this->selectedStoreId);
            
            // Verify store belongs to current tenant
            $tenantId = $this->getCurrentTenantId();
            if ($store && $store->tenant_id === $tenantId) {
                return $store;
            }
        }

        // Fallback to StoreContext (for single store tenants)
        /** @var StoreContext $context */
        $context = app(StoreContext::class);
        $storeId = $context->current(Auth::user());

        if ($storeId) {
            return Store::query()->find($storeId);
        }

        // Last resort: get first store for current tenant
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId) {
            return Store::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
        }

        return null;
    }

    protected function getAvailableStores(): \Illuminate\Support\Collection
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        
        return $globalFilter->getAvailableStores();
    }

    protected function getCurrentTenantId(): ?string
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        
        return $globalFilter->getCurrentTenantId();
    }

    protected function defaultSettings(): array
    {
        return [
            'tax_rate' => 0,
            'service_charge_rate' => 0,
            'tax_included' => false,
            'website_url' => null,
            'thank_you_message' => null,
            'wifi_name' => null,
            'wifi_password' => null,
            'receipt_footer' => null,
        ];
    }
}

