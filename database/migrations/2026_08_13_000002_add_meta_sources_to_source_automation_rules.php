<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('source_automation_rules')) {
            return;
        }

        DB::statement("
            ALTER TABLE source_automation_rules
            MODIFY COLUMN source ENUM(
                'meta',
                'meta_awareness',
                'facebook_lead_ads',
                'pabbly',
                'mcube',
                'ivr',
                'google_sheets',
                'csv',
                'manual_import',
                'website',
                'whatsapp',
                'instagram',
                '99acres',
                'all'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('source_automation_rules')) {
            return;
        }

        DB::table('source_automation_rules')
            ->whereIn('source', ['meta', 'meta_awareness'])
            ->update(['source' => 'facebook_lead_ads']);

        DB::statement("
            ALTER TABLE source_automation_rules
            MODIFY COLUMN source ENUM(
                'facebook_lead_ads',
                'pabbly',
                'mcube',
                'ivr',
                'google_sheets',
                'csv',
                'manual_import',
                'website',
                'whatsapp',
                'instagram',
                '99acres',
                'all'
            ) NOT NULL
        ");
    }
};
