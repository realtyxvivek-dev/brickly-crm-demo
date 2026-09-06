<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ProcessMetaQueue extends Command
{
    protected $signature = 'meta-queue:process
        {--max-jobs=20 : Maximum jobs to process in one run}
        {--tries=3 : Retry attempts per job}
        {--timeout=120 : Worker timeout in seconds}';

    protected $description = 'Process queued Meta and default jobs in a short shared-hosting safe worker run.';

    public function handle(): int
    {
        $lock = Cache::lock('meta-queue:process', 55);

        if (!$lock->get()) {
            $this->info('Meta queue worker already running.');
            return self::SUCCESS;
        }

        try {
            return Artisan::call('queue:work', [
                'connection' => config('queue.default', 'database'),
                '--queue' => 'meta,default',
                '--stop-when-empty' => true,
                '--max-jobs' => max(1, (int) $this->option('max-jobs')),
                '--tries' => max(1, (int) $this->option('tries')),
                '--timeout' => max(30, (int) $this->option('timeout')),
                '--sleep' => 1,
                '--backoff' => 10,
                '--force' => true,
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
