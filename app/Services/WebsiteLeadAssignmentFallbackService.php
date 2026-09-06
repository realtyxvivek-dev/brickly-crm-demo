<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Models\WebsiteIntegration;

class WebsiteLeadAssignmentFallbackService
{
    public function __construct(
        private readonly LeadAssignmentWorkflowService $leadAssignmentWorkflowService,
    ) {
    }

    public function apply(Lead $lead, WebsiteIntegration $integration, int $actorUserId): array
    {
        if ($integration->fallback_type === WebsiteIntegration::FALLBACK_DEFAULT_USER && $integration->fallback_user_id) {
            $assignee = User::find($integration->fallback_user_id);

            if ($assignee) {
                $lead->update([
                    'website_queue_status' => null,
                ]);

                $assignment = $this->leadAssignmentWorkflowService->assignLead(
                    $lead,
                    (int) $assignee->id,
                    $actorUserId,
                    'Website integration fallback assignment',
                );

                return [
                    'type' => 'default_user',
                    'applied' => true,
                    'assigned_to' => [
                        'id' => $assignee->id,
                        'name' => $assignee->name,
                    ],
                    'workflow' => $assignment,
                ];
            }
        }

        $lead->update([
            'website_queue_status' => 'unassigned_website',
        ]);

        return [
            'type' => 'unassigned_crm_queue',
            'applied' => true,
            'assigned_to' => null,
            'queue_status' => 'unassigned_website',
        ];
    }
}
