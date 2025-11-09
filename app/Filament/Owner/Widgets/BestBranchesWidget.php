<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Payment;
use App\Models\Store;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;
use App\Support\Currency;

class BestBranchesWidget extends BaseWidget
{
    protected static ?string $heading = 'Cabang dengan Penjualan Terbaik';

    protected int | string | array $columnSpan = ['xl' => 6];

    public ?string $filter = 'this_month';

    protected function getTableHeading(): ?string
    {
        return static::$heading;
    }

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
        $owner = auth()->user();

        $start = now();
        $end = now();

        if ($this->filter === 'this_week') {
            $start = now()->startOfWeek();
            $end = now()->endOfWeek();
        } elseif ($this->filter === 'this_month') {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
        } else {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
        }

        $query = Store::query()
            ->select([
                DB::raw('stores.id as id'),
                DB::raw('stores.id as store_id'),
                DB::raw('stores.name as store_name'),
                DB::raw('SUM(payments.amount) as revenue'),
                DB::raw('COUNT(payments.id) as transactions'),
            ])
            ->leftJoin('payments', 'payments.store_id', '=', 'stores.id')
            ->where('payments.status', 'completed')
            ->whereBetween('payments.created_at', [$start, $end])
            ->groupBy('stores.id', 'stores.name');

        // Restrict to stores assigned to this owner (all branches), defaulting to primary store if none
        $storeIds = collect();
        if ($owner) {
            $storeIds = $owner->storeAssignments()->pluck('store_id');
            if ($storeIds->isEmpty() && $owner->store_id) {
                $storeIds = collect([$owner->store_id]);
            }
        }
        if ($storeIds->isNotEmpty()) {
            $query->whereIn('stores.id', $storeIds->all());
        } else {
            $query->whereRaw('1 = 0');
        }

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
            ->striped();
    }
}


