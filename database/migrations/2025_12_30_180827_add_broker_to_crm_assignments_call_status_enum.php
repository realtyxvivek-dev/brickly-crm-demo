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
        DB::statement("ALTER TABLE crm_assignments MODIFY COLUMN call_status ENUM('pending', 'called_interested', 'called_not_interested', 'completed', 'broker') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Before reverting, ensure no 'broker' status records exist
        // If broker records exist, they need to be updated first
        DB::statement("ALTER TABLE crm_assignments MODIFY COLUMN call_status ENUM('pending', 'called_interested', 'called_not_interested', 'completed') DEFAULT 'pending'");
    }
};
