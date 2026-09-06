<?php

namespace App\Console\Commands;

use App\Models\Incentive;
use App\Models\PostSaleCase;
use App\Services\PostSalesService;
use Illuminate\Console\Command;

class SyncPostSalesCases extends Command
{
    protected $signature = 'post-sales:sync-eligible {--dry-run} {--limit=0}';
    protected $description = 'Audit or import verified closer incentives into Post Sales';

    public function handle(PostSalesService $service): int
    {
        $query = Incentive::with('siteVisit')->where('type', 'closer')->where('status', 'verified')->whereNotNull('finance_manager_verified_by')->orderBy('id');
        if ((int) $this->option('limit') > 0) $query->limit((int) $this->option('limit'));
        $eligible = $query->get();
        $existing = PostSaleCase::whereIn('site_visit_id', $eligible->pluck('site_visit_id'))->count();
        $this->table(['Eligible', 'Already linked', 'Would create'], [[$eligible->count(), $existing, max(0, $eligible->count() - $existing)]]);
        if ($this->option('dry-run')) return self::SUCCESS;

        $created = 0;
        foreach ($eligible as $incentive) {
            $before = PostSaleCase::where('site_visit_id', $incentive->site_visit_id)->exists();
            $service->syncIncentive($incentive, true);
            if (!$before) $created++;
        }
        $this->info("Created {$created} historical handover cases. Customer reminders remain disabled.");
        return self::SUCCESS;
    }
}
