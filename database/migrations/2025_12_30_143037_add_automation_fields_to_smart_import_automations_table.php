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
        Schema::table('smart_import_automations', function (Blueprint $table) {
            $table->enum('lead_source', ['google_sheet', 'csv_excel'])->nullable()->after('description');
            $table->enum('distribution_mode', ['percentage', 'random', 'one_sheet_per_user'])->nullable()->after('assignment_mode');
            $table->boolean('auto_create_call_task')->default(true)->after('distribution_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smart_import_automations', function (Blueprint $table) {
            $table->dropColumn(['lead_source', 'distribution_mode', 'auto_create_call_task']);
        });
    }
};
