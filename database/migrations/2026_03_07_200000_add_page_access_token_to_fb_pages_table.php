<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_pages', function (Blueprint $table) {
            $table->text('page_access_token')->nullable()->after('page_name');
        });

        $settings = DB::table('fb_lead_ads_settings')->first();
        if ($settings && !empty($settings->page_id) && !empty($settings->page_access_token)) {
            DB::table('fb_pages')->updateOrInsert(
                ['page_id' => $settings->page_id],
                [
                    'page_access_token' => $settings->page_access_token,
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('fb_pages', function (Blueprint $table) {
            $table->dropColumn('page_access_token');
        });
    }
};
