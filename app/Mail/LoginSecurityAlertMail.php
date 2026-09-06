<?php

namespace App\Mail;

use App\Models\LoginSecurityEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginSecurityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LoginSecurityEvent $event,
        public string $audience = 'user',
        public ?User $recipient = null
    ) {
    }

    public function envelope(): Envelope
    {
        $prefix = $this->audience === 'admin' ? 'Admin Alert: ' : '';

        return new Envelope(
            subject: $prefix . 'CRM login security alert',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-security-alert',
        );
    }
}
