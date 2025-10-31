<?php

namespace App\Filament\Owner\Resources\Payments\Pages;

use App\Filament\Owner\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use App\Support\Currency;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi Pembayaran')
                    ->schema([
                        TextEntry::make('id')->label('ID Pembayaran')->copyable(),
                        TextEntry::make('order.order_number')->label('No. Pesanan')->badge()->color('primary'),
                        TextEntry::make('status')->label('Status')->badge()->color(fn(string $state): string => match ($state) {
                            'pending'=>'warning','processing'=>'info','completed'=>'success','failed'=>'danger','cancelled'=>'gray','refunded'=>'warning',default=>'gray',
                        }),
                        TextEntry::make('payment_method')->label('Metode')->formatStateUsing(fn(string $state): string => match ($state) {
                            'cash' => 'Tunai',
                            'credit_card' => 'Kartu Kredit',
                            'debit_card' => 'Kartu Debit',
                            'qris' => 'QRIS',
                            'bank_transfer' => 'Transfer Bank',
                            'e_wallet' => 'E-Wallet',
                            default => ucfirst($state),
                        }),
                        TextEntry::make('gateway')->label('Gateway')->placeholder('Langsung'),
                        TextEntry::make('reference_number')->label('Referensi')->placeholder('-')->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Jumlah & Waktu')
                    ->schema([
                        Grid::make(2)->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('amount')->label('Jumlah')->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->amount ?? 0))),
                                TextEntry::make('gateway_fee')->label('Biaya Gateway')->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->gateway_fee ?? 0)))->placeholder('Tanpa Biaya'),
                            ]),
                            Grid::make(1)->schema([
                                TextEntry::make('processed_at')->label('Diproses Pada')->dateTime()->since()->placeholder('Belum Diproses'),
                                TextEntry::make('created_at')->label('Dibuat')->dateTime()->since(),
                            ]),
                        ]),
                    ])
                    ->columns(1),
            ]);
    }
}


