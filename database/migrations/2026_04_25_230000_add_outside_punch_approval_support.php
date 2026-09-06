<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_policies', 'allow_outside_punch_requests')) {
                $table->boolean('allow_outside_punch_requests')->default(true)->after('geo_fence_required');
            }
            if (!Schema::hasColumn('attendance_policies', 'outside_punch_permission_default_enabled')) {
                $table->boolean('outside_punch_permission_default_enabled')->default(false)->after('allow_outside_punch_requests');
            }
        });

        Schema::table('user_attendance_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('user_attendance_profiles', 'allow_outside_punch_requests')) {
                $table->boolean('allow_outside_punch_requests')->nullable()->after('attendance_enabled');
            }
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_records', 'outside_punch_status')) {
                $table->string('outside_punch_status', 50)->nullable()->after('has_missing_punch_out');
            }
            if (!Schema::hasColumn('attendance_records', 'outside_punch_distance_meters')) {
                $table->decimal('outside_punch_distance_meters', 10, 2)->nullable()->after('outside_punch_status');
            }
        });

        if (!Schema::hasTable('attendance_outside_punch_permissions')) {
            Schema::create('attendance_outside_punch_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('attendance_policy_id')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('allow_punch_in')->default(true);
                $table->boolean('allow_punch_out')->default(true);
                $table->text('reason')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'att_out_perm_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('attendance_policy_id', 'att_out_perm_policy_fk')->references('id')->on('attendance_policies')->nullOnDelete();
                $table->foreign('created_by', 'att_out_perm_creator_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['user_id', 'start_date', 'end_date'], 'att_outside_permissions_user_date_idx');
                $table->index(['attendance_policy_id', 'start_date', 'end_date'], 'att_outside_permissions_policy_date_idx');
            });
        }

        if (!Schema::hasTable('attendance_outside_punch_requests')) {
            Schema::create('attendance_outside_punch_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('attendance_date');
                $table->enum('punch_type', ['in', 'out']);
                $table->dateTime('requested_at');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedBigInteger('office_location_id')->nullable();
                $table->decimal('geo_distance_meters', 10, 2)->nullable();
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'consumed', 'cancelled'])->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('attendance_event_id')->nullable();
                $table->unsignedBigInteger('attendance_record_id')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'att_out_req_user_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('office_location_id', 'att_out_req_office_fk')->references('id')->on('office_locations')->nullOnDelete();
                $table->foreign('approved_by', 'att_out_req_approver_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('attendance_event_id', 'att_out_req_event_fk')->references('id')->on('attendance_events')->nullOnDelete();
                $table->foreign('attendance_record_id', 'att_out_req_record_fk')->references('id')->on('attendance_records')->nullOnDelete();
                $table->index(['user_id', 'attendance_date', 'punch_type'], 'att_outside_requests_user_date_type_idx');
                $table->index(['status', 'attendance_date'], 'att_outside_requests_status_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_outside_punch_requests');
        Schema::dropIfExists('attendance_outside_punch_permissions');

        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (['outside_punch_status', 'outside_punch_distance_meters'] as $column) {
                if (Schema::hasColumn('attendance_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('user_attendance_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('user_attendance_profiles', 'allow_outside_punch_requests')) {
                $table->dropColumn('allow_outside_punch_requests');
            }
        });

        Schema::table('attendance_policies', function (Blueprint $table) {
            foreach (['allow_outside_punch_requests', 'outside_punch_permission_default_enabled'] as $column) {
                if (Schema::hasColumn('attendance_policies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
