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
        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('assigned_to')->constrained('users')->onDelete('cascade');
            $table->enum('task_type', ['calling', 'follow_up', 'cnp_retry'])->default('calling');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'rescheduled'])->default('pending');
            $table->datetime('scheduled_at');
            $table->datetime('completed_at')->nullable();
            $table->enum('outcome', ['interested', 'not_interested', 'cnp', 'block', 'rescheduled'])->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['lead_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telecaller_tasks');
    }
};
