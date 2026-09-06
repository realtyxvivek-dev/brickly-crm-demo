<?php

namespace App\Listeners;

use App\Events\SiteVisitCreated;
use App\Services\WhatsAppAutomationTriggerService;
use Illuminate\Support\Facades\Log;

class DispatchWhatsAppSiteVisitAutomation
{
    public function __construct(
        private readonly WhatsAppAutomationTriggerService $triggerService
    ) {
    }

    public function handle(SiteVisitCreated $event): void
    {
        try {
            $this->triggerService->siteVisitScheduled($event->siteVisit->fresh(['lead.activeAssignments.assignedTo.role']), $event->siteVisit->created_by);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp site-visit automation dispatch failed', [
                'site_visit_id' => $event->siteVisit->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
