<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE source_automation_rules
            MODIFY COLUMN source ENUM(
                'facebook_lead_ads',
                'pabbly',
                'mcube',
                'google_sheets',
                'csv',
                'website',
                'instagram',
                '99acres',
                'all'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('source_automation_rules')
            ->where('source', '99acres')
            ->update(['source' => 'all']);

        DB::statement("
            ALTER TABLE source_automation_rules
            MODIFY COLUMN source ENUM(
                'facebook_lead_ads',
                'pabbly',
                'mcube',
                'google_sheets',
                'csv',
                'website',
                'instagram',
                'all'
            ) NOT NULL
        ");
    }
};
