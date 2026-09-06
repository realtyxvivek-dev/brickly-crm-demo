<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'System Owner - Full system access',
                'permissions' => [
                    'insight_sheet.view',
                    'insight_sheet.edit',
                    'insight_sheet.export',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'CRM',
                'slug' => 'crm',
                'description' => 'Operations Manager - View all leads, assign leads, manage site visits',
                'is_active' => true,
            ],
            [
                'name' => 'HR Manager',
                'slug' => 'hr_manager',
                'description' => 'Human Resources Manager - Manage HR related tasks',
                'is_active' => true,
            ],
            [
                'name' => 'Junior HR',
                'slug' => 'junior_hr',
                'description' => 'Handle assigned hiring candidates only',
                'is_active' => true,
            ],
            [
                'name' => 'Finance Manager',
                'slug' => 'finance_manager',
                'description' => 'Finance Manager - Approve and manage incentive requests',
                'is_active' => true,
            ],
            [
                'name' => 'Ad Manager',
                'slug' => 'ad_manager',
                'description' => 'Monitor assigned lead sources, view source reports, and raise purchase requests',
                'is_active' => true,
            ],
            [
                'name' => 'Lead Manager',
                'slug' => 'lead_manager',
                'description' => 'Lead Bank Manager - Manage lead bank imports, requests, approvals, and execution tasks',
                'permissions' => [
                    'lead_bank.view',
                    'lead_bank.import',
                    'lead_bank.manage_tags',
                    'lead_bank.approve_requests',
                    'execution_desk.view',
                    'execution_desk.team_all',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Sales Manager',
                'slug' => 'sales_manager',
                'description' => 'View all team leads, assign leads to sales executives, track team performance',
                'is_active' => true,
            ],
            [
                'name' => 'Senior Manager',
                'slug' => 'senior_manager',
                'description' => 'Senior Manager with extended permissions in the hierarchy',
                'is_active' => true,
            ],
            [
                'name' => 'Assistant Sales Manager',
                'slug' => 'assistant_sales_manager',
                'description' => 'View assigned leads, update lead status, create site visits, manage sales executives',
                'is_active' => true,
            ],
            [
                'name' => 'Sales Executive',
                'slug' => 'sales_executive',
                'description' => 'View assigned leads only, update call status, add call remarks, verify leads',
                'is_active' => true,
            ],
            [
                'name' => 'Marketing Manager',
                'slug' => 'marketing_manager',
                'description' => 'Manage marketing execution work, assign internal tasks, and monitor team delivery',
                'is_active' => true,
            ],
            [
                'name' => 'Marketing Executive',
                'slug' => 'marketing_executive',
                'description' => 'Handle assigned marketing execution work and update task progress',
                'is_active' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
