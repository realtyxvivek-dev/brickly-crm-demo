<?php

namespace App\Console\Commands;

use App\Models\PayrollFreeze;
use App\Services\PayslipGenerationService;
use Illuminate\Console\Command;

class AttendanceGeneratePayslips extends Command
{
    protected $signature = 'attendance:generate-payslips {year} {month}';

    protected $description = 'Generate payslips for a frozen payroll month';

    public function handle(PayslipGenerationService $service): int
    {
        $freeze = PayrollFreeze::query()
            ->where('year', (int) $this->argument('year'))
            ->where('month', (int) $this->argument('month'))
            ->where('status', 'frozen')
            ->latest()
            ->first();

        if (!$freeze) {
            $this->error('No frozen payroll found for the selected month.');
            return self::FAILURE;
        }

        $count = $service->generateForFreeze($freeze);
        $this->info("Generated {$count} payslips.");

        return self::SUCCESS;
    }
}
