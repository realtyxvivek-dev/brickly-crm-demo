<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Meeting;
use App\Models\SiteVisit;

class WhatsAppAutomationTriggerService
{
    public function __construct(
        private readonly WhatsAppAutomationService $automationService
    ) {
    }

    public function handleTrigger(string $eventName, array $entityContext = []): array
    {
        return $this->automationService->handleTrigger($eventName, $entityContext);
    }

    public function leadCreated(Lead $lead, ?int $actorId = null): array
    {
        return $this->handleTrigger('lead_created', [
            'lead' => $lead,
            'actor_type' => 'system',
            'actor_id' => $actorId,
        ]);
    }

    public function leadAssigned(Lead $lead, LeadAssignment|int|null $assignment = null, ?int $actorId = null): array
    {
        $assignmentModel = $assignment instanceof LeadAssignment ? $assignment : null;

        return $this->handleTrigger('lead_assigned', [
            'lead' => $lead,
            'assignment' => $assignmentModel,
            'assignment_id' => is_int($assignment) ? $assignment : $assignmentModel?->id,
            'actor_type' => 'system',
            'actor_id' => $actorId,
        ]);
    }

    public function meetingScheduled(Meeting $meeting, ?int $actorId = null): array
    {
        return $this->handleTrigger('meeting_scheduled', [
            'lead' => $meeting->lead,
            'meeting' => $meeting,
            'actor_type' => 'system',
            'actor_id' => $actorId,
        ]);
    }

    public function meetingCompleted(Meeting $meeting, ?int $actorId = null): array
    {
        return $this->handleTrigger('meeting_completed', [
            'lead' => $meeting->lead,
            'meeting' => $meeting,
            'actor_type' => 'system',
            'actor_id' => $actorId,
        ]);
    }

    public function siteVisitScheduled(SiteVisit $siteVisit, ?int $actorId = null): array
    {
        return $this->handleTrigger('site_visit_scheduled', [
            'lead' => $siteVisit->lead,
            'site_visit' => $siteVisit,
            'actor_type' => 'system',
            'actor_id' => $actorId,
        ]);
    }

    public function siteVisitVerified(SiteVisit $siteVisit, ?int $actorId = null): array
    {
        return $this->handleTrigger('site_visit_verified', [
            'lead' => $siteVisit->lead,
            'site_visit' => $siteVisit,
            'actor_type' => 'system',
            'actor_id' => $actorId,
        ]);
    }
}
