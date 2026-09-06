<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_download_requests', function (Blueprint $table) {
            $table->unsignedInteger('download_click_count')->default(0)->after('exported_records_count');
            $table->timestamp('last_downloaded_at')->nullable()->after('download_click_count');
        });
    }

    public function down(): void
    {
        Schema::table('lead_download_requests', function (Blueprint $table) {
            $table->dropColumn(['download_click_count', 'last_downloaded_at']);
        });
    }
};
