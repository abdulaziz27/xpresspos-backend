<?php

namespace App\Filament\Owner\Pages\Settings;

use App\Models\Tenant;
use App\Services\GlobalFilterService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class BusinessSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Pengaturan Bisnis';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.owner.pages.settings.business-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('owner');
    }

    public function mount(): void
    {
        $tenant = $this->resolveTenant();

        if (!$tenant) {
            Notification::make()
                ->title('Tenant tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        // Load tenant data
        $this->data = [
            'name' => $tenant->name,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'timezone' => $tenant->settings['timezone'] ?? 'Asia/Jakarta',
            'currency' => $tenant->settings['currency'] ?? 'IDR',
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profil Bisnis')
                    ->description('Informasi dasar bisnis yang berlaku untuk semua toko di tenant ini.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Brand / Bisnis')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Nama bisnis Anda'),

                                TextInput::make('email')
                                    ->label('Email Bisnis')
                                    ->email()
                                    ->maxLength(255)
                                    ->placeholder('bisnis@example.com'),
                            ]),

                        TextInput::make('phone')
                            ->label('Nomor Telepon Utama')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('+62 812-3456-7890'),
                    ]),

                Section::make('Preferensi Umum')
                    ->description('Pengaturan default yang akan digunakan di semua toko.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('timezone')
                                    ->label('Zona Waktu')
                                    ->options([
                                        'Asia/Jakarta' => 'WIB (Jakarta)',
                                        'Asia/Makassar' => 'WITA (Makassar)',
                                        'Asia/Jayapura' => 'WIT (Jayapura)',
                                    ])
                                    ->default('Asia/Jakarta')
                                    ->required(),

                                Select::make('currency')
                                    ->label('Mata Uang')
                                    ->options([
                                        'IDR' => 'IDR - Rupiah',
                                        // TODO: Enable other currencies when needed
                                        // 'USD' => 'USD - US Dollar',
                                        // 'SGD' => 'SGD - Singapore Dollar',
                                    ])
                                    ->default('IDR')
                                    ->required()
                                    ->disabled(fn () => true) // Disable for now, only IDR available
                                    ->helperText('Saat ini hanya IDR yang tersedia.'),
                            ]),
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
        $tenant = $this->resolveTenant();

        if (!$tenant) {
            Notification::make()
                ->title('Tenant tidak ditemukan')
                ->danger()
                ->send();
            return;
        }

        $state = $this->form->getState();

        // Update tenant fields
        $tenant->update([
            'name' => $state['name'],
            'email' => $state['email'] ?? null,
            'phone' => $state['phone'] ?? null,
            'settings' => array_merge(
                $tenant->settings ?? [],
                [
                    'timezone' => $state['timezone'] ?? 'Asia/Jakarta',
                    'currency' => $state['currency'] ?? 'IDR',
                ]
            ),
        ]);

        Notification::make()
            ->title('Pengaturan bisnis berhasil disimpan.')
            ->success()
            ->send();
    }

    protected function resolveTenant(): ?Tenant
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        
        $tenant = $globalFilter->getCurrentTenant();

        if (!$tenant) {
            $tenant = auth()->user()?->currentTenant();
        }

        return $tenant;
    }
}

