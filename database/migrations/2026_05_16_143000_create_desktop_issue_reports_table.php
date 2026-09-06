<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desktop_issue_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issue_type', 50)->default('other');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('current_url')->nullable();
            $table->string('page_title')->nullable();
            $table->string('app_version_name', 50)->nullable();
            $table->unsignedInteger('app_version_code')->nullable();
            $table->string('device_name', 150)->nullable();
            $table->string('os_version')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->json('activity_logs')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_to_user_id', 'status']);
            $table->index(['reported_by_user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_issue_reports');
    }
};
