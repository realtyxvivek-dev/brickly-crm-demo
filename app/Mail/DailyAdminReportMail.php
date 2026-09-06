<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyAdminReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $report)
    {
    }

    public function envelope(): Envelope
    {
        $date = $this->report['range_label'] ?? ($this->report['date'] instanceof Carbon
            ? $this->report['date']->format('d M Y')
            : Carbon::parse($this->report['date'])->format('d M Y'));

        return new Envelope(
            subject: "Base CRM ERP Report - {$date}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-admin-report',
        );
    }
}
