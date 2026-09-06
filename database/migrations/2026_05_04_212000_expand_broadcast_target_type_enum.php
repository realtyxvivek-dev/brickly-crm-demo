<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE broadcast_messages
            MODIFY target_type ENUM('all_users','role_based','specific_users')
            NOT NULL DEFAULT 'all_users'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE broadcast_messages
            MODIFY target_type ENUM('all_users','role_based')
            NOT NULL DEFAULT 'all_users'
        ");
    }
};
