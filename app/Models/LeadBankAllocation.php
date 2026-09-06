<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadBankAllocation extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_RECALLED = 'recalled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PROTECTED = 'protected';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'lead_bank_request_id',
        'lead_id',
        'lead_assignment_id',
        'assigned_to',
        'assigned_by',
        'status',
        'allocated_at',
        'expires_at',
        'recalled_at',
        'recalled_by',
        'recall_reason',
        'meta',
    ];

    protected $casts = [
        'allocated_at' => 'datetime',
        'expires_at' => 'datetime',
        'recalled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(LeadBankRequest::class, 'lead_bank_request_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class, 'lead_assignment_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function recalledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recalled_by');
    }
}
