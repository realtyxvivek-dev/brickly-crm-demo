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
        Schema::table('google_sheets_config', function (Blueprint $table) {
            $table->foreignId('linked_telecaller_id')->nullable()->after('automation_id')->constrained('users')->onDelete('set null');
            $table->integer('per_sheet_daily_limit')->nullable()->after('linked_telecaller_id');
            
            $table->index('linked_telecaller_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_sheets_config', function (Blueprint $table) {
            $table->dropForeign(['linked_telecaller_id']);
            $table->dropColumn(['linked_telecaller_id', 'per_sheet_daily_limit']);
        });
    }
};
