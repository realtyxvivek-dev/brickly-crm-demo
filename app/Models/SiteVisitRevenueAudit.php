<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVisitRevenueAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_visit_id',
        'old_revenue_value',
        'new_revenue_value',
        'old_revenue_note',
        'new_revenue_note',
        'changed_by',
    ];

    protected $casts = [
        'old_revenue_value' => 'decimal:2',
        'new_revenue_value' => 'decimal:2',
    ];

    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
