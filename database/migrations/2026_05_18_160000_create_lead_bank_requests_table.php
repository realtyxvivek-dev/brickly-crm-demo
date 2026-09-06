<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_bank_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('city')->nullable()->index();
            $table->string('source')->nullable()->index();
            $table->json('tag_ids')->nullable();
            $table->unsignedSmallInteger('expiry_days')->default(7);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'partially_approved', 'fulfilled', 'rejected', 'cancelled'])->default('pending')->index();
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('allocatable_count')->default(0);
            $table->json('match_snapshot')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'status']);
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_bank_requests');
    }
};
