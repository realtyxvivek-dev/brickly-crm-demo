<?php

namespace App\Console\Commands;

use App\Services\Instagram\InstagramReadinessService;
use Illuminate\Console\Command;

class InstagramHealthCheck extends Command
{
    protected $signature = 'instagram:health-check {--json : Output machine-readable JSON}';

    protected $description = 'Check Instagram automation production readiness';

    public function handle(InstagramReadinessService $readiness): int
    {
        $report = $readiness->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));

            return $report['ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Instagram Automation Readiness');
        $this->newLine();

        $this->table(
            ['Check', 'Status', 'Message'],
            collect($report['checks'])->map(fn (array $check) => [
                $check['name'],
                strtoupper($check['status']),
                $check['message'],
            ])->all()
        );

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            collect($report['summary'])->map(fn ($value, string $key) => [
                str($key)->replace('_', ' ')->title()->toString(),
                $value,
            ])->values()->all()
        );

        if (!$report['ready']) {
            $this->error('Instagram automation is not production-ready yet.');

            return self::FAILURE;
        }

        if ($report['has_warnings']) {
            $this->warn('Instagram automation core checks passed, but warnings need review.');

            return self::SUCCESS;
        }

        $this->info('Instagram automation is production-ready from the application side.');

        return self::SUCCESS;
    }
}
