<?php

namespace App\Services;

use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Models\UserMetricReset;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardResponseTimeService
{
    public function getAverageResponseTimeByUser($startDate = null, $endDate = null): array
    {
        $users = User::with('role')
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereNotIn('slug', [Role::ADMIN, Role::CRM]);
            })
            ->get()
            ->filter(function (User $user) {
                if ($user->role && $user->role->slug === Role::SALES_MANAGER && $user->manager_id === null) {
                    return false;
                }

                return true;
            })
            ->values();

        if ($users->isEmpty()) {
            return [];
        }

        $resetMap = UserMetricReset::query()
            ->where('metric_key', UserMetricReset::METRIC_RESPONSE_TIME)
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $assignmentsQuery = LeadAssignment::query()
            ->whereIn('assigned_to', $users->pluck('id'))
            ->whereNotNull('assigned_at');

        if ($startDate && $endDate) {
            $assignmentsQuery->whereBetween('assigned_at', [$startDate, $endDate]);
        }

        $assignmentsByUser = $assignmentsQuery
            ->orderBy('assigned_at')
            ->get(['lead_id', 'assigned_to', 'assigned_at'])
            ->groupBy('assigned_to');

        $responseEvents = $this->loadResponseEvents($assignmentsByUser->flatten(1));

        $result = [];

        foreach ($users as $user) {
            $assignments = $assignmentsByUser->get($user->id, collect());

            $resetAt = optional($resetMap->get($user->id))->reset_at;
            $responseMinutesList = [];

            foreach ($assignments as $assignment) {
                $assignedAt = $assignment->assigned_at instanceof Carbon
                    ? $assignment->assigned_at
                    : Carbon::parse($assignment->assigned_at);

                $pairKey = $this->responsePairKey((int) $assignment->lead_id, (int) $assignment->assigned_to);
                $firstResponse = $this->resolveFirstResponseAt($responseEvents->get($pairKey, collect()), $assignedAt);

                if (!$firstResponse) {
                    continue;
                }

                if ($resetAt && $firstResponse->lt($resetAt)) {
                    continue;
                }

                $responseMinutesList[] = (int) round($assignedAt->diffInMinutes($firstResponse));
            }

            $avgMinutes = count($responseMinutesList) > 0
                ? round(array_sum($responseMinutesList) / count($responseMinutesList), 1)
                : 0;

            $result[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'avg_response_minutes' => $avgMinutes,
                'responded_count' => count($responseMinutesList),
                'can_reset' => true,
                'reset_at' => $resetAt?->toIso8601String(),
            ];
        }

        usort($result, function (array $a, array $b) {
            $cmp = (float) ($a['avg_response_minutes'] <=> $b['avg_response_minutes']);
            return $cmp !== 0 ? $cmp : strcasecmp($a['user_name'] ?? '', $b['user_name'] ?? '');
        });

        return $result;
    }

    public function resetForUser(int $userId, ?int $resetBy = null): UserMetricReset
    {
        return UserMetricReset::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'metric_key' => UserMetricReset::METRIC_RESPONSE_TIME,
            ],
            [
                'reset_at' => now(),
                'reset_by' => $resetBy,
            ]
        );
    }

    private function loadResponseEvents(Collection $assignments): Collection
    {
        if ($assignments->isEmpty()) {
            return collect();
        }

        $leadIds = $assignments->pluck('lead_id')->filter()->unique()->values();
        $userIds = $assignments->pluck('assigned_to')->filter()->unique()->values();
        $validPairs = $assignments
            ->mapWithKeys(fn (LeadAssignment $assignment) => [
                $this->responsePairKey((int) $assignment->lead_id, (int) $assignment->assigned_to) => true,
            ]);
        $events = collect();

        Task::query()
            ->whereIn('lead_id', $leadIds)
            ->whereIn('assigned_to', $userIds)
            ->where('type', 'phone_call')
            ->where(function ($query) {
                $query->whereNotNull('completed_at')
                    ->orWhereNotNull('outcome_recorded_at');
            })
            ->get(['lead_id', 'assigned_to', 'completed_at', 'outcome_recorded_at'])
            ->each(function (Task $task) use ($validPairs, $events) {
                $key = $this->responsePairKey((int) $task->lead_id, (int) $task->assigned_to);
                if (!$validPairs->has($key)) {
                    return;
                }

                foreach ([$task->completed_at, $task->outcome_recorded_at] as $timestamp) {
                    if ($timestamp) {
                        $events->push(['key' => $key, 'timestamp' => $timestamp]);
                    }
                }
            });

        TelecallerTask::query()
            ->whereIn('lead_id', $leadIds)
            ->whereIn('assigned_to', $userIds)
            ->whereNotNull('completed_at')
            ->get(['lead_id', 'assigned_to', 'completed_at'])
            ->each(function (TelecallerTask $task) use ($validPairs, $events) {
                $key = $this->responsePairKey((int) $task->lead_id, (int) $task->assigned_to);
                if ($validPairs->has($key) && $task->completed_at) {
                    $events->push(['key' => $key, 'timestamp' => $task->completed_at]);
                }
            });

        return $events
            ->groupBy('key')
            ->map(fn (Collection $group) => $group
                ->pluck('timestamp')
                ->map(fn ($timestamp) => $timestamp instanceof Carbon ? $timestamp : Carbon::parse($timestamp))
                ->sort()
                ->values());
    }

    private function resolveFirstResponseAt(Collection $timestamps, Carbon $assignedAt): ?Carbon
    {
        return $timestamps
            ->filter(function (Carbon $value) use ($assignedAt) {
                return $value->greaterThanOrEqualTo($assignedAt);
            })
            ->first();
    }

    private function responsePairKey(int $leadId, int $userId): string
    {
        return $leadId . ':' . $userId;
    }
}
