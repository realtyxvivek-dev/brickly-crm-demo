<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE = 'late';
    public const STATUS_HALF_DAY = 'half_day';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_WEEK_OFF = 'week_off';
    public const STATUS_HOLIDAY = 'holiday';
    public const STATUS_LEAVE = 'leave';

    protected $fillable = [
        'user_id',
        'attendance_date',
        'office_location_id',
        'attendance_policy_id',
        'first_punch_in_at',
        'last_punch_out_at',
        'status',
        'status_source',
        'auto_first_punch_in_at',
        'auto_last_punch_out_at',
        'auto_status',
        'auto_status_source',
        'late_minutes',
        'auto_late_minutes',
        'worked_minutes',
        'auto_worked_minutes',
        'payable_day_fraction',
        'auto_payable_day_fraction',
        'has_missing_punch_out',
        'auto_has_missing_punch_out',
        'manual_first_punch_in_at',
        'manual_last_punch_out_at',
        'manual_status',
        'manual_override_reason',
        'manual_overridden_by',
        'manual_overridden_at',
        'manual_cleared_by',
        'manual_cleared_at',
        'outside_punch_status',
        'outside_punch_distance_meters',
        'is_suspicious',
        'fraud_review_status',
        'fraud_payroll_blocked',
        'fraud_review_reason',
        'suspicion_flags_json',
        'finalized_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'first_punch_in_at' => 'datetime',
        'last_punch_out_at' => 'datetime',
        'auto_first_punch_in_at' => 'datetime',
        'auto_last_punch_out_at' => 'datetime',
        'manual_first_punch_in_at' => 'datetime',
        'manual_last_punch_out_at' => 'datetime',
        'has_missing_punch_out' => 'boolean',
        'auto_has_missing_punch_out' => 'boolean',
        'outside_punch_distance_meters' => 'float',
        'is_suspicious' => 'boolean',
        'fraud_payroll_blocked' => 'boolean',
        'suspicion_flags_json' => 'array',
        'finalized_at' => 'datetime',
        'manual_overridden_at' => 'datetime',
        'manual_cleared_at' => 'datetime',
        'payable_day_fraction' => 'decimal:2',
        'auto_payable_day_fraction' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function attendancePolicy(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicy::class);
    }

    public function overrideLogs(): HasMany
    {
        return $this->hasMany(AttendanceRecordOverrideLog::class)->latest();
    }

    public function manualOverriddenByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_overridden_by');
    }

    public function manualClearedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_cleared_by');
    }

    public function hasActiveManualOverride(): bool
    {
        return $this->manual_cleared_at === null
            && (
                $this->manual_status !== null
                || $this->manual_first_punch_in_at !== null
                || $this->manual_last_punch_out_at !== null
            );
    }
}
