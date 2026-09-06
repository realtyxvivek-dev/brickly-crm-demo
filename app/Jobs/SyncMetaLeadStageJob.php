<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\MetaLeadReviewSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMetaLeadStageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $leadId)
    {
        $this->onQueue('default');
    }

    public function handle(MetaLeadReviewSyncService $syncService): void
    {
        $lead = Lead::with('latestFbLead')->find($this->leadId);
        if (!$lead) {
            return;
        }

        $syncService->sync($lead);
    }
}
