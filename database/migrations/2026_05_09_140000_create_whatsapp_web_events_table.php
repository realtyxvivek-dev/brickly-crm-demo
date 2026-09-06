<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_web_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_hash')->unique();
            $table->string('session_key', 120)->nullable()->index();
            $table->string('phone', 32)->index();
            $table->string('contact_name')->nullable();
            $table->text('message_preview')->nullable();
            $table->timestamp('message_timestamp')->nullable()->index();
            $table->enum('decision', ['reenquiry', 'new_lead', 'duplicate', 'ignored', 'error'])->default('ignored');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->enum('processed_by_mode', ['assist', 'auto'])->default('assist');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->json('payload_meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_web_events');
    }
};
