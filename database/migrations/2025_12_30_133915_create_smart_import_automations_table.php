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
        Schema::create('smart_import_automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_name')->nullable();
            $table->enum('file_type', ['csv', 'xlsx', 'xls'])->default('csv');
            $table->integer('total_rows')->default(0);
            $table->json('column_mapping')->nullable();
            $table->enum('duplicate_handling', ['skip', 'merge', 'update'])->default('skip');
            $table->enum('assignment_mode', ['percentage', 'fixed_count', 'round_robin'])->default('percentage');
            $table->json('distribution_config')->nullable();
            $table->json('conditions')->nullable();
            $table->foreignId('fallback_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('sla_minutes')->nullable();
            $table->foreignId('escalation_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['draft', 'active', 'paused', 'completed'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->dateTime('last_executed_at')->nullable();
            $table->integer('execution_count')->default(0);
            $table->timestamps();

            $table->index(['created_by', 'status']);
            $table->index('last_executed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_import_automations');
    }
};
