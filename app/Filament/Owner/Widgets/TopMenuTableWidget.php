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

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = ['xl' => 6];

    protected function getTableHeading(): string | Htmlable | null
    {
        return 'Produk Terlaris (Top 10) • ' . $this->dashboardFilterContextLabel();
    }

    public function table(Table $table): Table
    {
            $filters = $this->dashboardFilters();
            $storeIds = $this->dashboardStoreIds();
        $tenantId = $filters['tenant_id'] ?? null;
        $dateRange = $filters['range'] ?? null;

        if (! $tenantId || empty($storeIds) || ! $dateRange) {
            return $this->buildFallbackTable(
                $table,
                'Tenant atau Cabang Belum Dipilih',
                'Silakan pilih tenant dan cabang untuk melihat produk terlaris.'
            );
        }

        try {
            $summary = $this->dashboardFilterSummary();

                $query = Product::query()
                ->select('products.*')
                ->selectSub(
                    DB::table('cogs_history')
                        ->selectRaw('COALESCE(SUM(quantity_sold), 0)')
                        ->whereColumn('cogs_history.product_id', 'products.id')
                        ->whereIn('cogs_history.store_id', $storeIds)
                        ->whereBetween('cogs_history.created_at', [$dateRange['start'], $dateRange['end']]),
                    'total_qty'
                )
                ->where('tenant_id', $tenantId)
                ->where('status', true)
                        ->having('total_qty', '>', 0)
                        ->orderByDesc('total_qty')
                        ->limit(10);

            return $table
                ->query($query)
                ->columns($this->tableColumns())
                ->emptyStateHeading('Tidak Ada Produk yang Terjual')
                ->emptyStateDescription('Belum ada transaksi untuk ' . ($summary['store'] ?? 'Semua Cabang') . '.')
                ->paginated(false)
                ->striped();
        } catch (\Throwable $e) {
            report($e);

            return $this->buildFallbackTable(
                $table,
                'Tidak dapat memuat data',
                'Terjadi kesalahan saat memuat data penjualan produk.'
            );
                    }
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    protected function tableColumns(): array
    {
        return [
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
        ];
    }

    protected function buildFallbackTable(Table $table, string $heading, string $description): Table
    {
            return $table
                ->query(Product::query()->whereRaw('1 = 0'))
            ->columns($this->tableColumns())
            ->emptyStateHeading($heading)
            ->emptyStateDescription($description)
                ->paginated(false)
                ->striped();
    }
}
