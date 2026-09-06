<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'csv' to the source enum
        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('website', 'referral', 'walk_in', 'call', 'social_media', 'google_sheets', 'csv', 'other') DEFAULT 'other'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'csv' from enum (revert to previous state)
        DB::statement("ALTER TABLE `leads` MODIFY COLUMN `source` ENUM('website', 'referral', 'walk_in', 'call', 'social_media', 'google_sheets', 'other') DEFAULT 'other'");
    }
};
