<?php

namespace Tests\Feature;

use App\Models\FbLeadAdsSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MetaCplSetupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_marketing_token_is_encrypted_in_settings_storage(): void
    {
        $settings = FbLeadAdsSettings::create([
            'page_access_token' => 'page-token',
            'marketing_access_token' => 'marketing-secret-token',
            'ad_account_id' => 'act_123',
            'graph_version' => 'v18.0',
            'cpl_sync_enabled' => true,
        ]);

        $raw = DB::table('fb_lead_ads_settings')->where('id', $settings->id)->first();

        $this->assertNotSame('marketing-secret-token', $raw->marketing_access_token);
        $this->assertSame('marketing-secret-token', $settings->fresh()->marketing_access_token);
    }

    public function test_meta_ad_insights_sync_upserts_daily_ad_spend(): void
    {
        FbLeadAdsSettings::create([
            'marketing_access_token' => 'marketing-secret-token',
            'ad_account_id' => '123',
            'graph_version' => 'v18.0',
            'cpl_sync_enabled' => true,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [[
                    'date_start' => '2026-08-01',
                    'date_stop' => '2026-08-01',
                    'campaign_id' => 'camp_1',
                    'campaign_name' => 'Campaign One',
                    'adset_id' => 'set_1',
                    'adset_name' => 'Adset One',
                    'ad_id' => 'ad_1',
                    'ad_name' => 'Ad One',
                    'spend' => '250.75',
                    'actions' => [
                        ['action_type' => 'lead', 'value' => '5'],
                    ],
                ]],
            ], 200),
        ]);

        $exitCode = Artisan::call('meta-ads:sync-insights', [
            '--from' => '2026-08-01',
            '--to' => '2026-08-01',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('meta_ad_insights_daily', [
            'date' => '2026-08-01 00:00:00',
            'ad_account_id' => 'act_123',
            'ad_id' => 'ad_1',
            'spend' => 250.75,
            'meta_leads' => 5,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('fb_lead_ads_settings', function (Blueprint $table) {
            $table->id();
            $table->text('page_access_token')->nullable();
            $table->text('marketing_access_token')->nullable();
            $table->string('ad_account_id')->nullable();
            $table->boolean('cpl_sync_enabled')->default(false);
            $table->timestamp('last_cpl_synced_at')->nullable();
            $table->string('page_id')->nullable();
            $table->string('graph_version')->default('v18.0');
            $table->string('webhook_verify_token')->nullable();
            $table->string('app_secret')->nullable();
            $table->boolean('signature_verification_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('meta_ad_insights_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('ad_account_id');
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('adset_id')->nullable();
            $table->string('adset_name')->nullable();
            $table->string('ad_id');
            $table->string('ad_name')->nullable();
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedInteger('meta_leads')->default(0);
            $table->json('actions_json')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['date', 'ad_account_id', 'ad_id']);
        });
    }
}
