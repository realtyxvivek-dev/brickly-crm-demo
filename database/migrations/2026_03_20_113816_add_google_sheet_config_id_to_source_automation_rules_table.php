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
        Schema::table('source_automation_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('google_sheet_config_id')->nullable()->after('fb_form_id');
            $table->foreign('google_sheet_config_id')->references('id')->on('google_sheets_config')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('source_automation_rules', function (Blueprint $table) {
            $table->dropForeign(['google_sheet_config_id']);
            $table->dropColumn('google_sheet_config_id');
        });
    }
};
