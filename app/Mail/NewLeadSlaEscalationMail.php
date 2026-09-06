<?php

namespace App\Mail;

use App\Models\NewLeadSlaAutomationState;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewLeadSlaEscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewLeadSlaAutomationState $state,
        public array $attemptTrail
    ) {
    }

    public function envelope(): Envelope
    {
        $leadName = $this->state->lead?->name ?? 'Lead';

        return new Envelope(
            subject: "New Lead SLA Escalation: {$leadName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-lead-sla-escalation',
        );
    }
}
