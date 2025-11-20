<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\Store;
use App\Support\Currency;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class BestBranchesWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected int | string | array $columnSpan = ['xl' => 6];

    protected function getTableHeading(): string | Htmlable | null
    {
        return 'Cabang dengan Penjualan Terbaik • ' . $this->dashboardFilterContextLabel();
    }

    public function table(Table $table): Table
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();

        if (empty($storeIds)) {
            return $table
                ->query(Store::query()->whereRaw('1 = 0'))
                ->emptyStateHeading('Tidak ada data cabang')
                ->emptyStateDescription('Tenant atau cabang belum dipilih.');
        }

        $dateRange = $filters['range'];
        $summary = $this->dashboardFilterSummary();

        $query = Store::query()
            ->select([
                DB::raw('stores.id as id'),
                DB::raw('stores.id as store_id'),
                DB::raw('stores.name as store_name'),
                DB::raw('COALESCE(SUM(payments.amount), 0) as revenue'),
                DB::raw('COUNT(payments.id) as transactions'),
            ])
            ->leftJoin('payments', function ($join) use ($dateRange) {
                $join->on('payments.store_id', '=', 'stores.id')
                    ->where('payments.status', '=', 'completed')
                    ->whereBetween('payments.created_at', [$dateRange['start'], $dateRange['end']]);
            })
            ->whereIn('stores.id', $storeIds)
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('revenue');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('store_name')
                    ->label('Cabang')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('revenue')
                    ->label('Pendapatan')
                    ->formatStateUsing(fn ($state, $record) => Currency::rupiah((float) ($state ?? $record->revenue ?? 0)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('transactions')
                    ->label('Transaksi')
                    ->sortable(),
            ])
            ->defaultSort('revenue', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Tidak ada data cabang')
            ->emptyStateDescription('Belum ada transaksi untuk ' . ($summary['store'] ?? 'Semua Cabang') . '.')
            ->striped();
    }
}
