<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $payload = [
            'name' => 'Lead Manager',
            'description' => 'Lead Bank Manager - Manage lead bank imports, requests, approvals, and execution tasks',
            'permissions' => json_encode([
                'lead_bank.view',
                'lead_bank.import',
                'lead_bank.manage_tags',
                'lead_bank.approve_requests',
                'execution_desk.view',
                'execution_desk.team_all',
            ]),
            'is_active' => true,
            'updated_at' => now(),
        ];

        $exists = DB::table('roles')->where('slug', 'lead_manager')->exists();

        if ($exists) {
            DB::table('roles')->where('slug', 'lead_manager')->update($payload);
        } else {
            DB::table('roles')->insert($payload + [
                'slug' => 'lead_manager',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep the role in place to avoid orphaning users on rollback.
    }
};
