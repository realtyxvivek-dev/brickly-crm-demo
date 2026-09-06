<?php

namespace App\Console\Commands;

use App\Models\TelecallerTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MoveOverdueCallsToPending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telecaller:move-overdue-to-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move overdue scheduled calls to pending status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue calls and rescheduled tasks...');

        // Part 1: Move all overdue tasks (more than 15 minutes old, any status except completed/pending)
        // Use NOW() in SQL to ensure proper timezone comparison with database
        $overdueTasks = TelecallerTask::where('status', '!=', 'completed')
            ->where('status', '!=', 'pending')
            ->whereRaw('scheduled_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)')
            ->get();

        // Part 2: Move rescheduled tasks within 10 minutes (even if not overdue yet)
        $rescheduledNearTasks = TelecallerTask::where('status', 'rescheduled')
            ->whereRaw('scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 10 MINUTE)')
            ->get();

        // Combine both sets (remove duplicates)
        $allTasksToMove = $overdueTasks->merge($rescheduledNearTasks)->unique('id');

        $count = 0;
        foreach ($allTasksToMove as $task) {
            $oldStatus = $task->status;
            $task->update([
                'status' => 'pending',
                'moved_to_pending_at' => now(),
            ]);
            $count++;
            
            Log::info("Moved overdue/rescheduled call to pending", [
                'task_id' => $task->id,
                'lead_id' => $task->lead_id,
                'old_status' => $oldStatus,
                'scheduled_at' => $task->scheduled_at,
            ]);
        }

        if ($count > 0) {
            $this->info("Moved {$count} overdue/rescheduled call(s) to pending status.");
        } else {
            $this->info("No overdue/rescheduled calls to move.");
        }
        
        return Command::SUCCESS;
    }
}
