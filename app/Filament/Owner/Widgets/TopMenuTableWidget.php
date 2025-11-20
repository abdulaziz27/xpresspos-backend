<?php

namespace App\Filament\Owner\Widgets;

use App\Models\CogsHistory;
use App\Services\GlobalFilterService;
use Illuminate\Support\Facades\DB;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class TopMenuTableWidget extends BaseWidget
{
    /**
     * UPDATED: Now using GlobalFilterService for unified multi-store dashboard
     * 
     * Widget automatically refreshes when global filter changes
     */

    protected static ?string $heading = 'Produk Terlaris (Top 10)';

    protected int | string | array $columnSpan = ['xl' => 6];

    #[On('filter-updated')]
    public function refreshWidget(): void
    {
        // Trigger refresh when global filter changes
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $globalFilter = app(GlobalFilterService::class);
        
        // Get current filter values from global filter
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();
        $dateRange = $globalFilter->getCurrentDateRange();
        
        if (empty($storeIds)) {
            return $table
                ->query(\App\Models\Product::query()->whereRaw('1 = 0'))
                ->emptyStateHeading('Tidak Ada Data')
                ->emptyStateDescription('Tidak ada cabang untuk tenant ini.');
        }

        return $table
            ->query(
                \App\Models\Product::query()
                    ->whereIn('store_id', $storeIds)
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
            ->modifyQueryUsing(function ($query) use ($storeIds, $dateRange) {
                // Use global filter date range
                $start = $dateRange['start'];
                $end = $dateRange['end'];

                $sumSub = DB::table('cogs_history')
                    ->selectRaw('COALESCE(SUM(quantity_sold), 0)')
                    ->whereColumn('cogs_history.product_id', 'products.id')
                    ->whereIn('cogs_history.store_id', $storeIds)
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


