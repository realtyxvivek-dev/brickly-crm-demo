<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('google_sheets_config', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_id');
            $table->string('sheet_name')->default('Sheet1');
            $table->string('api_key')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('service_account_json_path', 500)->nullable();
            $table->string('range', 50)->default('A:Z');
            $table->string('name_column', 10)->default('A');
            $table->string('phone_column', 10)->default('B');
            $table->string('notes_column', 10)->default('C');
            $table->string('status_column', 10)->default('D');
            $table->string('notes_column_sync', 10)->default('E');
            $table->dateTime('last_sync_at')->nullable();
            $table->integer('last_synced_row')->default(0);
            $table->boolean('auto_sync_enabled')->default(true);
            $table->integer('sync_interval_minutes')->default(5);
            $table->foreignId('assignment_rule_id')->nullable()->constrained('assignment_rules')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->boolean('completion_notification_sent')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index('assignment_rule_id');
            $table->index('auto_sync_enabled');
            $table->index('last_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_sheets_config');
    }
};
