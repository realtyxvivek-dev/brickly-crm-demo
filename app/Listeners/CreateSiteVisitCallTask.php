<?php

namespace App\Listeners;

use App\Events\SiteVisitCreated;
use App\Services\TelecallerTaskService;
use Illuminate\Support\Facades\Log;

class CreateSiteVisitCallTask
{
    protected $telecallerTaskService;

    public function __construct(TelecallerTaskService $telecallerTaskService)
    {
        $this->telecallerTaskService = $telecallerTaskService;
    }

    /**
     * Handle the event - Auto-create calling task 10 minutes before site visit
     */
    public function handle(SiteVisitCreated $event): void
    {
        try {
            $siteVisit = $event->siteVisit;

            // Create calling task 10 minutes before site visit
            $task = $this->telecallerTaskService->createSiteVisitCallTask(
                $siteVisit,
                $siteVisit->created_by
            );

            if ($task) {
                Log::info("Auto-created calling task for site visit", [
                    'task_id' => $task->id,
                    'site_visit_id' => $siteVisit->id,
                    'lead_id' => $siteVisit->lead_id,
                    'scheduled_at' => $task->scheduled_at,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to create calling task for site visit {$event->siteVisit->id}: " . $e->getMessage());
        }
    }
}
