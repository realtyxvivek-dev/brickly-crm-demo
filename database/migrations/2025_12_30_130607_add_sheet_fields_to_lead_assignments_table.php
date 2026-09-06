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
        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->foreignId('sheet_config_id')->nullable()->after('is_active')->constrained('google_sheets_config')->onDelete('set null');
            $table->integer('sheet_row_number')->nullable()->after('sheet_config_id');
            
            $table->index(['sheet_config_id', 'sheet_row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->dropForeign(['sheet_config_id']);
            $table->dropIndex(['sheet_config_id', 'sheet_row_number']);
            $table->dropColumn(['sheet_config_id', 'sheet_row_number']);
        });
    }
};
