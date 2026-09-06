<?php

namespace App\Helpers;

use Carbon\Carbon;

class DashboardHelper
{
    /**
     * Calculate conversion rate percentage
     */
    public static function calculateConversionRate(int $completed, int $total): float
    {
        if ($total === 0) {
            return 0;
        }
        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get average call duration from tasks
     */
    public static function getCallDurationAverage(array $tasks): float
    {
        $completedTasks = array_filter($tasks, function ($task) {
            return isset($task['completed_at']) && isset($task['scheduled_at']) && $task['completed_at'] && $task['scheduled_at'];
        });

        if (empty($completedTasks)) {
            return 0;
        }

        $totalDuration = 0;
        $count = 0;

        foreach ($completedTasks as $task) {
            $start = Carbon::parse($task['scheduled_at']);
            $end = Carbon::parse($task['completed_at']);
            $duration = $end->diffInMinutes($start);
            $totalDuration += $duration;
            $count++;
        }

        return $count > 0 ? round($totalDuration / $count, 1) : 0;
    }

    /**
     * Get SLA compliance percentage
     */
    public static function getSlaCompliancePercentage(int $met, int $total): float
    {
        if ($total === 0) {
            return 0;
        }
        return round(($met / $total) * 100, 2);
    }

    /**
     * Format time remaining until deadline
     */
    public static function formatTimeRemaining(Carbon $deadline): string
    {
        $now = Carbon::now();
        
        if ($now->greaterThan($deadline)) {
            return 'Overdue';
        }

        $diffInMinutes = $now->diffInMinutes($deadline, false);
        
        if ($diffInMinutes < 60) {
            return $diffInMinutes . ' mins';
        }
        
        $diffInHours = $now->diffInHours($deadline, false);
        if ($diffInHours < 24) {
            return $diffInHours . ' hrs';
        }
        
        $diffInDays = $now->diffInDays($deadline, false);
        return $diffInDays . ' days';
    }

    /**
     * Get progress bar color based on percentage
     */
    public static function getProgressBarColor(float $percentage): string
    {
        if ($percentage >= 80) {
            return 'green';
        } elseif ($percentage >= 60) {
            return 'blue';
        } elseif ($percentage >= 40) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    /**
     * Format number with K suffix for thousands
     */
    public static function formatNumber(int $number): string
    {
        if ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return (string) $number;
    }
}

