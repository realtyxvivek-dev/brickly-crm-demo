<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseEntry extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const PAYMENT_MODE_CASH = 'cash';
    public const PAYMENT_MODE_BANK = 'bank';
    public const PAYMENT_MODE_UPI = 'upi';
    public const PAYMENT_MODE_CREDIT_CARD = 'credit_card';

    protected $fillable = [
        'company_id',
        'expense_category_id',
        'expense_subcategory_id',
        'expense_date',
        'amount',
        'payment_mode',
        'expense_payment_method_id',
        'paid_to',
        'reference_no',
        'remarks',
        'status',
        'reject_reason',
        'attachment_path',
        'created_by',
        'updated_by',
        'approval_assigned_to',
        'approval_assigned_by',
        'approval_assigned_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'deleted_by',
        'delete_reason',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'approval_assigned_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseSubcategory::class, 'expense_subcategory_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_assigned_by');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(ExpensePaymentMethod::class, 'expense_payment_method_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function paymentDisplay(): string
    {
        $mode = ExpensePaymentMethod::types()[$this->payment_mode] ?? strtoupper((string) $this->payment_mode);

        if (!$this->paymentMethod) {
            return $mode;
        }

        return $mode . ' - ' . $this->paymentMethod->name;
    }
}
