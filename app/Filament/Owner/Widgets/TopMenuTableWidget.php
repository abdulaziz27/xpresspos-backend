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
        try {
            $filters = $this->dashboardFilters();
            $storeIds = $this->dashboardStoreIds();
            $summary = $this->dashboardFilterSummary();
            $tenantId = $filters['tenant_id'] ?? null;
            $dateRange = $filters['range'] ?? null;

            $hasValidFilters = $tenantId && ! empty($storeIds) && $dateRange;
            
            $query = Product::query()->whereRaw('1 = 0');

            if ($hasValidFilters) {
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
            }

            $emptyHeading = $hasValidFilters
                ? 'Tidak Ada Produk yang Terjual'
                : 'Tenant atau Cabang Belum Dipilih';

            $emptyDescription = $hasValidFilters
                ? 'Belum ada transaksi untuk ' . ($summary['store'] ?? 'Semua Cabang') . '.'
                : 'Silakan pilih tenant dan cabang untuk melihat produk terlaris.';

            return $table
                ->query($query)
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
                ->emptyStateHeading($emptyHeading)
                ->emptyStateDescription($emptyDescription)
                ->paginated(false)
                ->striped();
        } catch (\Throwable $e) {
            report($e);

            return $table
                ->query(Product::query()->whereRaw('1 = 0'))
                ->emptyStateHeading('Tidak dapat memuat data')
                ->emptyStateDescription('Terjadi kesalahan saat memuat data penjualan produk.')
                ->paginated(false)
                ->striped();
        }
    }
}
