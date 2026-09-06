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
        Schema::create('smart_import_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('smart_import_automations')->onDelete('cascade');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->integer('total_leads')->default(0);
            $table->integer('imported_leads')->default(0);
            $table->integer('skipped_leads')->default(0);
            $table->integer('failed_leads')->default(0);
            $table->integer('duplicate_leads')->default(0);
            $table->integer('queued_leads')->default(0);
            $table->json('execution_log')->nullable();
            $table->text('error_log')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('executed_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index(['automation_id', 'status']);
            $table->index('started_at');
            $table->index('executed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_import_executions');
    }
};
