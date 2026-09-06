<?php

namespace App\Services;

use App\Models\CallingCenterCampaign;
use App\Models\CallingCenterCampaignItem;
use App\Models\CallingCenterPushRequest;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CallingCenterService
{
    public function __construct(private readonly McubeOutboundCallService $mcubeOutboundCallService)
    {
    }

    public function eligibleAgents(): Collection
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', function (Builder $query) {
                $query->whereIn('slug', [
                    Role::TELECALLER,
                    Role::SALES_EXECUTIVE,
                    Role::ASSISTANT_SALES_MANAGER,
                    Role::SENIOR_MANAGER,
                    Role::SALES_MANAGER,
                ]);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'role_id']);
    }

    public function leadQueryFromFilters(array $filters): Builder
    {
        $query = Lead::query()
            ->whereHas('importedLeads', function (Builder $importQuery) {
                $importQuery->whereHas('importBatch', function (Builder $batchQuery) {
                    $batchQuery->where('import_kind', 'lead_bank');
                });
            });

        if (($filters['folder_type'] ?? null) === 'system') {
            if (($filters['folder_key'] ?? null) === 'unassigned') {
                $query->whereDoesntHave('activeAssignments');
            }
        }

        if (($filters['folder_type'] ?? null) === 'tag' && !empty($filters['folder_tag_id'])) {
            $tagId = (int) $filters['folder_tag_id'];
            $query->whereHas('leadTags', fn (Builder $tagQuery) => $tagQuery->where('lead_tags.id', $tagId));
        }

        foreach (['city', 'source', 'status'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function createCampaign(User $creator, array $data): CallingCenterCampaign
    {
        $leadIds = collect($data['lead_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($leadIds->isEmpty()) {
            $leadIds = $this->leadQueryFromFilters($data)
                ->limit((int) ($data['quantity'] ?? 100))
                ->pluck('id');
        } else {
            $leadIds = $this->leadQueryFromFilters($data)
                ->whereIn('id', $leadIds->all())
                ->pluck('id');
        }

        return DB::transaction(function () use ($creator, $data, $leadIds) {
            $campaign = CallingCenterCampaign::create([
                'name' => trim((string) $data['name']),
                'assigned_to' => (int) $data['assigned_to'],
                'created_by' => $creator->id,
                'source_type' => 'lead_bank',
                'folder_type' => $data['folder_type'] ?? 'system',
                'folder_key' => $data['folder_key'] ?? 'all',
                'folder_tag_id' => $data['folder_tag_id'] ?? null,
                'filters' => collect($data)->only(['city', 'source', 'status', 'search'])->filter(fn ($value) => filled($value))->all(),
                'status' => CallingCenterCampaign::STATUS_DRAFT,
                'delay_seconds' => max(0, (int) ($data['delay_seconds'] ?? 120)),
                'retry_policy' => $data['retry_policy'] ?? 'none',
                'max_retries' => max(0, (int) ($data['max_retries'] ?? 0)),
            ]);

            $existingActiveLeadIds = CallingCenterCampaignItem::query()
                ->whereIn('lead_id', $leadIds)
                ->whereIn('status', [
                    CallingCenterCampaignItem::STATUS_PENDING,
                    CallingCenterCampaignItem::STATUS_DIALING,
                    CallingCenterCampaignItem::STATUS_OUTCOME_PENDING,
                ])
                ->pluck('lead_id')
                ->all();

            if ($leadIds->isEmpty()) {
                return $campaign->fresh(['items']);
            }

            $seenPhones = [];
            Lead::query()
                ->whereIn('id', $leadIds)
                ->get()
                ->each(function (Lead $lead) use ($campaign, &$seenPhones, $existingActiveLeadIds) {
                    $phone = $this->normalizedPhone((string) $lead->phone);
                    $skipReason = null;

                    if ($phone === '') {
                        $skipReason = 'missing_phone';
                    } elseif (isset($seenPhones[$phone])) {
                        $skipReason = 'duplicate_phone_in_campaign';
                    } elseif (in_array($lead->id, $existingActiveLeadIds, true)) {
                        $skipReason = 'already_in_active_calling_campaign';
                    } elseif ($lead->is_dead || in_array((string) $lead->status, ['dead', 'not_interested', 'junk'], true)) {
                        $skipReason = 'blocked_status';
                    } elseif ($lead->whatsapp_opted_out_at) {
                        $skipReason = 'do_not_call';
                    }

                    $task = null;
                    if (!$skipReason) {
                        $task = Task::create([
                            'lead_id' => $lead->id,
                            'assigned_to' => $campaign->assigned_to,
                            'type' => 'phone_call',
                            'title' => 'Calling Center: ' . ($campaign->name ?: 'Campaign #' . $campaign->id),
                            'description' => 'Calling Center campaign item.',
                            'status' => 'pending',
                            'scheduled_at' => now(),
                            'created_by' => $campaign->created_by,
                        ]);
                    }

                    CallingCenterCampaignItem::create([
                        'campaign_id' => $campaign->id,
                        'lead_id' => $lead->id,
                        'task_id' => $task?->id,
                        'phone' => $phone,
                        'status' => $skipReason ? CallingCenterCampaignItem::STATUS_SKIPPED : CallingCenterCampaignItem::STATUS_PENDING,
                        'next_call_at' => $skipReason ? null : now(),
                        'meta' => $skipReason ? ['skip_reason' => $skipReason] : null,
                    ]);

                    if ($phone !== '') {
                        $seenPhones[$phone] = true;
                    }
                });

            return $campaign->fresh(['items']);
        });
    }

    public function startCampaign(CallingCenterCampaign $campaign): array
    {
        $campaign->update([
            'status' => CallingCenterCampaign::STATUS_RUNNING,
            'started_at' => $campaign->started_at ?: now(),
            'paused_at' => null,
        ]);

        return $this->triggerNextForCampaign($campaign->fresh());
    }

    public function pauseCampaign(CallingCenterCampaign $campaign): void
    {
        $campaign->update([
            'status' => CallingCenterCampaign::STATUS_PAUSED,
            'paused_at' => now(),
        ]);
    }

    public function cancelCampaign(CallingCenterCampaign $campaign): void
    {
        $campaign->update(['status' => CallingCenterCampaign::STATUS_CANCELLED]);
    }

    public function processDueCampaigns(): int
    {
        if (!Schema::hasTable('calling_center_campaigns') || !Schema::hasTable('calling_center_campaign_items')) {
            return 0;
        }

        $count = 0;
        CallingCenterCampaign::query()
            ->where('status', CallingCenterCampaign::STATUS_RUNNING)
            ->whereHas('items', function (Builder $query) {
                $query->where('status', CallingCenterCampaignItem::STATUS_PENDING)
                    ->where(function (Builder $dateQuery) {
                        $dateQuery->whereNull('next_call_at')->orWhere('next_call_at', '<=', now());
                    });
            })
            ->with('assignedTo')
            ->chunkById(50, function ($campaigns) use (&$count) {
                foreach ($campaigns as $campaign) {
                    $result = $this->triggerNextForCampaign($campaign);
                    if ($result['started'] ?? false) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function triggerNextForCampaign(CallingCenterCampaign $campaign): array
    {
        if ($campaign->status !== CallingCenterCampaign::STATUS_RUNNING) {
            return ['started' => false, 'message' => 'Campaign is not running.'];
        }

        if ($this->agentHasActiveItem((int) $campaign->assigned_to)) {
            return ['started' => false, 'message' => 'Agent already has an active calling item.'];
        }

        $item = $campaign->items()
            ->with(['lead', 'task'])
            ->where('status', CallingCenterCampaignItem::STATUS_PENDING)
            ->where(function (Builder $query) {
                $query->whereNull('next_call_at')->orWhere('next_call_at', '<=', now());
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (!$item) {
            $this->completeIfFinished($campaign);
            return ['started' => false, 'message' => 'No due pending item.'];
        }

        $agent = $campaign->assignedTo ?: User::find($campaign->assigned_to);
        if (!$agent || !$item->lead) {
            $item->update(['status' => CallingCenterCampaignItem::STATUS_FAILED, 'call_status' => 'missing_agent_or_lead']);
            return ['started' => false, 'message' => 'Missing agent or lead.'];
        }

        $item->update([
            'status' => CallingCenterCampaignItem::STATUS_DIALING,
            'locked_at' => now(),
            'call_started_at' => now(),
            'attempt_count' => $item->attempt_count + 1,
        ]);

        $refid = "calling_campaign:{$campaign->id}:item:{$item->id}";
        $result = $this->mcubeOutboundCallService->initiate(
            $agent,
            $item->lead,
            $item->task,
            $item->phone,
            $refid,
            $item->id
        );

        $item->update([
            'mcube_outbound_attempt_id' => $result['attempt_id'] ?? null,
            'status' => ($result['success'] ?? false) ? CallingCenterCampaignItem::STATUS_OUTCOME_PENDING : CallingCenterCampaignItem::STATUS_FAILED,
            'call_status' => ($result['success'] ?? false) ? 'initiated' : 'failed',
            'call_ended_at' => ($result['success'] ?? false) ? null : now(),
            'meta' => array_merge($item->meta ?? [], ['mcube_result' => $result]),
        ]);

        if (!($result['success'] ?? false) && $this->pauseAfterConsecutiveFailures($campaign)) {
            return [
                'started' => false,
                'message' => 'Campaign paused after repeated MCUBE failures.',
                'item_id' => $item->id,
            ];
        }

        return [
            'started' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? 'Call processed.',
            'item_id' => $item->id,
        ];
    }

    public function submitOutcome(CallingCenterCampaignItem $item, User $user, array $data): array
    {
        $item->loadMissing(['campaign', 'lead', 'task']);
        if ((int) $item->campaign->assigned_to !== (int) $user->id && !$user->canUseCallingCenter('calling_center.view_team_report')) {
            abort(403);
        }

        return DB::transaction(function () use ($item, $data) {
            $outcome = (string) $data['outcome'];
            $remark = $data['remark'] ?? null;
            $nextAt = filled($data['next_action_at'] ?? null) ? Carbon::parse($data['next_action_at']) : null;
            $shouldRetry = $this->shouldRetryItem($item, $outcome);
            $retryAt = $shouldRetry ? now()->addSeconds((int) $item->campaign->delay_seconds) : null;
            $history = array_merge($item->meta['outcome_history'] ?? [], [[
                'outcome' => $outcome,
                'remark' => $remark,
                'submitted_at' => now()->toDateTimeString(),
                'attempt_count' => (int) $item->attempt_count,
                'call_status' => $item->call_status,
            ]]);

            $item->update([
                'status' => $shouldRetry ? CallingCenterCampaignItem::STATUS_PENDING : CallingCenterCampaignItem::STATUS_COMPLETED,
                'outcome' => $outcome,
                'remark' => $remark,
                'outcome_submitted_at' => now(),
                'call_ended_at' => $item->call_ended_at ?: now(),
                'next_call_at' => $retryAt ?: $item->next_call_at,
                'meta' => array_merge($item->meta ?? [], [
                    'outcome_history' => $history,
                    'retry_scheduled' => $shouldRetry,
                    'last_retry_reason' => $shouldRetry ? $this->retryReason($item, $outcome) : null,
                ]),
            ]);

            if ($item->task) {
                $item->task->update([
                    'status' => $shouldRetry ? 'pending' : 'completed',
                    'outcome' => $outcome,
                    'outcome_remark' => $remark,
                    'outcome_recorded_at' => now(),
                    'completed_at' => $shouldRetry ? null : now(),
                    'next_action_at' => $shouldRetry ? $retryAt : $nextAt,
                ]);
            }

            $this->applyLeadOutcome($item, $outcome, $remark, $nextAt);
            if (!$shouldRetry) {
                $this->scheduleNextItem($item->campaign);
            }
            $this->completeIfFinished($item->campaign);

            return [
                'success' => true,
                'message' => $shouldRetry
                    ? 'Outcome submitted. Retry will start after campaign delay.'
                    : 'Outcome submitted. Next call will start after campaign delay.',
            ];
        });
    }

    public function linkInboundWebhook(array $payload): ?CallingCenterCampaignItem
    {
        $refid = (string) ($payload['refid'] ?? $payload['refId'] ?? '');
        if (!preg_match('/calling_campaign:(\d+):item:(\d+)/', $refid, $matches)) {
            return null;
        }

        $item = CallingCenterCampaignItem::find((int) $matches[2]);
        if (!$item) {
            return null;
        }

        $status = strtolower((string) ($payload['dialstatus'] ?? $payload['status'] ?? ''));
        $item->update([
            'call_status' => $status ?: $item->call_status,
            'status' => $item->status === CallingCenterCampaignItem::STATUS_DIALING
                ? CallingCenterCampaignItem::STATUS_OUTCOME_PENDING
                : $item->status,
            'call_ended_at' => now(),
            'meta' => array_merge($item->meta ?? [], ['last_mcube_webhook' => $payload]),
        ]);

        return $item;
    }

    private function applyLeadOutcome(CallingCenterCampaignItem $item, string $outcome, ?string $remark, $nextAt): void
    {
        $lead = $item->lead;
        if (!$lead) {
            return;
        }

        $updates = ['last_contacted_at' => now()];

        if ($outcome === 'interested') {
            $updates['status'] = 'interested';
        } elseif ($outcome === 'not_interested') {
            $updates['status'] = 'not_interested';
        } elseif ($outcome === 'junk') {
            $updates['status'] = 'junk';
        } elseif ($outcome === 'cnp') {
            $updates['cnp_count'] = ((int) $lead->cnp_count) + 1;
        } elseif ($outcome === 'follow_up' && $nextAt) {
            $updates['status'] = 'follow_up';
            $updates['next_followup_at'] = $nextAt;
            $followUp = FollowUp::create([
                'lead_id' => $lead->id,
                'created_by' => $item->campaign->assigned_to,
                'type' => 'call',
                'notes' => $remark ?: 'Calling Center follow-up.',
                'scheduled_at' => $nextAt,
                'status' => 'scheduled',
                'outcome' => 'pending',
            ]);
            Task::create([
                'lead_id' => $lead->id,
                'assigned_to' => $item->campaign->assigned_to,
                'type' => 'phone_call',
                'title' => 'Follow Up: Calling Center',
                'description' => $remark,
                'status' => 'pending',
                'scheduled_at' => $nextAt,
                'created_by' => $item->campaign->assigned_to,
                'follow_up_id' => $followUp->id,
            ]);
        }

        if ($remark) {
            $updates['notes'] = trim(($lead->notes ? $lead->notes . "\n" : '') . '[' . now()->format('Y-m-d H:i:s') . '] Calling Center outcome: ' . $outcome . ' - ' . $remark);
        }

        $lead->update($updates);
    }

    private function scheduleNextItem(CallingCenterCampaign $campaign): void
    {
        $nextItem = $campaign->items()
            ->where('status', CallingCenterCampaignItem::STATUS_PENDING)
            ->orderBy('id')
            ->first();

        if ($nextItem) {
            $nextItem->update(['next_call_at' => now()->addSeconds((int) $campaign->delay_seconds)]);
        }
    }

    private function shouldRetryItem(CallingCenterCampaignItem $item, string $outcome): bool
    {
        $campaign = $item->campaign;
        if (!$campaign || $campaign->retry_policy === 'none' || (int) $campaign->max_retries <= 0) {
            return false;
        }

        if ((int) $item->attempt_count > (int) $campaign->max_retries) {
            return false;
        }

        return $this->retryReason($item, $outcome) !== null;
    }

    private function retryReason(CallingCenterCampaignItem $item, string $outcome): ?string
    {
        $callStatus = strtolower((string) $item->call_status);
        $retryableStatuses = ['noanswer', 'no_answer', 'busy', 'executive_busy'];

        if ($outcome === 'cnp') {
            return 'cnp';
        }

        if (in_array($callStatus, $retryableStatuses, true)) {
            return $callStatus;
        }

        return null;
    }

    private function pauseAfterConsecutiveFailures(CallingCenterCampaign $campaign): bool
    {
        $recentItems = $campaign->items()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        if ($recentItems->count() < 5 || $recentItems->contains(fn ($item) => $item->status !== CallingCenterCampaignItem::STATUS_FAILED)) {
            return false;
        }

        $campaign->update([
            'status' => CallingCenterCampaign::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        return true;
    }

    private function completeIfFinished(CallingCenterCampaign $campaign): void
    {
        $campaign->refresh();
        $openExists = $campaign->items()
            ->whereIn('status', [
                CallingCenterCampaignItem::STATUS_PENDING,
                CallingCenterCampaignItem::STATUS_DIALING,
                CallingCenterCampaignItem::STATUS_OUTCOME_PENDING,
            ])
            ->exists();

        if (!$openExists && $campaign->status === CallingCenterCampaign::STATUS_RUNNING) {
            $campaign->update([
                'status' => CallingCenterCampaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    private function agentHasActiveItem(int $agentId): bool
    {
        return CallingCenterCampaignItem::query()
            ->whereIn('status', [CallingCenterCampaignItem::STATUS_DIALING, CallingCenterCampaignItem::STATUS_OUTCOME_PENDING])
            ->whereHas('campaign', fn (Builder $query) => $query->where('assigned_to', $agentId)->where('status', CallingCenterCampaign::STATUS_RUNNING))
            ->exists();
    }

    private function normalizedPhone(string $phone): string
    {
        return $this->mcubeOutboundCallService->normalizePhone($phone);
    }
}
