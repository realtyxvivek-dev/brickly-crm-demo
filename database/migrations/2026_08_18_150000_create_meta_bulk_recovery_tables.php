<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_bulk_recovery_scans', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('queued')->index();
            $table->string('scope_type')->default('all');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('per_form_limit')->default(50);
            $table->unsignedInteger('total_limit')->default(500);
            $table->unsignedInteger('forms_total')->default(0);
            $table->unsignedInteger('forms_scanned')->default(0);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('importable_count')->default(0);
            $table->unsignedInteger('already_present_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('meta_bulk_recovery_scan_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('meta_bulk_recovery_scans')->cascadeOnDelete();
            $table->unsignedBigInteger('fb_page_id')->nullable()->index();
            $table->unsignedBigInteger('fb_form_id')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('importable_count')->default(0);
            $table->unsignedInteger('already_present_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['scan_id', 'fb_form_id']);
        });

        Schema::create('meta_bulk_recovery_scan_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('meta_bulk_recovery_scans')->cascadeOnDelete();
            $table->foreignId('scan_form_id')->constrained('meta_bulk_recovery_scan_forms')->cascadeOnDelete();
            $table->unsignedBigInteger('fb_form_id')->nullable()->index();
            $table->string('leadgen_id');
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('meta_created_time')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('ad_name')->nullable();
            $table->longText('raw_meta_json')->nullable();
            $table->string('status')->default('ready')->index();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['scan_id', 'leadgen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_bulk_recovery_scan_leads');
        Schema::dropIfExists('meta_bulk_recovery_scan_forms');
        Schema::dropIfExists('meta_bulk_recovery_scans');
    }
};
