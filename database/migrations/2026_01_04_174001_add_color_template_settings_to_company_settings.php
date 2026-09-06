<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Note: Color template settings are stored as key-value pairs in company_settings table,
     * so no schema changes are needed. Settings will be added via seeder.
     */
    public function up(): void
    {
        // No schema changes needed - settings are stored as rows in company_settings table
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No schema changes to reverse
    }
};
