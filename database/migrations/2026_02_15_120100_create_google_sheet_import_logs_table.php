<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_sheet_import_logs')) {
            return;
        }

        Schema::create('google_sheet_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_sheets_config_id')
                ->constrained('google_sheets_config')
                ->onDelete('cascade');
            $table->string('trigger_source', 20)->default('cron'); // cron|manual
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->default(0);
            $table->boolean('timed_out')->default(false);
            $table->string('status', 20)->default('success'); // success|partial|failed|no_changes
            $table->unsignedBigInteger('last_processed_row_before')->default(1);
            $table->unsignedBigInteger('last_processed_row_after')->default(1);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('already_exists_count')->default(0);
            $table->unsignedInteger('already_synced_count')->default(0);
            $table->unsignedInteger('missing_required_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['google_sheets_config_id', 'started_at'], 'gs_import_logs_config_started_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheet_import_logs');
    }
};
