<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_insights_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('ad_account_id', 80);
            $table->string('campaign_id', 80)->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('adset_id', 80)->nullable();
            $table->string('adset_name')->nullable();
            $table->string('ad_id', 80);
            $table->string('ad_name')->nullable();
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedInteger('meta_leads')->default(0);
            $table->json('actions_json')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['date', 'ad_account_id', 'ad_id'], 'meta_ad_insights_daily_unique');
            $table->index(['ad_id', 'date'], 'meta_ad_insights_daily_ad_date_idx');
            $table->index(['campaign_id', 'date'], 'meta_ad_insights_daily_campaign_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_insights_daily');
    }
};
