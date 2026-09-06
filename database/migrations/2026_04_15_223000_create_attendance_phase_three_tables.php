<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->string('late_penalty_type')->default('none')->after('regularization_abuse_threshold');
            $table->unsignedInteger('late_penalty_threshold')->default(3)->after('late_penalty_type');
        });

        Schema::table('user_attendance_profiles', function (Blueprint $table) {
            $table->decimal('base_salary', 12, 2)->nullable()->after('salary_mode');
        });

        Schema::create('attendance_monthly_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $table->decimal('present_days', 6, 2)->default(0);
            $table->decimal('half_days', 6, 2)->default(0);
            $table->decimal('absent_days', 6, 2)->default(0);
            $table->decimal('paid_leave_days', 6, 2)->default(0);
            $table->decimal('unpaid_leave_days', 6, 2)->default(0);
            $table->decimal('weekoff_days', 6, 2)->default(0);
            $table->decimal('holiday_days', 6, 2)->default(0);
            $table->unsignedInteger('late_count')->default(0);
            $table->decimal('late_penalty_days', 6, 2)->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->decimal('payable_days', 6, 2)->default(0);
            $table->decimal('estimated_salary', 12, 2)->nullable();
            $table->boolean('is_frozen')->default(false);
            $table->timestamp('frozen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'year', 'month']);
            $table->index(['year', 'month', 'office_location_id']);
        });

        Schema::create('payroll_freezes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $table->string('freeze_scope')->default('company');
            $table->string('status')->default('draft');
            $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('frozen_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_freeze_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_freeze_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_monthly_rollup_id')->nullable()->constrained('attendance_monthly_rollups')->nullOnDelete();
            $table->json('snapshot_json');
            $table->timestamps();
            $table->index(['payroll_freeze_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_freeze_items');
        Schema::dropIfExists('payroll_freezes');
        Schema::dropIfExists('attendance_monthly_rollups');

        Schema::table('user_attendance_profiles', function (Blueprint $table) {
            $table->dropColumn('base_salary');
        });

        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn(['late_penalty_type', 'late_penalty_threshold']);
        });
    }
};
