<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RemoveSpecificUsers extends Command
{
    protected $signature = 'users:remove-specific';
    protected $description = 'Remove specific users: Keep only Ankit in Admin, remove TEST, Akash, Prashant';

    public function handle()
    {
        // 1. Remove other admins (keep only Ankit)
        $adminsToRemove = ['Admin', 'admin'];
        foreach ($adminsToRemove as $adminName) {
            $user = User::where('name', $adminName)->first();
            if ($user) {
                // Check if user has team members
                $teamCount = $user->teamMembers()->count();
                if ($teamCount > 0) {
                    $this->warn("Cannot delete '{$adminName}' - has {$teamCount} team members. Updating team members first...");
                    $user->teamMembers()->update(['manager_id' => null]);
                }
                $user->delete();
                $this->info("Removed admin: {$adminName}");
            }
        }
        
        // 2. Remove TEST (Telecaller)
        $test = User::where('name', 'TEST')->first();
        if ($test) {
            $test->delete();
            $this->info("Removed user: TEST (Telecaller)");
        } else {
            $this->warn("User 'TEST' not found!");
        }
        
        // 3. Remove Akash (Senior Manager)
        $akash = User::where('name', 'Akash')->first();
        if ($akash) {
            $teamCount = $akash->teamMembers()->count();
            if ($teamCount > 0) {
                $this->warn("Cannot delete 'Akash' - has {$teamCount} team members. Updating team members first...");
                $akash->teamMembers()->update(['manager_id' => null]);
            }
            $akash->delete();
            $this->info("Removed user: Akash (Senior Manager)");
        } else {
            $this->warn("User 'Akash' not found!");
        }
        
        // 4. Remove Prashant (Sales Executive)
        $prashant = User::where('name', 'Prashant')->first();
        if ($prashant) {
            $prashant->delete();
            $this->info("Removed user: Prashant (Sales Executive)");
        } else {
            $this->warn("User 'Prashant' not found!");
        }
        
        $this->newLine();
        $this->info("Removal completed!");
        
        return Command::SUCCESS;
    }
}

