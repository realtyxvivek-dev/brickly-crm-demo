<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->default(200);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->time('reminder_time')->default('09:00:00');
            $table->time('late_after_time')->default('09:00:00');
            $table->unsignedInteger('grace_minutes')->default(0);
            $table->time('normal_window_end_time')->default('11:30:00');
            $table->time('half_day_start_time')->default('13:00:00');
            $table->time('half_day_end_time')->default('16:00:00');
            $table->boolean('geo_fence_required')->default(true);
            $table->boolean('photo_required')->default(true);
            $table->unsignedInteger('compress_max_width')->default(1280);
            $table->unsignedInteger('compress_max_height')->default(1280);
            $table->unsignedTinyInteger('compress_quality')->default(75);
            $table->unsignedInteger('suspicious_geo_threshold_meters')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_attendance_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $table->foreignId('attendance_policy_id')->nullable()->constrained('attendance_policies')->nullOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('salary_mode')->nullable();
            $table->date('effective_from')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('attendance_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $table->string('name');
            $table->date('holiday_date');
            $table->boolean('is_paid')->default(true);
            $table->timestamps();
            $table->index(['holiday_date', 'office_location_id']);
        });

        Schema::create('attendance_weekoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'day_of_week', 'effective_from', 'effective_to'], 'attendance_weekoffs_lookup_idx');
        });

        Schema::create('attendance_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('compressed_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedTinyInteger('compression_quality')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('event_date');
            $table->string('event_type');
            $table->timestamp('event_time');
            $table->string('source')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $table->decimal('geo_distance_meters', 10, 2)->nullable();
            $table->boolean('inside_geo_fence')->nullable();
            $table->foreignId('photo_id')->nullable()->constrained('attendance_photos')->nullOnDelete();
            $table->string('device_fingerprint')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'event_date', 'event_type']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->foreignId('office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();
            $table->foreignId('attendance_policy_id')->nullable()->constrained('attendance_policies')->nullOnDelete();
            $table->timestamp('first_punch_in_at')->nullable();
            $table->timestamp('last_punch_out_at')->nullable();
            $table->string('status')->default('absent');
            $table->string('status_source')->default('auto');
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->decimal('payable_day_fraction', 4, 2)->default(0);
            $table->boolean('has_missing_punch_out')->default(false);
            $table->boolean('is_suspicious')->default(false);
            $table->json('suspicion_flags_json')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_photos');
        Schema::dropIfExists('attendance_weekoffs');
        Schema::dropIfExists('attendance_holidays');
        Schema::dropIfExists('user_attendance_profiles');
        Schema::dropIfExists('attendance_policies');
        Schema::dropIfExists('office_locations');
    }
};
