<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_sheets_request_logs')) {
            return;
        }

        Schema::create('google_sheets_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_sheets_config_id')
                ->constrained('google_sheets_config')
                ->onDelete('cascade');
            $table->uuid('request_id')->index();
            $table->string('request_ip', 45)->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status', 30)->default('success');
            $table->boolean('is_test')->default(false);
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['google_sheets_config_id', 'created_at'], 'gsrl_config_created_idx');
            $table->index('status', 'gsrl_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheets_request_logs');
    }
};
