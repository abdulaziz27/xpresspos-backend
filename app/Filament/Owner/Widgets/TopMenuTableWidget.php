<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class TopMenuTableWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected int | string | array $columnSpan = ['xl' => 6];

    protected function getTableHeading(): string | Htmlable | null
    {
        return 'Produk Terlaris (Top 10) • ' . $this->dashboardFilterContextLabel();
    }

    public function table(Table $table): Table
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();
        $summary = $this->dashboardFilterSummary();

        if (empty($storeIds)) {
            return $table
                ->query(Product::query()->whereRaw('1 = 0'))
                ->emptyStateHeading('Tidak Ada Data')
                ->emptyStateDescription('Tenant atau cabang belum dipilih.');
        }

        $dateRange = $filters['range'];

        return $table
            ->query(
                Product::query()
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
                $sumSub = DB::table('cogs_history')
                    ->selectRaw('COALESCE(SUM(quantity_sold), 0)')
                    ->whereColumn('cogs_history.product_id', 'products.id')
                    ->whereIn('cogs_history.store_id', $storeIds)
                    ->whereBetween('cogs_history.created_at', [$dateRange['start'], $dateRange['end']]);

                $query->select('products.*')
                    ->selectSub($sumSub, 'total_qty')
                    ->having('total_qty', '>', 0)
                    ->orderByDesc('total_qty')
                    ->limit(10);
            })
            ->emptyStateHeading('Tidak Ada Produk yang Terjual')
            ->emptyStateDescription('Belum ada transaksi untuk ' . ($summary['store'] ?? 'Semua Cabang') . '.')
            ->paginated(false)
            ->striped();
    }
}
