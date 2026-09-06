<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayslip extends Model
{
    use HasFactory;

    public const STATUS_PREVIEW = 'preview';
    public const STATUS_EMPLOYEE_REVIEW = 'employee_review';
    public const STATUS_CORRECTION_REQUESTED = 'correction_requested';
    public const STATUS_CORRECTION_RESOLVED = 'correction_resolved';
    public const STATUS_HR_LOCKED = 'hr_locked';
    public const STATUS_ADMIN_APPROVED = 'admin_approved';
    public const STATUS_ADMIN_REJECTED = 'admin_rejected';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_GENERATED = 'generated';

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'payroll_freeze_id',
        'payslip_number',
        'verification_token',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'generated_at',
        'pdf_path',
        'snapshot_json',
        'status',
        'employee_correction_note',
        'employee_correction_requested_at',
        'hr_resolution_note',
        'hr_resolved_by',
        'hr_resolved_at',
        'admin_reviewed_by',
        'admin_reviewed_at',
        'admin_remark',
        'payment_mode',
        'payment_reference',
        'paid_amount',
        'payment_proof_path',
        'paid_at',
        'finance_paid_by',
        'finance_remark',
    ];

    protected $casts = [
        'gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'generated_at' => 'datetime',
        'snapshot_json' => 'array',
        'employee_correction_requested_at' => 'datetime',
        'hr_resolved_at' => 'datetime',
        'admin_reviewed_at' => 'datetime',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payrollFreeze(): BelongsTo
    {
        return $this->belongsTo(PayrollFreeze::class);
    }

    public function hrResolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_resolved_by');
    }

    public function adminReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_reviewed_by');
    }

    public function financePayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_paid_by');
    }

    public function versions()
    {
        return $this->hasMany(PayrollVersion::class);
    }

    public function canEmployeeRequestCorrection(): bool
    {
        return in_array($this->status, [self::STATUS_PREVIEW, self::STATUS_EMPLOYEE_REVIEW], true);
    }

    public function canDownloadFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_ADMIN_APPROVED,
            self::STATUS_PAYMENT_PENDING,
            self::STATUS_PAID,
            self::STATUS_GENERATED,
        ], true);
    }
}
