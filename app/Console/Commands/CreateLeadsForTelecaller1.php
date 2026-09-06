<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use App\Models\Role;
use App\Models\TelecallerTask;
use App\Models\CrmAssignment;
use App\Events\LeadAssigned;
use App\Services\TelecallerTaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateLeadsForTelecaller1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:create-for-telecaller1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create 20 leads with random Indian names for Telecaller 1 (source: csv)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::beginTransaction();

        try {
            // Find Telecaller 1
            $telecaller = User::where('email', 'telecaller1@realtorcrm.com')->first();

            if (!$telecaller) {
                $this->error('Telecaller 1 not found! Please ensure user with email "telecaller1@realtorcrm.com" exists.');
                return Command::FAILURE;
            }

            // Verify it's a sales executive
            if (!$telecaller->isSalesExecutive()) {
                $this->error("User '{$telecaller->name}' is not a sales executive!");
                return Command::FAILURE;
            }

            $this->info("Found Telecaller: {$telecaller->name} (ID: {$telecaller->id})");

            // Get admin user for created_by
            $adminRole = Role::where('slug', Role::ADMIN)->first();
            $admin = $adminRole ? User::where('role_id', $adminRole->id)->where('is_active', true)->first() : null;
            $createdBy = $admin ? $admin->id : 1; // Use admin ID or fallback to 1

            // Random Indian first and last names - expanded list for 20 unique leads
            $firstNames = [
                'Raj', 'Priya', 'Amit', 'Neha', 'Rahul', 'Sneha', 'Vikram', 'Anjali', 'Deepak', 'Kavita',
                'Arjun', 'Swati', 'Rohit', 'Pooja', 'Sachin', 'Divya', 'Karan', 'Meera', 'Vishal', 'Tanvi',
                'Aditya', 'Ananya', 'Rohan', 'Simran', 'Manish', 'Shreya', 'Nikhil', 'Kriti', 'Suresh', 'Nisha'
            ];
            $lastNames = [
                'Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Verma', 'Yadav', 'Reddy', 'Malik', 'Chauhan',
                'Shah', 'Mehta', 'Joshi', 'Desai', 'Agarwal', 'Gandhi', 'Nair', 'Rao', 'Iyer', 'Menon',
                'Pandey', 'Thakur', 'Kapoor', 'Bansal', 'Arora', 'Goyal', 'Aggarwal', 'Saxena', 'Mittal', 'Bhatia'
            ];

            $this->info("Creating 20 leads for Telecaller 1...");
            $this->newLine();

            $totalLeadsCreated = 0;
            $createdLeadIds = [];

            for ($i = 1; $i <= 20; $i++) {
                // Generate random Indian name
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $randomName = $firstName . ' ' . $lastName;

                // Generate random 10-digit phone number starting with 9
                $randomPhone = '9' . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);

                // Ensure phone number is unique (check if exists)
                while (Lead::where('phone', $randomPhone)->exists()) {
                    $randomPhone = '9' . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                }

                // Create lead with random dummy data
                $lead = Lead::create([
                    'name' => $randomName,
                    'phone' => $randomPhone,
                    'source' => 'csv',
                    'status' => 'new',
                    'created_by' => $createdBy,
                ]);

                $createdLeadIds[] = $lead->id;

                // Create assignment
                $assignment = LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $telecaller->id,
                    'assigned_by' => $createdBy,
                    'assignment_type' => 'primary',
                    'assignment_method' => 'manual',
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);

                // Fire LeadAssigned event - this will auto-create calling task via CreateTelecallerTask listener
                // Wrap in try-catch to handle broadcasting errors (Pusher may not be configured)
                try {
                    event(new LeadAssigned($lead, $telecaller->id, $createdBy));
                } catch (\Exception $e) {
                    // Broadcasting errors (like Pusher) shouldn't stop command
                    // Log but continue - listeners may have already run
                    Log::warning("Broadcasting error in command (non-critical): " . $e->getMessage());
                }

                // Manual fallback: Ensure task and CrmAssignment are created even if event fails
                // Check if sales executive role is correct
                if ($telecaller->role && $telecaller->role->slug === Role::SALES_EXECUTIVE) {
                    // Create CrmAssignment if not exists
                    $crmAssignment = CrmAssignment::firstOrCreate(
                        [
                            'lead_id' => $lead->id,
                            'assigned_to' => $telecaller->id,
                            'call_status' => 'pending',
                        ],
                        [
                            'customer_name' => $lead->name,
                            'phone' => $lead->phone,
                            'assigned_by' => $createdBy,
                            'assigned_at' => now(),
                        ]
                    );

                    // Create TelecallerTask if not exists
                    $existingTask = TelecallerTask::where('lead_id', $lead->id)
                        ->where('assigned_to', $telecaller->id)
                        ->where('task_type', 'calling')
                        ->where('status', 'pending')
                        ->first();

                    if (!$existingTask) {
                        try {
                            $taskService = app(TelecallerTaskService::class);
                            $taskService->createCallingTask($lead, $telecaller, $createdBy);
                        } catch (\Exception $e) {
                            Log::error("Failed to create task manually in command: " . $e->getMessage());
                        }
                    }
                }

                $this->line("  ✓ Created lead {$i}/20: {$lead->name} (Phone: {$lead->phone})");
                $totalLeadsCreated++;
            }

            DB::commit();

            // Verify tasks were created
            $tasksCreated = TelecallerTask::whereIn('lead_id', $createdLeadIds)
                ->where('task_type', 'calling')
                ->where('status', 'pending')
                ->count();

            $crmAssignmentsCreated = CrmAssignment::whereIn('lead_id', $createdLeadIds)->count();

            $this->newLine();
            $this->info("✅ Successfully created {$totalLeadsCreated} leads with CSV source for Telecaller 1!");
            $this->info("✅ Created {$totalLeadsCreated} lead assignments");
            $this->info("✅ Created {$tasksCreated} calling tasks (auto-created via LeadAssigned event)");
            $this->info("✅ Created {$crmAssignmentsCreated} CRM assignments");
            $this->newLine();

            if ($tasksCreated < $totalLeadsCreated) {
                $this->warn("⚠️  Warning: Expected {$totalLeadsCreated} tasks but found {$tasksCreated}.");
            } else {
                $this->info("✅ All calling tasks successfully auto-created!");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("");
            $this->error("❌ Failed to create leads: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
