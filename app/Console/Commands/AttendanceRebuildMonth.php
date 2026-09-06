<?php

namespace App\Console\Commands;

use App\Services\AttendanceFinalizerService;
use App\Services\PayrollComputationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AttendanceRebuildMonth extends Command
{
    protected $signature = 'attendance:rebuild-month {year} {month}';

    protected $description = 'Rebuild attendance final records and payroll rollups for a month';

    public function handle(
        AttendanceFinalizerService $finalizerService,
        PayrollComputationService $payrollComputationService
    ): int {
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');
        $date = Carbon::create($year, $month, 1);

        foreach (range(1, $date->daysInMonth) as $day) {
            $finalizerService->finalizeForDate(Carbon::create($year, $month, $day));
        }

        $payrollComputationService->computeMonth($year, $month);
        $this->info("Attendance rebuilt for {$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT));

        return self::SUCCESS;
    }
}
