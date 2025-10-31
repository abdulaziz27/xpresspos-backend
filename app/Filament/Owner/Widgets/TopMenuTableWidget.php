<?php

namespace App\Filament\Owner\Widgets;

use App\Models\CogsHistory;
use Illuminate\Support\Facades\DB;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopMenuTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Produk Terlaris (Top 10)';

    protected int | string | array $columnSpan = ['xl' => 6];

    public ?string $filter = 'today';

    protected function getFilters(): array
    {
        return [
            'today' => 'Hari ini',
            'this_week' => 'Minggu ini',
            'this_month' => 'Bulan ini',
        ];
    }

    public function table(Table $table): Table
    {
        $storeId = auth()->user()?->store_id;

        return $table
            ->query(
                \App\Models\Product::query()
                    ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Terjual')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?? 0),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('date_range')
                    ->label('Tanggal')
                    ->default('today')
                    ->options([
                        'today' => 'Hari ini',
                        'this_week' => 'Minggu ini',
                        'this_month' => 'Bulan ini',
                    ])
                    ->query(function ($query, array $data) use ($storeId) {
                        $range = $data['value'] ?? $data['date_range'] ?? 'today';
                        $start = now();
                        $end = now();
                        if ($range === 'this_week') {
                            $start = now()->startOfWeek();
                            $end = now()->endOfWeek();
                        } elseif ($range === 'this_month') {
                            $start = now()->startOfMonth();
                            $end = now()->endOfMonth();
                        } else {
                            $start = now()->startOfDay();
                            $end = now()->endOfDay();
                        }

                        $sumSub = DB::table('cogs_history')
                            ->selectRaw('COALESCE(SUM(quantity_sold), 0)')
                            ->whereColumn('cogs_history.product_id', 'products.id')
                            ->when($storeId, fn ($q) => $q->where('cogs_history.store_id', $storeId))
                            ->whereBetween('cogs_history.created_at', [$start, $end]);

                        $query->select('products.*')
                            ->selectSub($sumSub, 'total_qty')
                            ->having('total_qty', '>', 0)
                            ->orderByDesc('total_qty')
                            ->limit(10);
                    })
            ])
            ->modifyQueryUsing(function ($query) use ($storeId) {
                // Default TODAY aggregation, ensures 'total_qty' is present even before filters run
                $start = now()->startOfDay();
                $end = now()->endOfDay();

                $sumSub = DB::table('cogs_history')
                    ->selectRaw('COALESCE(SUM(quantity_sold), 0)')
                    ->whereColumn('cogs_history.product_id', 'products.id')
                    ->when($storeId, fn ($q) => $q->where('cogs_history.store_id', $storeId))
                    ->whereBetween('cogs_history.created_at', [$start, $end]);

                $query->select('products.*')
                    ->selectSub($sumSub, 'total_qty')
                    ->having('total_qty', '>', 0)
                    ->orderByDesc('total_qty')
                    ->limit(10);
            })
            ->emptyStateHeading('Tidak Ada Produk yang Terjual')
            ->emptyStateDescription('Semua produk belum ada transaksi dalam periode ini.')
            ->paginated(false)
            ->striped();
    }
}


