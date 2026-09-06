<?php

namespace App\Listeners;

use App\Events\LeadStatusUpdated;
use App\Services\WhatsAppAutomationTriggerService;
use Illuminate\Support\Facades\Log;

class DispatchWhatsAppLeadStatusAutomation
{
    public function __construct(
        private readonly WhatsAppAutomationTriggerService $triggerService
    ) {
    }

    public function handle(LeadStatusUpdated $event): void
    {
        try {
            $trigger = in_array($event->newStatus, ['closed', 'booked', 'lost'], true)
                ? 'lead_closed'
                : 'lead_stage_changed';

            $this->triggerService->handleTrigger($trigger, [
                'lead' => $event->lead->fresh(['activeAssignments.assignedTo.role']),
                'actor_type' => 'system',
                'actor_id' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp lead-status automation dispatch failed', [
                'lead_id' => $event->lead->id,
                'new_status' => $event->newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
