<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CallLogService
{
    private function applyDateRange($query, string $dateRange = 'today', $startDate = null, $endDate = null): void
    {
        switch ($dateRange) {
            case 'this_week':
                $query->thisWeek();
                break;
            case 'this_month':
                $query->thisMonth();
                break;
            case 'custom':
                if ($startDate && $endDate) {
                    $query->whereBetween('start_time', [
                        Carbon::parse($startDate)->startOfDay(),
                        Carbon::parse($endDate)->endOfDay(),
                    ]);
                } else {
                    $query->today();
                }
                break;
            case 'today':
            default:
                $query->today();
                break;
        }
    }

    /**
     * Format duration from seconds to "Xh Ym Zs"
     */
    public function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }

    /**
     * Calculate connection rate percentage
     */
    public function calculateConnectionRate(int $totalCalls, int $completedCalls): float
    {
        if ($totalCalls === 0) {
            return 0;
        }
        return round(($completedCalls / $totalCalls) * 100, 2);
    }

    /**
     * Get call statistics for a specific user
     */
    public function getCallStatistics(?int $userId = null, string $dateRange = 'today', $startDate = null, $endDate = null): array
    {
        $query = CallLog::query();

        if ($userId) {
            $query->forUser($userId);
        }

        // Apply date range
        $this->applyDateRange($query, $dateRange, $startDate, $endDate);

        $stats = $query->selectRaw('
            COUNT(*) as total_calls,
            SUM(CASE WHEN call_type = "incoming" THEN 1 ELSE 0 END) as incoming_calls,
            SUM(CASE WHEN call_type = "outgoing" THEN 1 ELSE 0 END) as outgoing_calls,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_calls,
            SUM(duration) as total_duration,
            AVG(duration) as average_duration
        ')->first();

        $totalCalls = (int)($stats->total_calls ?? 0);
        $completedCalls = (int)($stats->completed_calls ?? 0);
        $totalDuration = (int)($stats->total_duration ?? 0);
        $averageDuration = (int)($stats->average_duration ?? 0);

        return [
            'total_calls' => $totalCalls,
            'incoming_calls' => (int)($stats->incoming_calls ?? 0),
            'outgoing_calls' => (int)($stats->outgoing_calls ?? 0),
            'completed_calls' => $completedCalls,
            'total_duration' => $totalDuration,
            'formatted_duration' => $this->formatDuration($totalDuration),
            'average_duration' => $averageDuration,
            'formatted_average_duration' => $this->formatDuration($averageDuration),
            'connection_rate' => $this->calculateConnectionRate($totalCalls, $completedCalls),
        ];
    }

    /**
     * Get team call statistics for a manager
     */
    public function getTeamCallStatistics(int $managerId, string $dateRange = 'today', $startDate = null, $endDate = null): array
    {
        $manager = User::findOrFail($managerId);
        
        // Get all team member IDs
        $teamMemberIds = $manager->getAllTeamMemberIds();
        $teamMemberIds[] = $managerId; // Include manager's own calls

        $query = CallLog::query()->forTeam($teamMemberIds);

        // Apply date range
        $this->applyDateRange($query, $dateRange, $startDate, $endDate);

        $stats = $query->selectRaw('
            COUNT(*) as total_calls,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_calls,
            SUM(duration) as total_duration,
            AVG(duration) as average_duration
        ')->first();

        $totalCalls = (int)($stats->total_calls ?? 0);
        $completedCalls = (int)($stats->completed_calls ?? 0);
        $totalDuration = (int)($stats->total_duration ?? 0);
        $averageDuration = (int)($stats->average_duration ?? 0);

        // Get top performers
        $topPerformers = $this->getTopPerformers($teamMemberIds, $dateRange, 5, $startDate, $endDate);

        return [
            'total_calls' => $totalCalls,
            'completed_calls' => $completedCalls,
            'total_duration' => $totalDuration,
            'formatted_duration' => $this->formatDuration($totalDuration),
            'average_duration' => $averageDuration,
            'formatted_average_duration' => $this->formatDuration($averageDuration),
            'connection_rate' => $this->calculateConnectionRate($totalCalls, $completedCalls),
            'team_member_count' => count($teamMemberIds),
            'top_performers' => $topPerformers,
        ];
    }

    /**
     * Get system-wide call statistics
     */
    public function getSystemCallStatistics(string $dateRange = 'today', $startDate = null, $endDate = null): array
    {
        $query = CallLog::query();

        // Apply date range
        $this->applyDateRange($query, $dateRange, $startDate, $endDate);

        $stats = $query->selectRaw('
            COUNT(*) as total_calls,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_calls,
            SUM(duration) as total_duration,
            AVG(duration) as average_duration
        ')->first();

        $totalCalls = (int)($stats->total_calls ?? 0);
        $completedCalls = (int)($stats->completed_calls ?? 0);
        $totalDuration = (int)($stats->total_duration ?? 0);
        $averageDuration = (int)($stats->average_duration ?? 0);

        // Get calls by role
        $callsByRole = $this->getCallsByRole($dateRange, $startDate, $endDate);
        
        // Get top users
        $topUsers = $this->getTopPerformers(null, $dateRange, 10, $startDate, $endDate);

        // Get outcome distribution
        $outcomeDistribution = $this->getOutcomeDistribution($dateRange, $startDate, $endDate);

        return [
            'total_calls' => $totalCalls,
            'completed_calls' => $completedCalls,
            'total_duration' => $totalDuration,
            'formatted_duration' => $this->formatDuration($totalDuration),
            'average_duration' => $averageDuration,
            'formatted_average_duration' => $this->formatDuration($averageDuration),
            'connection_rate' => $this->calculateConnectionRate($totalCalls, $completedCalls),
            'calls_by_role' => $callsByRole,
            'top_users' => $topUsers,
            'outcome_distribution' => $outcomeDistribution,
        ];
    }

    /**
     * Get calls per hour for a specific user and date
     */
    public function getCallsPerHour(?int $userId = null, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();
        $query = CallLog::query()->whereDate('start_time', $date);

        if ($userId) {
            $query->forUser($userId);
        }

        $callsPerHour = $query->selectRaw('HOUR(start_time) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        // Fill in missing hours with 0
        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $result[$i] = $callsPerHour[$i] ?? 0;
        }

        return $result;
    }

    /**
     * Get top performers by number of calls
     */
    public function getTopPerformers(?array $userIds = null, string $dateRange = 'today', int $limit = 10, $startDate = null, $endDate = null): array
    {
        $query = CallLog::query();

        if ($userIds) {
            $query->forTeam($userIds);
        }

        // Apply date range
        $this->applyDateRange($query, $dateRange, $startDate, $endDate);

        $performers = $query->selectRaw('
            COALESCE(user_id, telecaller_id) as user_id,
            COUNT(*) as total_calls,
            SUM(duration) as total_duration,
            AVG(duration) as average_duration
        ')
        ->groupBy('user_id')
        ->orderBy('total_calls', 'desc')
        ->limit($limit)
        ->get();

        $result = [];
        foreach ($performers as $performer) {
            $user = User::find($performer->user_id);
            if ($user) {
                $result[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'total_calls' => (int)$performer->total_calls,
                    'total_duration' => (int)$performer->total_duration,
                    'formatted_duration' => $this->formatDuration((int)$performer->total_duration),
                    'average_duration' => (int)$performer->average_duration,
                    'formatted_average_duration' => $this->formatDuration((int)$performer->average_duration),
                ];
            }
        }

        return $result;
    }

    /**
     * Get calls by role
     */
    public function getCallsByRole(string $dateRange = 'today', $startDate = null, $endDate = null): array
    {
        $query = CallLog::query();

        $this->applyDateRange($query, $dateRange, $startDate, $endDate);

        $callsByRole = $query->join('users', function($join) {
                $join->on('call_logs.user_id', '=', 'users.id')
                     ->orOn('call_logs.telecaller_id', '=', 'users.id');
            })
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->selectRaw('roles.name as role_name, roles.slug as role_slug, COUNT(*) as call_count')
            ->groupBy('roles.id', 'roles.name', 'roles.slug')
            ->orderBy('call_count', 'desc')
            ->get();

        return $callsByRole->map(function($item) {
            return [
                'role_name' => $item->role_name,
                'role_slug' => $item->role_slug,
                'call_count' => (int)$item->call_count,
            ];
        })->toArray();
    }

    /**
     * Get outcome distribution
     */
    public function getOutcomeDistribution(string $dateRange = 'today', $startDate = null, $endDate = null): array
    {
        $query = CallLog::query();

        $this->applyDateRange($query, $dateRange, $startDate, $endDate);

        $distribution = $query->selectRaw('
            call_outcome,
            COUNT(*) as count
        ')
        ->whereNotNull('call_outcome')
        ->groupBy('call_outcome')
        ->orderBy('count', 'desc')
        ->get();

        return $distribution->map(function($item) {
            return [
                'outcome' => $item->call_outcome,
                'label' => match($item->call_outcome) {
                    'interested' => 'Interested',
                    'not_interested' => 'Not Interested',
                    'callback' => 'Callback Requested',
                    'no_answer' => 'No Answer',
                    'busy' => 'Busy',
                    'other' => 'Other',
                    default => ucfirst($item->call_outcome),
                },
                'count' => (int)$item->count,
            ];
        })->toArray();
    }

    /**
     * Suggest next followup date based on call outcome
     */
    public function suggestNextFollowup(CallLog $callLog): ?Carbon
    {
        if (!$callLog->call_outcome) {
            return null;
        }

        $baseDate = $callLog->start_time ?? Carbon::now();

        return match($callLog->call_outcome) {
            'interested' => $baseDate->copy()->addDays(1), // Follow up next day
            'callback' => $baseDate->copy()->addHours(4), // Callback in 4 hours
            'not_interested' => null, // No followup needed
            'no_answer' => $baseDate->copy()->addHours(2), // Try again in 2 hours
            'busy' => $baseDate->copy()->addHours(1), // Try again in 1 hour
            'other' => $baseDate->copy()->addDays(3), // Follow up in 3 days
            default => $baseDate->copy()->addDays(1),
        };
    }
}
