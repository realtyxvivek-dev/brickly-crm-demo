<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `fcm_tokens` MODIFY `token_hash` CHAR(64) NOT NULL');
        DB::statement('ALTER TABLE `push_subscriptions` MODIFY `endpoint_hash` CHAR(64) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `fcm_tokens` MODIFY `token_hash` CHAR(64) NULL');
        DB::statement('ALTER TABLE `push_subscriptions` MODIFY `endpoint_hash` CHAR(64) NULL');
    }
};
