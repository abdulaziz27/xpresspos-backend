<?php

namespace App\Filament\Owner\Resources\Members\Schemas;

use App\Models\MemberTier;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Member Information')
                    ->description('Basic member details and contact information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('member_number')
                                    ->label('Member Number')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(20),
                            ]),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('date_of_birth')
                                    ->label('Date of Birth')
                                    ->displayFormat('d/m/Y'),

                                Select::make('tier_id')
                                    ->label('Member Tier')
                                    ->options(function () {
                                        $storeId = auth()->user()?->currentStoreId();

                                        return MemberTier::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->active()
                                            ->ordered()
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Tier Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(fn($state, callable $set) => $set('slug', str()->slug($state))),

                                        TextInput::make('slug')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('min_points')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),

                                        TextInput::make('max_points')
                                            ->numeric()
                                            ->minValue(0)
                                            ->nullable()
                                            ->helperText('Kosongkan jika tidak ada batas.'),

                                        TextInput::make('discount_percentage')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->default(0)
                                            ->suffix('%'),

                                        ColorPicker::make('color')
                                            ->default('#6B7280'),

                                        Textarea::make('description')
                                            ->rows(2)
                                            ->maxLength(1000),

                                        Toggle::make('is_active')
                                            ->default(true),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        $storeId = auth()->user()?->currentStoreId();
                                        $data['store_id'] = $storeId;
                                        $nextSort = MemberTier::withoutStoreScope()
                                            ->where('store_id', $storeId)
                                            ->max('sort_order');
                                        $data['sort_order'] = ($nextSort ?? 0) + 1;

                                        return MemberTier::create($data)->getKey();
                                    }),
                            ]),

                        Textarea::make('address')
                            ->rows(3)
                            ->maxLength(500),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->columns(1),

                Section::make('Loyalty Information')
                    ->description('Loyalty points and visit statistics')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('loyalty_points')
                                    ->label('Loyalty Points')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('total_spent')
                                    ->label('Total Spent')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('visit_count')
                                    ->label('Visit Count')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                DatePicker::make('last_visit_at')
                                    ->label('Last Visit')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
