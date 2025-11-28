<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Expense;
use App\Models\Store;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class ProfitLossDetailTable extends BaseWidget
{
    protected static ?int $sort = 12;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    #[On('profit-loss-filter-updated')]
    public function refreshWidget(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getExpensesQuery())
            ->heading('Detail Biaya Operasional')
            ->description('Daftar lengkap biaya operasional berdasarkan kategori')
            ->columns([
                TextColumn::make('category')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('expense_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make())
                    ->alignEnd(),
            ])
            ->defaultSort('expense_date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    protected function getExpensesQuery()
    {
        $filters = Session::get('local_filter.profitlossreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'this_month';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        if (!$tenantId) {
            return Expense::query()->whereRaw('1 = 0'); // Empty query
        }

        $storeIds = $this->getStoreIds($tenantId, $storeId);
        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);

        if (empty($storeIds)) {
            return Expense::query()->whereRaw('1 = 0'); // Empty query
        }

        $query = Expense::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds);

        if ($range['start'] && $range['end']) {
            $query->whereBetween('expense_date', [
                $range['start']->toDateString(),
                $range['end']->toDateString()
            ]);
        }

        return $query;
    }

    protected function getDefaultFilters(): array
    {
        $user = Auth::user();
        $tenantId = $user?->currentTenant()?->id;
        $globalService = app(\App\Services\GlobalFilterService::class);
        $preset = 'this_month';
        $range = $globalService->getDateRangeForPreset($preset);

        return [
            'tenant_id' => $tenantId,
            'store_id' => null,
            'date_preset' => $preset,
            'date_start' => $range['start']->toDateString(),
            'date_end' => $range['end']->toDateString(),
        ];
    }

    protected function getStoreIds(?string $tenantId, ?string $storeId): array
    {
        if (!$tenantId) {
            return [];
        }

        if ($storeId) {
            return [$storeId];
        }

        return Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    protected function getDateRange(string $preset, ?string $dateStart, ?string $dateEnd): array
    {
        if ($preset === 'custom' && $dateStart && $dateEnd) {
            return [
                'start' => \Carbon\Carbon::parse($dateStart)->startOfDay(),
                'end' => \Carbon\Carbon::parse($dateEnd)->endOfDay(),
            ];
        }

        $globalService = app(\App\Services\GlobalFilterService::class);
        return $globalService->getDateRangeForPreset($preset);
    }
}

