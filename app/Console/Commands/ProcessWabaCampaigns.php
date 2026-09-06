<?php

namespace App\Console\Commands;

use App\Services\WabaCampaignService;
use Illuminate\Console\Command;

class ProcessWabaCampaigns extends Command
{
    protected $signature = 'waba-campaigns:process {--limit=50}';

    protected $description = 'Process queued Meta WABA bulk campaigns.';

    public function handle(WabaCampaignService $service): int
    {
        $result = $service->processDueCampaigns((int) $this->option('limit'));

        $this->info(sprintf(
            'Processed: %d, Sent: %d, Failed: %d',
            $result['processed'],
            $result['sent'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
