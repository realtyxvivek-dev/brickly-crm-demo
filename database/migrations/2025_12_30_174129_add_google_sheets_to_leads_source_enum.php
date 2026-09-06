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
        // Modify ENUM to include 'google_sheets'
        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('website', 'referral', 'walk_in', 'call', 'social_media', 'google_sheets', 'other') DEFAULT 'other'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert ENUM back to original (remove 'google_sheets')
        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('website', 'referral', 'walk_in', 'call', 'social_media', 'other') DEFAULT 'other'");
    }
};
