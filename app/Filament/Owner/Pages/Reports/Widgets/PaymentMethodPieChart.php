<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Payment;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class PaymentMethodPieChart extends ChartWidget
{
    protected ?string $heading = 'Transaksi Per Metode Pembayaran';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected static bool $isLazy = false;

    #[On('sales-report-filter-updated')]
    public function refreshChart(): void
    {
        $this->cachedData = null;
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $filters = Session::get('local_filter.salesreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'this_month';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        if (!$tenantId) {
            return [
                'datasets' => [[ 'data' => [] ]],
                'labels' => [],
            ];
        }

        $storeIds = [];
        if ($storeId) {
            $storeIds = [$storeId];
        } else {
            $storeIds = Store::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        }

        if (empty($storeIds)) {
            return [
                'datasets' => [[ 'data' => [] ]],
                'labels' => [],
            ];
        }

        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);

        // Get payment methods data (only QRIS and Cash) - count transactions per payment method
        $paymentMethods = Payment::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds)
            ->where('status', 'completed')
            ->whereIn('payment_method', ['qris', 'cash'])
            ->whereBetween(DB::raw('COALESCE(paid_at, processed_at, created_at)'), [$range['start'], $range['end']])
            ->select('payment_method', DB::raw('COUNT(*) as total_transactions'))
            ->groupBy('payment_method')
            ->get();

        $labels = [];
        $data = [];
        $backgroundColors = [];
        $borderColors = [];

        // Warna biru tua dan biru muda untuk metode pembayaran
        $colors = [
            'cash' => ['bg' => 'rgba(30, 64, 175, 0.8)', 'border' => 'rgb(30, 64, 175)'],   // Biru tua
            'qris' => ['bg' => 'rgba(191, 219, 254, 0.8)', 'border' => 'rgb(191, 219, 254)'],   // Biru muda
        ];

        foreach ($paymentMethods as $method) {
            $methodName = $this->getPaymentMethodDisplayName($method->payment_method);
            $labels[] = $methodName;
            $data[] = (int) $method->total_transactions;
            
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
                    'hoverOffset' => 10,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'animation' => [
                'animateRotate' => true,
                'animateScale' => false,
                'duration' => 1000,
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'nearest',
            ],
            'onHover' => 'function(event, activeElements) {
                if (activeElements.length > 0) {
                    event.native.target.style.cursor = "pointer";
                } else {
                    event.native.target.style.cursor = "default";
                }
            }',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'datalabels' => [
                    'display' => true,
                    'color' => '#fff',
                    'font' => [
                        'weight' => 'bold',
                        'size' => 14,
                    ],
                    'formatter' => 'function(value, context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return percentage + "%";
                    }',
                ],
            ],
        ];
    }

    private function getPaymentMethodDisplayName(string $method): string
    {
        return match($method) {
            'cash' => 'Tunai',
            'qris' => 'QRIS',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
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

    protected function getDateRange(string $preset, ?string $dateStart, ?string $dateEnd): array
    {
        if ($preset === 'custom' && $dateStart && $dateEnd) {
            return [
                'start' => Carbon::parse($dateStart)->startOfDay(),
                'end' => Carbon::parse($dateEnd)->endOfDay(),
            ];
        }

        $globalService = app(\App\Services\GlobalFilterService::class);
        return $globalService->getDateRangeForPreset($preset);
    }
}

