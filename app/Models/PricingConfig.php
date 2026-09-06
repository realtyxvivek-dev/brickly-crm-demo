<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'bsp_per_sqft',
        'price_rounding_rule',
    ];

    protected $casts = [
        'bsp_per_sqft' => 'decimal:2',
    ];

    /**
     * Get the project that owns the pricing config.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get formatted BSP.
     */
    public function getFormattedBspAttribute(): string
    {
        return '₹' . number_format($this->bsp_per_sqft, 2) . ' / sq.ft';
    }
}
