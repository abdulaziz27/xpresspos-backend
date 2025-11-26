<?php

namespace App\Filament\Owner\Resources\TenantAddOns\RelationManagers;

use App\Support\Currency;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Riwayat Pembayaran';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('xendit_invoice_id')
            ->columns([
                Tables\Columns\TextColumn::make('xendit_invoice_id')
                    ->label('Invoice ID')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Str::ucfirst($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->alignEnd()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? Str::of($state)->replace('_', ' ')->title() : '-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Jatuh Tempo')
                    ->dateTime('d M Y H:i')
                    ->description(fn ($record) => $record->expires_at?->diffForHumans())
                    ->color(fn ($state) => blank($state) ? null : (now()->greaterThan($state) ? 'danger' : 'gray')),

                Tables\Columns\TextColumn::make('last_reminder_sent_at')
                    ->label('Reminder Terakhir')
                    ->since()
                    ->placeholder('Belum pernah'),

                Tables\Columns\TextColumn::make('reminder_count')
                    ->label('Jumlah Reminder')
                    ->badge()
                    ->color('info')
                    ->default(0),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Actions\Action::make('openInvoice')
                    ->label('Buka Invoice')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => $record->invoice_url ?: null, true)
                    ->visible(fn ($record) => filled($record->invoice_url)),
                Actions\Action::make('copyInvoice')
                    ->label('Salin Link')
                    ->icon('heroicon-o-document-duplicate')
                    ->hidden(fn ($record) => blank($record->invoice_url))
                    ->action(function ($record) {
                        if (blank($record->invoice_url)) {
                            Notification::make()
                                ->title('Link invoice tidak tersedia')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Link invoice:')
                            ->body($record->invoice_url)
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => filled($record->invoice_url)),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}

