<?php

namespace App\Jobs;

use App\Services\TelecallerLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResetDailyLimitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(TelecallerLimitService $limitService): void
    {
        try {
            Log::info('Starting daily limits reset job');
            $limitService->resetDailyCounts();
            Log::info('Daily limits reset completed successfully');
        } catch (\Exception $e) {
            Log::error('Daily limits reset job failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
