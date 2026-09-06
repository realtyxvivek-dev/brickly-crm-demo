<?php

namespace App\Console\Commands;

use App\Services\AttendanceFinalizerService;
use Illuminate\Console\Command;

class AttendanceFinalizeDay extends Command
{
    protected $signature = 'attendance:finalize-day {date?}';

    protected $description = 'Finalize attendance records for a given date';

    public function handle(AttendanceFinalizerService $finalizerService): int
    {
        $finalizerService->finalizeForDate($this->argument('date') ?: now()->toDateString());

        return self::SUCCESS;
    }
}
