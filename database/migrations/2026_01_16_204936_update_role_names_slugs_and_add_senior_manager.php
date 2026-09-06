<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * IMPORTANT: Update in correct order to avoid slug conflicts:
     * 1. First update 'sales_executive' → 'assistant_sales_manager'
     * 2. Then update 'telecaller' → 'sales_executive'
     * 3. Add new Senior Manager role
     */
    public function up(): void
    {
        // Step 1: Update Sales Executive → Assistant Sales Manager (frees up 'sales_executive' slug)
        DB::table('roles')
            ->where('slug', 'sales_executive')
            ->update([
                'name' => 'Assistant Sales Manager',
                'slug' => 'assistant_sales_manager',
                'description' => 'View assigned leads, update lead status, create site visits, manage sales executives',
                'updated_at' => now(),
            ]);

        // Step 2: Update Telecaller → Sales Executive (now 'sales_executive' slug is available)
        DB::table('roles')
            ->where('slug', 'telecaller')
            ->update([
                'name' => 'Sales Executive',
                'slug' => 'sales_executive',
                'description' => 'View assigned leads only, update call status, add call remarks',
                'updated_at' => now(),
            ]);

        // Step 3: Add new Senior Manager role if it doesn't exist
        $seniorManagerExists = DB::table('roles')->where('slug', 'senior_manager')->exists();
        if (!$seniorManagerExists) {
            DB::table('roles')->insert([
                'name' => 'Senior Manager',
                'slug' => 'senior_manager',
                'description' => 'Senior level manager with extended permissions',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove Senior Manager role
        DB::table('roles')->where('slug', 'senior_manager')->delete();

        // Revert Sales Executive → Telecaller
        DB::table('roles')
            ->where('slug', 'sales_executive')
            ->update([
                'name' => 'Telecaller',
                'slug' => 'telecaller',
                'description' => 'View assigned leads only, update call status, add call remarks',
                'updated_at' => now(),
            ]);

        // Revert Assistant Sales Manager → Sales Executive
        DB::table('roles')
            ->where('slug', 'assistant_sales_manager')
            ->update([
                'name' => 'Sales Executive',
                'slug' => 'sales_executive',
                'description' => 'View assigned leads, update lead status, create site visits, manage telecallers',
                'updated_at' => now(),
            ]);
    }
};
