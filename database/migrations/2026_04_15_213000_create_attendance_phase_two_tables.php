<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->string('approval_mode_leave')->default('hr_only')->after('suspicious_geo_threshold_meters');
            $table->string('approval_mode_regularization')->default('hr_only')->after('approval_mode_leave');
            $table->unsignedInteger('regularization_abuse_threshold')->default(3)->after('approval_mode_regularization');
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_paid')->default(true);
            $table->boolean('allow_half_day')->default(false);
            $table->decimal('annual_quota', 6, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('opening_balance', 6, 2)->default(0);
            $table->decimal('credited', 6, 2)->default(0);
            $table->decimal('used', 6, 2)->default(0);
            $table->decimal('remaining', 6, 2)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('duration_mode')->default('full_day');
            $table->decimal('days_requested', 6, 2)->default(1);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('final_approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_regularizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('request_type');
            $table->timestamp('requested_in_time')->nullable();
            $table->timestamp('requested_out_time')->nullable();
            $table->string('requested_status')->nullable();
            $table->text('reason')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('abuse_score_snapshot')->default(0);
            $table->timestamp('final_approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->string('step_type');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_approvals');
        Schema::dropIfExists('attendance_regularizations');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');

        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn([
                'approval_mode_leave',
                'approval_mode_regularization',
                'regularization_abuse_threshold',
            ]);
        });
    }
};
