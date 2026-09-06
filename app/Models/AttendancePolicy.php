<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'office_location_id',
        'is_default',
        'reminder_time',
        'late_after_time',
        'grace_minutes',
        'normal_window_end_time',
        'half_day_start_time',
        'half_day_end_time',
        'geo_fence_required',
        'allow_outside_punch_requests',
        'outside_punch_permission_default_enabled',
        'photo_required',
        'selfie_required',
        'face_review_required',
        'payroll_block_on_pending_face_review',
        'duplicate_photo_threshold',
        'face_compare_provider',
        'liveness_provider',
        'provider_settings_json',
        'compress_max_width',
        'compress_max_height',
        'compress_quality',
        'suspicious_geo_threshold_meters',
        'approval_mode_leave',
        'approval_mode_regularization',
        'regularization_abuse_threshold',
        'late_penalty_type',
        'late_penalty_threshold',
        'overtime_enabled',
        'overtime_after_minutes',
        'overtime_min_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'geo_fence_required' => 'boolean',
        'allow_outside_punch_requests' => 'boolean',
        'outside_punch_permission_default_enabled' => 'boolean',
        'photo_required' => 'boolean',
        'selfie_required' => 'boolean',
        'face_review_required' => 'boolean',
        'payroll_block_on_pending_face_review' => 'boolean',
        'provider_settings_json' => 'array',
        'overtime_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(UserAttendanceProfile::class);
    }
}
