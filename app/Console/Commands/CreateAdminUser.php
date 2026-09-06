<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {--email=admin@realtorcrm.com} {--password=admin123}';
    
    protected $description = 'Check if admin user exists, if not create it';

    public function handle()
    {
        $email = $this->option('email');
        $password = $this->option('password');
        
        // Get admin role
        $adminRole = Role::where('slug', 'admin')->first();
        
        if (!$adminRole) {
            $this->error('Admin role not found! Please run: php artisan db:seed --class=RoleSeeder');
            return 1;
        }
        
        // Check if admin user exists
        $adminUser = User::where('email', $email)->first();
        
        if ($adminUser) {
            $this->info("Admin user already exists!");
            $this->line("Email: {$adminUser->email}");
            $this->line("Name: {$adminUser->name}");
            $this->line("Role: " . ($adminUser->role->name ?? 'N/A'));
            $this->line("Active: " . ($adminUser->is_active ? 'Yes' : 'No'));
            
            // Check if password needs to be updated
            if ($this->confirm('Do you want to update the password?', false)) {
                $adminUser->password = Hash::make($password);
                $adminUser->save();
                $this->info("Password updated successfully!");
            }
            
            return 0;
        }
        
        // Create admin user
        $this->info("Admin user not found. Creating admin user...");
        
        $user = User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
        
        $this->info("✅ Admin user created successfully!");
        $this->line("Email: {$user->email}");
        $this->line("Password: {$password}");
        $this->line("Role: {$adminRole->name}");
        
        return 0;
    }
}
