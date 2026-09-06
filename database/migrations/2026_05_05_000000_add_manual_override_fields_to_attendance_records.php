<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManualOverrideFieldsToAttendanceRecords extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_records', 'auto_first_punch_in_at')) {
                $table->dateTime('auto_first_punch_in_at')->nullable()->after('last_punch_out_at');
            }
            if (!Schema::hasColumn('attendance_records', 'auto_last_punch_out_at')) {
                $table->dateTime('auto_last_punch_out_at')->nullable()->after('auto_first_punch_in_at');
            }
            if (!Schema::hasColumn('attendance_records', 'auto_status')) {
                $table->string('auto_status', 40)->nullable()->after('status');
            }
            if (!Schema::hasColumn('attendance_records', 'auto_status_source')) {
                $table->string('auto_status_source', 40)->nullable()->after('status_source');
            }
            if (!Schema::hasColumn('attendance_records', 'auto_late_minutes')) {
                $table->integer('auto_late_minutes')->nullable()->after('late_minutes');
            }
            if (!Schema::hasColumn('attendance_records', 'auto_worked_minutes')) {
                $table->integer('auto_worked_minutes')->nullable()->after('worked_minutes');
            }
            if (!Schema::hasColumn('attendance_records', 'auto_payable_day_fraction')) {
                $table->decimal('auto_payable_day_fraction', 4, 2)->nullable()->after('payable_day_fraction');
            }
            if (!Schema::hasColumn('attendance_records', 'auto_has_missing_punch_out')) {
                $table->boolean('auto_has_missing_punch_out')->nullable()->after('has_missing_punch_out');
            }

            if (!Schema::hasColumn('attendance_records', 'manual_first_punch_in_at')) {
                $table->dateTime('manual_first_punch_in_at')->nullable()->after('auto_last_punch_out_at');
            }
            if (!Schema::hasColumn('attendance_records', 'manual_last_punch_out_at')) {
                $table->dateTime('manual_last_punch_out_at')->nullable()->after('manual_first_punch_in_at');
            }
            if (!Schema::hasColumn('attendance_records', 'manual_status')) {
                $table->string('manual_status', 40)->nullable()->after('auto_status');
            }
            if (!Schema::hasColumn('attendance_records', 'manual_override_reason')) {
                $table->text('manual_override_reason')->nullable()->after('manual_status');
            }
            if (!Schema::hasColumn('attendance_records', 'manual_overridden_by')) {
                $table->foreignId('manual_overridden_by')->nullable()->after('manual_override_reason')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('attendance_records', 'manual_overridden_at')) {
                $table->dateTime('manual_overridden_at')->nullable()->after('manual_overridden_by');
            }
            if (!Schema::hasColumn('attendance_records', 'manual_cleared_by')) {
                $table->foreignId('manual_cleared_by')->nullable()->after('manual_overridden_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('attendance_records', 'manual_cleared_at')) {
                $table->dateTime('manual_cleared_at')->nullable()->after('manual_cleared_by');
            }
        });

        if (!Schema::hasTable('attendance_record_override_logs')) {
            Schema::create('attendance_record_override_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
                $table->string('action', 20);
                $table->text('reason')->nullable();
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->timestamps();

                $table->index(['attendance_record_id', 'created_at'], 'arol_record_created_idx');
                $table->index(['user_id', 'created_at'], 'arol_user_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_record_override_logs');

        Schema::table('attendance_records', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_records', 'manual_overridden_by')) {
                $table->dropConstrainedForeignId('manual_overridden_by');
            }
            if (Schema::hasColumn('attendance_records', 'manual_cleared_by')) {
                $table->dropConstrainedForeignId('manual_cleared_by');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('attendance_records', 'auto_first_punch_in_at') ? 'auto_first_punch_in_at' : null,
                Schema::hasColumn('attendance_records', 'auto_last_punch_out_at') ? 'auto_last_punch_out_at' : null,
                Schema::hasColumn('attendance_records', 'auto_status') ? 'auto_status' : null,
                Schema::hasColumn('attendance_records', 'auto_status_source') ? 'auto_status_source' : null,
                Schema::hasColumn('attendance_records', 'auto_late_minutes') ? 'auto_late_minutes' : null,
                Schema::hasColumn('attendance_records', 'auto_worked_minutes') ? 'auto_worked_minutes' : null,
                Schema::hasColumn('attendance_records', 'auto_payable_day_fraction') ? 'auto_payable_day_fraction' : null,
                Schema::hasColumn('attendance_records', 'auto_has_missing_punch_out') ? 'auto_has_missing_punch_out' : null,
                Schema::hasColumn('attendance_records', 'manual_first_punch_in_at') ? 'manual_first_punch_in_at' : null,
                Schema::hasColumn('attendance_records', 'manual_last_punch_out_at') ? 'manual_last_punch_out_at' : null,
                Schema::hasColumn('attendance_records', 'manual_status') ? 'manual_status' : null,
                Schema::hasColumn('attendance_records', 'manual_override_reason') ? 'manual_override_reason' : null,
                Schema::hasColumn('attendance_records', 'manual_overridden_at') ? 'manual_overridden_at' : null,
                Schema::hasColumn('attendance_records', 'manual_cleared_at') ? 'manual_cleared_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
}
