<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_lead_ads_settings', function (Blueprint $table) {
            $table->id();
            $table->text('page_access_token')->nullable();
            $table->string('page_id', 50)->nullable();
            $table->string('graph_version', 20)->default('v18.0');
            $table->string('webhook_verify_token', 255)->nullable();
            $table->string('app_secret', 255)->nullable()->comment('Optional: for X-Hub-Signature-256 verification');
            $table->boolean('signature_verification_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_lead_ads_settings');
    }
};
