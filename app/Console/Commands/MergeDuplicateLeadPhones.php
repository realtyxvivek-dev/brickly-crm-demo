<?php

namespace App\Console\Commands;

use App\Services\LeadMergeService;
use App\Services\SystemErrorAlertService;
use Illuminate\Console\Command;
use Throwable;

class MergeDuplicateLeadPhones extends Command
{
    protected $signature = 'leads:merge-duplicate-phones
        {--execute : Commit merges; without this option the command is a dry run}
        {--limit= : Maximum duplicate groups to inspect}
        {--phone= : Inspect or merge one phone number}
        {--actor= : User ID recorded as the merge actor}';

    protected $description = 'Audit or transactionally merge duplicate CRM leads by canonical phone.';

    public function handle(LeadMergeService $mergeService, SystemErrorAlertService $alerts): int
    {
        $execute = (bool) $this->option('execute');
        $groups = $mergeService->duplicateGroups(
            $this->option('phone') ?: null,
            $this->option('limit') ? max(1, (int) $this->option('limit')) : null
        );

        $this->info(($execute ? 'MERGE' : 'DRY RUN') . ": {$groups->count()} duplicate group(s).");
        $merged = 0;
        $failed = 0;

        foreach ($groups as $normalizedPhone) {
            $preview = $mergeService->preview($normalizedPhone);
            $this->line(json_encode($preview, JSON_UNESCAPED_SLASHES));

            if (!$execute) {
                continue;
            }

            try {
                $result = $mergeService->merge($normalizedPhone, $this->option('actor') ? (int) $this->option('actor') : null);
                $this->info("Merged into lead #{$result['master_lead_id']}: " . implode(', ', $result['merged_lead_ids']));
                $merged++;
            } catch (Throwable $exception) {
                $failed++;
                $this->error("{$normalizedPhone}: {$exception->getMessage()}");
                $alerts->sendOperationalMessage(implode("\n", [
                    'CRM LEAD MERGE FAILED',
                    '',
                    "Phone: {$normalizedPhone}",
                    'Error: ' . mb_strimwidth($exception->getMessage(), 0, 700, '...'),
                    'Time: ' . now()->format('d M Y h:i A'),
                ]));
            }
        }

        $remaining = $mergeService->duplicateGroups()->count();
        $this->info("Completed. Merged: {$merged}; failed: {$failed}; remaining groups: {$remaining}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
