<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulksmsplans_ivr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('token')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('default_source', 50)->default('ivr');
            $table->boolean('auto_create_lead')->default(true);
            $table->boolean('create_missed_call_task')->default(true);
            $table->foreignId('fallback_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('bulksmsplans_ivr_settings')->insert([
            'token' => null,
            'is_enabled' => false,
            'default_source' => 'ivr',
            'auto_create_lead' => true,
            'create_missed_call_task' => true,
            'fallback_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('ivr_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index();
            $table->string('external_call_id')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('agent_phone', 20)->nullable();
            $table->string('agent_name')->nullable();
            $table->string('call_status', 50)->nullable();
            $table->string('direction', 20)->nullable();
            $table->string('recording_url')->nullable();
            $table->string('dtmf_option', 50)->nullable();
            $table->timestamp('call_starttime')->nullable();
            $table->timestamp('call_endtime')->nullable();
            $table->integer('duration')->nullable();
            $table->enum('status', ['success', 'skipped', 'failed'])->default('failed');
            $table->string('message')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('call_log_id')->nullable()->constrained('call_logs')->nullOnDelete();
            $table->json('normalized_payload')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider', 'external_call_id'], 'ivr_provider_external_call_unique');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_webhook_logs');
        Schema::dropIfExists('bulksmsplans_ivr_settings');
    }
};
