<?php

namespace App\Filament\Owner\Resources\Orders\Pages;

use App\Filament\Owner\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use App\Support\Currency;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Bagian 1: Informasi inti pesanan (dua kolom)
                Section::make('Informasi Pesanan')
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('No. Pesanan')
                            ->copyable(),
                        TextEntry::make('user.name')
                            ->label('Staf')
                            ->placeholder('-'),
                        TextEntry::make('member.name')
                            ->label('Pelanggan/Member')
                            ->placeholder('Pelanggan Umum'),
                        TextEntry::make('table.name')
                            ->label('Meja')
                            ->placeholder('Tanpa Meja'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'draft' => 'gray',
                                'open' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('notes')
                            ->label('Catatan Pesanan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Bagian 2: Pembayaran & Waktu (dua kolom)
                Section::make('Pembayaran & Waktu')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Kolom kiri: ringkasan biaya
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('subtotal_amount')->label('Subtotal')->formatStateUsing(fn($s) => Currency::rupiah((float) $s)),
                                        TextEntry::make('tax_amount')->label('Pajak')->formatStateUsing(fn($s) => Currency::rupiah((float) $s)),
                                        TextEntry::make('discount_amount')->label('Diskon')->formatStateUsing(fn($s) => Currency::rupiah((float) $s)),
                                        TextEntry::make('service_charge_amount')->label('Biaya Layanan')->formatStateUsing(fn($s) => Currency::rupiah((float) $s)),
                                        TextEntry::make('total_amount')
                                            ->label('Total')
                                            ->formatStateUsing(fn($s) => Currency::rupiah((float) $s))
                                            ->weight('medium')
                                            ->color('success')
                                            ->columnSpanFull(),
                                        TextEntry::make('payment_method')->label('Metode Pembayaran')->placeholder('Belum Diatur'),
                                    ]),

                                // Kolom kanan: waktu
                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('created_at')->label('Dibuat')->dateTime()->since(),
                                        TextEntry::make('completed_at')->label('Selesai Pada')->dateTime()->since()->placeholder('Belum Selesai'),
                                    ]),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}


