<?php

namespace Database\Seeders;

use App\Models\Target;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TargetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $salesManagerRole = Role::where('slug', 'sales_manager')->first();
        $salesExecutiveRole = Role::where('slug', 'sales_executive')->first();

        if (!$salesManagerRole || !$salesExecutiveRole) {
            $this->command->error('Roles not found. Please run RoleSeeder first.');
            return;
        }

        // Get current month start date
        $currentMonth = Carbon::now()->startOfMonth();

        // Set targets for all Sales Managers
        $salesManagers = User::where('role_id', $salesManagerRole->id)
            ->where('is_active', true)
            ->get();

        foreach ($salesManagers as $manager) {
            Target::updateOrCreate(
                [
                    'user_id' => $manager->id,
                    'target_month' => $currentMonth,
                ],
                [
                    'target_meetings' => 10,
                    'target_visits' => 10,
                    'target_closers' => 5,
                    'target_prospects_extract' => 0,
                    'target_prospects_verified' => 0,
                    'target_calls' => 0,
                ]
            );
        }

        // Set targets for all Sales Executives
        $salesExecutives = User::where('role_id', $salesExecutiveRole->id)
            ->where('is_active', true)
            ->get();

        foreach ($salesExecutives as $executive) {
            Target::updateOrCreate(
                [
                    'user_id' => $executive->id,
                    'target_month' => $currentMonth,
                ],
                [
                    'target_meetings' => 0,
                    'target_visits' => 8,
                    'target_closers' => 0,
                    'target_prospects_extract' => 0,
                    'target_prospects_verified' => 150,
                    'target_calls' => 6000,
                ]
            );
        }

        $this->command->info('Default targets created successfully!');
        $this->command->info('Sales Managers: 10 meetings, 10 visits, 5 closers (monthly)');
        $this->command->info('Sales Executives: 6000 calls/month, 150 prospects/month, 8 visits/month');
    }
}
