<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\StoreContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethodBreakdownWidget extends ChartWidget
{
    protected ?string $heading = 'Payment Methods';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'last_6_months';

    protected function getData(): array
    {
        $tenant = $this->resolveTenant();

        if (!$tenant) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $baseQuery = SubscriptionPayment::where(function (Builder $query) use ($tenant) {
            $query->whereHas('subscription', function (Builder $subQuery) use ($tenant) {
                $subQuery->where('tenant_id', $tenant->id);
            })->orWhereHas('landingSubscription', function (Builder $subQuery) use ($tenant) {
                $subQuery->where('tenant_id', $tenant->id);
            });
        })->where('status', 'paid');

        $period = match ($this->filter) {
            'last_month' => now()->subMonth(),
            'last_3_months' => now()->subMonths(3),
            'last_6_months' => now()->subMonths(6),
            'last_12_months' => now()->subMonths(12),
            default => now()->subMonths(6),
        };

        $paymentMethods = $baseQuery->clone()
            ->where('paid_at', '>=', $period)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('payment_method')
            ->orderByDesc('count')
            ->get();

        $labels = [];
        $data = [];
        $backgroundColors = [];
        $borderColors = [];

        $colors = [
            'bank_transfer' => ['bg' => 'rgba(59, 130, 246, 0.8)', 'border' => 'rgb(59, 130, 246)'],
            'e_wallet' => ['bg' => 'rgba(34, 197, 94, 0.8)', 'border' => 'rgb(34, 197, 94)'],
            'qris' => ['bg' => 'rgba(168, 85, 247, 0.8)', 'border' => 'rgb(168, 85, 247)'],
            'credit_card' => ['bg' => 'rgba(249, 115, 22, 0.8)', 'border' => 'rgb(249, 115, 22)'],
            'debit_card' => ['bg' => 'rgba(236, 72, 153, 0.8)', 'border' => 'rgb(236, 72, 153)'],
        ];

        foreach ($paymentMethods as $method) {
            $methodName = $this->getPaymentMethodDisplayName($method->payment_method);
            $labels[] = $methodName;
            $data[] = $method->count;
            
            $color = $colors[$method->payment_method] ?? ['bg' => 'rgba(107, 114, 128, 0.8)', 'border' => 'rgb(107, 114, 128)'];
            $backgroundColors[] = $color['bg'];
            $borderColors[] = $color['border'];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const label = context.label || "";
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return label + ": " + value + " (" + percentage + "%)";
                        }',
                    ],
                ],
            ],
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            'last_month' => 'Last month',
            'last_3_months' => 'Last 3 months',
            'last_6_months' => 'Last 6 months',
            'last_12_months' => 'Last 12 months',
        ];
    }

    private function getPaymentMethodDisplayName(string $method): string
    {
        return match($method) {
            'bank_transfer' => 'Bank Transfer',
            'e_wallet' => 'E-Wallet',
            'qris' => 'QRIS',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public static function canView(): bool
    {
        $tenant = static::resolveTenantFromContext();

        if (!$tenant) {
            return false;
        }

        // Show widget only if tenant has payment data
        return SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })->orWhereHas('landingSubscription', function (Builder $query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })->where('status', 'paid')->exists();
    }

    protected function resolveTenant(): ?Tenant
    {
        return static::resolveTenantFromContext();
    }

    protected static function resolveTenantFromContext(): ?Tenant
    {
        $storeContext = app(StoreContext::class);
        $storeId = $storeContext->current(auth()->user());

        if (!$storeId) {
            return null;
        }

        $store = Store::find($storeId);

        return $store?->tenant;
    }
}