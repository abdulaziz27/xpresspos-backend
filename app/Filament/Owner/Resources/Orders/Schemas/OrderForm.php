<?php

namespace App\Filament\Owner\Resources\Orders\Schemas;

use App\Models\Member;
use App\Models\Table;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Information')
                    ->description('Basic order details and customer information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('order_number')
                                    ->label('Order Number')
                                    ->disabled()
                                    ->dehydrated(false),

                                Select::make('user_id')
                                    ->label('Staff')
                                    ->options(function () {
                                        return User::where('store_id', auth()->user()->store_id)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->default(auth()->id())
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('member_id')
                                    ->label('Customer/Member')
                                    ->options(function () {
                                        return Member::where('store_id', auth()->user()->store_id)
                                            ->where('is_active', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->tel()
                                            ->maxLength(20),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $data['store_id'] = auth()->user()->store_id;
                                        $data['member_number'] = 'MBR' . str_pad(Member::count() + 1, 6, '0', STR_PAD_LEFT);
                                        return Member::create($data)->getKey();
                                    }),

                                Select::make('table_id')
                                    ->label('Table')
                                    ->options(function () {
                                        return Table::where('store_id', auth()->user()->store_id)
                                            ->where('is_active', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'open' => 'Open',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),

                        Textarea::make('notes')
                            ->label('Order Notes')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(1),

                Section::make('Order Totals')
                    ->description('Financial calculations and payment information')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('tax_amount')
                                    ->label('Tax Amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('discount_amount')
                                    ->label('Discount Amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->default(0),

                                TextInput::make('service_charge')
                                    ->label('Service Charge')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->default(0),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('total_amount')
                                    ->label('Total Amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'card' => 'Card',
                                        'qris' => 'QRIS',
                                        'transfer' => 'Bank Transfer',
                                        'other' => 'Other',
                                    ])
                                    ->searchable(),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Timestamps')
                    ->description('Order timing information')
                    ->schema([
                        DateTimePicker::make('completed_at')
                            ->label('Completed At')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record?->status === 'completed'),
            ]);
    }
}
