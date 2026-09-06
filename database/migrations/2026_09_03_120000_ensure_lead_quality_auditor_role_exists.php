<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $payload = [
            'name' => 'Lead Quality Auditor',
            'description' => 'Review lead quality in Insight Sheet and manage own attendance requests',
            'permissions' => json_encode([
                'insight_sheet.view',
                'insight_sheet.edit',
                'insight_sheet.export',
            ]),
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (DB::table('roles')->where('slug', 'lead_quality_auditor')->exists()) {
            DB::table('roles')->where('slug', 'lead_quality_auditor')->update($payload);
            return;
        }

        DB::table('roles')->insert($payload + [
            'slug' => 'lead_quality_auditor',
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep the role so a rollback cannot leave assigned users without a role.
    }
};
