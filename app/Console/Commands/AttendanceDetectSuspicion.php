<?php

namespace App\Console\Commands;

use App\Services\SuspicionDetectionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AttendanceDetectSuspicion extends Command
{
    protected $signature = 'attendance:detect-suspicion {date?}';

    protected $description = 'Re-scan punch events for suspicion flags';

    public function handle(SuspicionDetectionService $suspicionDetectionService): int
    {
        $date = Carbon::parse($this->argument('date') ?: now()->toDateString());
        $count = $suspicionDetectionService->reviewOpenForDate($date);

        $this->info("Suspicion scan complete for {$date->toDateString()}. New flags logged: {$count}");

        return self::SUCCESS;
    }
}
