<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attendance_policies', 'selfie_required')) {
            Schema::table('attendance_policies', function (Blueprint $table) {
                $table->boolean('selfie_required')->default(false)->after('photo_required');
                $table->boolean('face_review_required')->default(false)->after('selfie_required');
                $table->boolean('payroll_block_on_pending_face_review')->default(false)->after('face_review_required');
                $table->unsignedInteger('duplicate_photo_threshold')->default(2)->after('payroll_block_on_pending_face_review');
                $table->string('face_compare_provider')->nullable()->after('duplicate_photo_threshold');
                $table->string('liveness_provider')->nullable()->after('face_compare_provider');
                $table->json('provider_settings_json')->nullable()->after('liveness_provider');
            });
        }

        if (!Schema::hasColumn('attendance_photos', 'file_hash')) {
            Schema::table('attendance_photos', function (Blueprint $table) {
                $table->string('file_hash', 128)->nullable()->after('compression_quality');
                $table->json('meta_json')->nullable()->after('file_hash');
                $table->index('file_hash');
            });
        }

        if (!Schema::hasColumn('attendance_records', 'fraud_review_status')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->string('fraud_review_status')->default('clear')->after('is_suspicious');
                $table->boolean('fraud_payroll_blocked')->default(false)->after('fraud_review_status');
                $table->text('fraud_review_reason')->nullable()->after('fraud_payroll_blocked');
            });
        }

        if (!Schema::hasTable('salary_structures')) {
            Schema::create('salary_structures', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('salary_structure_components')) {
            Schema::create('salary_structure_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
                $table->string('component_type');
                $table->string('code');
                $table->string('label');
                $table->string('calc_type')->default('fixed');
                $table->decimal('value', 12, 2)->default(0);
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['salary_structure_id', 'component_type'], 'ss_components_type_idx');
            });
        }

        if (!Schema::hasTable('payroll_deduction_heads')) {
            Schema::create('payroll_deduction_heads', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('type')->default('deduction');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_salary_profiles')) {
            Schema::create('user_salary_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('salary_structure_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('base_salary', 12, 2)->default(0);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'effective_from']);
            });
        }

        if (!Schema::hasTable('payroll_manual_adjustments')) {
            Schema::create('payroll_manual_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payroll_deduction_head_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->string('label');
                $table->string('type');
                $table->decimal('amount', 12, 2);
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['year', 'month', 'user_id']);
            });
        }

        if (!Schema::hasTable('payroll_payslip_settings')) {
            Schema::create('payroll_payslip_settings', function (Blueprint $table) {
                $table->id();
                $table->string('payslip_prefix')->default('PSL');
                $table->string('company_name')->nullable();
                $table->text('header_text')->nullable();
                $table->text('footer_text')->nullable();
                $table->text('default_notes')->nullable();
                $table->string('signatory_name')->nullable();
                $table->string('signatory_title')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payroll_payslips')) {
            Schema::create('payroll_payslips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->foreignId('payroll_freeze_id')->nullable()->constrained()->nullOnDelete();
                $table->string('payslip_number')->unique();
                $table->decimal('gross_pay', 12, 2)->default(0);
                $table->decimal('total_deductions', 12, 2)->default(0);
                $table->decimal('net_pay', 12, 2)->default(0);
                $table->timestamp('generated_at')->nullable();
                $table->string('pdf_path')->nullable();
                $table->json('snapshot_json');
                $table->string('status')->default('generated');
                $table->timestamps();
                $table->unique(['user_id', 'year', 'month']);
                $table->index(['year', 'month']);
            });
        }

        if (!Schema::hasTable('attendance_face_reviews')) {
            Schema::create('attendance_face_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
                $table->foreignId('attendance_event_id')->nullable()->constrained('attendance_events')->nullOnDelete();
                $table->foreignId('attendance_photo_id')->nullable()->constrained('attendance_photos')->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status')->default('pending_review');
                $table->text('remarks')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_face_reviews');
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_payslip_settings');
        Schema::dropIfExists('payroll_manual_adjustments');
        Schema::dropIfExists('user_salary_profiles');
        Schema::dropIfExists('payroll_deduction_heads');
        Schema::dropIfExists('salary_structure_components');
        Schema::dropIfExists('salary_structures');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['fraud_review_status', 'fraud_payroll_blocked', 'fraud_review_reason']);
        });

        Schema::table('attendance_photos', function (Blueprint $table) {
            $table->dropIndex(['file_hash']);
            $table->dropColumn(['file_hash', 'meta_json']);
        });

        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn([
                'selfie_required',
                'face_review_required',
                'payroll_block_on_pending_face_review',
                'duplicate_photo_threshold',
                'face_compare_provider',
                'liveness_provider',
                'provider_settings_json',
            ]);
        });
    }
};
