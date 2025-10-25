<?php

namespace App\Filament\Owner\Resources\SubscriptionPaymentResource\Pages;

use App\Filament\Owner\Resources\SubscriptionPaymentResource;
use App\Models\SubscriptionPayment;
use App\Services\StoreContext;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptionPayments extends ListRecords
{
    protected static string $resource = SubscriptionPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_payments')
                ->label('Export Payments')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action('exportPayments'),
            
            Actions\Action::make('view_subscription')
                ->label('View Subscription')
                ->icon('heroicon-o-credit-card')
                ->color('success')
                ->url(fn (): string => 
                    class_exists('App\Filament\Owner\Resources\SubscriptionResource') 
                        ? \App\Filament\Owner\Resources\SubscriptionResource::getUrl('index')
                        : '#'
                ),
        ];
    }

    public function getTabs(): array
    {
        $storeContext = app(StoreContext::class);
        $baseQuery = SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeContext) {
            $query->where('store_id', $storeContext->current(auth()->user()));
        })->orWhereHas('landingSubscription', function (Builder $query) use ($storeContext) {
            $query->whereHas('provisionedStore', function (Builder $subQuery) use ($storeContext) {
                $subQuery->where('id', $storeContext->current(auth()->user()));
            });
        });

        return [
            'all' => Tab::make('All Payments')
                ->badge($baseQuery->count()),
            
            'paid' => Tab::make('Paid')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid'))
                ->badge($baseQuery->where('status', 'paid')->count())
                ->badgeColor('success'),
            
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge($baseQuery->where('status', 'pending')->count())
                ->badgeColor('warning'),
            
            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed'))
                ->badge($baseQuery->where('status', 'failed')->count())
                ->badgeColor('danger'),
            
            'this_month' => Tab::make('This Month')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('created_at', '>=', now()->startOfMonth())
                          ->where('created_at', '<=', now()->endOfMonth())
                )
                ->badge($baseQuery->where('created_at', '>=', now()->startOfMonth())
                    ->where('created_at', '<=', now()->endOfMonth())
                    ->count())
                ->badgeColor('primary'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SubscriptionPaymentResource\Widgets\PaymentStatsWidget::class,
        ];
    }

    public function exportPayments(): void
    {
        $storeContext = app(StoreContext::class);
        $payments = SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeContext) {
            $query->where('store_id', $storeContext->current(auth()->user()));
        })->orWhereHas('landingSubscription', function (Builder $query) use ($storeContext) {
            $query->whereHas('provisionedStore', function (Builder $subQuery) use ($storeContext) {
                $subQuery->where('id', $storeContext->current(auth()->user()));
            });
        })->with(['subscription.plan', 'landingSubscription'])
        ->orderBy('created_at', 'desc')
        ->get();

        $csvData = [];
        $csvData[] = [
            'Payment ID',
            'Plan',
            'Amount',
            'Status',
            'Payment Method',
            'Created At',
            'Paid At',
        ];

        foreach ($payments as $payment) {
            $csvData[] = [
                $payment->external_id,
                $payment->subscription?->plan?->name ?? 'N/A',
                $payment->amount,
                ucfirst($payment->status),
                $payment->getPaymentMethodDisplayName(),
                $payment->created_at->format('Y-m-d H:i:s'),
                $payment->paid_at?->format('Y-m-d H:i:s') ?? 'Not paid',
            ];
        }

        $filename = 'subscription_payments_' . now()->format('Y_m_d_H_i_s') . '.csv';
        $handle = fopen('php://temp', 'w+');
        
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->send();
    }
}