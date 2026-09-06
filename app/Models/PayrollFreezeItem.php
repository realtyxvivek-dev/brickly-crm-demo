<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollFreezeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_freeze_id',
        'user_id',
        'attendance_monthly_rollup_id',
        'snapshot_json',
    ];

    protected $casts = [
        'snapshot_json' => 'array',
    ];

    public function payrollFreeze(): BelongsTo
    {
        return $this->belongsTo(PayrollFreeze::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rollup(): BelongsTo
    {
        return $this->belongsTo(AttendanceMonthlyRollup::class, 'attendance_monthly_rollup_id');
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(PayrollPayslip::class, 'payroll_freeze_id', 'payroll_freeze_id')
            ->whereColumn('user_id', 'payroll_freeze_items.user_id');
    }
}
