<?php

namespace App\Console\Commands;

use App\Services\LeadMergeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnforceUniqueLeadPhones extends Command
{
    protected $signature = 'leads:enforce-unique-phone {--force : Add the index after validation}';

    protected $description = 'Add the final unique index after all canonical lead phone duplicates are merged.';

    public function handle(LeadMergeService $mergeService): int
    {
        $remaining = $mergeService->duplicateGroups()->count();
        if ($remaining > 0) {
            $this->error("Cannot add unique index: {$remaining} duplicate group(s) remain.");
            return self::FAILURE;
        }

        if ($this->indexExists()) {
            $this->info('Unique normalized-phone index already exists.');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->warn('Validation passed. Re-run with --force to add the unique index.');
            return self::SUCCESS;
        }

        Schema::table('leads', function ($table) {
            $table->unique('normalized_phone', 'leads_normalized_phone_unique');
        });

        $this->info('Unique normalized-phone index added.');
        return self::SUCCESS;
    }

    private function indexExists(): bool
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return DB::table('information_schema.STATISTICS')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', 'leads')
                ->where('INDEX_NAME', 'leads_normalized_phone_unique')
                ->exists();
        }

        return collect(DB::select("PRAGMA index_list('leads')"))
            ->contains(fn ($index) => $index->name === 'leads_normalized_phone_unique');
    }
}
