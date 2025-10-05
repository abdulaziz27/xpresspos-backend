<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class ReportExportReady extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $reportType,
        public string $format,
        public string $fileName,
        public string $downloadUrl,
        public Carbon $expiresAt
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Report Export is Ready - ' . ucfirst(str_replace('_', ' ', $this->reportType)),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.report-export-ready',
            with: [
                'reportType' => ucfirst(str_replace('_', ' ', $this->reportType)),
                'format' => strtoupper($this->format),
                'fileName' => $this->fileName,
                'downloadUrl' => $this->downloadUrl,
                'expiresAt' => $this->expiresAt->format('M j, Y \a\t g:i A'),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}