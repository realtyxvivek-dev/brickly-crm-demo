<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_code')->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'waiting', 'completed', 'closed', 'reopened', 'rejected'])->default('open');
            $table->dateTime('due_at')->nullable();
            $table->unsignedInteger('estimated_time_minutes')->nullable();
            $table->unsignedInteger('actual_time_minutes')->nullable();
            $table->text('waiting_reason')->nullable();
            $table->foreignId('waiting_on_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_status_changed_at')->nullable();
            $table->string('context_label', 50);
            $table->string('source_module')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->enum('trigger_type', ['manual', 'automation_future'])->default('manual');
            $table->boolean('is_private')->default(false);
            $table->timestamp('due_reminder_sent_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['assigned_by', 'status']);
            $table->index(['due_at', 'status']);
            $table->index('context_label');
            $table->index('is_private');
            $table->index('last_status_changed_at');
        });

        Schema::create('execution_task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('execution_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'type']);
        });

        Schema::create('execution_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('execution_tasks')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('execution_task_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('execution_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'sort_order']);
        });

        Schema::create('execution_saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('scope_tab', 30)->default('my_queue');
            $table->json('filters');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_saved_views');
        Schema::dropIfExists('execution_task_checklists');
        Schema::dropIfExists('execution_task_attachments');
        Schema::dropIfExists('execution_task_activities');
        Schema::dropIfExists('execution_tasks');
    }
};
