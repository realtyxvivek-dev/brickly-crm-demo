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
            $table->json('selected_columns_json')->nullable()->after('sheet_name')->comment('Array of selected column letters to include in mapping');
            $table->boolean('is_draft')->default(false)->after('is_active')->comment('Whether this config is a draft (setup not completed)');
            $table->dateTime('setup_completed_at')->nullable()->after('is_draft')->comment('When the setup was completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_sheets_config', function (Blueprint $table) {
            $table->dropColumn(['selected_columns_json', 'is_draft', 'setup_completed_at']);
        });
    }
};
