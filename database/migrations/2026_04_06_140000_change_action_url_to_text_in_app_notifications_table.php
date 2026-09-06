<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_notifications') || !Schema::hasColumn('app_notifications', 'action_url')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `app_notifications` MODIFY COLUMN `action_url` TEXT NULL');
            return;
        }

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE app_notifications ALTER COLUMN action_url TYPE TEXT');
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_notifications') || !Schema::hasColumn('app_notifications', 'action_url')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `app_notifications` MODIFY COLUMN `action_url` VARCHAR(255) NULL');
            return;
        }

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE app_notifications ALTER COLUMN action_url TYPE VARCHAR(255)');
    }
};
