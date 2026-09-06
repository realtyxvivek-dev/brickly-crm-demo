<?php

namespace App\Mail;

use App\Models\LeadDownloadRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadDownloadReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeadDownloadRequest $leadDownloadRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Lead Export Is Ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead-download-ready',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
