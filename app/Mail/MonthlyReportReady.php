<?php

namespace App\Mail;

use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MonthlyReportReady extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Store $store,
        public array $reportData,
        public string $pdfPath,
        public Carbon $reportMonth
    ) {
        $this->onQueue('emails');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Monthly Report Ready - {$this->reportMonth->format('F Y')} | {$this->store->name}",
            tags: ['monthly-report', 'business-intelligence'],
            metadata: [
                'store_id' => $this->store->id,
                'report_month' => $this->reportMonth->format('Y-m'),
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.monthly-report-ready',
            with: [
                'store' => $this->store,
                'reportData' => $this->reportData,
                'reportMonth' => $this->reportMonth,
                'executiveSummary' => $this->reportData['executive_summary'],
                'kpis' => $this->reportData['key_performance_indicators'],
                'recommendations' => $this->reportData['recommendations'],
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        // Attach PDF report if it exists
        if (Storage::exists($this->pdfPath)) {
            $attachments[] = Attachment::fromStorage($this->pdfPath)
                ->as("Monthly_Report_{$this->store->name}_{$this->reportMonth->format('Y_m')}.pdf")
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}