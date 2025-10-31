<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OwnerStatsWidget extends BaseWidget
{
    public ?string $filter = 'today';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hari ini',
            'this_week' => 'Minggu ini',
            'this_month' => 'Bulan ini',
        ];
    }

    protected function getStats(): array
    {
        $storeId = auth()->user()?->store_id;

        if (!$storeId) {
            return [];
        }

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

        $ordersCount = Order::where('store_id', $storeId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $revenue = Payment::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        return [
            Stat::make('Total Transaksi', $ordersCount)
                ->description('Jumlah transaksi pada rentang waktu')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),

            Stat::make('Total Pendapatan', 'Rp ' . number_format($revenue, 0, ',', '.'))
                ->description('Pendapatan pada rentang waktu')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Total Produk', Product::where('store_id', $storeId)->count())
                ->description('Produk dalam katalog')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Member Aktif', Member::where('store_id', $storeId)->where('is_active', true)->count())
                ->description('Member terdaftar')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
}
