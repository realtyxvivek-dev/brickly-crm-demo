<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_routing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_type', 50)->unique();
            $table->string('mode', 50)->default('reporting_senior');
            $table->json('fixed_role_ids')->nullable();
            $table->foreignId('fixed_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('fallback_role_ids')->nullable();
            $table->foreignId('fallback_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('verification_routing_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_type', 50);
            $table->string('source_type', 30);
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('source_team_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verifier_type', 30);
            $table->foreignId('verifier_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('verifier_role_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('workflow_type', 'vrm_workflow_idx');
            $table->index('source_type', 'vrm_source_idx');
            $table->index('is_active', 'vrm_active_idx');
            $table->index('priority', 'vrm_priority_idx');
            $table->index(['workflow_type', 'is_active', 'priority'], 'vrm_workflow_active_priority_idx');
            $table->index(['workflow_type', 'source_type'], 'vrm_workflow_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_routing_mappings');
        Schema::dropIfExists('verification_routing_settings');
    }
};
