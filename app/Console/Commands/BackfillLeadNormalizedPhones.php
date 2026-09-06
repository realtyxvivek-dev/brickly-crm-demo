<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\DuplicateDetectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillLeadNormalizedPhones extends Command
{
    protected $signature = 'leads:backfill-normalized-phone {--chunk=1000 : Number of leads to process per chunk} {--dry-run : Report changes without writing them}';

    protected $description = 'Backfill indexed normalized phone values for existing CRM leads.';

    public function handle(DuplicateDetectionService $duplicateDetectionService): int
    {
        if (!Schema::hasColumn('leads', 'normalized_phone') || !Schema::hasColumn('leads', 'phone_country_iso')) {
            $this->error('Phone columns are missing. Run migrations first.');
            return self::FAILURE;
        }

        $chunkSize = max(100, (int) $this->option('chunk'));
        $processed = 0;
        $updated = 0;
        $invalid = 0;
        $dryRun = (bool) $this->option('dry-run');

        $phoneTailSql = DB::connection()->getDriverName() === 'mysql'
            ? 'RIGHT(normalized_phone, 10)'
            : 'substr(normalized_phone, -10)';

        Lead::withTrashed()
            ->select(['id', 'phone', 'normalized_phone', 'phone_country_iso', 'deleted_at'])
            ->where(function ($query) use ($phoneTailSql) {
                $query->where(function ($query) {
                    $query->whereNull('normalized_phone')
                        ->orWhere('normalized_phone', '');
                })
                    ->orWhereRaw("phone <> {$phoneTailSql}")
                    ->orWhere(function ($query) {
                        $query->whereNotNull('deleted_at')->whereNotNull('normalized_phone');
                    });
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function ($leads) use ($duplicateDetectionService, &$processed, &$updated, &$invalid, $dryRun) {
                foreach ($leads as $lead) {
                    $processed++;
                    $parsed = $duplicateDetectionService->parsedLeadPhone($lead->phone, $lead->phone_country_iso);

                    if ($lead->deleted_at !== null) {
                        if (filled($lead->normalized_phone) && !$dryRun) {
                            DB::table('leads')->where('id', $lead->id)->update(['normalized_phone' => null]);
                        }
                        $updated += filled($lead->normalized_phone) ? 1 : 0;
                        continue;
                    }

                    if (!$parsed) {
                        $invalid++;
                        if (filled($lead->normalized_phone) && !$dryRun) {
                            DB::table('leads')->where('id', $lead->id)->update(['normalized_phone' => null]);
                        }
                        $updated += filled($lead->normalized_phone) ? 1 : 0;
                        continue;
                    }

                    $needsUpdate = $lead->phone !== $parsed['e164']
                        || $lead->normalized_phone !== $parsed['normalized']
                        || $lead->phone_country_iso !== $parsed['country_iso'];
                    if ($needsUpdate) {
                        if (!$dryRun) {
                            DB::table('leads')->where('id', $lead->id)->update([
                                'phone' => $parsed['e164'],
                                'normalized_phone' => $parsed['normalized'],
                                'phone_country_iso' => $parsed['country_iso'],
                            ]);
                        }
                        $updated++;
                    }
                }
            });

        $mode = $dryRun ? 'Would update' : 'Updated';
        $this->info("Processed {$processed} lead(s); {$mode} {$updated}; invalid {$invalid}.");

        return self::SUCCESS;
    }
}
