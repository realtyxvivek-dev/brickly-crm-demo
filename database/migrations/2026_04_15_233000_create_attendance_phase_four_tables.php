<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_suspicion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('attendance_events')->nullOnDelete();
            $table->string('flag_type');
            $table->string('severity')->default('medium');
            $table->json('details_json')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'flag_type', 'status']);
            $table->index(['created_at', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_suspicion_logs');
    }
};
