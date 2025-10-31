<?php

namespace App\Filament\Owner\Resources\Refunds\Schemas;

use App\Models\Order;
use App\Models\Payment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Support\Money;

class RefundForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Refund Information')
                    ->description('Basic refund details and reason')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('order_id')
                                    ->label('Order')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->options(function () {
                                        $user = auth()->user();
                                        $storeId = $user ? $user->store_id : null;

                                        return Order::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->where('status', 'completed')
                                            ->with('member')
                                            ->get()
                                            ->mapWithKeys(function ($order) {
                                                $customerName = $order->member ? $order->member->name : 'Walk-in Customer';
                                                $label = "#{$order->order_number} - {$customerName} - Rp " . number_format($order->total_amount, 0, ',', '.');
                                                return [$order->id => $label];
                                            });
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $order = Order::find($state);
                                            if ($order) {
                                                // Get the payment for this order
                                                $payment = $order->payments()->where('status', 'completed')->first();
                                                if ($payment) {
                                                    $set('payment_id', $payment->id);
                                                    $set('amount', $order->total_amount);
                                                }
                                            }
                                        }
                                    }),

                                Select::make('payment_id')
                                    ->label('Payment')
                                    ->required()
                                    ->searchable()
                                    ->options(function (callable $get) {
                                        $orderId = $get('order_id');
                                        if (!$orderId) return [];

                                        return Payment::where('order_id', $orderId)
                                            ->where('status', 'completed')
                                            ->get()
                                            ->mapWithKeys(function ($payment) {
                                                $label = "{$payment->method} - Rp " . number_format($payment->amount, 0, ',', '.');
                                                return [$payment->id => $label];
                                            });
                                    }),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->placeholder('50.000')
                                    ->helperText('Bisa input: 50000 atau 50.000')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state)),

                                Select::make('status')
                                    ->required()
                                    ->options([
                                        'pending' => 'Pending',
                                        'approved' => 'Approved',
                                        'processed' => 'Processed',
                                        'completed' => 'Completed',
                                        'rejected' => 'Rejected',
                                    ])
                                    ->default('pending')
                                    ->helperText('Current refund status'),
                            ]),

                        Textarea::make('reason')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Explain the reason for this refund (e.g., wrong order, quality issue, customer complaint)'),

                        Textarea::make('notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Additional notes or internal comments about this refund'),
                    ])
                    ->columns(1),
            ]);
    }
}