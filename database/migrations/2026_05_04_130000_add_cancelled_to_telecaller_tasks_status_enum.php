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
        DB::statement("
            ALTER TABLE telecaller_tasks
            MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'rescheduled', 'cancelled')
            NOT NULL DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('telecaller_tasks')
            ->where('status', 'cancelled')
            ->update(['status' => 'pending']);

        DB::statement("
            ALTER TABLE telecaller_tasks
            MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'rescheduled')
            NOT NULL DEFAULT 'pending'
        ");
    }
};
