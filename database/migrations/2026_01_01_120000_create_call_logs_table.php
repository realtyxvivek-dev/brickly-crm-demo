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
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telecaller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->foreignId('task_id')->nullable()->constrained('telecaller_tasks')->onDelete('set null');
            $table->string('phone_number', 20);
            $table->enum('call_type', ['incoming', 'outgoing'])->default('outgoing');
            $table->datetime('start_time');
            $table->datetime('end_time')->nullable();
            $table->integer('duration')->default(0); // in seconds
            $table->enum('status', ['completed', 'missed', 'rejected', 'busy'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['telecaller_id', 'start_time']);
            $table->index('lead_id');
            $table->index('task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};

