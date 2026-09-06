<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `telecaller_tasks` MODIFY COLUMN `task_type` ENUM('calling', 'follow_up', 'cnp_retry', 'lead_form_fill', 'pre_meeting_reminder') DEFAULT 'calling'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE `telecaller_tasks` MODIFY COLUMN `task_type` ENUM('calling', 'follow_up', 'cnp_retry', 'lead_form_fill') DEFAULT 'calling'"
        );
    }
};
