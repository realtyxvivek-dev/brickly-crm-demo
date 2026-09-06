<?php

namespace App\Console\Commands;

use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDummyLeadsForSalesManager extends Command
{
    protected $signature = 'leads:create-dummy-for-manager
                            {manager_identifier=Satya : Manager email or partial name}
                            {count=5 : Number of leads to create}
                            {--source=website : Lead source}
                            {--cleanup-prefix=SATYA-WEB : Remove earlier dummy leads with this prefix before creating new ones}';

    protected $description = 'Create dummy leads for a sales manager/assistant sales manager with guaranteed calling tasks';

    public function handle(): int
    {
        $managerIdentifier = (string) $this->argument('manager_identifier');
        $count = max(1, (int) $this->argument('count'));
        $source = Lead::normalizeSource((string) $this->option('source'));
        $cleanupPrefix = trim((string) $this->option('cleanup-prefix'));

        DB::beginTransaction();

        try {
            $manager = User::query()
                ->with('role')
                ->where('is_active', true)
                ->whereHas('role', function ($q) {
                    $q->whereIn('slug', [
                        Role::ASSISTANT_SALES_MANAGER,
                        Role::SALES_MANAGER,
                        Role::SENIOR_MANAGER,
                    ]);
                })
                ->where(function ($q) use ($managerIdentifier) {
                    $q->where('email', $managerIdentifier)
                        ->orWhere('name', 'like', '%' . $managerIdentifier . '%');
                })
                ->orderByRaw(
                    "CASE
                        WHEN role_id = (SELECT id FROM roles WHERE slug = ?) THEN 0
                        WHEN role_id = (SELECT id FROM roles WHERE slug = ?) THEN 1
                        ELSE 2
                    END",
                    [Role::ASSISTANT_SALES_MANAGER, Role::SALES_MANAGER]
                )
                ->orderBy('id')
                ->first();

            if (!$manager) {
                $this->error("Manager/ASM matching '{$managerIdentifier}' not found.");
                DB::rollBack();
                return self::FAILURE;
            }

            $this->info("Target user: {$manager->name} ({$manager->email}) [{$manager->role->slug}]");
            $this->info("Creating {$count} dummy leads with source '{$source}'.");

            $adminRole = Role::where('slug', Role::ADMIN)->first();
            $admin = $adminRole
                ? User::where('role_id', $adminRole->id)->where('is_active', true)->first()
                : null;
            $createdBy = $admin?->id ?? $manager->id;

            $firstNames = ['Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Reyansh', 'Kabir', 'Ananya', 'Diya', 'Ishita', 'Kavya', 'Meera', 'Priya', 'Sneha', 'Tanvi', 'Rohit', 'Rahul', 'Amit', 'Karan', 'Sakshi'];
            $lastNames = ['Sharma', 'Patel', 'Singh', 'Verma', 'Gupta', 'Yadav', 'Reddy', 'Chauhan', 'Joshi', 'Agarwal', 'Nair', 'Iyer', 'Menon', 'Saxena', 'Pandey', 'Tiwari', 'Malhotra', 'Kapoor', 'Bansal', 'Mishra'];

            if ($cleanupPrefix !== '') {
                $oldLeads = Lead::where('source', $source)
                    ->where('name', 'like', $cleanupPrefix . '%')
                    ->get();

                foreach ($oldLeads as $oldLead) {
                    Task::where('lead_id', $oldLead->id)->delete();
                    LeadAssignment::where('lead_id', $oldLead->id)->delete();
                    $oldLead->delete();
                }

                if ($oldLeads->isNotEmpty()) {
                    $this->info("Removed {$oldLeads->count()} old dummy leads with prefix '{$cleanupPrefix}'.");
                }
            }

            $leadsCreated = 0;
            $tasksCreated = 0;

            for ($i = 1; $i <= $count; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $name = trim($cleanupPrefix . ' ' . $firstName . ' ' . $lastName);
                $phone = '9' . str_pad((string) rand(0, 999999999), 9, '0', STR_PAD_LEFT);

                $lead = Lead::create([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => Str::slug($firstName . '.' . $lastName . '.' . $i, '.') . '@example.com',
                    'source' => $source,
                    'status' => 'new',
                    'created_by' => $createdBy,
                ]);

                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $manager->id,
                    'assigned_by' => $createdBy,
                    'assignment_type' => 'primary',
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);

                try {
                    event(new LeadAssigned($lead, $manager->id, $createdBy));
                } catch (\Throwable $eventError) {
                    $this->warn("LeadAssigned event warning for lead {$lead->id}: {$eventError->getMessage()}");
                }

                $task = Task::query()
                    ->where('lead_id', $lead->id)
                    ->where('assigned_to', $manager->id)
                    ->where('type', 'phone_call')
                    ->first();

                if (!$task) {
                    $task = Task::create([
                        'lead_id' => $lead->id,
                        'assigned_to' => $manager->id,
                        'type' => 'phone_call',
                        'title' => 'Call lead: ' . $lead->name,
                        'description' => "Phone call task for lead: {$lead->name} ({$lead->phone})",
                        'status' => 'pending',
                        'scheduled_at' => now()->addMinutes(10),
                        'created_by' => $createdBy,
                    ]);
                }

                $leadsCreated++;
                $tasksCreated += $task ? 1 : 0;
                $this->line("Created lead {$lead->id}: {$lead->name} -> task #{$task->id}");
            }

            DB::commit();

            $this->info("Created {$leadsCreated} leads and {$tasksCreated} calling tasks for {$manager->name}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to create dummy leads: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
