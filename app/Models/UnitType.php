<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'tower_id',
        'unit_type',
        'area_sqft',
        'floor_plan_image',
        'calculated_price',
        'display_label',
        'is_starting_from',
    ];

    protected $casts = [
        'area_sqft' => 'decimal:2',
        'calculated_price' => 'decimal:2',
        'is_starting_from' => 'boolean',
    ];

    /**
     * Get the project that owns the unit type.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tower that owns the unit type (if any).
     */
    public function tower(): BelongsTo
    {
        return $this->belongsTo(Tower::class);
    }

    /**
     * Generate display label.
     */
    public function generateDisplayLabel(): void
    {
        $this->display_label = $this->unit_type . ' (' . number_format($this->area_sqft, 0) . ' sq.ft)';
    }

    /**
     * Calculate price based on BSP.
     */
    public function calculatePrice(?float $bsp = null): void
    {
        if (!$bsp) {
            $bsp = $this->project->pricingConfig?->bsp_per_sqft;
        }

        if (!$bsp) {
            $this->calculated_price = null;
            return;
        }

        $price = $this->area_sqft * $bsp;

        // Apply rounding if configured
        $roundingRule = $this->project->pricingConfig?->price_rounding_rule;
        if ($roundingRule === 'nearest_1000') {
            $price = round($price / 1000) * 1000;
        } elseif ($roundingRule === 'nearest_10000') {
            $price = round($price / 10000) * 10000;
        }

        $this->calculated_price = $price;
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): ?string
    {
        if (!$this->calculated_price) {
            return null;
        }

        // Convert to Lakhs or Crores
        if ($this->calculated_price >= 10000000) {
            return '₹' . number_format($this->calculated_price / 10000000, 2) . ' Cr';
        } elseif ($this->calculated_price >= 100000) {
            return '₹' . number_format($this->calculated_price / 100000, 2) . ' L';
        }

        return '₹' . number_format($this->calculated_price, 0);
    }

    /**
     * Get floor plan image URL.
     */
    public function getFloorPlanImageUrlAttribute(): ?string
    {
        if (!$this->floor_plan_image) {
            return null;
        }

        if (str_starts_with($this->floor_plan_image, 'http')) {
            return $this->floor_plan_image;
        }

        return asset('storage/' . $this->floor_plan_image);
    }

    /**
     * Boot method to auto-generate display label.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($unitType) {
            if (!$unitType->display_label) {
                $unitType->generateDisplayLabel();
            }
        });
    }
}
