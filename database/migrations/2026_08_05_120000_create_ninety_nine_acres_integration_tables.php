<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ninety_nine_acres_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('api_key', 80)->unique();
            $table->string('default_status', 40)->default('new');
            $table->string('fallback_type', 40)->default('unassigned_crm_queue');
            $table->foreignId('fallback_user_id')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ninety_nine_acres_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ninety_nine_acres_setting_id')->nullable();
            $table->string('request_id', 64)->index();
            $table->string('request_ip', 45)->nullable();
            $table->string('external_lead_id', 120)->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->json('mapped_payload')->nullable();
            $table->json('validation_result')->nullable();
            $table->json('assignment_result')->nullable();
            $table->json('fallback_result')->nullable();
            $table->string('status', 40)->default('received')->index();
            $table->foreignId('lead_id')->nullable();
            $table->boolean('duplicate')->default(false);
            $table->boolean('is_test')->default(false);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('external_lead_id', 'nna_logs_external_lead_idx');
            $table->foreign('ninety_nine_acres_setting_id', 'nna_logs_setting_fk')
                ->references('id')
                ->on('ninety_nine_acres_settings')
                ->nullOnDelete();
            $table->foreign('lead_id', 'nna_logs_lead_fk')
                ->references('id')
                ->on('leads')
                ->nullOnDelete();
        });

        Schema::table('ninety_nine_acres_settings', function (Blueprint $table) {
            $table->foreign('fallback_user_id', 'nna_settings_fallback_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ninety_nine_acres_request_logs');
        Schema::dropIfExists('ninety_nine_acres_settings');
    }
};
