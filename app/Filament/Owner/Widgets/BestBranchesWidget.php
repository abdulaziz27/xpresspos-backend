<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Payment;
use App\Models\Store;
use App\Services\GlobalFilterService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;
use App\Support\Currency;
use Livewire\Attributes\On;

class BestBranchesWidget extends BaseWidget
{
    /**
     * UPDATED: Now using GlobalFilterService for unified multi-store dashboard
     * 
     * Widget automatically refreshes when global filter changes
     * Shows all branches or specific branch based on global filter
     */

    protected static ?string $heading = 'Cabang dengan Penjualan Terbaik';

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
                ->query(Store::query()->whereRaw('1 = 0'))
                ->emptyStateHeading('Tidak ada data cabang')
                ->emptyStateDescription('Tidak ada cabang untuk tenant ini.');
        }

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
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->revenue ?? 0)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('transactions')
                    ->label('Transaksi')
                    ->sortable(),
            ])
            ->defaultSort('revenue', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Tidak ada data cabang')
            ->emptyStateDescription('Belum ada transaksi dalam periode ini.')
            ->striped();
    }
}


