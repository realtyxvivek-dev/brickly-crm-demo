<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use App\Models\Role;
use App\Models\TelecallerTask;
use App\Models\CrmAssignment;
use App\Events\LeadAssigned;
use App\Services\TelecallerTaskService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DummyLeadsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 20 dummy leads: 10 leads for each of 2 telecallers
     * Each lead has source='csv' and auto-creates calling tasks
     */
    public function run()
    {
        DB::beginTransaction();
        
        try {
            // Get sales executive role
            $salesExecutiveRole = Role::where('slug', Role::SALES_EXECUTIVE)->first();
            if (!$salesExecutiveRole) {
                $this->command->error('Sales Executive role not found! Please ensure sales_executive role exists in database.');
                return;
            }
            
            // Get first 2 active sales executives
            $salesExecutives = User::where('role_id', $salesExecutiveRole->id)
                ->where('is_active', true)
                ->take(2)
                ->get();
            
            if ($salesExecutives->count() < 2) {
                $this->command->warn("Only {$salesExecutives->count()} sales executive(s) found. Need at least 2 to create dummy leads.");
                return;
            }
            
            // Get admin user for created_by
            $adminRole = Role::where('slug', Role::ADMIN)->first();
            $admin = $adminRole ? User::where('role_id', $adminRole->id)->where('is_active', true)->first() : null;
            $createdBy = $admin ? $admin->id : 1; // Use admin ID or fallback to 1
            
            $totalLeadsCreated = 0;
            
            // Random Indian first and last names
            $firstNames = ['Raj', 'Priya', 'Amit', 'Neha', 'Rahul', 'Sneha', 'Vikram', 'Anjali', 'Deepak', 'Kavita', 'Arjun', 'Swati', 'Rohit', 'Pooja', 'Sachin', 'Divya', 'Karan', 'Meera', 'Vishal', 'Tanvi'];
            $lastNames = ['Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Verma', 'Yadav', 'Reddy', 'Malik', 'Chauhan', 'Shah', 'Mehta', 'Joshi', 'Desai', 'Agarwal', 'Gandhi', 'Nair', 'Rao', 'Iyer', 'Menon'];
            
            // First, remove old dummy leads with old naming pattern
            $oldLeads = Lead::where('source', 'csv')
                ->where('name', 'like', 'Dummy Lead%')
                ->get();
            
            if ($oldLeads->count() > 0) {
                $this->command->info("Removing {$oldLeads->count()} old dummy leads with old naming pattern...");
                foreach ($oldLeads as $oldLead) {
                    // Delete related records first
                    TelecallerTask::where('lead_id', $oldLead->id)->delete();
                    CrmAssignment::where('lead_id', $oldLead->id)->delete();
                    LeadAssignment::where('lead_id', $oldLead->id)->delete();
                    $oldLead->delete();
                }
                $this->command->info("✅ Removed {$oldLeads->count()} old dummy leads");
            }
            
            foreach ($salesExecutives as $executive) {
                $this->command->info("Creating 10 leads for sales executive: {$executive->name} (ID: {$executive->id})");
                
                for ($i = 1; $i <= 10; $i++) {
                    // Generate random Indian name
                    $firstName = $firstNames[array_rand($firstNames)];
                    $lastName = $lastNames[array_rand($lastNames)];
                    $randomName = $firstName . ' ' . $lastName;
                    
                    // Generate random 10-digit phone number starting with 9
                    $randomPhone = '9' . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);
                    
                    // Create lead with random dummy data
                    $lead = Lead::create([
                        'name' => $randomName,
                        'phone' => $randomPhone,
                        'source' => 'csv',
                        'status' => 'new',
                        'created_by' => $createdBy,
                    ]);
                    
                    // Create assignment
                    $assignment = LeadAssignment::create([
                        'lead_id' => $lead->id,
                        'assigned_to' => $executive->id,
                        'assigned_by' => $createdBy,
                        'assignment_type' => 'primary',
                        'assigned_at' => now(),
                        'is_active' => true,
                    ]);
                    
                    // Fire LeadAssigned event - this will auto-create calling task via CreateTelecallerTask listener
                    try {
                        event(new LeadAssigned($lead, $executive->id, $createdBy));
                    } catch (\Exception $e) {
                        Log::warning("Broadcasting error in seeder (non-critical): " . $e->getMessage());
                    }
                    
                    // Manual fallback: Ensure task and CrmAssignment are created even if event fails
                    if ($executive->role && $executive->role->slug === Role::SALES_EXECUTIVE) {
                        $crmAssignment = CrmAssignment::firstOrCreate(
                            [
                                'lead_id' => $lead->id,
                                'assigned_to' => $executive->id,
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
                            ->where('assigned_to', $executive->id)
                            ->where('task_type', 'calling')
                            ->where('status', 'pending')
                            ->first();
                            
                        if (!$existingTask) {
                            try {
                                $taskService = app(TelecallerTaskService::class);
                                $taskService->createCallingTask($lead, $executive, $createdBy);
                            } catch (\Exception $e) {
                                Log::error("Failed to create task manually in seeder: " . $e->getMessage());
                            }
                        }
                    }
                    
                    $this->command->line("  ✓ Created lead: {$lead->name} (Phone: {$lead->phone})");
                    $totalLeadsCreated++;
                }
                
                $this->command->info("  → Created 10 leads with random names for {$executive->name}");
            }
            
            DB::commit();
            
            // Verify tasks were created (check for leads created in this seeder run)
            // Get lead IDs created in this run
            $createdLeadIds = Lead::where('source', 'csv')
                ->where('created_at', '>=', now()->subMinute())
                ->pluck('id');
            
            $tasksCreated = \App\Models\TelecallerTask::whereIn('lead_id', $createdLeadIds)
                ->where('task_type', 'calling')
                ->where('status', 'pending')
                ->count();
            
            $crmAssignmentsCreated = \App\Models\CrmAssignment::whereIn('lead_id', $createdLeadIds)->count();
            
            $this->command->info("");
            $this->command->info("✅ Successfully created {$totalLeadsCreated} dummy leads with CSV source!");
            $this->command->info("✅ Created {$totalLeadsCreated} lead assignments");
            $this->command->info("✅ Created {$tasksCreated} calling tasks (auto-created via LeadAssigned event)");
            $this->command->info("✅ Created {$crmAssignmentsCreated} CRM assignments");
            $this->command->info("");
            
            if ($tasksCreated < $totalLeadsCreated) {
                $this->command->warn("⚠️  Warning: Expected {$totalLeadsCreated} tasks but found {$tasksCreated}. Some tasks may not have been auto-created.");
                $this->command->info("   This could happen if the assigned users are not sales executives or if tasks already existed.");
            } else {
                $this->command->info("✅ All calling tasks successfully auto-created!");
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("");
            $this->command->error("❌ Failed to create dummy leads: " . $e->getMessage());
            $this->command->error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
}
