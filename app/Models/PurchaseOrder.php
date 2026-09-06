<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    public const REQUEST_TYPE_NEED_PURCHASE = 'need_purchase';
    public const REQUEST_TYPE_ALREADY_PURCHASED = 'already_purchased';

    public const TYPE_PHYSICAL = 'physical';
    public const TYPE_NON_PHYSICAL = 'non_physical';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DELETE_REQUESTED = 'delete_requested';
    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'request_number',
        'po_number',
        'created_by',
        'company_id',
        'request_type',
        'purchase_type',
        'vendor_name',
        'expense_category_id',
        'expense_subcategory_id',
        'purpose',
        'required_by_date',
        'attachment_path',
        'delivery_location',
        'expected_delivery_date',
        'receiver_name',
        'service_period',
        'license_note',
        'renewal_date',
        'total_amount',
        'status',
        'admin_reviewed_by',
        'admin_reviewed_at',
        'admin_remark',
        'received_by',
        'received_date',
        'receiving_note',
        'received_attachment_path',
        'expense_entry_id',
        'delete_requested_by',
        'delete_requested_at',
        'delete_request_reason',
        'delete_restore_status',
        'delete_reviewed_by',
        'delete_reviewed_at',
        'delete_reject_reason',
    ];

    protected $casts = [
        'required_by_date' => 'date',
        'expected_delivery_date' => 'date',
        'renewal_date' => 'date',
        'total_amount' => 'decimal:2',
        'admin_reviewed_at' => 'datetime',
        'received_date' => 'date',
        'delete_requested_at' => 'datetime',
        'delete_reviewed_at' => 'datetime',
    ];

    public static function types(): array
    {
        return [
            self::TYPE_PHYSICAL => 'Physical Product',
            self::TYPE_NON_PHYSICAL => 'Non-Physical Product',
        ];
    }

    public static function requestTypes(): array
    {
        return [
            self::REQUEST_TYPE_NEED_PURCHASE => 'Need Purchase',
            self::REQUEST_TYPE_ALREADY_PURCHASED => 'Already Purchased / Reimbursement',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PAYMENT_PENDING => 'Payment Pending',
            self::STATUS_PAID => 'Paid',
            self::STATUS_RECEIVED => 'Received',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_DELETE_REQUESTED => 'Delete Requested',
            self::STATUS_DELETED => 'Deleted / Voided',
        ];
    }

    public static function itemCategories(): array
    {
        return [
            'office_supplies' => 'Office Supplies',
            'software' => 'Software',
            'hardware' => 'Hardware',
            'marketing' => 'Marketing',
            'travel' => 'Travel',
            'other' => 'Other',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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

    public function expenseEntry(): BelongsTo
    {
        return $this->belongsTo(ExpenseEntry::class, 'expense_entry_id');
    }

    public function adminReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_reviewed_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function deleteRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delete_requested_by');
    }

    public function deleteReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delete_reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PurchaseOrderApprovalLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchaseOrderPayment::class);
    }

    public function isEditableByCreator(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_REJECTED], true);
    }

    public function isEditableByFinanceManager(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_REJECTED,
            self::STATUS_PAYMENT_PENDING,
        ], true);
    }

    public function canRequestDeleteByFinanceManager(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_REJECTED,
            self::STATUS_PAYMENT_PENDING,
        ], true);
    }

    public function canDeleteDirectlyByFinanceManager(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_REJECTED,
            self::STATUS_PAYMENT_PENDING,
            self::STATUS_DELETE_REQUESTED,
        ], true);
    }
}
