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

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = ['xl' => 6];

    protected function getTableHeading(): string | Htmlable | null
    {
        return 'Cabang dengan Penjualan Terbaik • ' . $this->dashboardFilterContextLabel();
    }

    public function table(Table $table): Table
    {
            $filters = $this->dashboardFilters();
            $storeIds = $this->dashboardStoreIds();
        $tenantId = $filters['tenant_id'] ?? null;
        $dateRange = $filters['range'] ?? null;

        if (! $tenantId || empty($storeIds) || ! $dateRange) {
            return $this->buildEmptyTable(
                $table,
                'Tenant atau Cabang Belum Dipilih',
                'Silakan pilih tenant dan cabang untuk melihat performa cabang.'
            );
        }

        try {
            $summary = $this->dashboardFilterSummary();

            // Use subquery to ensure proper date filtering
            $query = Store::query()
                ->select([
                    DB::raw('stores.id as id'),
                    DB::raw('stores.id as store_id'),
                    DB::raw('stores.name as store_name'),
                    DB::raw('COALESCE(payment_stats.revenue, 0) as revenue'),
                    DB::raw('COALESCE(payment_stats.transactions, 0) as transactions'),
                ])
                ->leftJoinSub(
                    DB::table('payments')
                        ->select('store_id')
                        ->selectRaw('SUM(COALESCE(received_amount, amount)) as revenue')
                        ->selectRaw('COUNT(id) as transactions')
                        ->where('status', 'completed')
                        ->whereBetween(DB::raw('COALESCE(paid_at, processed_at, created_at)'), [$dateRange['start'], $dateRange['end']])
                        ->whereIn('store_id', $storeIds)
                        ->groupBy('store_id'),
                    'payment_stats',
                    'payment_stats.store_id',
                    '=',
                    'stores.id'
                )
                ->where('stores.tenant_id', $tenantId)
                ->whereIn('stores.id', $storeIds)
                ->groupBy('stores.id', 'stores.name', 'payment_stats.revenue', 'payment_stats.transactions')
                ->orderByDesc('revenue');

            return $table
                ->query($query)
                ->columns($this->tableColumns())
                ->defaultSort('revenue', 'desc')
                ->paginated(false)
                ->emptyStateHeading('Tidak ada data cabang')
                ->emptyStateDescription('Belum ada transaksi untuk ' . ($summary['store'] ?? 'Semua Cabang') . '.')
                ->striped();
        } catch (\Throwable $e) {
            report($e);

            return $this->buildEmptyTable(
                $table,
                'Tidak dapat memuat data cabang',
                'Terjadi kesalahan saat memuat performa cabang.'
            );
        }
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    protected function tableColumns(): array
    {
        return [
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
        ];
    }

    protected function buildEmptyTable(Table $table, string $heading, string $description): Table
    {
            return $table
                ->query(Store::query()->whereRaw('1 = 0'))
            ->columns($this->tableColumns())
            ->emptyStateHeading($heading)
            ->emptyStateDescription($description)
                ->paginated(false)
                ->striped();
    }
}
