<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_exit_workflows')) {
            Schema::create('employee_exit_workflows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_profile_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('status')->default('on_notice');
                $table->date('notice_start_date')->nullable();
                $table->date('resignation_date')->nullable();
                $table->date('last_working_date')->nullable();
                $table->text('exit_reason')->nullable();
                $table->timestamp('hr_clearance_completed_at')->nullable();
                $table->timestamp('finance_clearance_completed_at')->nullable();
                $table->timestamp('asset_clearance_completed_at')->nullable();
                $table->timestamp('login_disabled_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('last_notice_reminder_sent_at')->nullable();
                $table->timestamp('last_asset_alert_sent_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['status', 'last_working_date'], 'emp_exit_status_lwd_idx');
            });
        }

        if (!Schema::hasTable('employee_salary_revisions')) {
            Schema::create('employee_salary_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_salary_profile_id')->nullable()->constrained('user_salary_profiles')->nullOnDelete();
                $table->foreignId('salary_structure_id')->nullable()->constrained('salary_structures')->nullOnDelete();
                $table->decimal('previous_base_salary', 12, 2)->default(0);
                $table->decimal('new_base_salary', 12, 2)->default(0);
                $table->decimal('previous_total_salary', 12, 2)->default(0);
                $table->decimal('new_total_salary', 12, 2)->default(0);
                $table->date('effective_from');
                $table->string('reason')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['employee_profile_id', 'effective_from'], 'emp_sal_rev_profile_eff_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_revisions');
        Schema::dropIfExists('employee_exit_workflows');
    }
};
