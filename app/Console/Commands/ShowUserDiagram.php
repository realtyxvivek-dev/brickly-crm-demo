<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;

class ShowUserDiagram extends Command
{
    protected $signature = 'users:diagram';
    protected $description = 'Display complete user hierarchy diagram';

    public function handle()
    {
        // Get all users with relationships
        $users = User::with(['role', 'manager', 'teamMembers.role'])->get();
        
        // Group by role
        $usersByRole = $users->groupBy('role.slug');
        
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('                    SYSTEM USERS DIAGRAM');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();
        
        // Display Admins
        if ($usersByRole->has('admin')) {
            $this->info('┌─────────────────────────────────────────────────────────────┐');
            $this->info('│                    ADMIN LEVEL                               │');
            $this->info('├─────────────────────────────────────────────────────────────┤');
            foreach ($usersByRole->get('admin') as $user) {
                $this->line("│  • {$user->name}");
            }
            $this->info('└─────────────────────────────────────────────────────────────┘');
            $this->newLine();
        }
        
        // Display CRM
        if ($usersByRole->has('crm')) {
            $this->info('┌─────────────────────────────────────────────────────────────┐');
            $this->info('│                    CRM LEVEL                                │');
            $this->info('├─────────────────────────────────────────────────────────────┤');
            foreach ($usersByRole->get('crm') as $user) {
                $this->line("│  • {$user->name}");
            }
            $this->info('└─────────────────────────────────────────────────────────────┘');
            $this->newLine();
        }
        
        // Display Sales Head (Senior Managers with no manager)
        $salesHeads = $users->filter(function($user) {
            return $user->role->slug === 'sales_manager' && $user->manager_id === null;
        });
        
        if ($salesHeads->count() > 0) {
            $this->info('┌─────────────────────────────────────────────────────────────┐');
            $this->info('│              SALES HEAD (Senior Manager)                      │');
            $this->info('├─────────────────────────────────────────────────────────────┤');
            
            foreach ($salesHeads as $salesHead) {
                $this->line("│  • {$salesHead->name} (Sales Head)");
                $this->displayTeam($salesHead, 1);
            }
            
            $this->info('└─────────────────────────────────────────────────────────────┘');
            $this->newLine();
        }
        
        // Display other Senior Managers (with managers)
        $otherSalesManagers = $users->filter(function($user) {
            return $user->role->slug === 'sales_manager' && $user->manager_id !== null;
        });
        
        if ($otherSalesManagers->count() > 0) {
            $this->info('┌─────────────────────────────────────────────────────────────┐');
            $this->info('│              SENIOR MANAGERS                                  │');
            $this->info('├─────────────────────────────────────────────────────────────┤');
            
            foreach ($otherSalesManagers as $manager) {
                $managerName = $manager->manager ? $manager->manager->name : 'None';
                $this->line("│  • {$manager->name} (Manager: {$managerName})");
                $this->displayTeam($manager, 1);
            }
            
            $this->info('└─────────────────────────────────────────────────────────────┘');
            $this->newLine();
        }
        
        // Display Sales Executives
        if ($usersByRole->has('sales_executive')) {
            $this->info('┌─────────────────────────────────────────────────────────────┐');
            $this->info('│              SALES EXECUTIVES                                │');
            $this->info('├─────────────────────────────────────────────────────────────┤');
            
            foreach ($usersByRole->get('sales_executive') as $exec) {
                $managerName = $exec->manager ? $exec->manager->name : 'None';
                $this->line("│  • {$exec->name} (Manager: {$managerName})");
            }
            
            $this->info('└─────────────────────────────────────────────────────────────┘');
            $this->newLine();
        }
        
        // Summary
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('                         SUMMARY');
        $this->info('═══════════════════════════════════════════════════════════════');
        
        foreach ($usersByRole as $roleSlug => $roleUsers) {
            $roleName = $roleUsers->first()->role->name;
            $this->line("  {$roleName}: {$roleUsers->count()}");
        }
        
        $this->info("  Total Users: {$users->count()}");
        $this->info('═══════════════════════════════════════════════════════════════');
        
        return Command::SUCCESS;
    }
    
    protected function displayTeam($user, $level = 0)
    {
        $teamMembers = $user->teamMembers;
        
        if ($teamMembers->count() === 0) {
            return;
        }
        
        $indent = str_repeat('│  ', $level);
        $lastIndex = $teamMembers->count() - 1;
        
        foreach ($teamMembers as $index => $member) {
            $isLast = $index === $lastIndex;
            $prefix = $isLast ? '└──' : '├──';
            $this->line("{$indent}{$prefix} {$member->name} ({$member->role->name})");
            
            // Recursively display sub-teams
            if ($member->teamMembers->count() > 0) {
                $this->displayTeam($member, $level + 1);
            }
        }
    }
}

