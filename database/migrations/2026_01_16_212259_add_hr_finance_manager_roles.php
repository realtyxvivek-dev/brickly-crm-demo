<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert HR Manager role after CRM
        $crmRole = DB::table('roles')->where('slug', 'crm')->first();
        if ($crmRole) {
            $hrManagerExists = DB::table('roles')->where('slug', 'hr_manager')->exists();
            if (!$hrManagerExists) {
                DB::table('roles')->insert([
                    'name' => 'HR Manager',
                    'slug' => 'hr_manager',
                    'description' => 'Human Resources Manager - Manage HR related tasks',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Insert Finance Manager role after HR Manager
        $financeManagerExists = DB::table('roles')->where('slug', 'finance_manager')->exists();
        if (!$financeManagerExists) {
            DB::table('roles')->insert([
                'name' => 'Finance Manager',
                'slug' => 'finance_manager',
                'description' => 'Finance Manager - Approve and manage incentive requests',
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
        // Remove HR Manager and Finance Manager roles
        DB::table('roles')->where('slug', 'hr_manager')->delete();
        DB::table('roles')->where('slug', 'finance_manager')->delete();
    }
};
