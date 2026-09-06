<?php

namespace App\Console\Commands;

use App\Services\AsmCnpAutomationService;
use Illuminate\Console\Command;

class ProcessAsmCnpAutomation extends Command
{
    protected $signature = 'asm-cnp:process';

    protected $description = 'Process ASM fresh lead CNP automation transfers';

    public function handle(AsmCnpAutomationService $service): int
    {
        $result = $service->processDueTransfers();

        $this->info(sprintf(
            'Processed: %d | Transferred: %d | Quarantined: %d | Cancelled: %d | Skipped: %d',
            $result['processed'] ?? 0,
            $result['transferred'] ?? 0,
            $result['quarantined'] ?? 0,
            $result['cancelled'] ?? 0,
            $result['skipped'] ?? 0
        ));

        return self::SUCCESS;
    }
}
