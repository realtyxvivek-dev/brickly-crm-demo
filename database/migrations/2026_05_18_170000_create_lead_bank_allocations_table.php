<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_bank_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_bank_request_id')->constrained('lead_bank_requests')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_assignment_id')->nullable()->constrained('lead_assignments')->nullOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['active', 'recalled', 'expired', 'protected', 'converted'])->default('active');
            $table->timestamp('allocated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('recalled_at')->nullable();
            $table->foreignId('recalled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recall_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['lead_bank_request_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_bank_allocations');
    }
};
