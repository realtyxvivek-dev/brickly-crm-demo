<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE source_automation_rules
            MODIFY COLUMN source ENUM(
                'facebook_lead_ads',
                'pabbly',
                'mcube',
                'google_sheets',
                'csv',
                'website',
                'all'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::table('source_automation_rules')
            ->where('source', 'website')
            ->update(['source' => 'all']);

        DB::statement("
            ALTER TABLE source_automation_rules
            MODIFY COLUMN source ENUM(
                'facebook_lead_ads',
                'pabbly',
                'mcube',
                'google_sheets',
                'csv',
                'all'
            ) NOT NULL
        ");
    }
};
