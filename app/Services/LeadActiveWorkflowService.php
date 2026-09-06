<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\SiteVisit;

class LeadActiveWorkflowService
{
    public function moveLeadToWorkflow(?int $leadId, string $workflow, ?int $currentRecordId = null): void
    {
        if (!$leadId) {
            return;
        }

        $reason = 'superseded_by_' . $workflow;

        $this->archiveQueueFollowUps($leadId, $workflow === 'follow_up' ? $currentRecordId : null, $reason);
        $this->archiveQueueMeetings($leadId, $workflow === 'meeting' ? $currentRecordId : null, $reason);
        $this->archiveQueueSiteVisits($leadId, $workflow === 'site_visit' ? $currentRecordId : null, $reason);
    }

    private function archiveQueueFollowUps(int $leadId, ?int $exceptId, string $reason): void
    {
        if (!FollowUp::supportsQueueArchiving()) {
            return;
        }

        FollowUp::withQueueHidden()
            ->where('lead_id', $leadId)
            ->where(function ($query) {
                $query->where(function ($openQuery) {
                    $openQuery->where('status', 'scheduled')
                        ->whereNull('completed_at');
                })->orWhere(function ($completedQuery) {
                    $completedQuery->where('status', 'completed')
                        ->whereNotNull('completed_at');
                });
            })
            ->whereNull('queue_hidden_at')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->get()
            ->each(fn (FollowUp $followUp) => $followUp->archiveForQueue($reason));
    }

    private function archiveQueueMeetings(int $leadId, ?int $exceptId, string $reason): void
    {
        if (!Meeting::supportsQueueArchiving()) {
            return;
        }

        Meeting::withQueueHidden()
            ->where('lead_id', $leadId)
            ->where(function ($query) {
                $query->where(function ($openQuery) {
                    $openQuery->where('status', 'scheduled')
                        ->whereNull('completed_at');
                })->orWhere(function ($completedQuery) {
                    $completedQuery->where('status', 'completed')
                        ->whereNotNull('completed_at');
                });
            })
            ->whereNull('queue_hidden_at')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->get()
            ->each(fn (Meeting $meeting) => $meeting->archiveForQueue($reason));
    }

    private function archiveQueueSiteVisits(int $leadId, ?int $exceptId, string $reason): void
    {
        if (!SiteVisit::supportsQueueArchiving()) {
            return;
        }

        SiteVisit::withQueueHidden()
            ->where('lead_id', $leadId)
            ->where(function ($query) {
                $query->where(function ($openQuery) {
                    $openQuery->whereIn('status', ['scheduled', 'in_progress', 'rescheduled'])
                        ->whereNull('completed_at');
                })->orWhere(function ($completedQuery) {
                    $completedQuery->where('status', 'completed')
                        ->whereNotNull('completed_at')
                        ->where(function ($verificationQuery) {
                            $verificationQuery->whereNull('verification_status')
                                ->orWhere('verification_status', '!=', 'verified');
                        });
                });
            })
            ->whereNull('queue_hidden_at')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->get()
            ->each(fn (SiteVisit $siteVisit) => $siteVisit->archiveForQueue($reason));
    }
}
