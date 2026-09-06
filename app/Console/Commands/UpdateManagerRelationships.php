<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UpdateManagerRelationships extends Command
{
    protected $signature = 'users:update-managers';
    protected $description = 'Update manager relationships for specific users';

    public function handle()
    {
        // Find Shushank Shukla (Senior Manager)
        $shushank = User::where('name', 'Shushank Shukla')->first();
        
        if (!$shushank) {
            $this->error('Shushank Shukla not found!');
            return Command::FAILURE;
        }
        
        // Verify Shushank Shukla is Senior Manager
        if ($shushank->role->slug !== 'sales_manager') {
            $this->warn('Shushank Shukla is not a Senior Manager. Current role: ' . $shushank->role->name);
            // Update role to Senior Manager if needed
            $salesManagerRole = \App\Models\Role::where('slug', 'sales_manager')->first();
            if ($salesManagerRole) {
                $shushank->update(['role_id' => $salesManagerRole->id]);
                $this->info('Updated Shushank Shukla role to Senior Manager');
            }
        }
        
        // Update manager for Satya Pandey, Naveen, and Ritika
        $usersToUpdate = [
            'Satya Pandey',
            'Naveen',
            'Ritika'
        ];
        
        $updated = 0;
        foreach ($usersToUpdate as $userName) {
            $user = User::where('name', $userName)->first();
            
            if ($user) {
                $oldManager = $user->manager ? $user->manager->name : 'None';
                $user->update(['manager_id' => $shushank->id]);
                $this->info("Updated {$userName}: Manager changed from '{$oldManager}' to 'Shushank Shukla'");
                $updated++;
            } else {
                $this->warn("User '{$userName}' not found!");
            }
        }
        
        // Verify the updates
        $shushank->refresh();
        $teamMembers = $shushank->teamMembers()->with('role')->get();
        
        $this->newLine();
        $this->info("Successfully updated {$updated} users.");
        $this->newLine();
        $this->info("Shushank Shukla ({$shushank->role->name}) now manages:");
        foreach ($teamMembers as $member) {
            $this->line("  • {$member->name} ({$member->role->name})");
        }
        
        return Command::SUCCESS;
    }
}

