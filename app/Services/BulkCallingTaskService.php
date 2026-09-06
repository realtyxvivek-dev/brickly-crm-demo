<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Role;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BulkCallingTaskService
{
    private const ELIGIBLE_ROLE_SLUGS = [
        Role::SALES_EXECUTIVE,
        Role::SALES_MANAGER,
        Role::SENIOR_MANAGER,
        Role::ASSISTANT_SALES_MANAGER,
    ];

    private const OPEN_TASK_STATUSES = [
        'pending',
        'in_progress',
        'rescheduled',
    ];

    public function getEligibleUsers(): Collection
    {
        $roleIds = Role::query()
            ->whereIn('slug', self::ELIGIBLE_ROLE_SLUGS)
            ->pluck('id');

        return User::query()
            ->whereIn('role_id', $roleIds)
            ->where('is_active', true)
            ->with('role')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (User $user) => $user->role->name ?? 'Other');
    }

    public function previewLeads(User $assignedUser, array $filters, bool $includeExistingOpenTasks, int $perPage = 50): LengthAwarePaginator
    {
        $query = $this->assignedLeadsQuery($assignedUser, $filters, $includeExistingOpenTasks);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function (Lead $lead) use ($assignedUser) {
            $lead->setAttribute('has_open_call_task', $this->leadHasOpenCallingTask($lead, $assignedUser));
            $lead->setAttribute('assigned_at', optional($lead->activeAssignments->first()?->assigned_at)?->toDateTimeString());

            return $lead;
        });

        $paginator->setCollection($items);

        return $paginator;
    }

    public function createTasks(
        User $assignedUser,
        Carbon $startAt,
        int $gapMinutes,
        ?string $notes,
        bool $includeExistingOpenTasks,
        bool $allEligible,
        array $filters,
        array $leadIds = []
    ): array {
        $notes = $notes !== null ? trim($notes) : null;
        $requestedIds = collect($leadIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        $baseQuery = $this->assignedLeadsQuery($assignedUser, $filters, true);

        $candidateLeads = $allEligible
            ? $baseQuery->get()
            : $baseQuery->whereIn('id', $requestedIds)->get();

        $candidateLeads = $candidateLeads->keyBy('id');
        $eligibleIds = $candidateLeads->keys()->values();
        $sourceIds = $allEligible
            ? $eligibleIds
            : $eligibleIds->concat($requestedIds->diff($eligibleIds))->values();

        $created = 0;
        $skipped = 0;
        $createdScheduleTimes = [];
        $reasons = [
            'duplicate_open_task' => 0,
            'lead_not_eligible' => 0,
        ];

        $scheduleIndex = 0;
        foreach ($sourceIds as $leadId) {
            /** @var Lead|null $lead */
            $lead = $candidateLeads->get((int) $leadId);

            if (!$lead) {
                $skipped++;
                $reasons['lead_not_eligible']++;
                continue;
            }

            if (!$includeExistingOpenTasks && $this->leadHasOpenCallingTask($lead, $assignedUser)) {
                $skipped++;
                $reasons['duplicate_open_task']++;
                continue;
            }

            $scheduledAt = $startAt->copy()->addMinutes($scheduleIndex * $gapMinutes);
            $this->createSingleTask($lead, $assignedUser, $scheduledAt, $notes);
            $created++;
            $scheduleIndex++;
            $createdScheduleTimes[] = $scheduledAt->copy();
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'reason_counts' => $reasons,
            'first_scheduled_at' => $createdScheduleTimes[0]?->toDateTimeString(),
            'last_scheduled_at' => !empty($createdScheduleTimes)
                ? $createdScheduleTimes[array_key_last($createdScheduleTimes)]->toDateTimeString()
                : null,
        ];
    }

    public function assignedLeadsCount(User $assignedUser, array $filters, bool $includeExistingOpenTasks): int
    {
        return $this->assignedLeadsQuery($assignedUser, $filters, $includeExistingOpenTasks)->count();
    }

    public function assignedLeadsQuery(User $assignedUser, array $filters, bool $includeExistingOpenTasks): Builder
    {
        $query = Lead::query()
            ->with(['activeAssignments' => function ($relation) use ($assignedUser) {
                $relation->where('assigned_to', $assignedUser->id)->latest('assigned_at');
            }])
            ->whereHas('activeAssignments', function ($relation) use ($assignedUser) {
                $relation->where('assigned_to', $assignedUser->id);
            });

        $query->whereNotIn('status', ['junk', 'not_interested', 'closed'])
            ->where(function (Builder $builder) {
                $builder->where('is_dead', false)
                    ->orWhereNull('is_dead');
            });

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '') {
            $query->where('source', $source);
        }

        if (!$includeExistingOpenTasks) {
            if ($assignedUser->isSalesExecutive() || $assignedUser->isTelecaller()) {
                $query->whereDoesntHave('tasks', function (Builder $taskQuery) use ($assignedUser) {
                    $taskQuery->where('assigned_to', $assignedUser->id)
                        ->where('task_type', 'calling')
                        ->whereIn('status', self::OPEN_TASK_STATUSES);
                });
            } else {
                $query->whereDoesntHave('managerTasks', function (Builder $taskQuery) use ($assignedUser) {
                    $taskQuery->where('assigned_to', $assignedUser->id)
                        ->where('type', 'phone_call')
                        ->whereIn('status', self::OPEN_TASK_STATUSES);
                });
            }
        }

        return $query->latest('id');
    }

    public function isEligibleAssignee(User $user): bool
    {
        return $user->isSalesExecutive()
            || $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager();
    }

    private function leadHasOpenCallingTask(Lead $lead, User $assignedUser): bool
    {
        if ($assignedUser->isSalesExecutive() || $assignedUser->isTelecaller()) {
            return TelecallerTask::query()
                ->where('lead_id', $lead->id)
                ->where('assigned_to', $assignedUser->id)
                ->where('task_type', 'calling')
                ->whereIn('status', self::OPEN_TASK_STATUSES)
                ->exists();
        }

        return Task::query()
            ->where('lead_id', $lead->id)
            ->where('assigned_to', $assignedUser->id)
            ->where('type', 'phone_call')
            ->whereIn('status', self::OPEN_TASK_STATUSES)
            ->exists();
    }

    private function createSingleTask(Lead $lead, User $assignedUser, Carbon $scheduledAt, ?string $notes): void
    {
        if ($assignedUser->isSalesExecutive() || $assignedUser->isTelecaller()) {
            TelecallerTask::create([
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'task_type' => 'calling',
                'status' => 'pending',
                'scheduled_at' => $scheduledAt,
                'created_by' => auth()->id() ?? $assignedUser->manager_id ?? 1,
                'notes' => $notes,
            ]);

            return;
        }

        Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedUser->id,
            'type' => 'phone_call',
            'title' => "Call lead: {$lead->name}",
            'description' => "Phone call task for lead: {$lead->name} ({$lead->phone})",
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'created_by' => auth()->id() ?? $assignedUser->manager_id ?? 1,
            'notes' => $notes,
        ]);
    }
}
