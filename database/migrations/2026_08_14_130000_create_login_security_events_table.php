<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('login_security_events')) {
            return;
        }

        Schema::create('login_security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email_normalized')->index();
            $table->string('event_type', 40)->index();
            $table->string('login_method', 20)->nullable()->index();
            $table->string('lock_level', 20)->default('none')->index();
            $table->string('status', 30)->default('recorded')->index();
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('lock_until')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_summary')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy', 10, 2)->nullable();
            $table->string('selfie_path')->nullable();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_type', 'created_at'], 'login_sec_user_event_created_idx');
            $table->index(['email_normalized', 'event_type', 'created_at'], 'login_sec_email_event_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_security_events');
    }
};
