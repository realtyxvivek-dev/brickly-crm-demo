<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;

class UpdateSalesManagers extends Command
{
    protected $signature = 'users:update-sales-managers';
    protected $description = 'Update Shushank Shukla and Satya Pandey to Senior Manager, assign Arpit as manager to independent users';

    public function handle()
    {
        // Get roles
        $salesManagerRole = Role::where('slug', 'sales_manager')->first();
        $salesExecutiveRole = Role::where('slug', 'sales_executive')->first();
        
        if (!$salesManagerRole || !$salesExecutiveRole) {
            $this->error('Required roles not found!');
            return Command::FAILURE;
        }
        
        // Find Arpit (Sales Head)
        $arpit = User::where('name', 'Arpit')->first();
        if (!$arpit) {
            $this->error('Arpit (Sales Head) not found!');
            return Command::FAILURE;
        }
        
        // 1. Update Shushank Shukla to Senior Manager (under Arpit)
        $shushank = User::where('name', 'Shushank Shukla')->first();
        if ($shushank) {
            $oldRole = $shushank->role->name;
            $oldManager = $shushank->manager ? $shushank->manager->name : 'None';
            
            $shushank->update([
                'role_id' => $salesManagerRole->id,
                'manager_id' => $arpit->id,
            ]);
            
            $this->info("Updated Shushank Shukla: Role changed from '{$oldRole}' to 'Senior Manager', Manager changed from '{$oldManager}' to 'Arpit'");
        } else {
            $this->warn('Shushank Shukla not found!');
        }
        
        // 2. Update Satya Pandey to Senior Manager (under Arpit)
        $satya = User::where('name', 'Satya Pandey')->first();
        if ($satya) {
            $oldRole = $satya->role->name;
            $oldManager = $satya->manager ? $satya->manager->name : 'None';
            
            $satya->update([
                'role_id' => $salesManagerRole->id,
                'manager_id' => $arpit->id,
            ]);
            
            $this->info("Updated Satya Pandey: Role changed from '{$oldRole}' to 'Senior Manager', Manager changed from '{$oldManager}' to 'Arpit'");
        } else {
            $this->warn('Satya Pandey not found!');
        }
        
        // 3. Update independent users to have Arpit as manager
        $independentUsers = ['Alpish', 'Mohit Yadav', 'Ayushi'];
        
        foreach ($independentUsers as $userName) {
            $user = User::where('name', $userName)->first();
            if ($user) {
                $oldManager = $user->manager ? $user->manager->name : 'None';
                $user->update([
                    'manager_id' => $arpit->id,
                ]);
                $this->info("Updated {$userName}: Manager changed from '{$oldManager}' to 'Arpit'");
            } else {
                $this->warn("User '{$userName}' not found!");
            }
        }
        
        // Display updated hierarchy
        $this->newLine();
        $this->info('=== Updated Hierarchy ===');
        
        $arpit->refresh();
        $arpitTeam = $arpit->teamMembers()->with('role')->get();
        $this->info("Arpit (Sales Head - Senior Manager):");
        
        foreach ($arpitTeam as $member) {
            $this->line("  ├── {$member->name} ({$member->role->name})");
            
            // Show sub-teams for Senior Managers
            if ($member->role->slug === 'sales_manager') {
                $subTeam = $member->teamMembers()->with('role')->get();
                foreach ($subTeam as $subMember) {
                    $this->line("  │   └── {$subMember->name} ({$subMember->role->name})");
                }
            }
        }
        
        return Command::SUCCESS;
    }
}

