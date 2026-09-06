<?php

namespace App\Console\Commands;

use App\Services\WhatsAppAutomationService;
use Illuminate\Console\Command;

class ProcessWhatsAppAutomationQueue extends Command
{
    protected $signature = 'whatsapp-automation:process {--limit=50}';

    protected $description = 'Process pending WhatsApp automation sends';

    public function handle(WhatsAppAutomationService $automationService): int
    {
        $limit = (int) $this->option('limit');
        $scheduled = $automationService->scheduleNoActivityRules($limit);
        $processed = $automationService->processPending($limit);
        $this->info("Scheduled {$scheduled} no-activity log(s) and processed {$processed} WhatsApp automation log(s).");

        return self::SUCCESS;
    }
}
