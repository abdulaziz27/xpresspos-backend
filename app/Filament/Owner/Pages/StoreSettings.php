<?php

namespace App\Filament\Owner\Pages;

use App\Models\Store;
use App\Services\StoreContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use UnitEnum;

class StoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Store Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Store Operations';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.owner.pages.store-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $store = $this->resolveStore();

        $this->data = array_merge(
            $this->defaultSettings(),
            $store->settings ?? []
        );

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                ->icon('heroicon-o-archive-box')
                ->color('primary'),
        ];
    }

    public function save(): void
    {
        $store = $this->resolveStore();
        $state = $this->form->getState();

        $store->update([
            'settings' => array_merge($this->defaultSettings(), $state),
        ]);

        Notification::make()
            ->title('Pengaturan toko berhasil disimpan.')
            ->success()
            ->send();
    }

    protected function resolveStore(): Store
    {
        /** @var StoreContext $context */
        $context = app(StoreContext::class);
        $storeId = $context->current(Auth::user());

        abort_unless($storeId, 404);

        return Store::query()->findOrFail($storeId);
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
