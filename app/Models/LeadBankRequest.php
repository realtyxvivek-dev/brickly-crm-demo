<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadBankRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_PARTIALLY_APPROVED,
        self::STATUS_FULFILLED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'requested_by',
        'reviewed_by',
        'quantity',
        'city',
        'source',
        'tag_ids',
        'expiry_days',
        'reason',
        'status',
        'matched_count',
        'allocatable_count',
        'match_snapshot',
        'rejection_reason',
        'reviewed_at',
    ];

    protected $casts = [
        'tag_ids' => 'array',
        'match_snapshot' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LeadBankAllocation::class);
    }
}
