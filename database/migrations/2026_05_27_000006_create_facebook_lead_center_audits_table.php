<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_lead_center_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('page_title')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('matched_rows')->default(0);
            $table->unsignedInteger('webhook_rows')->default(0);
            $table->unsignedInteger('missing_rows')->default(0);
            $table->unsignedInteger('possible_duplicate_rows')->default(0);
            $table->unsignedInteger('unreadable_rows')->default(0);
            $table->json('browser_meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'flc_audits_user_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('facebook_lead_center_audit_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_lead_center_audit_id');
            $table->string('row_hash', 64)->index();
            $table->string('leadgen_id')->nullable()->index();
            $table->string('page_id')->nullable()->index();
            $table->string('form_id')->nullable()->index();
            $table->string('lead_name')->nullable();
            $table->string('phone', 32)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('submitted_at_text')->nullable();
            $table->longText('raw_text')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status', 40)->default('not_enough_data')->index();
            $table->string('match_source')->nullable();
            $table->text('match_reason')->nullable();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->unsignedBigInteger('fb_lead_id')->nullable();
            $table->unsignedBigInteger('fb_webhook_event_id')->nullable();
            $table->unsignedBigInteger('meta_oauth_event_id')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->unsignedBigInteger('imported_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['facebook_lead_center_audit_id', 'row_hash'], 'flc_audit_row_hash_unique');
            $table->foreign('facebook_lead_center_audit_id', 'flc_rows_audit_fk')->references('id')->on('facebook_lead_center_audits')->cascadeOnDelete();
            $table->foreign('crm_lead_id', 'flc_rows_lead_fk')->references('id')->on('leads')->nullOnDelete();
            $table->foreign('fb_lead_id', 'flc_rows_fb_lead_fk')->references('id')->on('fb_leads')->nullOnDelete();
            $table->foreign('fb_webhook_event_id', 'flc_rows_fb_event_fk')->references('id')->on('fb_webhook_events')->nullOnDelete();
            $table->foreign('meta_oauth_event_id', 'flc_rows_oauth_event_fk')->references('id')->on('meta_oauth_events')->nullOnDelete();
            $table->foreign('imported_by_user_id', 'flc_rows_import_user_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_lead_center_audit_rows');
        Schema::dropIfExists('facebook_lead_center_audits');
    }
};
