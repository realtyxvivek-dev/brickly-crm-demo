<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `leads` MODIFY `property_type` VARCHAR(100) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `leads` MODIFY `property_type` ENUM('apartment', 'villa', 'plot', 'commercial', 'other') NULL");
    }
};
