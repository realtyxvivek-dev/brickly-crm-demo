<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SeedLeadsPendingResponse extends Command
{
    protected $signature = 'leads:seed-pending-response {count=10}';
    protected $description = 'Create sample leads (assignment only, no task/CRM) for testing "Leads Allocated – No Response Yet" with varied assigned_at times';

    public function handle()
    {
        $count = (int) $this->argument('count');
        $count = max(1, min(20, $count));

        $salesExecutiveRole = Role::where('slug', Role::SALES_EXECUTIVE)->first();
        if (!$salesExecutiveRole) {
            $this->error('Sales Executive role not found.');
            return 1;
        }

        $executives = User::where('role_id', $salesExecutiveRole->id)
            ->where('is_active', true)
            ->take(2)
            ->get();

        if ($executives->isEmpty()) {
            $this->error('No active Sales Executives found.');
            return 1;
        }

        $adminRole = Role::where('slug', Role::ADMIN)->first();
        $createdBy = $adminRole
            ? (User::where('role_id', $adminRole->id)->where('is_active', true)->first()?->id ?? 1)
            : 1;

        $assignedAtVariants = [
            [Carbon::now(), 'just now'],
            [Carbon::now()->subMinutes(30), '30m ago'],
            [Carbon::now()->subHours(2), '2h ago'],
            [Carbon::now()->subDay(), '1d ago'],
        ];

        DB::beginTransaction();
        try {
            $created = 0;
            for ($i = 1; $i <= $count; $i++) {
                $variant = $assignedAtVariants[($i - 1) % count($assignedAtVariants)];
                $assignedAt = $variant[0];
                $executive = $executives[$created % $executives->count()];

                $phone = '98765' . str_pad((string) (10000 + $created), 5, '0', STR_PAD_LEFT);
                if (Lead::where('phone', $phone)->exists()) {
                    $phone = '98765' . str_pad((string) (50000 + $created + $i), 5, '0', STR_PAD_LEFT);
                }

                $lead = Lead::create([
                    'name' => 'Sample Pending ' . $i,
                    'phone' => $phone,
                    'source' => 'sample_pending',
                    'status' => 'new',
                    'created_by' => $createdBy,
                ]);

                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $executive->id,
                    'assigned_by' => $createdBy,
                    'assignment_type' => 'primary',
                    'assignment_method' => 'manual',
                    'assigned_at' => $assignedAt,
                    'is_active' => true,
                ]);

                $this->line("  ✓ Lead #{$i}: {$lead->name} → {$executive->name} (assigned {$variant[1]})");
                $created++;
            }

            DB::commit();
            $this->info("Created {$created} sample leads (source: sample_pending). They will appear in 'Leads Allocated – No Response Yet'.");
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return 1;
        }
    }
}
