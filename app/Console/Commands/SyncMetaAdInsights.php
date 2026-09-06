<?php

namespace App\Console\Commands;

use App\Models\FbLeadAdsSettings;
use App\Models\MetaAdInsightDaily;
use App\Services\FacebookGraphService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncMetaAdInsights extends Command
{
    protected $signature = 'meta-ads:sync-insights {--from=} {--to=}';

    protected $description = 'Sync Meta Ads Insights spend and lead actions for CPL reporting.';

    public function handle(): int
    {
        $settings = FbLeadAdsSettings::getSettings();

        if (!$settings->cpl_sync_enabled) {
            $this->warn('CPL sync is disabled.');
            return self::SUCCESS;
        }

        if (empty($settings->marketing_access_token) || empty($settings->ad_account_id)) {
            $this->error('Marketing access token or ad account ID is missing.');
            return self::FAILURE;
        }

        $to = $this->option('to') ? Carbon::parse($this->option('to')) : now();
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : $to->copy()->subDays(7);

        $client = FacebookGraphService::fromToken($settings->marketing_access_token, $settings->graph_version ?? 'v18.0');
        $adAccountId = FacebookGraphService::normalizeAdAccountId($settings->ad_account_id);
        $result = $client->getAdInsights($adAccountId, $from->toDateString(), $to->toDateString());

        if (!$result['success']) {
            $this->error($result['error'] ?? 'Meta insights sync failed.');
            return self::FAILURE;
        }

        $count = 0;
        foreach ($result['rows'] as $row) {
            $adId = (string) ($row['ad_id'] ?? '');
            if ($adId === '') {
                continue;
            }

            MetaAdInsightDaily::updateOrCreate(
                [
                    'date' => $row['date_start'] ?? $from->toDateString(),
                    'ad_account_id' => $adAccountId,
                    'ad_id' => $adId,
                ],
                [
                    'campaign_id' => $row['campaign_id'] ?? null,
                    'campaign_name' => $row['campaign_name'] ?? null,
                    'adset_id' => $row['adset_id'] ?? null,
                    'adset_name' => $row['adset_name'] ?? null,
                    'ad_name' => $row['ad_name'] ?? null,
                    'spend' => (float) ($row['spend'] ?? 0),
                    'meta_leads' => $this->extractLeadActionCount($row['actions'] ?? []),
                    'actions_json' => $row['actions'] ?? [],
                    'synced_at' => now(),
                ]
            );
            $count++;
        }

        $settings->forceFill([
            'ad_account_id' => $adAccountId,
            'last_cpl_synced_at' => now(),
        ])->save();

        $this->info("Synced {$count} Meta ad insight rows.");
        return self::SUCCESS;
    }

    private function extractLeadActionCount(array $actions): int
    {
        foreach ($actions as $action) {
            $type = (string) ($action['action_type'] ?? '');
            if (in_array($type, ['lead', 'onsite_conversion.lead_grouped', 'offsite_conversion.fb_pixel_lead'], true)) {
                return (int) ($action['value'] ?? 0);
            }
        }

        return 0;
    }
}
