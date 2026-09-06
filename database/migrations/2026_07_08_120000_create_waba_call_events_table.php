<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waba_call_events', function (Blueprint $table) {
            $table->id();
            $table->string('meta_call_id')->nullable()->unique();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('telecaller_task_id')->nullable()->constrained('telecaller_tasks')->nullOnDelete();
            $table->string('phone', 32)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('direction', 32)->default('incoming');
            $table->string('event_type', 64)->default('call');
            $table->string('status', 64)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('task_created_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'occurred_at']);
            $table->index(['phone', 'occurred_at']);
            $table->index(['event_type', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waba_call_events');
    }
};
