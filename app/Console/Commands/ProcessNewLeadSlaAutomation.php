<?php

namespace App\Console\Commands;

use App\Services\NewLeadSlaAutomationService;
use Illuminate\Console\Command;

class ProcessNewLeadSlaAutomation extends Command
{
    protected $signature = 'new-lead-sla:process';

    protected $description = 'Assign and process new lead no-response SLA automation';

    public function handle(NewLeadSlaAutomationService $service): int
    {
        $result = $service->processAutomationCycle();

        $this->info(sprintf(
            'Assigned: %d | Started: %d | Responded: %d | Transferred: %d | Escalated: %d | Cancelled: %d',
            $result['assigned'] ?? 0,
            $result['started'] ?? 0,
            $result['responded'] ?? 0,
            $result['transferred'] ?? 0,
            $result['escalated'] ?? 0,
            $result['cancelled'] ?? 0
        ));

        return self::SUCCESS;
    }
}
