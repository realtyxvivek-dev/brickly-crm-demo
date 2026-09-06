<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('token')->unique();
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('max_visits')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });

        Schema::create('project_page_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_share_link_id')->nullable()->constrained('project_share_links')->nullOnDelete();
            $table->string('event_name');
            $table->string('section')->nullable();
            $table->string('session_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['project_id', 'event_name']);
            $table->index(['project_share_link_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_page_events');
        Schema::dropIfExists('project_share_links');
    }
};
