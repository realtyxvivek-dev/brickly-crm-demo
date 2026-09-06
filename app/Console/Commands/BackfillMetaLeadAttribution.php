<?php

namespace App\Console\Commands;

use App\Models\FbLead;
use App\Models\FbLeadAdsSettings;
use App\Services\FacebookGraphService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillMetaLeadAttribution extends Command
{
    protected $signature = 'meta-ads:backfill-lead-attribution {--from=} {--to=} {--limit=500}';

    protected $description = 'Backfill Meta lead ad/campaign/adset attribution onto stored fb_leads rows.';

    public function handle(): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : now()->subYear()->startOfDay();
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : now()->endOfDay();
        $limit = max(1, (int) $this->option('limit'));
        $settings = FbLeadAdsSettings::getSettings();

        $leads = FbLead::with('form.page')
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($query) {
                $query->whereNull('ad_id')->orWhereNull('campaign_id')->orWhereNull('adset_id');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($leads as $fbLead) {
            $token = $fbLead->form?->page?->page_access_token;
            if (!$token) {
                $failed++;
                continue;
            }

            $client = FacebookGraphService::fromToken($token, $settings->graph_version ?? 'v18.0');
            $result = $client->getLeadDetails((string) $fbLead->leadgen_id);

            if (!$result['success']) {
                $failed++;
                continue;
            }

            $data = $result['data'] ?? [];
            $fbLead->forceFill([
                'ad_id' => $data['ad_id'] ?? $fbLead->ad_id,
                'ad_name' => $data['ad_name'] ?? $fbLead->ad_name,
                'adset_id' => $data['adset_id'] ?? $fbLead->adset_id,
                'adset_name' => $data['adset_name'] ?? $fbLead->adset_name,
                'campaign_id' => $data['campaign_id'] ?? $fbLead->campaign_id,
                'campaign_name' => $data['campaign_name'] ?? $fbLead->campaign_name,
                'platform' => $data['platform'] ?? $fbLead->platform,
                'meta_created_time' => !empty($data['created_time']) ? Carbon::parse($data['created_time']) : $fbLead->meta_created_time,
                'raw_response_json' => array_merge($fbLead->raw_response_json ?? [], $data),
            ])->save();
            $updated++;
        }

        $this->info("Backfill complete. Updated {$updated}, failed/skipped {$failed}.");
        return self::SUCCESS;
    }
}
