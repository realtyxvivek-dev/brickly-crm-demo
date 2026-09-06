<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RemoveDemoUsers extends Command
{
    protected $signature = 'users:remove-demo';
    protected $description = 'Remove demo users created by UserSeeder';

    public function handle()
    {
        // Demo users from UserSeeder
        $demoUsers = [
            'CRM Manager',
            'Sales Head',
            'Senior Manager 1',
            'Senior Manager 2',
            'Telecaller 1',
            'Telecaller 2',
            'Telecaller 3',
            'Telecaller 4',
        ];
        
        $removed = 0;
        $notFound = [];
        
        foreach ($demoUsers as $userName) {
            $user = User::where('name', $userName)->first();
            if ($user) {
                // Check if user has team members
                $teamCount = $user->teamMembers()->count();
                if ($teamCount > 0) {
                    $this->warn("Cannot delete '{$userName}' - has {$teamCount} team members. Updating team members first...");
                    // Update team members to have no manager
                    $user->teamMembers()->update(['manager_id' => null]);
                }
                
                $user->delete();
                $this->info("Removed demo user: {$userName}");
                $removed++;
            } else {
                $notFound[] = $userName;
            }
        }
        
        // Also check for users with demo email patterns
        $demoEmails = [
            'crm@realtorcrm.com',
            'saleshead@realtorcrm.com',
            'salesmanager1@realtorcrm.com',
            'salesmanager2@realtorcrm.com',
            'telecaller1@realtorcrm.com',
            'telecaller2@realtorcrm.com',
            'telecaller3@realtorcrm.com',
            'telecaller4@realtorcrm.com',
        ];
        
        foreach ($demoEmails as $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $teamCount = $user->teamMembers()->count();
                if ($teamCount > 0) {
                    $user->teamMembers()->update(['manager_id' => null]);
                }
                $user->delete();
                $this->info("Removed demo user: {$user->name} ({$email})");
                $removed++;
            }
        }
        
        $this->newLine();
        $this->info("Removed {$removed} demo users.");
        
        if (!empty($notFound)) {
            $this->comment("Users not found: " . implode(', ', $notFound));
        }
        
        return Command::SUCCESS;
    }
}

