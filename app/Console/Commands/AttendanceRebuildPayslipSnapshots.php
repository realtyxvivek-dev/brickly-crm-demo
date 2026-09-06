<?php

namespace App\Console\Commands;

use App\Models\PayrollFreeze;
use App\Models\PayrollPayslip;
use App\Services\PayrollComputationService;
use Illuminate\Console\Command;

class AttendanceRebuildPayslipSnapshots extends Command
{
    protected $signature = 'attendance:rebuild-payslip-snapshots {year} {month}';

    protected $description = 'Rebuild stored payslip snapshots for an already generated payroll month';

    public function handle(PayrollComputationService $service): int
    {
        $freeze = PayrollFreeze::query()
            ->with('items.user')
            ->where('year', (int) $this->argument('year'))
            ->where('month', (int) $this->argument('month'))
            ->where('status', 'frozen')
            ->latest()
            ->first();

        if (!$freeze) {
            $this->error('No frozen payroll found for the selected month.');
            return self::FAILURE;
        }

        foreach ($freeze->items as $item) {
            $payslip = PayrollPayslip::query()
                ->where('user_id', $item->user_id)
                ->where('year', $freeze->year)
                ->where('month', $freeze->month)
                ->first();

            if (!$payslip) {
                continue;
            }

            $payslip->update([
                'snapshot_json' => $service->buildPayslipSnapshotFromFreezeItem($item),
                'status' => 'stale',
            ]);
        }

        $this->info('Payslip snapshots rebuilt and marked stale.');

        return self::SUCCESS;
    }
}
