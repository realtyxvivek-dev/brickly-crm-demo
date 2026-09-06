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
        \DB::statement("ALTER TABLE `targets` ADD COLUMN `manager_target_calculation_logic` ENUM('juniors_sum', 'individual_plus_team') NULL AFTER `target_calls`");
        \DB::statement("ALTER TABLE `targets` ADD COLUMN `manager_junior_scope` ENUM('executives_only', 'executives_and_telecallers') NULL AFTER `manager_target_calculation_logic`");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::statement("ALTER TABLE `targets` DROP COLUMN `manager_junior_scope`");
        \DB::statement("ALTER TABLE `targets` DROP COLUMN `manager_target_calculation_logic`");
    }
};
