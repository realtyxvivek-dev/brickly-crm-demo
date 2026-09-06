<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use App\Models\Role;
use App\Models\TelecallerTask;
use App\Models\CrmAssignment;
use App\Services\TelecallerTaskService;
use App\Events\LeadAssigned;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CreateDummyLeadsForRitika extends Command
{
    protected $signature = 'leads:create-dummy-for-ritika {count=10}';
    protected $description = 'Create dummy leads for Ritika user with calling tasks';

    public function handle()
    {
        $count = (int) $this->argument('count');
        
        DB::beginTransaction();
        
        try {
            // Find Ritika user (by name or email)
            $ritika = User::where(function($q) {
                $q->where('name', 'like', '%ritika%')
                  ->orWhere('name', 'like', '%Ritika%')
                  ->orWhere('email', 'like', '%ritika%');
            })->first();
            
            if (!$ritika) {
                $this->error("Ritika user not found! Please check if user exists with name or email containing 'ritika'.");
                return 1;
            }
            
            // Verify user is a sales executive
            if (!$ritika->role || $ritika->role->slug !== Role::SALES_EXECUTIVE) {
                $this->warn("User '{$ritika->name}' is not a sales executive. Current role: " . ($ritika->role ? $ritika->role->slug : 'none'));
                $this->warn("Proceeding anyway, but tasks will be created as TelecallerTask...");
            }
            
            $this->info("Creating {$count} dummy leads for: {$ritika->name} (ID: {$ritika->id}, Email: {$ritika->email})");
            
            // Get admin user for created_by
            $adminRole = Role::where('slug', Role::ADMIN)->first();
            $admin = $adminRole ? User::where('role_id', $adminRole->id)->where('is_active', true)->first() : null;
            $createdBy = $admin ? $admin->id : 1;
            
            // Random Indian names
            $firstNames = ['Raj', 'Priya', 'Amit', 'Neha', 'Rahul', 'Sneha', 'Vikram', 'Anjali', 'Deepak', 'Kavita', 'Arjun', 'Swati', 'Rohit', 'Pooja', 'Sachin', 'Divya', 'Karan', 'Meera', 'Vishal', 'Tanvi'];
            $lastNames = ['Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Verma', 'Yadav', 'Reddy', 'Malik', 'Chauhan', 'Shah', 'Mehta', 'Joshi', 'Desai', 'Agarwal', 'Gandhi', 'Nair', 'Rao', 'Iyer', 'Menon'];
            
            $leadsCreated = 0;
            $tasksCreated = 0;
            $taskService = app(TelecallerTaskService::class);
            
            for ($i = 1; $i <= $count; $i++) {
                // Generate random Indian name
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $randomName = $firstName . ' ' . $lastName;
                
                // Generate random 10-digit phone number starting with 9
                $randomPhone = '9' . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                
                // Create lead with source='csv' (similar to DummyLeadsSeeder)
                $lead = Lead::create([
                    'name' => $randomName,
                    'phone' => $randomPhone,
                    'source' => 'csv',
                    'status' => 'new',
                    'created_by' => $createdBy,
                ]);
                
                // Create assignment to Ritika
                $assignment = LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $ritika->id,
                    'assigned_by' => $createdBy,
                    'assignment_type' => 'primary',
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);
                
                // Create CrmAssignment
                $crmAssignment = CrmAssignment::firstOrCreate(
                    [
                        'lead_id' => $lead->id,
                        'assigned_to' => $ritika->id,
                        'call_status' => 'pending',
                    ],
                    [
                        'customer_name' => $lead->name,
                        'phone' => $lead->phone,
                        'assigned_by' => $createdBy,
                        'assigned_at' => now(),
                    ]
                );
                
                // Create calling task using TelecallerTaskService
                try {
                    $task = $taskService->createCallingTask($lead, $ritika, $createdBy);
                    $tasksCreated++;
                } catch (\Exception $e) {
                    Log::error("Failed to create calling task for lead {$lead->id}: " . $e->getMessage());
                    $this->warn("  ⚠ Failed to create task for lead: {$lead->name}");
                }
                
                $this->line("  ✓ Created lead: {$lead->name} (Phone: {$lead->phone})");
                $leadsCreated++;
            }
            
            DB::commit();
            
            $this->info("");
            $this->info("✅ Successfully created {$leadsCreated} dummy leads for {$ritika->name}!");
            $this->info("✅ Created {$leadsCreated} lead assignments");
            $this->info("✅ Created {$tasksCreated} calling tasks");
            $this->info("");
            
            return 0;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("");
            $this->error("❌ Failed to create dummy leads: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}
