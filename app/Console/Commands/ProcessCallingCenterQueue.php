<?php

namespace App\Console\Commands;

use App\Services\CallingCenterService;
use Illuminate\Console\Command;

class ProcessCallingCenterQueue extends Command
{
    protected $signature = 'calling-center:process';

    protected $description = 'Process due Calling Center campaign calls.';

    public function handle(CallingCenterService $callingCenterService): int
    {
        $started = $callingCenterService->processDueCampaigns();
        $this->info("Calling Center processed. Started calls: {$started}");

        return self::SUCCESS;
    }
}
