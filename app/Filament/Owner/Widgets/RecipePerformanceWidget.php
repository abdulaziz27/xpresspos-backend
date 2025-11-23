<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\CogsHistory;
use App\Models\Recipe;
use App\Support\Currency;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class RecipePerformanceWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected static ?string $heading = 'Recipe Performance';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        try {
            $filters = $this->dashboardFilters();
            $tenantId = $filters['tenant_id'];
            $storeIds = $this->dashboardStoreIds();

            $query = Recipe::query()->whereRaw('1 = 0');

            if ($tenantId) {
                $query = Recipe::withoutGlobalScopes()
                    ->with(['product', 'items'])
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true);
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
                        ->tooltip(fn (TextColumn $column): ?string => strlen($column->getState()) > 25 ? $column->getState() : null),

                    TextColumn::make('yield_quantity')
                        ->label('Yield')
                        ->numeric()
                        ->sortable()
                        ->alignCenter()
                        ->suffix(fn ($record) => ' ' . $record->yield_unit),

                    TextColumn::make('total_cost')
                        ->label('Recipe Cost')
                        ->formatStateUsing(fn ($s, $record) => Currency::rupiah((float) ($s ?? $record->total_cost ?? 0)))
                        ->sortable()
                        ->alignEnd(),

                    TextColumn::make('cost_per_unit')
                        ->label('Cost/Unit')
                        ->formatStateUsing(fn ($s, $record) => Currency::rupiah((float) ($s ?? $record->cost_per_unit ?? 0)))
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
                        ->getStateUsing(function ($record) use ($storeIds) {
                            $monthStart = Carbon::now()->startOfMonth();
                            $monthEnd = Carbon::now()->endOfMonth();

                            $usageQuery = CogsHistory::where('product_id', $record->product_id)
                                ->whereBetween('created_at', [$monthStart, $monthEnd]);

                            if (! empty($storeIds)) {
                                $usageQuery->whereIn('store_id', $storeIds);
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
                            if (! $record->product || ! $record->product->price || ! $record->cost_per_unit) {
                                return 'N/A';
                            }

                            $margin = $record->product->price - $record->cost_per_unit;
                            $marginPercentage = ($margin / $record->product->price) * 100;

                            return number_format($marginPercentage, 1) . '%';
                        })
                        ->badge()
                        ->color(function ($state) {
                            if ($state === 'N/A') {
                                return 'gray';
                            }

                            $percentage = (float) str_replace('%', '', $state);

                            return $percentage >= 50 ? 'success'
                                : ($percentage >= 30 ? 'warning' : 'danger');
                        })
                        ->alignCenter(),
                ])
                ->defaultSort('total_cost', 'desc')
                ->paginated(false)
                ->striped();
        } catch (\Throwable $e) {
            report($e);

            return $table
                ->query(Recipe::query()->whereRaw('1 = 0'))
                ->emptyStateHeading('Tidak dapat memuat data resep')
                ->emptyStateDescription('Terjadi kesalahan saat memuat performa resep.')
                ->paginated(false)
                ->striped();
        }
    }
}
