<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token')->unique();
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->text('message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['created_by', 'created_at']);
        });

        Schema::create('lead_proposal_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_proposal_id')->constrained('lead_proposals')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['lead_proposal_id', 'project_id']);
        });

        Schema::create('lead_proposal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_proposal_id')->constrained('lead_proposals')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('event_name');
            $table->string('section')->nullable();
            $table->string('session_id')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('meta')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['lead_proposal_id', 'event_name']);
            $table->index(['lead_proposal_id', 'project_id']);
            $table->index(['lead_proposal_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_proposal_events');
        Schema::dropIfExists('lead_proposal_projects');
        Schema::dropIfExists('lead_proposals');
    }
};
