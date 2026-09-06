<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Support\AppUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $lead;
    public $assignedBy;

    public function __construct(Lead $lead, int $assignedBy)
    {
        $this->lead = $lead;
        $this->assignedBy = $assignedBy;
    }

    public function via($notifiable): array
    {
        $channels = ['database', 'broadcast'];
        if (config('mail.default') && config('mail.from.address')) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $leadName = $this->lead->name ?? 'Lead';
        $actionUrl = $notifiable->isHrManager() && $this->lead->is_hiring_candidate
            ? AppUrl::to('/hr-manager/hiring/' . $this->lead->id)
            : ($notifiable->isTelecaller() || $notifiable->isSalesExecutive()
                ? AppUrl::to('/telecaller/tasks?status=pending')
                : AppUrl::to('/leads'));

        return (new MailMessage)
            ->subject('New lead assigned: ' . $leadName)
            ->line('A new lead has been assigned to you.')
            ->line('**Lead:** ' . $leadName)
            ->action('View leads', $actionUrl)
            ->line('Please call and complete the task.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'lead_assigned',
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->name,
            'assigned_by' => $this->assignedBy,
            'message' => "A new lead '{$this->lead->name}' has been assigned to you.",
        ];
    }
}
