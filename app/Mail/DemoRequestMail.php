<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $demoRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->demoRequest['email'], $this->demoRequest['name'])],
            subject: '['.brand_name().'] New Demo Request — '.$this->demoRequest['company'],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demo-request');
    }

    public function attachments(): array
    {
        return [];
    }
}
