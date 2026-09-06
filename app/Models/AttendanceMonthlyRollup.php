<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMonthlyRollup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'office_location_id',
        'present_days',
        'half_days',
        'absent_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'weekoff_days',
        'holiday_days',
        'late_count',
        'late_penalty_days',
        'overtime_minutes',
        'payable_days',
        'estimated_salary',
        'is_frozen',
        'frozen_at',
    ];

    protected $casts = [
        'present_days' => 'decimal:2',
        'half_days' => 'decimal:2',
        'absent_days' => 'decimal:2',
        'paid_leave_days' => 'decimal:2',
        'unpaid_leave_days' => 'decimal:2',
        'weekoff_days' => 'decimal:2',
        'holiday_days' => 'decimal:2',
        'late_penalty_days' => 'decimal:2',
        'payable_days' => 'decimal:2',
        'estimated_salary' => 'decimal:2',
        'is_frozen' => 'boolean',
        'frozen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }
}
