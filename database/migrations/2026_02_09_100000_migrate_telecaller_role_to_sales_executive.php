<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrate all users with telecaller role to sales_executive, then remove telecaller role.
     */
    public function up(): void
    {
        $salesExecutiveRole = DB::table('roles')->where('slug', 'sales_executive')->first();
        $telecallerRole = DB::table('roles')->where('slug', 'telecaller')->first();

        if ($salesExecutiveRole && $telecallerRole) {
            DB::table('users')
                ->where('role_id', $telecallerRole->id)
                ->update(['role_id' => $salesExecutiveRole->id]);
        }

        if ($telecallerRole) {
            DB::table('roles')->where('slug', 'telecaller')->delete();
        }

        // Update lead_form_fields: telecaller -> sales_executive
        if (Schema::hasTable('lead_form_fields')) {
            DB::table('lead_form_fields')->where('field_level', 'telecaller')->update(['field_level' => 'sales_executive']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $salesExecutiveRole = DB::table('roles')->where('slug', 'sales_executive')->first();
        if (!$salesExecutiveRole) {
            return;
        }

        DB::table('roles')->insert([
            'name' => 'Telecaller',
            'slug' => 'telecaller',
            'description' => 'Make calls to leads, update call status, verify leads',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $telecallerRoleId = DB::table('roles')->where('slug', 'telecaller')->value('id');
        if ($telecallerRoleId) {
            DB::table('users')
                ->where('role_id', $salesExecutiveRole->id)
                ->whereIn('email', [
                    'telecaller1@realtorcrm.com',
                    'telecaller2@realtorcrm.com',
                    'telecaller3@realtorcrm.com',
                    'telecaller4@realtorcrm.com',
                ])
                ->update(['role_id' => $telecallerRoleId]);
        }
    }
};
