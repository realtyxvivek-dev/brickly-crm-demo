<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UpdateUserHierarchy extends Command
{
    protected $signature = 'users:update-hierarchy';
    protected $description = 'Update user hierarchy: Create Arpit as Sales Head, update Omkar, Naveen, Mahaveer, and Akash';

    public function handle()
    {
        // Get roles
        $salesManagerRole = Role::where('slug', 'sales_manager')->first();
        $salesExecutiveRole = Role::where('slug', 'sales_executive')->first();
        
        if (!$salesManagerRole || !$salesExecutiveRole) {
            $this->error('Required roles not found!');
            return Command::FAILURE;
        }
        
        // 1. Create Arpit as Sales Head (Senior Manager with no manager)
        $arpit = User::firstOrCreate(
            ['email' => 'arpit@imported.local'],
            [
                'name' => 'Arpit',
                'password' => Hash::make('password123'),
                'role_id' => $salesManagerRole->id,
                'manager_id' => null,
                'is_active' => true,
            ]
        );
        
        if ($arpit->wasRecentlyCreated) {
            $this->info('Created new user: Arpit (Sales Head)');
        } else {
            // Update if exists
            $arpit->update([
                'name' => 'Arpit',
                'role_id' => $salesManagerRole->id,
                'manager_id' => null,
            ]);
            $this->info('Updated user: Arpit (Sales Head)');
        }
        
        // 2. Update Omkar to Senior Manager (under Arpit)
        $omkar = User::where('name', 'Omkar')->first();
        if ($omkar) {
            $oldRole = $omkar->role->name;
            $omkar->update([
                'role_id' => $salesManagerRole->id,
                'manager_id' => $arpit->id,
            ]);
            $this->info("Updated Omkar: Role changed from '{$oldRole}' to 'Senior Manager', Manager set to 'Arpit'");
        } else {
            $this->warn('Omkar not found!');
        }
        
        // 3. Update Naveen and Mahaveer to Sales Executive (under Omkar)
        $usersToUpdate = ['Naveen', 'Mahaveer'];
        foreach ($usersToUpdate as $userName) {
            $user = User::where('name', $userName)->first();
            if ($user) {
                $oldRole = $user->role->name;
                $oldManager = $user->manager ? $user->manager->name : 'None';
                
                $user->update([
                    'role_id' => $salesExecutiveRole->id,
                    'manager_id' => $omkar ? $omkar->id : null,
                ]);
                
                $this->info("Updated {$userName}: Role changed from '{$oldRole}' to 'Sales Executive', Manager changed from '{$oldManager}' to 'Omkar'");
            } else {
                $this->warn("User '{$userName}' not found!");
            }
        }
        
        // 4. Update Akash - Remove from Sales Head (set manager to Arpit)
        $akash = User::where('name', 'Akash')->first();
        if ($akash) {
            $oldManager = $akash->manager ? $akash->manager->name : 'None';
            $akash->update([
                'manager_id' => $arpit->id,
            ]);
            $this->info("Updated Akash: Manager changed from '{$oldManager}' to 'Arpit' (removed from Sales Head)");
        } else {
            $this->warn('Akash not found!');
        }
        
        // Display final hierarchy
        $this->newLine();
        $this->info('=== Updated Hierarchy ===');
        
        // Arpit's team
        $arpit->refresh();
        $arpitTeam = $arpit->teamMembers()->with('role')->get();
        $this->info("Arpit (Sales Head - Senior Manager):");
        foreach ($arpitTeam as $member) {
            $this->line("  ├── {$member->name} ({$member->role->name})");
            if ($member->name === 'Omkar') {
                $omkarTeam = $member->teamMembers()->with('role')->get();
                foreach ($omkarTeam as $subMember) {
                    $this->line("  │   └── {$subMember->name} ({$subMember->role->name})");
                }
            }
        }
        
        return Command::SUCCESS;
    }
}

