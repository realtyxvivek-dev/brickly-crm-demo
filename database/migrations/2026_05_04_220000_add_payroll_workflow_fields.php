<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_freezes', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_freezes', 'hr_finalized_by')) {
                $table->foreignId('hr_finalized_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payroll_freezes', 'hr_finalized_at')) {
                $table->timestamp('hr_finalized_at')->nullable()->after('hr_finalized_by');
            }
            if (!Schema::hasColumn('payroll_freezes', 'submitted_to_admin_by')) {
                $table->foreignId('submitted_to_admin_by')->nullable()->after('hr_finalized_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payroll_freezes', 'submitted_to_admin_at')) {
                $table->timestamp('submitted_to_admin_at')->nullable()->after('submitted_to_admin_by');
            }
            if (!Schema::hasColumn('payroll_freezes', 'admin_reviewed_by')) {
                $table->foreignId('admin_reviewed_by')->nullable()->after('submitted_to_admin_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payroll_freezes', 'admin_reviewed_at')) {
                $table->timestamp('admin_reviewed_at')->nullable()->after('admin_reviewed_by');
            }
            if (!Schema::hasColumn('payroll_freezes', 'admin_remark')) {
                $table->text('admin_remark')->nullable()->after('admin_reviewed_at');
            }
            if (!Schema::hasColumn('payroll_freezes', 'locked_by_user_id')) {
                $table->foreignId('locked_by_user_id')->nullable()->after('admin_remark')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payroll_freezes', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('locked_by_user_id');
            }
            if (!Schema::hasColumn('payroll_freezes', 'lock_expires_at')) {
                $table->timestamp('lock_expires_at')->nullable()->after('locked_at');
            }
        });

        Schema::table('payroll_payslips', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_payslips', 'employee_correction_note')) {
                $table->text('employee_correction_note')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payroll_payslips', 'employee_correction_requested_at')) {
                $table->timestamp('employee_correction_requested_at')->nullable()->after('employee_correction_note');
            }
            if (!Schema::hasColumn('payroll_payslips', 'hr_resolution_note')) {
                $table->text('hr_resolution_note')->nullable()->after('employee_correction_requested_at');
            }
            if (!Schema::hasColumn('payroll_payslips', 'hr_resolved_by')) {
                $table->foreignId('hr_resolved_by')->nullable()->after('hr_resolution_note')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payroll_payslips', 'hr_resolved_at')) {
                $table->timestamp('hr_resolved_at')->nullable()->after('hr_resolved_by');
            }
            if (!Schema::hasColumn('payroll_payslips', 'admin_reviewed_by')) {
                $table->foreignId('admin_reviewed_by')->nullable()->after('hr_resolved_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payroll_payslips', 'admin_reviewed_at')) {
                $table->timestamp('admin_reviewed_at')->nullable()->after('admin_reviewed_by');
            }
            if (!Schema::hasColumn('payroll_payslips', 'admin_remark')) {
                $table->text('admin_remark')->nullable()->after('admin_reviewed_at');
            }
            if (!Schema::hasColumn('payroll_payslips', 'payment_mode')) {
                $table->string('payment_mode')->nullable()->after('admin_remark');
            }
            if (!Schema::hasColumn('payroll_payslips', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_mode');
            }
            if (!Schema::hasColumn('payroll_payslips', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->nullable()->after('payment_reference');
            }
            if (!Schema::hasColumn('payroll_payslips', 'payment_proof_path')) {
                $table->string('payment_proof_path')->nullable()->after('paid_amount');
            }
            if (!Schema::hasColumn('payroll_payslips', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_proof_path');
            }
            if (!Schema::hasColumn('payroll_payslips', 'finance_paid_by')) {
                $table->foreignId('finance_paid_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('payroll_payslips', 'finance_remark')) {
                $table->text('finance_remark')->nullable()->after('finance_paid_by');
            }
        });

        if (!Schema::hasTable('payroll_versions')) {
            Schema::create('payroll_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_freeze_id')->constrained('payroll_freezes')->cascadeOnDelete();
                $table->foreignId('payroll_payslip_id')->nullable()->constrained('payroll_payslips')->cascadeOnDelete();
                $table->unsignedInteger('version_no');
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('change_type');
                $table->json('previous_snapshot')->nullable();
                $table->json('new_snapshot')->nullable();
                $table->text('remark')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['payroll_freeze_id', 'payroll_payslip_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_versions');
    }
};
