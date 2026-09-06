<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\FacebookLeadAdsController;
use App\Services\FacebookLeadAdsHealthService;
use Illuminate\Console\Command;

class FacebookLeadAdsHealthCheck extends Command
{
    protected $signature = 'facebook-lead-ads:health-check {--notify : Send admin email when health issue signature changes}';

    protected $description = 'Check Facebook Lead Ads integration health and optionally notify admins.';

    public function handle(FacebookLeadAdsHealthService $healthService): int
    {
        $metaChecks = app(FacebookLeadAdsController::class)->metaFormsChecksForHealth();
        $snapshot = $healthService->snapshot($metaChecks);
        $notified = false;

        if ($this->option('notify')) {
            $notified = $healthService->maybeSendAlert($snapshot);
        }

        $this->info('Facebook Lead Ads health: ' . ($snapshot['label'] ?? 'Unknown'));

        foreach (($snapshot['reasons'] ?? []) as $reason) {
            $this->line('- ' . $reason);
        }

        if ($notified) {
            $this->info('Admin email alert sent.');
        }

        return ($snapshot['status'] ?? 'healthy') === 'broken' ? self::FAILURE : self::SUCCESS;
    }
}
