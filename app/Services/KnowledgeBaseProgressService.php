<?php

namespace App\Services;

use App\Models\KnowledgeBaseAssignment;
use App\Models\KnowledgeBasePathAssignment;
use App\Models\KnowledgeBaseProgress;
use Carbon\CarbonInterface;

class KnowledgeBaseProgressService
{
    public function resolveAssignmentStatus(?KnowledgeBaseAssignment $assignment, ?KnowledgeBaseProgress $progress, ?CarbonInterface $now = null): string
    {
        $now = $now ?: now();

        if ($progress?->completed_at) {
            if ($assignment?->completed_late || ($assignment?->due_date && $progress->completed_at->gt($assignment->due_date->copy()->endOfDay()))) {
                return 'completed_late';
            }

            return 'completed';
        }

        if ($assignment?->due_date) {
            if ($assignment->due_date->lt($now->toDateString())) {
                return 'overdue';
            }

            if ($assignment->due_date->between($now->toDateString(), $now->copy()->addDays(2)->toDateString())) {
                return 'due_soon';
            }
        }

        if (($progress?->status ?? 'not_started') === 'in_progress') {
            return 'in_progress';
        }

        return 'not_started';
    }

    public function resolvePathAssignmentStatus(KnowledgeBasePathAssignment $assignment, int $completedItems, int $totalItems, ?CarbonInterface $now = null): string
    {
        $now = $now ?: now();

        if ($totalItems > 0 && $completedItems >= $totalItems) {
            return $assignment->due_date && $assignment->due_date->lt($now->toDateString()) ? 'completed_late' : 'completed';
        }

        if ($assignment->due_date) {
            if ($assignment->due_date->lt($now->toDateString())) {
                return 'overdue';
            }

            if ($assignment->due_date->between($now->toDateString(), $now->copy()->addDays(2)->toDateString())) {
                return 'due_soon';
            }
        }

        return $completedItems > 0 ? 'in_progress' : 'not_started';
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'due_soon' => 'Due Soon',
            'overdue' => 'Overdue',
            'completed_late' => 'Completed Late',
            default => 'Not Started',
        };
    }
}
