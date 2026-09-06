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
        // Modify the enum to include 'datetime'
        DB::statement("ALTER TABLE lead_form_fields MODIFY COLUMN field_type ENUM('text', 'textarea', 'select', 'date', 'time', 'datetime', 'number', 'email', 'tel') NOT NULL COMMENT 'Type of input field'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'datetime' from the enum
        DB::statement("ALTER TABLE lead_form_fields MODIFY COLUMN field_type ENUM('text', 'textarea', 'select', 'date', 'time', 'number', 'email', 'tel') NOT NULL COMMENT 'Type of input field'");
    }
};
