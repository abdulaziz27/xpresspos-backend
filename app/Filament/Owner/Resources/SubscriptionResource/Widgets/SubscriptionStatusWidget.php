<?php

namespace App\Filament\Owner\Resources\SubscriptionResource\Widgets;

use App\Models\Subscription;
use Filament\Widgets\Widget;

class SubscriptionStatusWidget extends Widget
{
    protected string $view = 'filament.owner.widgets.subscription-status';

    public Subscription $record;

    protected function getViewData(): array
    {
        $latestPayment = $this->record->subscriptionPayments()
            ->latest()
            ->first();

        $nextPaymentAmount = $this->record->amount;
        $daysUntilRenewal = $this->record->ends_at->diffInDays();
        $isExpiringSoon = $daysUntilRenewal <= 7;
        $isExpired = $this->record->ends_at->isPast();

        return [
            'subscription' => $this->record,
            'latestPayment' => $latestPayment,
            'nextPaymentAmount' => $nextPaymentAmount,
            'daysUntilRenewal' => $daysUntilRenewal,
            'isExpiringSoon' => $isExpiringSoon,
            'isExpired' => $isExpired,
        ];
    }
}