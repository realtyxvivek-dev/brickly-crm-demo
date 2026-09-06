<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectSizeVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'project_unit_type_id',
        'size_label',
        'carpet_area_sqft',
        'builtup_area_sqft',
        'base_rate_per_sqft',
        'rounding_rule',
        'calculated_price',
        'manual_price_override',
        'final_price',
        'is_price_on_request',
        'status',
        'visible_on_public_page',
        'floor_plan_image_path',
        'details_pdf_path',
        'display_order',
        'is_featured',
    ];

    protected $casts = [
        'carpet_area_sqft' => 'decimal:2',
        'builtup_area_sqft' => 'decimal:2',
        'base_rate_per_sqft' => 'decimal:2',
        'calculated_price' => 'decimal:2',
        'manual_price_override' => 'decimal:2',
        'final_price' => 'decimal:2',
        'is_price_on_request' => 'boolean',
        'visible_on_public_page' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(ProjectUnitType::class, 'project_unit_type_id');
    }

    public function towers(): BelongsToMany
    {
        return $this->belongsToMany(Tower::class, 'project_tower_variant_map')
            ->withPivot(['inventory_notes', 'facing', 'floor_range'])
            ->withTimestamps();
    }

    public function getFormattedFinalPriceAttribute(): ?string
    {
        $price = $this->final_price;
        if (!$price) {
            return null;
        }

        if ($price >= 10000000) {
            return 'Rs ' . number_format($price / 10000000, 2) . ' Cr';
        }

        if ($price >= 100000) {
            return 'Rs ' . number_format($price / 100000, 2) . ' L';
        }

        return 'Rs ' . number_format($price, 0);
    }

    public function getFloorPlanImageUrlAttribute(): ?string
    {
        if ($this->floor_plan_image_path) {
            return asset('storage/' . $this->floor_plan_image_path);
        }

        return SystemSettings::getPublicPageDefaultAssetUrl('floor_plan_image', config('project_public_page.defaults.floor_plan_image'));
    }

    public function getDetailsPdfUrlAttribute(): ?string
    {
        return $this->details_pdf_path ? asset('storage/' . $this->details_pdf_path) : null;
    }
}
