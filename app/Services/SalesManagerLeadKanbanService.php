<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesManagerLeadKanbanService
{
    public function isEnabledFor(User $user): bool
    {
        return $user->isAdmin()
            || $user->isCrm()
            || $user->isAssistantSalesManager()
            || $user->isSeniorManager()
            || $user->isSalesManager();
    }

    public function columns(): array
    {
        return [
            'fresh' => 'Fresh / New',
            'cnp' => 'CNP',
            'connected' => 'Connected / Interested',
            'follow_up' => 'Follow-up',
            'meeting_scheduled' => 'Meeting Scheduled',
            'meeting_done' => 'Meeting Done',
            'visit_scheduled' => 'Visit Scheduled',
            'visit_done' => 'Visit Done',
            'closer' => 'Closer / KYC',
            'correction' => 'Correction Required',
            'closed' => 'Closed',
            'lost' => 'Lost / Not Interested',
        ];
    }

    public function filters($request): array
    {
        return [
            'date_filter' => $request->input('date_filter', 'all'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'search' => trim((string) $request->input('search', '')),
            'assigned_to' => $request->input('assigned_to'),
            'source' => $request->input('source'),
            'status' => $request->input('status'),
            'view_mode' => in_array($request->input('view_mode'), ['card', 'list'], true)
                ? $request->input('view_mode')
                : 'list',
        ];
    }

    public function build(User $user, array $filters): array
    {
        $scope = $this->visibilityScope($user);
        $visibleUserIds = $scope['user_ids'];
        $query = $this->baseLeadQuery($visibleUserIds);

        if ($range = $this->dateRange($filters)) {
            $query->whereBetween('created_at', $range);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['assigned_to'])) {
            $query->whereHas('activeAssignments', function ($q) use ($filters, $visibleUserIds) {
                if ($visibleUserIds !== null) {
                    $q->whereIn('assigned_to', $visibleUserIds);
                }

                $q->where('assigned_to', (int) $filters['assigned_to']);
            });
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $leads = $query->latest('updated_at')->limit(500)->get();
        $leadIds = $leads->pluck('id');

        $latestTasks = $this->latestByLead(Task::query()->whereIn('lead_id', $leadIds)->get(), 'scheduled_at');
        $latestMeetings = $this->latestByLead(Meeting::query()->whereIn('lead_id', $leadIds)->get(), 'scheduled_at');
        $latestVisits = $this->latestByLead(SiteVisit::query()->whereIn('lead_id', $leadIds)->get(), 'scheduled_at');

        $columns = collect($this->columns())->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'cards' => [],
        ])->all();

        foreach ($leads as $lead) {
            $task = $latestTasks->get($lead->id);
            $meeting = $latestMeetings->get($lead->id);
            $visit = $latestVisits->get($lead->id);
            $stage = $this->resolveStage($lead, $task, $meeting, $visit);
            $columns[$stage]['cards'][] = $this->card($lead, $task, $meeting, $visit, $stage);
        }

        return [
            'columns' => $columns,
            'summary' => $this->summary($leads, $leadIds, $visibleUserIds),
            'filterOptions' => $this->filterOptions($visibleUserIds, $scope),
            'filters' => $filters,
        ];
    }

    private function baseLeadQuery(?array $visibleUserIds)
    {
        $query = Lead::query()->with([
            'activeAssignments.assignedTo',
            'formFieldValues',
            'prospects' => fn ($prospects) => $prospects->latest('updated_at'),
        ]);

        if ($visibleUserIds === null) {
            return $query;
        }

        return $query->where(function ($q) use ($visibleUserIds) {
                $q->whereIn('created_by', $visibleUserIds)
                    ->orWhereHas('activeAssignments', function ($assignment) use ($visibleUserIds) {
                        $assignment->whereIn('assigned_to', $visibleUserIds);
                    });
            });
    }

    private function visibilityScope(User $user): array
    {
        if ($user->isAdmin() || $user->isCrm()) {
            return [
                'mode' => 'all',
                'label' => 'All Team',
                'show_all_option' => true,
                'user_ids' => null,
            ];
        }

        if ($user->isAssistantSalesManager()) {
            return [
                'mode' => 'self',
                'label' => $user->name ?: 'My Leads',
                'show_all_option' => false,
                'user_ids' => [$user->id],
            ];
        }

        $ids = collect([$user->id]);

        if (method_exists($user, 'getAllTeamMemberIds')) {
            $ids = $ids->merge((array) $user->getAllTeamMemberIds());
        }

        return [
            'mode' => 'team',
            'label' => 'My Team',
            'show_all_option' => true,
            'user_ids' => $ids->filter()->unique()->values()->all(),
        ];
    }

    private function latestByLead(Collection $items, string $dateField): Collection
    {
        return $items
            ->sortByDesc(fn ($item) => optional($item->{$dateField} ?? $item->updated_at)->timestamp ?? 0)
            ->unique('lead_id')
            ->keyBy('lead_id');
    }

    private function resolveStage(Lead $lead, ?Task $task, ?Meeting $meeting, ?SiteVisit $visit): string
    {
        $status = $this->normalize($lead->status);

        if ($this->containsAny($status, ['junk', 'lost', 'not_interested', 'not interested', 'dead', 'cancelled', 'canceled', 'rejected'])) {
            return 'lost';
        }

        if ($this->containsAny($status, ['closed', 'booking_confirmed', 'approved'])) {
            return 'closed';
        }

        if ($this->containsAny($status, ['correction'])) {
            return 'correction';
        }

        if ($this->containsAny($status, ['closer', 'kyc', 'closing']) || $this->visitHasCloserWork($visit)) {
            return 'closer';
        }

        if ($this->isDone($visit?->status, $visit?->completed_at)) {
            return 'visit_done';
        }

        if ($this->isOpenSchedule($visit?->status)) {
            return 'visit_scheduled';
        }

        if ($this->isDone($meeting?->status, $meeting?->completed_at)) {
            return 'meeting_done';
        }

        if ($this->isOpenSchedule($meeting?->status)) {
            return 'meeting_scheduled';
        }

        $taskText = $this->normalize(implode(' ', [
            $task?->type,
            $task?->title,
            $task?->description,
            $task?->notes,
            $task?->outcome,
        ]));

        if ($this->isCnpLead($lead, $taskText)) {
            return 'cnp';
        }

        if ($this->containsAny($status, ['follow']) || $this->containsAny($taskText, ['follow'])) {
            return 'follow_up';
        }

        if ($this->leadTemperature($lead) || $this->containsAny($status, ['connected', 'interested', 'prospect', 'verified', 'hot', 'warm', 'cold'])) {
            return 'connected';
        }

        return 'fresh';
    }

    private function isCnpLead(Lead $lead, string $taskText): bool
    {
        if ((int) ($lead->cnp_count ?? 0) > 0) {
            return true;
        }

        return $this->containsAny($this->normalize($lead->status), ['cnp', 'call not picked'])
            || $this->containsAny($taskText, [
                'cnp',
                'call not picked',
                'not picked',
                'previous call not picked',
                'cnp retry',
                'cnp rescheduled',
            ]);
    }

    private function visitHasCloserWork(?SiteVisit $visit): bool
    {
        if (!$visit) {
            return false;
        }

        $combined = $this->normalize(implode(' ', [
            $visit->closer_status,
            $visit->closing_verification_status,
            $visit->booking_lifecycle_status,
        ]));

        return $this->containsAny($combined, ['submitted', 'pending', 'correction', 'verified', 'booking', 'approved']);
    }

    private function isDone(?string $status, $completedAt): bool
    {
        return !empty($completedAt) || $this->containsAny($this->normalize($status), ['completed', 'verified', 'visited', 'done']);
    }

    private function isOpenSchedule(?string $status): bool
    {
        return $this->containsAny($this->normalize($status), ['scheduled', 'pending', 'rescheduled', 'in_progress']);
    }

    private function card(Lead $lead, ?Task $task, ?Meeting $meeting, ?SiteVisit $visit, string $stage): array
    {
        $assignment = $lead->activeAssignments->first();
        $phone = preg_replace('/\D+/', '', (string) $lead->phone);
        $next = $task?->scheduled_at ?? $meeting?->scheduled_at ?? $visit?->scheduled_at;
        $temperature = $this->leadTemperature($lead);

        return [
            'id' => $lead->id,
            'name' => $lead->name ?: 'Unnamed lead',
            'phone' => $lead->phone,
            'source' => $lead->source ?: 'N/A',
            'status' => $lead->status ?: 'new',
            'temperature' => $temperature,
            'temperature_label' => $temperature ? ucfirst($temperature) : null,
            'cnp_type' => $stage === 'cnp' ? ($this->leadTemperature($lead) ? 'interested' : 'fresh') : null,
            'assigned' => $assignment?->assignedTo?->name ?: 'Unassigned',
            'project' => $this->projectLabel($lead),
            'next_action' => $next ? Carbon::parse($next)->format('d M, h:i A') : 'No next action',
            'show_url' => route('leads.show', ['lead' => $lead->id]),
            'tel_url' => $phone ? 'tel:' . $phone : null,
            'wa_url' => $phone ? 'https://wa.me/' . (str_starts_with($phone, '91') ? $phone : '91' . $phone) : null,
            'followup_url' => route('leads.show', ['lead' => $lead->id, 'action' => 'followup']),
            'meeting_url' => route('leads.show', ['lead' => $lead->id, 'action' => 'meeting']),
            'visit_url' => route('leads.show', ['lead' => $lead->id, 'action' => 'site_visit']),
        ];
    }

    private function leadTemperature(Lead $lead): ?string
    {
        $latestProspect = $lead->relationLoaded('prospects')
            ? $lead->prospects->sortByDesc('updated_at')->first()
            : $lead->prospects()->latest('updated_at')->first();

        $formLeadStatus = $lead->relationLoaded('formFieldValues')
            ? optional($lead->formFieldValues->firstWhere('field_key', 'lead_status'))->field_value
            : $lead->getFormFieldValue('lead_status');

        foreach ([$latestProspect?->lead_status, $formLeadStatus, $lead->status] as $value) {
            $status = $this->normalize($value);

            if ($this->containsAny($status, ['hot'])) {
                return 'hot';
            }

            if ($this->containsAny($status, ['warm'])) {
                return 'warm';
            }

            if ($this->containsAny($status, ['cold'])) {
                return 'cold';
            }
        }

        return null;
    }

    private function projectLabel(Lead $lead): string
    {
        $projects = $lead->preferred_projects;

        if (is_array($projects)) {
            return implode(', ', array_filter($projects)) ?: 'N/A';
        }

        return $projects ?: 'N/A';
    }

    private function summary(Collection $leads, Collection $leadIds, ?array $visibleUserIds): array
    {
        $today = now()->toDateString();
        $now = now();

        $todayFollowups = Task::query()
            ->when($visibleUserIds !== null, fn ($q) => $q->whereIn('assigned_to', $visibleUserIds))
            ->whereIn('status', Task::OPEN_STATUSES)
            ->where(function ($q) {
                $q->where('type', 'like', '%follow%')->orWhere('title', 'like', '%follow%');
            })
            ->where(function ($q) use ($today) {
                $q->whereDate('scheduled_at', $today)->orWhereDate('due_date', $today);
            })
            ->count();

        $overdueTasks = Task::query()
            ->when($visibleUserIds !== null, fn ($q) => $q->whereIn('assigned_to', $visibleUserIds))
            ->whereIn('status', Task::OPEN_STATUSES)
            ->where(function ($q) use ($now) {
                $q->where('scheduled_at', '<', $now)->orWhere('due_date', '<', $now);
            })
            ->count();

        $openTaskLeadIds = Task::query()->whereIn('lead_id', $leadIds)->whereIn('status', Task::OPEN_STATUSES)->pluck('lead_id');
        $openMeetingLeadIds = Meeting::query()->whereIn('lead_id', $leadIds)->whereNull('completed_at')->whereIn('status', ['scheduled', 'pending', 'rescheduled', 'in_progress'])->pluck('lead_id');
        $openVisitLeadIds = SiteVisit::query()->whereIn('lead_id', $leadIds)->whereNull('completed_at')->whereIn('status', ['scheduled', 'pending', 'rescheduled', 'in_progress'])->pluck('lead_id');
        $withNext = $openTaskLeadIds->merge($openMeetingLeadIds)->merge($openVisitLeadIds)->unique();

        return [
            'total' => $leads->count(),
            'today_followups' => $todayFollowups,
            'overdue_tasks' => $overdueTasks,
            'no_next_action' => $leads->whereNotIn('id', $withNext)->count(),
        ];
    }

    private function filterOptions(?array $visibleUserIds, array $scope): array
    {
        $leadQuery = $this->baseLeadQuery($visibleUserIds);
        $usersQuery = User::query();

        if ($visibleUserIds !== null) {
            $usersQuery->whereIn('id', $visibleUserIds);
        }

        return [
            'users' => $usersQuery->orderBy('name')->get(['id', 'name']),
            'scope' => $scope,
            'sources' => (clone $leadQuery)->whereNotNull('source')->distinct()->orderBy('source')->pluck('source')->filter()->values(),
            'statuses' => (clone $leadQuery)->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->filter()->values(),
        ];
    }

    private function dateRange(array $filters): ?array
    {
        $now = now();

        return match ($filters['date_filter']) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => [
                $filters['start_date'] ? Carbon::parse($filters['start_date'])->startOfDay() : $now->copy()->startOfMonth(),
                $filters['end_date'] ? Carbon::parse($filters['end_date'])->endOfDay() : $now->copy()->endOfMonth(),
            ],
            default => null,
        };
    }

    private function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
