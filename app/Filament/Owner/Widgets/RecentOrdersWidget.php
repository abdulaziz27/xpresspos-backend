<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\Order;
use App\Support\Currency;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Pesanan Terbaru';

    public function table(Table $table): Table
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();

        $query = Order::query()->whereRaw('1 = 0');

        if (! empty($storeIds)) {
            $query = Order::withoutGlobalScopes()
                ->with(['member'])
                ->whereIn('store_id', $storeIds)
                ->whereBetween('created_at', [$filters['range']['start'], $filters['range']['end']])
                ->latest()
                ->limit(10);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'open' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Pelanggan')
                    ->placeholder('Pelanggan Umum'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada pesanan')
            ->emptyStateDescription('Tenan/cabang belum dipilih atau tidak ada pesanan pada periode ini.');
    }
}
