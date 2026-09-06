<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('site_visits', 'closer_review_remark')) {
                $table->text('closer_review_remark')->nullable()->after('closer_rejection_reason');
            }

            if (!Schema::hasColumn('site_visits', 'closer_submitted_at')) {
                $table->timestamp('closer_submitted_at')->nullable()->after('closer_review_remark');
            }

            if (!Schema::hasColumn('site_visits', 'closer_submitted_by')) {
                $table->foreignId('closer_submitted_by')->nullable()->after('closer_submitted_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('site_visits', 'closer_reviewed_at')) {
                $table->timestamp('closer_reviewed_at')->nullable()->after('closer_submitted_by');
            }

            if (!Schema::hasColumn('site_visits', 'closer_reviewed_by')) {
                $table->foreignId('closer_reviewed_by')->nullable()->after('closer_reviewed_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('site_visits', 'closer_resubmission_count')) {
                $table->unsignedInteger('closer_resubmission_count')->default(0)->after('closer_reviewed_by');
            }

            if (!Schema::hasColumn('site_visits', 'kyc_submitted_at')) {
                $table->timestamp('kyc_submitted_at')->nullable()->after('closer_resubmission_count');
            }

            if (!Schema::hasColumn('site_visits', 'kyc_last_corrected_at')) {
                $table->timestamp('kyc_last_corrected_at')->nullable()->after('kyc_submitted_at');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE site_visits
                MODIFY closer_status ENUM('draft', 'pending_crm', 'correction_required', 'approved', 'rejected') NULL
            ");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE site_visits
                MODIFY closer_status ENUM('pending', 'verified', 'rejected') NULL
            ");
        }

        Schema::table('site_visits', function (Blueprint $table) {
            if (Schema::hasColumn('site_visits', 'closer_submitted_by')) {
                $table->dropForeign(['closer_submitted_by']);
            }

            if (Schema::hasColumn('site_visits', 'closer_reviewed_by')) {
                $table->dropForeign(['closer_reviewed_by']);
            }

            $dropColumns = [];
            foreach ([
                'closer_review_remark',
                'closer_submitted_at',
                'closer_submitted_by',
                'closer_reviewed_at',
                'closer_reviewed_by',
                'closer_resubmission_count',
                'kyc_submitted_at',
                'kyc_last_corrected_at',
            ] as $column) {
                if (Schema::hasColumn('site_visits', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
