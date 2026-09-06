<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mail_type', 80)->index();
            $table->string('subject')->nullable();
            $table->string('recipient_email')->index();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('queued')->index();
            $table->json('payload_summary')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resend_of_log_id')->nullable()->constrained('mail_delivery_logs')->nullOnDelete();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->index(['mail_type', 'recipient_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_delivery_logs');
    }
};
