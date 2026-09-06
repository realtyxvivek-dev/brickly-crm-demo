<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeWelcomeOnboardingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $employeeName,
        public ?string $designation = null,
        public ?string $department = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to ' . config('app.name', 'Base CRM'))
            ->greeting('Welcome, ' . $this->employeeName)
            ->line('Your employee record has been activated in the HR system.')
            ->line('Designation: ' . ($this->designation ?: 'Not assigned'))
            ->line('Department: ' . ($this->department ?: 'Not assigned'))
            ->line('Please complete any pending HR documents and verify your details.');
    }
}
