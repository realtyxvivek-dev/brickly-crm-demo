<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix confusing role display names:
     *   slug 'sales_manager'  → name was "Senior Manager" → fix to "Sales Manager"
     *   slug 'senior_manager' → name was "Manager"        → fix to "Senior Manager"
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('slug', 'sales_manager')
            ->update(['name' => 'Sales Manager']);

        DB::table('roles')
            ->where('slug', 'senior_manager')
            ->update(['name' => 'Senior Manager']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('slug', 'sales_manager')
            ->update(['name' => 'Senior Manager']);

        DB::table('roles')
            ->where('slug', 'senior_manager')
            ->update(['name' => 'Manager']);
    }
};
