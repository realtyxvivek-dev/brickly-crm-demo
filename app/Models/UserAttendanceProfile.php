<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserAttendanceProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'office_location_id',
        'attendance_policy_id',
        'employee_code',
        'salary_mode',
        'attendance_enabled',
        'allow_outside_punch_requests',
        'attendance_rollout_stage',
        'base_salary',
        'effective_from',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'attendance_enabled' => 'boolean',
        'allow_outside_punch_requests' => 'boolean',
        'base_salary' => 'decimal:2',
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

    public function salaryProfile(): HasOne
    {
        return $this->hasOne(UserSalaryProfile::class, 'user_id', 'user_id');
    }
}
