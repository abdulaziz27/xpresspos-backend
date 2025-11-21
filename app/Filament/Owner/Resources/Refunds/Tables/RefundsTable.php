<?php

namespace App\Filament\Owner\Resources\Refunds\Tables;

use App\Services\StoreContext;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Model;

class RefundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->url(fn($record) => $record->order ? route('filament.owner.resources.orders.edit', $record->order) : null)
                    ->color('primary'),

                TextColumn::make('store.name')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('order.member.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('Walk-in Customer'),

                TextColumn::make('amount')
                    ->label('Refund Amount')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->amount ?? 0)))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'processed' => 'success',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->reason),

                TextColumn::make('user.name')
                    ->label('Requested By')
                    ->searchable()
                    ->placeholder('System'),

                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->searchable()
                    ->placeholder('Not approved'),

                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not approved')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not processed')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'processed' => 'Processed',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),

                SelectFilter::make('store_id')
                    ->label('Cabang')
                    ->options(self::storeOptions())
                    ->placeholder('Semua cabang'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn(Model $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Model $record) {
                        $record->approve(auth()->user());
                    })
                    ->successNotificationTitle('Refund approved successfully'),

                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-cog')
                    ->color('info')
                    ->visible(fn(Model $record) => $record->status === 'approved')
                    ->requiresConfirmation()
                    ->action(function (Model $record) {
                        $record->process();
                    })
                    ->successNotificationTitle('Refund processed successfully'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    protected static function storeOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return StoreContext::instance()
            ->accessibleStores($user)
            ->pluck('name', 'id')
            ->toArray();
    }
}