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
        // Update default value for sync_interval_minutes from 5 to 2
        DB::statement("ALTER TABLE `google_sheets_config` MODIFY COLUMN `sync_interval_minutes` INT DEFAULT 2");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert default value back to 5
        DB::statement("ALTER TABLE `google_sheets_config` MODIFY COLUMN `sync_interval_minutes` INT DEFAULT 5");
    }
};
