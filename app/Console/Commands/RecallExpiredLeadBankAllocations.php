<?php

namespace App\Console\Commands;

use App\Models\LeadBankAllocation;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadBankAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RecallExpiredLeadBankAllocations extends Command
{
    protected $signature = 'lead-bank:recall-expired {--cooldown-days=7} {--dry-run}';

    protected $description = 'Recall expired temporary Lead Bank allocations and apply cooldown.';

    public function handle(LeadBankAllocationService $allocationService): int
    {
        if (!Schema::hasTable('lead_bank_allocations')) {
            $this->warn('lead_bank_allocations table not found. Run php artisan migrate before enabling Lead Bank lifecycle automation.');
            return self::SUCCESS;
        }

        $cooldownDays = max(1, min(90, (int) $this->option('cooldown-days')));

        $expiredCount = LeadBankAllocation::query()
            ->where('status', LeadBankAllocation::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();

        if ($this->option('dry-run')) {
            $this->info("Expired active allocations found: {$expiredCount}");
            return self::SUCCESS;
        }

        if ($expiredCount === 0) {
            $this->info('No expired Lead Bank allocations found.');
            return self::SUCCESS;
        }

        $systemUser = $this->systemUser();
        if (!$systemUser) {
            $this->error('No admin or CRM user found to attribute Lead Bank recall audit.');
            return self::FAILURE;
        }

        $result = $allocationService->recallExpired($systemUser, $cooldownDays);

        $this->info("Expired recall completed. Recalled {$result['recalled']} allocation(s), protected {$result['protected']} allocation(s).");

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
