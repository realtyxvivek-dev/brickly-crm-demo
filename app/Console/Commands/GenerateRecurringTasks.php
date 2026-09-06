<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateRecurringTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:generate-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate recurring task instances based on recurrence patterns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating recurring tasks...');

        // Get all active recurring tasks
        $recurringTasks = Task::whereNotNull('recurrence_pattern')
            ->where('status', '!=', 'cancelled')
            ->where(function($query) {
                $query->whereNull('recurrence_end_date')
                      ->orWhere('recurrence_end_date', '>=', now());
            })
            ->get();

        $generated = 0;

        foreach ($recurringTasks as $parentTask) {
            // Check if we should continue recurring
            if (!$parentTask->shouldContinueRecurring()) {
                continue;
            }

            // Get the next occurrence date
            $nextDate = $parentTask->getNextOccurrenceDate();

            if (!$nextDate) {
                continue;
            }

            // Check if a task for this occurrence already exists
            $existingTask = Task::where('lead_id', $parentTask->lead_id)
                ->where('assigned_to', $parentTask->assigned_to)
                ->where('title', $parentTask->title)
                ->whereDate('scheduled_at', $nextDate->toDateString())
                ->whereNotNull('recurrence_pattern')
                ->first();

            if ($existingTask) {
                continue; // Task already exists for this date
            }

            // Only create if the next occurrence date is today or in the future
            // and within the next 30 days (to avoid creating too many at once)
            if ($nextDate->isFuture() && $nextDate->diffInDays(now()) > 30) {
                continue;
            }

            // Create the new task instance
            try {
                DB::beginTransaction();

                $newTask = Task::create([
                    'lead_id' => $parentTask->lead_id,
                    'assigned_to' => $parentTask->assigned_to,
                    'type' => $parentTask->type,
                    'title' => $parentTask->title,
                    'description' => $parentTask->description,
                    'status' => 'pending',
                    'priority' => $parentTask->priority ?? 'medium',
                    'scheduled_at' => $nextDate,
                    'due_date' => $parentTask->due_date ? $parentTask->due_date->copy()->addDays($nextDate->diffInDays($parentTask->scheduled_at)) : $nextDate,
                    'created_by' => $parentTask->created_by,
                    'notes' => $parentTask->notes,
                    // Don't copy recurrence pattern to new instance - it's a one-time occurrence
                ]);

                // Log activity
                \App\Models\TaskActivity::create([
                    'task_id' => $newTask->id,
                    'user_id' => null, // System generated
                    'activity_type' => 'created',
                    'description' => "Recurring task instance created from parent task #{$parentTask->id}",
                ]);

                // Also log on parent task
                \App\Models\TaskActivity::create([
                    'task_id' => $parentTask->id,
                    'user_id' => null,
                    'activity_type' => 'recurrence_generated',
                    'description' => "Generated recurring instance #{$newTask->id} scheduled for {$nextDate->format('M d, Y')}",
                ]);

                DB::commit();

                $generated++;
                $this->line("Generated task: {$newTask->title} for {$nextDate->format('M d, Y')}");

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to generate recurring task for parent task #{$parentTask->id}: " . $e->getMessage());
                $this->error("Failed to generate task: {$parentTask->title}");
            }
        }

        $this->info("Generated {$generated} recurring task instances.");
        
        return Command::SUCCESS;
    }
}
