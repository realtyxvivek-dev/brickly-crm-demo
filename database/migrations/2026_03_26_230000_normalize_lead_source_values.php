<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('website','referral','walk_in','call','social_media','google_sheets','csv','pabbly','facebook_lead_ads','mcube','crm_manual','manual','meta','ivr','sheet','google','99acres','housing','reference','other') DEFAULT 'other'");

        $map = [
            'facebook_lead_ads' => 'meta',
            'pabbly' => 'meta',
            'social_media' => 'meta',
            'google_sheets' => 'sheet',
            'csv' => 'sheet',
            'mcube' => 'ivr',
            'call' => 'ivr',
            'referral' => 'reference',
            'website' => 'website',
            'walk_in' => 'other',
            'crm_manual' => 'other',
            'manual' => 'other',
            'other' => 'other',
        ];

        foreach ($map as $from => $to) {
            DB::table('leads')->where('source', $from)->update(['source' => $to]);
        }

        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('meta','ivr','sheet','website','google','99acres','housing','reference','other') DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('website','referral','walk_in','call','social_media','google_sheets','csv','pabbly','facebook_lead_ads','mcube','other') DEFAULT 'other'");
    }
};
