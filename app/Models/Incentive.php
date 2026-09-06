<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incentive extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_visit_id',
        'user_id',
        'type',
        'amount',
        'status',
        'sales_head_verified_by',
        'sales_head_verified_at',
        'crm_verified_by',
        'crm_verified_at',
        'finance_manager_verified_by',
        'finance_manager_verified_at',
        'rejected_by',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sales_head_verified_at' => 'datetime',
        'crm_verified_at' => 'datetime',
        'finance_manager_verified_at' => 'datetime',
    ];

    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesHeadVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_head_verified_by');
    }

    public function crmVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'crm_verified_by');
    }

    public function financeManagerVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_manager_verified_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Check if incentive is verified by Finance Manager
     */
    public function isFullyVerified(): bool
    {
        return $this->status === 'verified' 
            && $this->finance_manager_verified_by !== null;
    }

    /**
     * Check if incentive is pending Sales Head verification (deprecated)
     */
    public function isPendingSalesHead(): bool
    {
        return $this->status === 'pending_sales_head';
    }

    /**
     * Check if incentive is pending CRM verification (deprecated)
     */
    public function isPendingCrm(): bool
    {
        return $this->status === 'pending_crm';
    }

    /**
     * Check if incentive is pending Finance Manager verification
     */
    public function isPendingFinanceManager(): bool
    {
        return $this->status === 'pending_finance_manager';
    }
}
