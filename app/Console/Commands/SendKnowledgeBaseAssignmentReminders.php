<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\KnowledgeBaseAssignment;
use App\Models\KnowledgeBasePathAssignment;
use App\Models\KnowledgeBaseProgress;
use Illuminate\Console\Command;

class SendKnowledgeBaseAssignmentReminders extends Command
{
    protected $signature = 'knowledge-base:send-reminders';

    protected $description = 'Send in-app due soon, due today, and overdue reminders for knowledge base assignments';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $dueSoonDate = $today->copy()->addDays(2)->toDateString();
        $count = 0;

        $itemAssignments = KnowledgeBaseAssignment::query()
            ->with('item:id,title,slug')
            ->whereNotNull('due_date')
            ->get();

        foreach ($itemAssignments as $assignment) {
            $progress = KnowledgeBaseProgress::query()
                ->where('knowledge_base_item_id', $assignment->knowledge_base_item_id)
                ->where('user_id', $assignment->user_id)
                ->first();

            if ($progress?->completed_at) {
                continue;
            }

            $notificationType = null;
            $title = null;

            if ($assignment->due_date->toDateString() === $dueSoonDate && $this->shouldRemind($assignment, $today)) {
                $notificationType = AppNotification::TYPE_KNOWLEDGE_BASE_DUE_SOON;
                $title = 'Knowledge Topic Due Soon';
            } elseif ($assignment->due_date->toDateString() === $today->toDateString() && $this->shouldRemind($assignment, $today)) {
                $notificationType = AppNotification::TYPE_KNOWLEDGE_BASE_DUE_TODAY;
                $title = 'Knowledge Topic Due Today';
            } elseif ($assignment->due_date->lt($today->toDateString()) && $this->shouldOverdueRemind($assignment, $today)) {
                $notificationType = AppNotification::TYPE_KNOWLEDGE_BASE_OVERDUE;
                $title = 'Knowledge Topic Overdue';
            }

            if (!$notificationType || !$assignment->item) {
                continue;
            }

            AppNotification::create([
                'user_id' => $assignment->user_id,
                'type' => $notificationType,
                'title' => $title,
                'message' => $assignment->item->title . ' ko review complete karna pending hai.',
                'data' => [
                    'knowledge_base_item_id' => $assignment->knowledge_base_item_id,
                    'due_date' => $assignment->due_date?->toDateString(),
                ],
                'action_type' => AppNotification::ACTION_KNOWLEDGE_BASE,
                'action_url' => route('knowledge-base.show', $assignment->item->slug),
            ]);

            $assignment->update(['last_reminded_at' => now()]);
            $count++;
        }

        $pathAssignments = KnowledgeBasePathAssignment::query()
            ->with('path:id,title')
            ->whereNotNull('due_date')
            ->get();

        foreach ($pathAssignments as $assignment) {
            $path = $assignment->path;
            if (!$path) {
                continue;
            }

            $completedCount = $path->pathItems()
                ->whereIn('knowledge_base_item_id', function ($query) use ($assignment) {
                    $query->select('knowledge_base_item_id')
                        ->from('knowledge_base_progress')
                        ->where('user_id', $assignment->user_id)
                        ->whereNotNull('completed_at');
                })
                ->count();
            $totalCount = $path->pathItems()->count();

            if ($totalCount > 0 && $completedCount >= $totalCount) {
                continue;
            }

            if (!$this->shouldOverdueRemind($assignment, $today)) {
                continue;
            }

            AppNotification::create([
                'user_id' => $assignment->user_id,
                'type' => AppNotification::TYPE_KNOWLEDGE_BASE_OVERDUE,
                'title' => 'Learning Path Pending',
                'message' => ($assignment->path?->title ?? 'Assigned learning path') . ' abhi pending hai.',
                'data' => [
                    'knowledge_base_path_id' => $assignment->knowledge_base_path_id,
                    'due_date' => $assignment->due_date?->toDateString(),
                ],
                'action_type' => AppNotification::ACTION_KNOWLEDGE_BASE,
                'action_url' => route('knowledge-base.index', ['tab' => 'assigned']),
            ]);

            $assignment->update(['last_reminded_at' => now()]);
            $count++;
        }

        $this->info("Sent {$count} knowledge base reminder notification(s).");

        return self::SUCCESS;
    }

    private function shouldRemind($assignment, $today): bool
    {
        return !$assignment->last_reminded_at || !$assignment->last_reminded_at->isSameDay($today);
    }

    private function shouldOverdueRemind($assignment, $today): bool
    {
        if (!$assignment->due_date || !$assignment->due_date->lt($today->toDateString())) {
            return false;
        }

        return !$assignment->last_reminded_at || $assignment->last_reminded_at->lt($today->copy()->subDay());
    }
}
