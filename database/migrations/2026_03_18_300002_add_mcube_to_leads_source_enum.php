<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('website','referral','walk_in','call','social_media','google_sheets','csv','pabbly','facebook_lead_ads','mcube','other') DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('website','referral','walk_in','call','social_media','google_sheets','csv','pabbly','facebook_lead_ads','other') DEFAULT 'other'");
    }
};
