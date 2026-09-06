<?php

namespace App\Jobs;

use App\Services\LeadAssignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoAssignUnassignedLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(LeadAssignmentService $assignmentService): void
    {
        try {
            Log::info('Starting auto-assignment of unassigned leads job');
            $results = $assignmentService->autoAssignUnassignedLeads();
            Log::info("Auto-assignment completed: {$results['assigned']} assigned, {$results['failed']} failed");
        } catch (\Exception $e) {
            Log::error('Auto-assignment job failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
