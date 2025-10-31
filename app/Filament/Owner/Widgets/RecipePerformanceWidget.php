<?php

namespace App\Filament\Owner\Widgets;

use App\Models\CogsHistory;
use App\Models\Product;
use App\Models\Recipe;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Support\Currency;

class RecipePerformanceWidget extends BaseWidget
{
    protected static ?string $heading = 'Recipe Performance';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $storeId = auth()->user()?->store_id;

        $query = Recipe::query()
            ->with(['product', 'items'])
            ->where('is_active', true);

        if ($storeId) {
            $query->where('store_id', $storeId);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('name')
                    ->label('Recipe Name')
                    ->searchable()
                    ->limit(25)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 25 ? $state : null;
                    }),

                TextColumn::make('yield_quantity')
                    ->label('Yield')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->suffix(fn($record) => ' ' . $record->yield_unit),

                TextColumn::make('total_cost')
                    ->label('Recipe Cost')
                    ->formatStateUsing(fn($s) => Currency::rupiah((float) $s))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('cost_per_unit')
                    ->label('Cost/Unit')
                    ->formatStateUsing(fn($s) => Currency::rupiah((float) $s))
                    ->sortable()
                    ->alignEnd()
                    ->color('success'),

                TextColumn::make('items_count')
                    ->label('Ingredients')
                    ->counts('items')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('monthly_usage')
                    ->label('Monthly Usage')
                    ->getStateUsing(function ($record) {
                        $monthStart = Carbon::now()->startOfMonth();
                        $monthEnd = Carbon::now()->endOfMonth();

                        $usageQuery = CogsHistory::where('product_id', $record->product_id)
                            ->whereBetween('created_at', [$monthStart, $monthEnd])
                            ->select('quantity_sold');

                        if ($record->store_id ?? $storeId) {
                            $usageQuery->where('store_id', $record->store_id ?? $storeId);
                        }

                        $usage = $usageQuery->sum('quantity_sold');

                        return number_format($usage) . ' units';
                    })
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                TextColumn::make('cost_efficiency')
                    ->label('Cost Efficiency')
                    ->getStateUsing(function ($record) {
                        if (!$record->product || !$record->product->price || !$record->cost_per_unit) {
                            return 'N/A';
                        }

                        $margin = $record->product->price - $record->cost_per_unit;
                        $marginPercentage = ($margin / $record->product->price) * 100;

                        return number_format($marginPercentage, 1) . '%';
                    })
                    ->badge()
                    ->color(function ($state) {
                        if ($state === 'N/A') return 'gray';
                        $percentage = (float) str_replace('%', '', $state);
                        return $percentage >= 50 ? 'success' : ($percentage >= 30 ? 'warning' : 'danger');
                    })
                    ->alignCenter(),
            ])
            ->defaultSort('total_cost', 'desc')
            ->paginated(false)
            ->striped();
    }
}
