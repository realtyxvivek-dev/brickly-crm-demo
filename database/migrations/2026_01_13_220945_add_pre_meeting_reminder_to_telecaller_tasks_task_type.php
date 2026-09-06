<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'pre_meeting_reminder' to the task_type ENUM
        // MySQL doesn't support direct ENUM modification, so we use raw SQL
        \DB::statement("ALTER TABLE `telecaller_tasks` MODIFY COLUMN `task_type` ENUM('calling', 'follow_up', 'cnp_retry', 'pre_meeting_reminder') DEFAULT 'calling'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'pre_meeting_reminder' from the task_type ENUM
        \DB::statement("ALTER TABLE `telecaller_tasks` MODIFY COLUMN `task_type` ENUM('calling', 'follow_up', 'cnp_retry') DEFAULT 'calling'");
    }
};
