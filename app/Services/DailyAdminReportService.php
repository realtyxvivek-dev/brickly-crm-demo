<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailyAdminReportService
{
    private const INTERESTED_STATUSES = [
        'interested',
        'connected',
        'verified_prospect',
        'meeting_scheduled',
        'visit_scheduled',
        'visit_done',
        'closed',
    ];

    private const HOT_STATUSES = [
        'meeting_scheduled',
        'visit_scheduled',
        'visit_done',
        'closed',
    ];

    private const WARM_STATUSES = [
        'interested',
        'connected',
        'verified_prospect',
    ];

    public const VALID_RANGES = [
        'today',
        'previous_day',
        'this_week',
        'previous_week',
        'this_month',
        'previous_month',
        'all_time',
    ];

    public function build(Carbon|string|null $date = null): array
    {
        $day = $date instanceof Carbon ? $date->copy() : Carbon::parse($date ?: today());
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        return $this->buildBetween($start, $end, $day, 'today', $day->format('d M Y'));
    }

    public function buildRange(string $range = 'today', Carbon|string|null $anchorDate = null): array
    {
        $anchor = $anchorDate instanceof Carbon ? $anchorDate->copy() : Carbon::parse($anchorDate ?: today());
        $range = in_array($range, self::VALID_RANGES, true) ? $range : 'today';

        if ($range === 'previous_day') {
            $day = $anchor->copy()->subDay();
            return $this->buildBetween($day->copy()->startOfDay(), $day->copy()->endOfDay(), $day, $range, 'Previous Day - ' . $day->format('d M Y'));
        }

        if ($range === 'this_week') {
            return $this->buildBetween($anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek(), $anchor, $range, 'This Week - ' . $anchor->copy()->startOfWeek()->format('d M') . ' to ' . $anchor->copy()->endOfWeek()->format('d M Y'));
        }

        if ($range === 'previous_week') {
            $week = $anchor->copy()->subWeek();
            return $this->buildBetween($week->copy()->startOfWeek(), $week->copy()->endOfWeek(), $week, $range, 'Previous Week - ' . $week->copy()->startOfWeek()->format('d M') . ' to ' . $week->copy()->endOfWeek()->format('d M Y'));
        }

        if ($range === 'this_month') {
            return $this->buildBetween($anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth(), $anchor, $range, $anchor->format('M Y'));
        }

        if ($range === 'previous_month') {
            $month = $anchor->copy()->subMonthNoOverflow();
            return $this->buildBetween($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $month, $range, 'Previous Month - ' . $month->format('M Y'));
        }

        if ($range === 'all_time') {
            return $this->buildBetween(Carbon::create(2000, 1, 1)->startOfDay(), now()->endOfDay(), $anchor, $range, 'All Time');
        }

        return $this->build($anchor);
    }

    public function buildBetween(Carbon $start, Carbon $end, Carbon $date, string $range = 'today', ?string $rangeLabel = null): array
    {
        $day = $date->copy();

        $leadBase = Lead::query()
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($leadQuery) {
                $leadQuery->where('is_hiring_candidate', false)
                    ->orWhereNull('is_hiring_candidate');
            });
        $meetingBase = Meeting::withQueueHidden();
        $visitBase = SiteVisit::withQueueHidden();
        $followUpBase = FollowUp::withQueueHidden();

        $leadStatus = $this->leadStatusBreakdown(clone $leadBase);
        $leadSources = $this->leadSourceBreakdown(clone $leadBase);
        $meetings = $this->meetingSummary(clone $meetingBase, $start, $end);
        $visits = $this->visitSummary(clone $visitBase, $start, $end);
        $followUps = $this->followUpSummary(clone $followUpBase, $start, $end);
        $users = $this->userLeadSummary($start, $end);
        $leadDisplayStatus = $this->leadDisplayStatusBreakdown(clone $leadBase, $leadStatus);
        $leadIntake = $this->leadIntakeSummary(clone $leadBase, $leadStatus, $leadDisplayStatus);
        $interestedBreakdown = $this->interestedBreakdown($leadStatus);
        $leadQuality = $this->leadQualitySummary($leadStatus, $leadIntake);
        $activitySummary = [
            'follow_ups' => $followUps['scheduled'],
            'meetings_scheduled' => $meetings['scheduled'],
            'meetings_completed' => $meetings['verified'],
            'visits_scheduled' => $visits['scheduled'],
            'visits_completed' => $visits['verified'],
        ];

        return [
            'date' => $day,
            'range' => $range,
            'range_label' => $rangeLabel ?: $day->format('d M Y'),
            'start_date' => $start,
            'end_date' => $end,
            'generated_at' => now(),
            'summary' => [
                'total_leads' => (clone $leadBase)->count(),
                'interested' => $leadIntake['interested'],
                'not_interested' => $leadIntake['not_interested'],
                'cnp' => $leadIntake['cnp'],
                'follow_up' => $followUps['scheduled'],
                'high_budget_clients' => $leadIntake['high_budget_clients'],
                'meetings_scheduled' => $meetings['scheduled'],
                'meetings_completed' => $meetings['verified'],
                'visits_scheduled' => $visits['scheduled'],
                'visits_completed' => $visits['verified'],
            ],
            'lead_intake' => $leadIntake,
            'interested_breakdown' => $interestedBreakdown,
            'lead_quality' => $leadQuality,
            'activity_summary' => $activitySummary,
            'lead_status' => $leadDisplayStatus->sortByDesc('count')->values()->all(),
            'lead_sources' => $leadSources->values()->all(),
            'meetings' => $meetings,
            'visits' => $visits,
            'follow_ups' => $followUps,
            'user_rows' => $users->values()->all(),
            'alerts' => [
                'no_remark_leads' => (clone $leadBase)
                    ->whereNull('next_followup_at')
                    ->whereDoesntHave('followUps')
                    ->count(),
                'missed_meetings' => $meetings['missed'],
                'missed_visits' => $visits['missed'],
                'pending_verifications' => $meetings['pending_verification'] + $visits['pending_verification'],
            ],
        ];
    }

    private function leadStatusBreakdown($query): Collection
    {
        return $query
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'key' => $row->status ?: 'new',
                'label' => str($row->status ?: 'new')->replace('_', ' ')->title()->toString(),
                'count' => (int) $row->total,
            ]);
    }

    private function leadIntakeSummary($query, Collection $leadStatus, Collection $leadDisplayStatus): array
    {
        $total = (clone $query)->count();
        $statusCount = fn (string $key) => (int) ($leadStatus->firstWhere('key', $key)['count'] ?? 0);
        $displayStatusCount = fn (string $key) => (int) ($leadDisplayStatus->firstWhere('key', $key)['count'] ?? 0);

        $intake = [
            'total' => $total,
            'interested' => self::sumStatusCounts($leadStatus, self::INTERESTED_STATUSES),
            'not_interested' => $statusCount('not_interested'),
            'cnp' => $displayStatusCount('cnp'),
            'junk' => $statusCount('junk'),
            'new' => $displayStatusCount('new'),
            'fresh_transfer' => $statusCount('fresh_transfer'),
            'high_budget_clients' => $this->highBudgetLeadCount(clone $query),
        ];

        $intake['other'] = max(0, $total - $intake['interested'] - $intake['not_interested'] - $intake['cnp'] - $intake['junk'] - $intake['new'] - $intake['fresh_transfer']);

        return $intake;
    }

    private function leadDisplayStatusBreakdown($query, Collection $leadStatus): Collection
    {
        $counts = $leadStatus
            ->mapWithKeys(fn (array $row) => [$row['key'] => (int) $row['count']])
            ->all();

        $newLeads = (clone $query)
            ->where(function ($inner) {
                $inner->where('status', 'new')->orWhereNull('status')->orWhere('status', '');
            })
            ->with('prospects:id,lead_id,verification_status,created_at')
            ->get(['id', 'status', 'cnp_count', 'next_followup_at']);

        $newLeadIds = $newLeads->pluck('id')->filter()->values();
        $taskColumns = ['id', 'lead_id', 'title', 'description', 'notes', 'outcome'];
        if (Schema::hasColumn('tasks', 'meeting_id')) {
            $taskColumns[] = 'meeting_id';
        }
        if (Schema::hasColumn('tasks', 'site_visit_id')) {
            $taskColumns[] = 'site_visit_id';
        }

        $tasksByLead = $newLeadIds->isEmpty()
            ? collect()
            : Task::query()
                ->whereIn('lead_id', $newLeadIds)
                ->where('type', 'phone_call')
                ->orderByDesc('id')
                ->get($taskColumns)
                ->groupBy('lead_id');

        foreach ($newLeads as $lead) {
            $displayStatus = $this->resolveDisplayStatusForNewLead($lead, $tasksByLead->get($lead->id, collect()));
            if ($displayStatus === 'new') {
                continue;
            }

            $counts['new'] = max(0, (int) ($counts['new'] ?? 0) - 1);
            $counts[$displayStatus] = (int) ($counts[$displayStatus] ?? 0) + 1;
        }

        return collect($counts)
            ->map(fn (int $count, string $key) => [
                'key' => $key,
                'label' => str($key)->replace('_', ' ')->title()->toString(),
                'count' => $count,
            ])
            ->values();
    }

    private function resolveDisplayStatusForNewLead(Lead $lead, Collection $tasks): string
    {
        if ((int) ($lead->cnp_count ?? 0) > 0) {
            return 'cnp';
        }

        if (!empty($lead->next_followup_at)) {
            return 'follow_up';
        }

        $latestRelevantTask = $tasks->first(function (Task $task) use ($lead) {
            $taskText = strtolower(trim(
                ($task->title ?? '') . ' ' .
                ($task->description ?? '') . ' ' .
                ($task->notes ?? '')
            ));

            $isCnpRetryTask = str_contains($taskText, 'cnp retry task created')
                || str_contains($taskText, 'cnp rescheduled')
                || str_contains($taskText, 'previous call not picked');

            if ($isCnpRetryTask) {
                return true;
            }

            $taskCategory = $this->determineTaskCategory($task, $lead);

            return ($task->outcome === 'cnp' && $taskCategory === 'fresh_lead')
                || ($task->outcome === 'follow_up' && $taskCategory === 'fresh_lead');
        });

        if (!$latestRelevantTask) {
            return 'new';
        }

        return $latestRelevantTask->outcome === 'follow_up' ? 'follow_up' : 'cnp';
    }

    private function determineTaskCategory(Task $task, Lead $lead): string
    {
        $prospect = $lead->prospects->sortByDesc('created_at')->first();
        $hasPendingProspect = $prospect && in_array($prospect->verification_status ?? '', ['pending', 'pending_verification'], true);

        $taskText = strtolower(trim(
            ($task->title ?? '') . ' ' .
            ($task->description ?? '') . ' ' .
            ($task->notes ?? '')
        ));

        $isFollowUpTask = str_contains($taskText, 'follow-up call')
            || str_contains($taskText, 'follow up call')
            || str_contains($taskText, 'follow-up scheduled');
        $isCnpRetryTask = str_contains($taskText, 'cnp retry task created')
            || str_contains($taskText, 'cnp rescheduled')
            || str_contains($taskText, 'previous call not picked');
        $isCloserTask = str_contains($taskText, 'closer');
        $isSiteVisitTask = $task->getAttribute('site_visit_id') !== null
            || str_contains($taskText, 'site visit')
            || str_contains($taskText, 'site-visit');
        $isMeetingTask = $task->getAttribute('meeting_id') !== null
            || str_contains($taskText, 'meeting id')
            || str_contains($taskText, 'pre-meeting')
            || (str_contains($taskText, 'meeting') && !$isSiteVisitTask);
        $isProspectTask = !$isFollowUpTask && !$isCnpRetryTask && $hasPendingProspect;
        $isFreshLeadTask = !$isFollowUpTask
            && !$isCnpRetryTask
            && !$isCloserTask
            && !$isSiteVisitTask
            && !$isMeetingTask
            && !$isProspectTask;

        if ($isFreshLeadTask) {
            return 'fresh_lead';
        }
        if ($isFollowUpTask) {
            return 'follow_up';
        }
        if ($isCloserTask) {
            return 'closer';
        }
        if ($isSiteVisitTask) {
            return 'site_visit';
        }
        if ($isMeetingTask) {
            return 'meeting';
        }
        if ($isProspectTask) {
            return 'prospect';
        }

        return 'other';
    }

    private static function sumStatusCounts(Collection $leadStatus, array $keys): int
    {
        return (int) $leadStatus
            ->whereIn('key', $keys)
            ->sum('count');
    }

    private function interestedBreakdown(Collection $leadStatus): array
    {
        return [
            ['key' => 'connected', 'label' => 'Connected', 'count' => $this->statusCount($leadStatus, 'connected')],
            ['key' => 'verified_prospect', 'label' => 'Verified Prospect', 'count' => $this->statusCount($leadStatus, 'verified_prospect')],
            ['key' => 'meeting_scheduled', 'label' => 'Meeting Scheduled', 'count' => $this->statusCount($leadStatus, 'meeting_scheduled')],
            ['key' => 'visit_scheduled', 'label' => 'Visit Scheduled', 'count' => $this->statusCount($leadStatus, 'visit_scheduled')],
            ['key' => 'visit_done', 'label' => 'Visit Done', 'count' => $this->statusCount($leadStatus, 'visit_done')],
            ['key' => 'closed', 'label' => 'Closed', 'count' => $this->statusCount($leadStatus, 'closed')],
        ];
    }

    private function leadQualitySummary(Collection $leadStatus, array $leadIntake): array
    {
        $hot = self::sumStatusCounts($leadStatus, self::HOT_STATUSES);
        $warm = self::sumStatusCounts($leadStatus, self::WARM_STATUSES);
        $cold = (int) ($leadIntake['not_interested'] ?? 0) + (int) ($leadIntake['junk'] ?? 0) + (int) ($leadIntake['cnp'] ?? 0);
        $pending = (int) ($leadIntake['new'] ?? 0) + (int) ($leadIntake['fresh_transfer'] ?? 0) + (int) ($leadIntake['other'] ?? 0);

        return [
            ['key' => 'hot', 'label' => 'Hot', 'count' => $hot, 'note' => 'Meeting / visit / close stage'],
            ['key' => 'warm', 'label' => 'Warm', 'count' => $warm, 'note' => 'Connected / verified prospect'],
            ['key' => 'cold', 'label' => 'Cold', 'count' => $cold, 'note' => 'Not interested / junk / CNP'],
            ['key' => 'pending', 'label' => 'Pending Bucket', 'count' => $pending, 'note' => 'New + fresh transfer + other'],
        ];
    }

    private function statusCount(Collection $leadStatus, string $key): int
    {
        return (int) ($leadStatus->firstWhere('key', $key)['count'] ?? 0);
    }

    private function leadSourceBreakdown($query): Collection
    {
        return $query
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'key' => $row->source ?: 'other',
                'label' => Lead::displaySourceLabel($row->source),
                'count' => (int) $row->total,
            ]);
    }

    private function meetingSummary($query, Carbon $start, Carbon $end): array
    {
        $scheduled = $this->openScheduledWorkflowQuery(clone $query, $start, $end, ['scheduled'])->count();
        $completed = (clone $query)->whereBetween('completed_at', [$start, $end])->count();
        $verified = $this->verifiedWorkflowQuery(clone $query, $start, $end)->count();
        $pendingVerification = (clone $query)->whereBetween('completed_at', [$start, $end])->where('verification_status', 'pending')->count();
        $cancelled = (clone $query)->whereBetween('scheduled_at', [$start, $end])->where('status', 'cancelled')->count();
        $missed = (clone $query)->whereBetween('scheduled_at', [$start, $end])->where('status', 'scheduled')->whereNull('completed_at')->count();

        return [
            'scheduled' => $scheduled,
            'completed' => $completed,
            'verified' => $verified,
            'pending_verification' => $pendingVerification,
            'cancelled' => $cancelled,
            'missed' => $missed,
            'completion_rate' => $scheduled > 0 ? round(($completed / $scheduled) * 100, 1) : 0,
            'by_user' => $this->workflowActivityByUser(Meeting::withQueueHidden(), $start, $end, ['scheduled']),
        ];
    }

    private function visitSummary($query, Carbon $start, Carbon $end): array
    {
        $scheduled = $this->openScheduledWorkflowQuery(clone $query, $start, $end, ['scheduled', 'in_progress', 'rescheduled'])->count();
        $completed = (clone $query)->whereBetween('completed_at', [$start, $end])->count();
        $verified = $this->verifiedWorkflowQuery(clone $query, $start, $end)->count();
        $pendingVerification = (clone $query)->whereBetween('completed_at', [$start, $end])->where('verification_status', 'pending')->count();
        $cancelled = (clone $query)->whereBetween('scheduled_at', [$start, $end])->where('status', 'cancelled')->count();
        $missed = (clone $query)->whereBetween('scheduled_at', [$start, $end])->where('status', 'scheduled')->whereNull('completed_at')->count();

        return [
            'scheduled' => $scheduled,
            'completed' => $completed,
            'verified' => $verified,
            'pending_verification' => $pendingVerification,
            'cancelled' => $cancelled,
            'missed' => $missed,
            'completion_rate' => $scheduled > 0 ? round(($completed / $scheduled) * 100, 1) : 0,
            'by_user' => $this->workflowActivityByUser(SiteVisit::withQueueHidden(), $start, $end, ['scheduled', 'in_progress', 'rescheduled']),
        ];
    }

    private function followUpSummary($query, Carbon $start, Carbon $end): array
    {
        $scheduled = (clone $query)->whereBetween('scheduled_at', [$start, $end])->count();
        $completed = (clone $query)->whereBetween('completed_at', [$start, $end])->count();
        $pending = (clone $query)->whereBetween('scheduled_at', [$start, $end])->where('status', 'scheduled')->whereNull('completed_at')->count();

        return [
            'scheduled' => $scheduled,
            'completed' => $completed,
            'pending' => $pending,
            'completion_rate' => $scheduled > 0 ? round(($completed / $scheduled) * 100, 1) : 0,
        ];
    }

    private function userLeadSummary(Carbon $start, Carbon $end): Collection
    {
        $assignedCounts = LeadAssignment::query()
            ->whereBetween('assigned_at', [$start, $end])
            ->select('assigned_to', DB::raw('COUNT(*) as assigned_count'))
            ->groupBy('assigned_to')
            ->pluck('assigned_count', 'assigned_to')
            ->map(fn ($count) => (int) $count);

        $followUpCounts = FollowUp::withQueueHidden()
            ->whereBetween('scheduled_at', [$start, $end])
            ->select('created_by', DB::raw('COUNT(*) as total'))
            ->groupBy('created_by')
            ->pluck('total', 'created_by')
            ->map(fn ($count) => (int) $count);

        $meetingScheduledCounts = $this->openScheduledWorkflowQuery(Meeting::withQueueHidden(), $start, $end, ['scheduled'])
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($count) => (int) $count);

        $meetingVerifiedCounts = $this->verifiedWorkflowQuery(Meeting::withQueueHidden(), $start, $end)
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($count) => (int) $count);

        $visitScheduledCounts = $this->openScheduledWorkflowQuery(SiteVisit::withQueueHidden(), $start, $end, ['scheduled', 'in_progress', 'rescheduled'])
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($count) => (int) $count);

        $visitVerifiedCounts = $this->verifiedWorkflowQuery(SiteVisit::withQueueHidden(), $start, $end)
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($count) => (int) $count);

        $userIds = collect()
            ->merge($assignedCounts->keys())
            ->merge($followUpCounts->keys())
            ->merge($meetingScheduledCounts->keys())
            ->merge($meetingVerifiedCounts->keys())
            ->merge($visitScheduledCounts->keys())
            ->merge($visitVerifiedCounts->keys())
            ->filter(fn ($userId) => is_numeric($userId) && (int) $userId > 0)
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values();

        $names = $userIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        return $userIds
            ->map(function (int $userId) use ($names, $assignedCounts, $followUpCounts, $meetingScheduledCounts, $meetingVerifiedCounts, $visitScheduledCounts, $visitVerifiedCounts) {
                $row = [
                    'user' => $names[$userId] ?? 'Unknown',
                    'assigned' => (int) ($assignedCounts[$userId] ?? 0),
                    'followups' => (int) ($followUpCounts[$userId] ?? 0),
                    'meetings' => (int) ($meetingScheduledCounts[$userId] ?? 0),
                    'meeting_scheduled' => (int) ($meetingScheduledCounts[$userId] ?? 0),
                    'meeting_verified' => (int) ($meetingVerifiedCounts[$userId] ?? 0),
                    'visits' => (int) ($visitScheduledCounts[$userId] ?? 0),
                    'visit_scheduled' => (int) ($visitScheduledCounts[$userId] ?? 0),
                    'visit_verified' => (int) ($visitVerifiedCounts[$userId] ?? 0),
                ];
                $row['execution_total'] = $row['followups'] + $row['meeting_scheduled'] + $row['meeting_verified'] + $row['visit_scheduled'] + $row['visit_verified'];
                $row['activity_total'] = $row['assigned'] + $row['execution_total'];

                return $row;
            })
            ->filter(fn (array $row) => $row['activity_total'] > 0)
            ->sort(fn (array $left, array $right) => [$right['execution_total'], $right['assigned']] <=> [$left['execution_total'], $left['assigned']])
            ->take(10)
            ->values();
    }

    private function openScheduledWorkflowQuery($query, Carbon $start, Carbon $end, array $statuses)
    {
        return $query
            ->whereIn('status', $statuses)
            ->whereNull('completed_at')
            ->whereBetween('scheduled_at', [$start, $end]);
    }

    private function verifiedWorkflowQuery($query, Carbon $start, Carbon $end)
    {
        return $query
            ->where('status', 'completed')
            ->where('verification_status', 'verified')
            ->where(function ($dateQuery) use ($start, $end) {
                $dateQuery->whereBetween('verified_at', [$start, $end])
                    ->orWhere(function ($fallbackQuery) use ($start, $end) {
                        $fallbackQuery->whereNull('verified_at')
                            ->whereBetween('completed_at', [$start, $end]);
                    });
            });
    }

    private function workflowActivityByUser($query, Carbon $start, Carbon $end, array $statuses): array
    {
        return $this->openScheduledWorkflowQuery($query, $start, $end, $statuses)
            ->with('assignedTo:id,name')
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'user' => $row->assignedTo?->name ?? 'Unassigned',
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function activityByUser($query, Carbon $start, Carbon $end, string $dateColumn): array
    {
        return $query
            ->with('assignedTo:id,name')
            ->whereBetween($dateColumn, [$start, $end])
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'user' => $row->assignedTo?->name ?? 'Unassigned',
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function highBudgetLeadCount($query): int
    {
        return $query
            ->with([
                'formFieldValues' => fn ($fieldQuery) => $fieldQuery->where('field_key', 'budget'),
                'prospects:id,lead_id,budget',
                'meetings:id,lead_id,budget_range',
                'siteVisits:id,lead_id,budget_range',
            ])
            ->get(['id', 'budget', 'budget_min', 'budget_max'])
            ->filter(fn (Lead $lead) => $this->leadHasAboveTwoCroreBudget($lead))
            ->count();
    }

    private function leadHasAboveTwoCroreBudget(Lead $lead): bool
    {
        if ($this->isAboveTwoCrore($this->resolveLeadBudget($lead), $lead->budget_min, $lead->budget_max)) {
            return true;
        }

        foreach ($lead->prospects ?? [] as $prospect) {
            if ($this->isAboveTwoCrore($prospect->budget ?? '')) {
                return true;
            }
        }

        foreach ($lead->meetings ?? [] as $meeting) {
            if ($this->isAboveTwoCrore($meeting->budget_range ?? '')) {
                return true;
            }
        }

        foreach ($lead->siteVisits ?? [] as $visit) {
            if ($this->isAboveTwoCrore($visit->budget_range ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function resolveLeadBudget(Lead $lead): string
    {
        $direct = trim((string) ($lead->budget ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $field = $lead->formFieldValues?->firstWhere('field_key', 'budget');

        return $field && trim((string) $field->field_value) !== ''
            ? trim((string) $field->field_value)
            : '';
    }

    private function isAboveTwoCrore(?string $budget, mixed $min = null, mixed $max = null): bool
    {
        $threshold = 20000000;

        if ($this->numericAmount($max) >= $threshold || $this->numericAmount($min) >= $threshold) {
            return true;
        }

        $normalized = strtolower(trim((string) $budget));
        if ($normalized === '') {
            return false;
        }

        $normalized = str_replace(['â€“', 'â€”', 'Ã¢â‚¬â€œ', 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“'], '-', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        if (preg_match('/above\s*([0-9]+(?:\.[0-9]+)?)\s*(cr|crore)/', $normalized, $matches)) {
            return (float) $matches[1] >= 2.0;
        }

        if (str_contains($normalized, 'above 2 cr') || str_contains($normalized, 'above 2 crore') || str_contains($normalized, 'above 3 cr')) {
            return true;
        }

        $amounts = $this->textAmounts($budget);
        if ($amounts->count() >= 2) {
            return (float) $amounts->first() >= $threshold || (float) $amounts->last() > $threshold;
        }

        if ($amounts->count() === 1) {
            return (float) $amounts->first() >= $threshold;
        }

        return $this->numericAmount($budget) >= $threshold;
    }

    private function numericAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = strtolower((string) $value);
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(cr|crore|lac|lakh)/', $text, $matches)) {
            $amount = (float) $matches[1];
            $unit = $matches[2];

            return ($unit === 'cr' || $unit === 'crore')
                ? $amount * 10000000
                : $amount * 100000;
        }

        $clean = preg_replace('/[^0-9.]/', '', $text);

        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    private function textAmounts(mixed $value): Collection
    {
        $text = strtolower((string) $value);
        if ($text === '') {
            return collect();
        }

        preg_match_all('/([0-9]+(?:\.[0-9]+)?)\s*(cr|crore|lac|lakh)/', $text, $matches, PREG_SET_ORDER);

        return collect($matches)->map(function (array $match) {
            $amount = (float) $match[1];
            $unit = $match[2];

            return ($unit === 'cr' || $unit === 'crore')
                ? $amount * 10000000
                : $amount * 100000;
        });
    }
}
