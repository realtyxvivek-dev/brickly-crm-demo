<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use App\Models\Role;
use App\Events\LeadAssigned;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignLeadToVivek extends Command
{
    protected $signature = 'leads:assign-to-vivek {count=1}';
    protected $description = 'Create lead with Indian name/number, assign to Vivek, with calling task';

    public function handle()
    {
        $count = max(1, min(10, (int) $this->argument('count')));
        $vivek = User::whereRaw('LOWER(name) LIKE ?', ['%vivek%'])
            ->where('is_active', true)
            ->first();

        if (!$vivek) {
            $this->error('User "Vivek" not found.');
            return 1;
        }

        $adminRole = Role::where('slug', Role::ADMIN)->first();
        $createdBy = $adminRole
            ? (User::where('role_id', $adminRole->id)->where('is_active', true)->first()?->id ?? 1)
            : 1;

        $firstNames = ['Raj', 'Priya', 'Amit', 'Neha', 'Rahul', 'Sneha', 'Vikram', 'Anjali', 'Deepak', 'Kavita'];
        $lastNames = ['Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Verma', 'Yadav', 'Reddy', 'Malik', 'Chauhan'];

        DB::beginTransaction();
        try {
            for ($i = 0; $i < $count; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $phone = '9' . str_pad((string) rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);

                $lead = Lead::create([
                    'name' => $firstName . ' ' . $lastName,
                    'phone' => $phone,
                    'source' => 'call',
                    'status' => 'new',
                    'created_by' => $createdBy,
                ]);

                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $vivek->id,
                    'assigned_by' => $createdBy,
                    'assignment_type' => 'primary',
                    'assignment_method' => 'manual',
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);

                try {
                    event(new LeadAssigned($lead, $vivek->id, $createdBy));
                } catch (\Throwable $e) {
                    if (str_contains($e->getMessage(), 'Pusher') || str_contains($e->getMessage(), 'broadcast')) {
                        $this->warn('  (Broadcast/Pusher error ignored - lead and task created)');
                    } else {
                        throw $e;
                    }
                }

                $this->line("  ✓ {$lead->name} ({$lead->phone}) → {$vivek->name}");
            }

            DB::commit();
            $this->info("Created {$count} lead(s) assigned to Vivek with calling task(s).");
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return 1;
        }
    }
}
