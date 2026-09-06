<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Swap role display names: Sales Manager -> Senior Manager, Senior Manager -> Manager.
     * Slugs remain unchanged.
     */
    public function up(): void
    {
        $salesManager = DB::table('roles')->where('slug', 'sales_manager')->first();
        $seniorManager = DB::table('roles')->where('slug', 'senior_manager')->first();

        if (!$salesManager || !$seniorManager) {
            return;
        }

        // Already in desired state.
        if ($salesManager->name === 'Senior Manager' && $seniorManager->name === 'Manager') {
            return;
        }

        DB::transaction(function () {
            // Avoid unique constraint collision on roles.name during swap.
            DB::table('roles')
                ->where('slug', 'senior_manager')
                ->where('name', 'Senior Manager')
                ->update([
                    'name' => '__tmp_senior_manager__',
                ]);

            DB::table('roles')
                ->where('slug', 'sales_manager')
                ->update([
                    'name' => 'Senior Manager',
                    'description' => 'View all team leads, assign leads to sales executives, track team performance',
                ]);

            DB::table('roles')
                ->where('slug', 'senior_manager')
                ->update([
                    'name' => 'Manager',
                    'description' => 'Manager with extended permissions in the hierarchy',
                ]);
        });
    }

    /**
     * Reverse the name swap.
     */
    public function down(): void
    {
        $salesManager = DB::table('roles')->where('slug', 'sales_manager')->first();
        $seniorManager = DB::table('roles')->where('slug', 'senior_manager')->first();

        if (!$salesManager || !$seniorManager) {
            return;
        }

        // Already in original state.
        if ($salesManager->name === 'Sales Manager' && $seniorManager->name === 'Senior Manager') {
            return;
        }

        DB::transaction(function () {
            DB::table('roles')
                ->where('slug', 'sales_manager')
                ->where('name', 'Manager')
                ->update([
                    'name' => '__tmp_sales_manager__',
                ]);

            DB::table('roles')
                ->where('slug', 'senior_manager')
                ->update([
                    'name' => 'Senior Manager',
                    'description' => 'Senior level manager with extended permissions',
                ]);

            DB::table('roles')
                ->where('slug', 'sales_manager')
                ->update([
                    'name' => 'Sales Manager',
                    'description' => 'View all team leads, assign leads to sales executives, track team performance',
                ]);
        });
    }
};
