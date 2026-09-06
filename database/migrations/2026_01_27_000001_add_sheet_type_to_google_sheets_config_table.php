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
            $table->enum('sheet_type', ['meta_facebook', 'google_forms', 'custom'])->default('custom')->after('sheet_name');
            $table->json('crm_status_columns_json')->nullable()->after('sheet_type');
            $table->string('api_endpoint_url')->nullable()->after('crm_status_columns_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_sheets_config', function (Blueprint $table) {
            $table->dropColumn(['sheet_type', 'crm_status_columns_json', 'api_endpoint_url']);
        });
    }
};
