<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangeRequiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $actionUrl
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Change your CRM password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-change-required',
        );
    }
}
