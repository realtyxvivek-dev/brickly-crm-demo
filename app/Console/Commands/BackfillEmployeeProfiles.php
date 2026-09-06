<?php

namespace App\Console\Commands;

use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillEmployeeProfiles extends Command
{
    protected $signature = 'employee:backfill-existing-users {--only-active=0 : Backfill only active users}';

    protected $description = 'Create employee master profiles for existing users without duplicating auth records';

    private const EMPLOYEE_CODE_PREFIXES = [
        Role::ADMIN => 'ADM',
        Role::CRM => 'CRM',
        Role::HR_MANAGER => 'HR',
        Role::FINANCE_MANAGER => 'FIN',
        Role::SALES_MANAGER => 'SM',
        Role::SENIOR_MANAGER => 'SRM',
        Role::ASSISTANT_SALES_MANAGER => 'ASM',
        Role::SALES_EXECUTIVE => 'EXE',
    ];

    public function handle(): int
    {
        $query = User::query()
            ->with(['role', 'attendanceProfile', 'employeeProfile'])
            ->when($this->option('only-active'), fn ($builder) => $builder->where('is_active', true))
            ->whereDoesntHave('employeeProfile');

        $created = 0;

        $query->chunkById(100, function ($users) use (&$created) {
            foreach ($users as $user) {
                EmployeeProfile::query()->create([
                    'user_id' => $user->id,
                    'employee_code' => $user->attendanceProfile?->employee_code ?: $this->generateEmployeeCode($user),
                    'joining_date' => optional($user->created_at)->toDateString(),
                    'employment_status' => $user->is_active ? EmployeeProfile::STATUS_ACTIVE : EmployeeProfile::STATUS_TERMINATED,
                    'employment_status_changed_at' => now(),
                    'salary_day_of_month' => 1,
                ]);

                $created++;
            }
        });

        $this->info("Employee profile backfill complete. Created {$created} records.");

        return self::SUCCESS;
    }

    private function generateEmployeeCode(User $user): string
    {
        $prefix = self::EMPLOYEE_CODE_PREFIXES[$user->role?->slug] ?? 'EMP';

        $maxNumber = EmployeeProfile::query()
            ->where('employee_code', 'like', $prefix . '%')
            ->get()
            ->map(fn (EmployeeProfile $profile) => (int) str_replace($prefix, '', $profile->employee_code))
            ->max() ?? 0;

        return sprintf('%s%04d', $prefix, $maxNumber + 1);
    }
}
