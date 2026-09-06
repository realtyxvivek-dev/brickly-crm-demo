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
        Schema::create('smart_import_lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('smart_import_executions')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('assigned_to')->constrained('users')->onDelete('restrict');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('restrict');
            $table->string('rule_applied', 255)->nullable();
            $table->integer('priority_level')->default(0);
            $table->enum('assignment_method', ['percentage', 'fixed_count', 'round_robin', 'condition', 'fallback'])->default('percentage');
            $table->boolean('is_queued')->default(false);
            $table->string('queued_reason', 255)->nullable();
            $table->dateTime('sla_started_at')->nullable();
            $table->dateTime('sla_met_at')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->dateTime('escalated_at')->nullable();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('override_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('override_reason')->nullable();
            $table->dateTime('override_at')->nullable();
            $table->timestamps();

            $table->index(['execution_id', 'lead_id']);
            $table->index(['assigned_to', 'is_queued']);
            $table->index('sla_started_at');
            $table->index('sla_breached');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_import_lead_assignments');
    }
};
