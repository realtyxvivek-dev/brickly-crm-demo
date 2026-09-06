<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Models\LeadAssignment;
use App\Services\WhatsAppAutomationTriggerService;
use Illuminate\Support\Facades\Log;

class DispatchWhatsAppLeadAssignedAutomation
{
    public function __construct(
        private readonly WhatsAppAutomationTriggerService $triggerService
    ) {
    }

    public function handle(LeadAssigned $event): void
    {
        try {
            $assignment = LeadAssignment::query()
                ->where('lead_id', $event->lead->id)
                ->where('assigned_to', $event->assignedTo)
                ->where('is_active', true)
                ->latest('id')
                ->first();

            $this->triggerService->leadAssigned($event->lead->fresh(['activeAssignments.assignedTo.role']), $assignment, $event->assignedBy);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp lead-assigned automation dispatch failed', [
                'lead_id' => $event->lead->id,
                'assigned_to' => $event->assignedTo,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
