<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcube_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('callid')->nullable()->index();
            $table->string('emp_phone', 20)->nullable();
            $table->string('callto', 20)->nullable();
            $table->string('dialstatus', 50)->nullable();
            $table->string('direction', 20)->nullable();
            $table->string('recording_url')->nullable();
            $table->timestamp('call_starttime')->nullable();
            $table->timestamp('call_endtime')->nullable();
            $table->enum('status', ['success', 'skipped', 'failed'])->default('failed');
            $table->string('message')->nullable();          // Processing result message
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('call_log_id')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcube_webhook_logs');
    }
};
