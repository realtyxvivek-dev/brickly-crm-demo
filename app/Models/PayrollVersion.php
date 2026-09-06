<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payroll_freeze_id',
        'payroll_payslip_id',
        'version_no',
        'changed_by',
        'change_type',
        'previous_snapshot',
        'new_snapshot',
        'remark',
        'created_at',
    ];

    protected $casts = [
        'previous_snapshot' => 'array',
        'new_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function payrollFreeze(): BelongsTo
    {
        return $this->belongsTo(PayrollFreeze::class);
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(PayrollPayslip::class, 'payroll_payslip_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
