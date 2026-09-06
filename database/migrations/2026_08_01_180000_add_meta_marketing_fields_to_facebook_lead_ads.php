<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_lead_ads_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('fb_lead_ads_settings', 'marketing_access_token')) {
                $table->text('marketing_access_token')->nullable()->after('page_access_token');
            }
            if (!Schema::hasColumn('fb_lead_ads_settings', 'ad_account_id')) {
                $table->string('ad_account_id', 80)->nullable()->after('marketing_access_token');
            }
            if (!Schema::hasColumn('fb_lead_ads_settings', 'cpl_sync_enabled')) {
                $table->boolean('cpl_sync_enabled')->default(false)->after('ad_account_id');
            }
            if (!Schema::hasColumn('fb_lead_ads_settings', 'last_cpl_synced_at')) {
                $table->timestamp('last_cpl_synced_at')->nullable()->after('cpl_sync_enabled');
            }
        });

        Schema::table('fb_leads', function (Blueprint $table) {
            foreach ([
                'ad_id' => 80,
                'ad_name' => 255,
                'adset_id' => 80,
                'adset_name' => 255,
                'campaign_id' => 80,
                'campaign_name' => 255,
                'platform' => 80,
            ] as $column => $length) {
                if (!Schema::hasColumn('fb_leads', $column)) {
                    $table->string($column, $length)->nullable()->after('raw_response_json');
                }
            }
            if (!Schema::hasColumn('fb_leads', 'meta_created_time')) {
                $table->timestamp('meta_created_time')->nullable()->after('platform');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fb_leads', function (Blueprint $table) {
            foreach (['ad_id', 'ad_name', 'adset_id', 'adset_name', 'campaign_id', 'campaign_name', 'platform', 'meta_created_time'] as $column) {
                if (Schema::hasColumn('fb_leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('fb_lead_ads_settings', function (Blueprint $table) {
            foreach (['marketing_access_token', 'ad_account_id', 'cpl_sync_enabled', 'last_cpl_synced_at'] as $column) {
                if (Schema::hasColumn('fb_lead_ads_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
