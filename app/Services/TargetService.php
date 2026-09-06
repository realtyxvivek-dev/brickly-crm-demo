<?php

namespace App\Services;

use App\Models\Target;
use App\Models\User;
use App\Models\Prospect;
use App\Models\Role;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TargetService
{
    /**
     * Set or update targets for a user for a specific month
     */
    public function setTargetsForUser(int $userId, string $month, array $targets): Target
    {
        // Parse month (format: YYYY-MM)
        $targetMonth = Carbon::parse($month . '-01')->startOfMonth();

        return Target::updateOrCreate(
            [
                'user_id' => $userId,
                'target_month' => $targetMonth,
            ],
            [
                'target_visits' => $targets['target_visits'] ?? 0,
                'target_meetings' => $targets['target_meetings'] ?? 0,
                'target_closers' => $targets['target_closers'] ?? 0,
                'target_prospects_extract' => $targets['target_prospects_extract'] ?? 0,
                'target_prospects_verified' => $targets['target_prospects_verified'] ?? 0,
                'target_calls' => $targets['target_calls'] ?? 0,
                'manager_target_calculation_logic' => $targets['manager_target_calculation_logic'] ?? null,
                'manager_junior_scope' => $targets['manager_junior_scope'] ?? null,
                'incentive_per_closer' => $targets['incentive_per_closer'] ?? null,
                'incentive_per_visit' => $targets['incentive_per_visit'] ?? null,
            ]
        );
    }

    /**
     * Get target progress for a user for a specific month
     */
    public function getTargetProgress(int $userId, ?string $month = null): ?array
    {
        if (!$month) {
            $month = now()->format('Y-m');
        }

        $targetMonth = Carbon::parse($month . '-01')->startOfMonth();
        
        $target = Target::where('user_id', $userId)
            ->where('target_month', $targetMonth)
            ->first();

        if (!$target) {
            return null;
        }

        return [
            'target' => $target,
            'progress' => $target->getProgressData(),
        ];
    }

    /**
     * Get all users with targets for a specific month
     */
    public function getUsersWithTargets(string $month): array
    {
        $targetMonth = Carbon::parse($month . '-01')->startOfMonth();

        $targets = Target::where('target_month', $targetMonth)
            ->with('user.role')
            ->get();

        $result = [];
        foreach ($targets as $target) {
            $result[] = [
                'target' => $target,
                'user' => $target->user,
                'progress' => $target->getProgressData(),
            ];
        }

        return $result;
    }

    /**
     * Get team targets progress for a manager
     */
    public function getTeamTargetsProgress(int $managerId, ?string $month = null): array
    {
        if (!$month) {
            $month = now()->format('Y-m');
        }

        $targetMonth = Carbon::parse($month . '-01')->startOfMonth();

        // Get all team members (sales executives under this manager)
        $teamMembers = User::where('manager_id', $managerId)
            ->whereHas('role', function($q) {
                $q->where('slug', 'sales_executive');
            })
            ->pluck('id');

        $targets = Target::whereIn('user_id', $teamMembers)
            ->where('target_month', $targetMonth)
            ->with(['user.role'])
            ->get();

        // Calculate team totals
        $teamMeetingsTarget = $targets->sum('target_meetings');
        $teamVisitsTarget = $targets->sum('target_visits');
        $teamClosersTarget = $targets->sum('target_closers');

        $teamMeetingsAchieved = 0;
        $teamVisitsAchieved = 0;
        $teamClosersAchieved = 0;

        foreach ($targets as $target) {
            $meetingsProgress = $target->getAchievementProgress('meetings');
            $visitsProgress = $target->getAchievementProgress('visits');
            $closersProgress = $target->getAchievementProgress('closers');

            $teamMeetingsAchieved += $meetingsProgress['achieved'];
            $teamVisitsAchieved += $visitsProgress['achieved'];
            $teamClosersAchieved += $closersProgress['achieved'];
        }

        $teamMeetingsPercentage = $teamMeetingsTarget > 0 ? min(100, round(($teamMeetingsAchieved / $teamMeetingsTarget) * 100, 2)) : 0;
        $teamVisitsPercentage = $teamVisitsTarget > 0 ? min(100, round(($teamVisitsAchieved / $teamVisitsTarget) * 100, 2)) : 0;
        $teamClosersPercentage = $teamClosersTarget > 0 ? min(100, round(($teamClosersAchieved / $teamClosersTarget) * 100, 2)) : 0;

        // Build individual team member data
        $teamMembersData = [];
        foreach ($targets as $target) {
            if (!$target->user) {
                continue; // Skip if user not found
            }
            
            // Ensure role is loaded
            if (!$target->user->relationLoaded('role')) {
                $target->user->load('role');
            }
            
            $meetingsProgress = $target->getAchievementProgress('meetings');
            $visitsProgress = $target->getAchievementProgress('visits');
            $closersProgress = $target->getAchievementProgress('closers');
            
            $teamMembersData[] = [
                'user_id' => $target->user_id,
                'user_name' => $target->user->name ?? 'N/A',
                'user_role' => $target->user->role->slug ?? 'N/A',
                'user_role_name' => $target->user->role->name ?? 'N/A',
                'targets' => [
                    'meetings' => $meetingsProgress,
                    'visits' => $visitsProgress,
                    'closers' => $closersProgress,
                ],
            ];
        }

        // Return structured data
        return [
            'team_totals' => [
                'meetings' => [
                    'target' => $teamMeetingsTarget,
                    'achieved' => $teamMeetingsAchieved,
                    'percentage' => $teamMeetingsPercentage,
                ],
                'visits' => [
                    'target' => $teamVisitsTarget,
                    'achieved' => $teamVisitsAchieved,
                    'percentage' => $teamVisitsPercentage,
                ],
                'closers' => [
                    'target' => $teamClosersTarget,
                    'achieved' => $teamClosersAchieved,
                    'percentage' => $teamClosersPercentage,
                ],
            ],
            'team_members' => $teamMembersData,
        ];
    }

    /**
     * Get system-wide target overview for admin/CRM
     */
    public function getSystemOverview(?string $month = null): array
    {
        if (!$month) {
            $month = now()->format('Y-m');
        }

        $targetMonth = Carbon::parse($month . '-01')->startOfMonth();

        $targets = Target::where('target_month', $targetMonth)
            ->with('user.role')
            ->get();

        $salesUsers = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SALES_EXECUTIVE,
            ]))
            ->orderBy('name')
            ->get();
        $userIds = $targets->pluck('user_id')
            ->merge($salesUsers->pluck('id'))
            ->filter()
            ->unique()
            ->values();
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();
        $actualsByUser = $this->systemOverviewActuals($userIds, $startOfMonth, $endOfMonth);
        $targetsByUser = $targets->keyBy('user_id');

        $totalTargets = [
            'prospects_extract' => 0,
            'prospects_verified' => 0,
            'calls' => 0,
        ];

        $totalActuals = [
            'prospects_extract' => 0,
            'prospects_verified' => 0,
            'calls' => 0,
        ];

        $details = $targets->map(function (Target $target) use ($actualsByUser, $targetsByUser, &$totalTargets, &$totalActuals) {
            $progress = $this->bulkProgressForTarget($target, $actualsByUser, $targetsByUser);

            $totalTargets['prospects_extract'] += $target->target_prospects_extract;
            $totalTargets['prospects_verified'] += $target->target_prospects_verified;
            $totalTargets['calls'] += $target->target_calls;

            $totalActuals['prospects_extract'] += $progress['prospects_extract']['actual'];
            $totalActuals['prospects_verified'] += $progress['prospects_verified']['actual'];
            $totalActuals['calls'] += $progress['calls']['actual'];

            return [
                'user' => ($target->user && $target->user->name) ? $target->user->name : 'Unknown',
                'progress' => $progress,
            ];
        });

        $percentages = [];
        foreach ($totalTargets as $key => $targetValue) {
            $percentages[$key] = $targetValue > 0 
                ? round(($totalActuals[$key] / $targetValue) * 100, 2) 
                : 0;
        }

        $achievementBreakdown = $salesUsers->map(function (User $user) use ($targetsByUser, $targetMonth, $actualsByUser) {
            $target = $targetsByUser->get($user->id) ?: new Target([
                'user_id' => $user->id,
                'target_month' => $targetMonth,
                'target_meetings' => 0,
                'target_visits' => 0,
                'target_closers' => 0,
            ]);
            $target->setRelation('user', $user);
            $progress = $this->bulkProgressForTarget($target, $actualsByUser, $targetsByUser);

            return [
                'user_name' => $user->name ?? 'Unknown',
                'role' => $user->role?->name ?? 'Unknown',
                'meetings' => $progress['meetings'],
                'visits' => $progress['visits'],
                'closers' => $progress['closers'],
            ];
        })->values();

        return [
            'month' => $month,
            'total_users' => $targets->count(),
            'targets' => $totalTargets,
            'actuals' => $totalActuals,
            'percentages' => $percentages,
            'details' => $details,
            'achievement_breakdown' => $achievementBreakdown,
        ];
    }

    private function systemOverviewActuals($userIds, Carbon $start, Carbon $end): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        $maps = [
            'prospects_extract' => Prospect::query()
                ->whereIn('telecaller_id', $userIds)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('telecaller_id as user_id, COUNT(*) as total')
                ->groupBy('telecaller_id')
                ->pluck('total', 'user_id'),
            'prospects_verified' => Prospect::query()
                ->whereIn('telecaller_id', $userIds)
                ->where('verification_status', 'verified')
                ->whereBetween('verified_at', [$start, $end])
                ->selectRaw('telecaller_id as user_id, COUNT(*) as total')
                ->groupBy('telecaller_id')
                ->pluck('total', 'user_id'),
            'calls' => DB::table('telecaller_tasks')
                ->whereIn('assigned_to', $userIds)
                ->where('task_type', 'calling')
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->whereBetween('completed_at', [$start, $end])
                ->selectRaw('assigned_to as user_id, COUNT(*) as total')
                ->groupBy('assigned_to')
                ->pluck('total', 'user_id'),
        ];

        $meetingBase = DB::table('meetings')
            ->where('meetings.status', 'completed')
            ->where('meetings.is_converted', false)
            ->whereNotNull('meetings.completed_at')
            ->whereNull('meetings.deleted_at')
            ->whereBetween('meetings.completed_at', [$start, $end])
            ->where(fn ($query) => $query->whereNull('meetings.verification_status')->orWhere('meetings.verification_status', '!=', 'rejected'));
        if (Schema::hasColumn('meetings', 'is_rescheduled')) {
            $meetingBase->where('meetings.is_rescheduled', false);
        }
        $maps['meetings'] = $this->workflowCountsByUser($meetingBase, 'meetings', $userIds);

        $visitBase = DB::table('site_visits')
            ->where('site_visits.verification_status', 'verified')
            ->whereNotNull('site_visits.verified_at')
            ->whereNull('site_visits.deleted_at')
            ->whereBetween('site_visits.verified_at', [$start, $end]);
        if (Schema::hasColumn('site_visits', 'is_rescheduled')) {
            $visitBase->where('site_visits.is_rescheduled', false);
        }
        $maps['visits'] = $this->workflowCountsByUser($visitBase, 'site_visits', $userIds);

        $closerBase = DB::table('site_visits')
            ->whereIn('site_visits.closer_status', ['approved', 'verified'])
            ->whereNull('site_visits.deleted_at');
        if (Schema::hasColumn('site_visits', 'actual_closer_date')) {
            $closerBase->where(function ($query) use ($start, $end) {
                $query->whereBetween('site_visits.actual_closer_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($legacy) use ($start, $end) {
                        $legacy->whereNull('site_visits.actual_closer_date')
                            ->whereNotNull('site_visits.closer_verified_at')
                            ->whereBetween('site_visits.closer_verified_at', [$start, $end]);
                    });
            });
        } else {
            $closerBase->whereNotNull('site_visits.closer_verified_at')
                ->whereBetween('site_visits.closer_verified_at', [$start, $end]);
        }
        if (Schema::hasColumn('site_visits', 'is_rescheduled')) {
            $closerBase->where('site_visits.is_rescheduled', false);
        }
        $maps['closers'] = $this->workflowCountsByUser($closerBase, 'site_visits', $userIds);

        return $maps;
    }

    private function workflowCountsByUser($baseQuery, string $table, $userIds)
    {
        $created = (clone $baseQuery)
            ->whereIn($table . '.created_by', $userIds)
            ->selectRaw($table . '.id as workflow_id, ' . $table . '.created_by as user_id');
        $assigned = (clone $baseQuery)
            ->whereIn($table . '.assigned_to', $userIds)
            ->selectRaw($table . '.id as workflow_id, ' . $table . '.assigned_to as user_id');
        $leadOwner = (clone $baseQuery)
            ->join('lead_assignments', function ($join) use ($table) {
                $join->on('lead_assignments.lead_id', '=', $table . '.lead_id')
                    ->where('lead_assignments.is_active', true);
            })
            ->whereIn('lead_assignments.assigned_to', $userIds)
            ->selectRaw($table . '.id as workflow_id, lead_assignments.assigned_to as user_id');

        return DB::query()
            ->fromSub($created->union($assigned)->union($leadOwner), 'workflow_users')
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');
    }

    private function bulkProgressForTarget(Target $target, array $actualsByUser, $targetsByUser): array
    {
        $userId = (int) $target->user_id;
        $progress = [];

        foreach (['prospects_extract', 'prospects_verified', 'calls'] as $type) {
            $targetValue = (int) ($target->{'target_' . $type} ?? 0);
            $actual = (int) ($actualsByUser[$type][$userId] ?? 0);
            $progress[$type] = [
                'target' => $targetValue,
                'actual' => $actual,
                'percentage' => $targetValue > 0 ? min(100, round(($actual / $targetValue) * 100, 2)) : 0,
            ];
        }

        foreach (['meetings', 'visits', 'closers'] as $type) {
            $targetValue = $this->finalTargetValue($target, $type, $targetsByUser);
            $actual = (int) ($actualsByUser[$type][$userId] ?? 0);
            $progress[$type] = [
                'target' => $targetValue,
                'achieved' => $actual,
                'percentage' => $targetValue > 0 ? min(100, round(($actual / $targetValue) * 100, 2)) : 0,
            ];
        }

        return $progress;
    }

    private function finalTargetValue(Target $target, string $type, $targetsByUser): int
    {
        $selfTarget = (int) ($target->{'target_' . $type} ?? 0);
        $roleSlug = $target->user?->role?->slug;
        if (
            !in_array($roleSlug, [Role::SALES_MANAGER, Role::SENIOR_MANAGER], true)
            || !filled($target->manager_target_calculation_logic)
        ) {
            return $selfTarget;
        }

        $juniorRoles = $target->manager_junior_scope === 'executives_only'
            ? [Role::ASSISTANT_SALES_MANAGER]
            : [Role::ASSISTANT_SALES_MANAGER, Role::SALES_EXECUTIVE];
        $teamTarget = $targetsByUser
            ->filter(fn (Target $juniorTarget) => (int) ($juniorTarget->user?->manager_id ?? 0) === (int) $target->user_id)
            ->filter(fn (Target $juniorTarget) => in_array($juniorTarget->user?->role?->slug, $juniorRoles, true))
            ->sum(fn (Target $juniorTarget) => (int) ($juniorTarget->{'target_' . $type} ?? 0));

        return $target->manager_target_calculation_logic === 'juniors_sum'
            ? (int) $teamTarget
            : $selfTarget + (int) $teamTarget;
    }

    /**
     * Bulk set targets for multiple users
     */
    public function bulkSetTargets(array $userIds, string $month, array $targets): array
    {
        $results = [];
        
        DB::beginTransaction();
        try {
            foreach ($userIds as $userId) {
                $target = $this->setTargetsForUser($userId, $month, $targets);
                $results[] = $target;
            }
            
            DB::commit();
            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Copy previous month targets into a destination month.
     */
    public function copyTargetsToMonth(string $sourceMonth, string $destinationMonth, array $sourceTargets, string $mode): array
    {
        $sourceTargetMonth = Carbon::parse($sourceMonth . '-01')->startOfMonth();
        $destinationTargetMonth = Carbon::parse($destinationMonth . '-01')->startOfMonth();

        $allowedModes = ['skip_existing', 'overwrite_existing'];
        if (!in_array($mode, $allowedModes, true)) {
            throw new \InvalidArgumentException('Invalid copy mode.');
        }

        $summary = [
            'source_month' => $sourceTargetMonth->format('Y-m'),
            'destination_month' => $destinationTargetMonth->format('Y-m'),
            'total_source_records' => count($sourceTargets),
            'copied' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        DB::transaction(function () use ($sourceTargets, $destinationTargetMonth, $mode, &$summary) {
            foreach ($sourceTargets as $sourceTarget) {
                $existingTarget = Target::where('user_id', $sourceTarget->user_id)
                    ->where('target_month', $destinationTargetMonth)
                    ->first();

                if ($existingTarget && $mode === 'skip_existing') {
                    $summary['skipped']++;
                    continue;
                }

                $payload = [
                    'target_visits' => $sourceTarget->target_visits ?? 0,
                    'target_meetings' => $sourceTarget->target_meetings ?? 0,
                    'target_closers' => $sourceTarget->target_closers ?? 0,
                    'target_prospects_extract' => $sourceTarget->target_prospects_extract ?? 0,
                    'target_prospects_verified' => $sourceTarget->target_prospects_verified ?? 0,
                    'target_calls' => $sourceTarget->target_calls ?? 0,
                    'manager_target_calculation_logic' => $sourceTarget->manager_target_calculation_logic,
                    'manager_junior_scope' => $sourceTarget->manager_junior_scope,
                    'incentive_per_closer' => $sourceTarget->incentive_per_closer,
                    'incentive_per_visit' => $sourceTarget->incentive_per_visit,
                ];

                if ($existingTarget) {
                    $existingTarget->update($payload);
                    $summary['updated']++;
                    continue;
                }

                Target::create(array_merge($payload, [
                    'user_id' => $sourceTarget->user_id,
                    'target_month' => $destinationTargetMonth,
                ]));
                $summary['copied']++;
            }
        });

        return $summary;
    }
}
