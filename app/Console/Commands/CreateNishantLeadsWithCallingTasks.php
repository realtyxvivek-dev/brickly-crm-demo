<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\TelecallerTask;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateNishantLeadsWithCallingTasks extends Command
{
    protected $signature = 'crm:create-nishant-leads-with-tasks';
    protected $description = 'Create 10 leads with Indian names assigned to Nishant (Sales Executive) and a calling task for each';

    public function handle(): int
    {
        $seRole = Role::where('slug', Role::SALES_EXECUTIVE)->first();
        if (!$seRole) {
            $this->error('Sales Executive role not found. Run: php artisan db:seed --class=RoleSeeder');
            return 1;
        }

        $nishant = User::where('email', 'nishant@realtorcrm.com')->first();
        if (!$nishant) {
            $asmRole = Role::where('slug', Role::ASSISTANT_SALES_MANAGER)->first();
            $mahaveer = $asmRole ? User::where('email', 'mahaveer@realtorcrm.com')->first() : null;
            $nishant = User::create([
                'name' => 'Nishant',
                'email' => 'nishant@realtorcrm.com',
                'password' => Hash::make('nishant123'),
                'role_id' => $seRole->id,
                'manager_id' => $mahaveer?->id,
                'is_active' => true,
            ]);
            $this->info("Created Nishant (Sales Executive) – ID: {$nishant->id}");
        } else {
            $this->info("Using Nishant (Sales Executive) – ID: {$nishant->id}");
        }

        $admin = User::whereHas('role', fn($q) => $q->where('slug', Role::ADMIN))->where('is_active', true)->first();
        $createdBy = $admin ? $admin->id : 1;

        // 10 distinct Indian names (first + last)
        $indianNames = [
            ['Raj', 'Sharma'],
            ['Priya', 'Patel'],
            ['Amit', 'Singh'],
            ['Neha', 'Kumar'],
            ['Rahul', 'Gupta'],
            ['Sneha', 'Verma'],
            ['Vikram', 'Yadav'],
            ['Anjali', 'Reddy'],
            ['Deepak', 'Nair'],
            ['Kavita', 'Iyer'],
        ];

        DB::beginTransaction();
        try {
            $leadsCreated = 0;
            $tasksCreated = 0;

            foreach ($indianNames as $index => [$first, $last]) {
                $name = "{$first} {$last}";
                $phone = '9' . str_pad((string) (800000001 + $index), 9, '0', STR_PAD_LEFT);

                $lead = Lead::create([
                    'name' => $name,
                    'phone' => $phone,
                    'source' => 'call',
                    'status' => 'new',
                    'created_by' => $createdBy,
                ]);

                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $nishant->id,
                    'assigned_by' => $createdBy,
                    'assignment_type' => 'primary',
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);

                $existing = TelecallerTask::where('lead_id', $lead->id)
                    ->where('assigned_to', $nishant->id)
                    ->where('task_type', 'calling')
                    ->first();

                if (!$existing) {
                    TelecallerTask::create([
                        'lead_id' => $lead->id,
                        'assigned_to' => $nishant->id,
                        'task_type' => 'calling',
                        'status' => 'pending',
                        'scheduled_at' => now()->subMinutes(15),
                        'created_by' => $createdBy,
                    ]);
                    $tasksCreated++;
                }

                $leadsCreated++;
                $this->line("  ✓ {$name} – lead + calling task");
            }

            DB::commit();
            $this->newLine();
            $this->info("Done. {$leadsCreated} leads with Indian names assigned to Nishant; {$tasksCreated} calling tasks created.");
            $this->info('Login as Nishant and open Task to see them.');
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
