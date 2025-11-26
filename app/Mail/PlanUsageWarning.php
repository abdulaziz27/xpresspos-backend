<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlanUsageWarning extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Tenant $tenant,
        public string $featureType, // 'products', 'staff', 'stores', 'transactions'
        public int $currentUsage,
        public int $limit,
        public float $usagePercentage,
        public string $threshold // '80' or '100'
    ) {
        $this->onQueue('emails');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $featureLabels = [
            'products' => 'Produk',
            'staff' => 'Staff',
            'stores' => 'Toko',
            'transactions' => 'Transaksi',
        ];

        $featureLabel = $featureLabels[$this->featureType] ?? ucfirst($this->featureType);
        $isCritical = $this->threshold === '100';
        $subject = $isCritical 
            ? "⚠️ Limit {$featureLabel} Tercapai - {$this->tenant->name} | XpressPOS"
            : "⚠️ Peringatan: Penggunaan {$featureLabel} Mencapai " . number_format($this->usagePercentage, 1) . "% - {$this->tenant->name} | XpressPOS";

        return new Envelope(
            subject: $subject,
            tags: ['plan-usage', 'usage-warning', $this->featureType],
            metadata: [
                'tenant_id' => $this->tenant->id,
                'feature_type' => $this->featureType,
                'usage_percentage' => $this->usagePercentage,
                'threshold' => $this->threshold,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $featureLabels = [
            'products' => 'Produk',
            'staff' => 'Staff',
            'stores' => 'Toko',
            'transactions' => 'Transaksi',
        ];

        $featureLabel = $featureLabels[$this->featureType] ?? ucfirst($this->featureType);
        $isCritical = $this->threshold === '100';
        $planName = $this->tenant->plan?->name ?? 'Unknown Plan';

        return new Content(
            view: 'emails.plan-usage-warning',
            with: [
                'tenant' => $this->tenant,
                'tenantName' => $this->tenant->name,
                'planName' => $planName,
                'featureType' => $this->featureType,
                'featureLabel' => $featureLabel,
                'currentUsage' => $this->currentUsage,
                'limit' => $this->limit,
                'usagePercentage' => $this->usagePercentage,
                'threshold' => $this->threshold,
                'isCritical' => $isCritical,
                'remaining' => max(0, $this->limit - $this->currentUsage),
                'upgradeUrl' => \App\Filament\Owner\Resources\SubscriptionResource::getUrl('index'),
            ],
        );
    }
}

