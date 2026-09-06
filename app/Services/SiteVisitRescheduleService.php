<?php

namespace App\Services;

use App\Events\SiteVisitCreated;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteVisitRescheduleService
{
    public function __construct(
        private readonly LeadSingleOpenTaskService $leadSingleOpenTaskService,
        private readonly SiteVisitTaskSyncService $siteVisitTaskSyncService,
    ) {
    }

    public function reschedule(SiteVisit $siteVisit, Carbon $scheduledAt, string $reason, User $actor): SiteVisit
    {
        return DB::transaction(function () use ($siteVisit, $scheduledAt, $reason, $actor) {
            $siteVisit = SiteVisit::withoutGlobalScopes()
                ->whereKey($siteVisit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $siteVisit->loadMissing(['lead', 'assignedTo', 'creator']);

            if (!empty($siteVisit->rescheduled_to_visit_id)) {
                return $this->rescheduledVisitResponse($siteVisit);
            }

            $existingRescheduledVisit = SiteVisit::withoutGlobalScopes()
                ->where('rescheduled_from_visit_id', $siteVisit->id)
                ->where('assigned_to', $siteVisit->assigned_to)
                ->where('status', 'scheduled')
                ->whereNull('deleted_at')
                ->whereBetween('scheduled_at', [
                    $scheduledAt->copy()->subMinute(),
                    $scheduledAt->copy()->addMinute(),
                ])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($existingRescheduledVisit) {
                $siteVisit->forceFill([
                    'rescheduled_to_visit_id' => $existingRescheduledVisit->id,
                ])->save();

                return $this->rescheduledVisitResponse($siteVisit);
            }

            if ($siteVisit->status !== 'scheduled') {
                throw ValidationException::withMessages([
                    'site_visit' => ['Can only reschedule scheduled site visits.'],
                ]);
            }

            $nextRescheduleCount = (int) ($siteVisit->reschedule_count ?? 0) + 1;
            $cancellationReason = 'Cancelled due to site visit reschedule: ' . $reason;

            $this->leadSingleOpenTaskService->cancelOpenTasksForSiteVisit($siteVisit, $cancellationReason);

            $siteVisit->forceFill([
                'status' => 'cancelled',
                'is_rescheduled' => true,
                'reschedule_count' => $nextRescheduleCount,
                'rescheduled_at' => now(),
                'rescheduled_by' => $actor->id,
                'reschedule_reason' => $reason,
                'queue_hidden_at' => SiteVisit::supportsQueueArchiving() ? now() : $siteVisit->queue_hidden_at,
                'queue_hidden_reason' => SiteVisit::supportsQueueArchiving() ? 'Rescheduled to a new site visit entry.' : $siteVisit->queue_hidden_reason,
            ])->save();

            $clonePayload = Arr::only($siteVisit->getAttributes(), $siteVisit->getFillable());
            $clonePayload = Arr::except($clonePayload, [
                'status',
                'scheduled_at',
                'completed_at',
                'verification_status',
                'verified_by',
                'verified_at',
                'rejection_reason',
                'closer_status',
                'converted_to_closer_at',
                'closer_verified_by',
                'closer_verified_at',
                'closer_rejection_reason',
                'closing_verification_status',
                'closing_verified_by',
                'closing_verified_at',
                'closing_rejection_reason',
                'is_dead',
                'dead_reason',
                'marked_dead_at',
                'marked_dead_by',
                'reminder_task_id',
                'rescheduled_at',
                'rescheduled_by',
                'reschedule_reason',
                'queue_hidden_at',
                'queue_hidden_reason',
                'rescheduled_from_visit_id',
                'rescheduled_to_visit_id',
            ]);

            $clonePayload['scheduled_at'] = $scheduledAt;
            $clonePayload['status'] = 'scheduled';
            $clonePayload['verification_status'] = 'pending';
            $clonePayload['verified_by'] = null;
            $clonePayload['verified_at'] = null;
            $clonePayload['rejection_reason'] = null;
            $clonePayload['closer_status'] = null;
            $clonePayload['converted_to_closer_at'] = null;
            $clonePayload['closer_verified_by'] = null;
            $clonePayload['closer_verified_at'] = null;
            $clonePayload['closer_rejection_reason'] = null;
            $clonePayload['closing_verification_status'] = null;
            $clonePayload['closing_verified_by'] = null;
            $clonePayload['closing_verified_at'] = null;
            $clonePayload['closing_rejection_reason'] = null;
            $clonePayload['is_dead'] = false;
            $clonePayload['dead_reason'] = null;
            $clonePayload['marked_dead_at'] = null;
            $clonePayload['marked_dead_by'] = null;
            $clonePayload['is_rescheduled'] = false;
            $clonePayload['reschedule_count'] = $nextRescheduleCount;
            $clonePayload['rescheduled_from_visit_id'] = $siteVisit->id;
            $clonePayload['rescheduled_to_visit_id'] = null;
            $clonePayload['reminder_task_id'] = null;
            $clonePayload['queue_hidden_at'] = null;
            $clonePayload['queue_hidden_reason'] = null;

            $newSiteVisit = SiteVisit::create($clonePayload);

            if (in_array('rescheduled_to_visit_id', $siteVisit->getFillable(), true)) {
                $siteVisit->forceFill([
                    'rescheduled_to_visit_id' => $newSiteVisit->id,
                ])->save();
            }

            $this->siteVisitTaskSyncService->syncReminderTask($newSiteVisit, $actor);

            event(new SiteVisitCreated($newSiteVisit));

            if ($newSiteVisit->lead instanceof Lead) {
                $leadType = $newSiteVisit->lead_type ?? null;
                $newSiteVisit->lead->updateStatusIfAllowed($leadType === 'Revisited' ? 'revisited_scheduled' : 'visit_scheduled');
            }

            return $newSiteVisit->fresh(['lead', 'creator', 'assignedTo', 'rescheduledBy']);
        });
    }

    private function rescheduledVisitResponse(SiteVisit $siteVisit): SiteVisit
    {
        return SiteVisit::withoutGlobalScopes()
            ->whereKey($siteVisit->rescheduled_to_visit_id)
            ->firstOrFail()
            ->fresh(['lead', 'creator', 'assignedTo', 'rescheduledBy']);
    }
}
