<?php

namespace App\Console\Commands;

use App\Models\LeadBankAllocation;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadBankAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncLeadBankProtectedAllocations extends Command
{
    protected $signature = 'lead-bank:sync-protected {--limit=500} {--dry-run}';

    protected $description = 'Mark active Lead Bank allocations as protected or converted when lead status qualifies.';

    public function handle(LeadBankAllocationService $allocationService): int
    {
        if (!Schema::hasTable('lead_bank_allocations')) {
            $this->warn('lead_bank_allocations table not found. Run php artisan migrate before enabling Lead Bank lifecycle automation.');
            return self::SUCCESS;
        }

        $limit = max(1, min(5000, (int) $this->option('limit')));

        $query = LeadBankAllocation::query()
            ->where('status', LeadBankAllocation::STATUS_ACTIVE)
            ->whereHas('lead', fn ($leadQuery) => $leadQuery->whereIn('status', LeadBankAllocationService::PROTECTED_STATUSES));

        if ($this->option('dry-run')) {
            $this->info('Protectable active allocations found: ' . $query->count());
            return self::SUCCESS;
        }

        $result = $allocationService->syncProtectedAllocations($this->systemUser(), $limit);

        $this->info("Protected sync completed. Protected {$result['protected']} allocation(s), converted {$result['converted']} allocation(s).");

        return self::SUCCESS;
    }

    private function systemUser(): ?User
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [Role::ADMIN, Role::CRM]))
            ->orderBy('id')
            ->first();
    }
}
