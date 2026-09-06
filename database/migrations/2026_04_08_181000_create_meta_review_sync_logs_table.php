<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_review_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status');
            $table->string('reason')->nullable();
            $table->string('previous_stage')->nullable();
            $table->string('meta_stage')->nullable();
            $table->text('note')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('event_name')->nullable();
            $table->string('meta_leadgen_id')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_summary')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['lead_id', 'meta_stage']);
            $table->index('dedupe_key');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_review_sync_logs');
    }
};
