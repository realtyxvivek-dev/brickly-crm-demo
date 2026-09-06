<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectCollateral extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'category',
        'title',
        'link',
        'link_type',
        'is_latest',
        'notes',
    ];

    protected $casts = [
        'is_latest' => 'boolean',
    ];

    /**
     * Get the project that owns the collateral.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Detect link type from URL.
     */
    public function detectLinkType(): void
    {
        if (str_contains($this->link, 'youtube.com') || str_contains($this->link, 'youtu.be')) {
            $this->link_type = 'youtube';
        } elseif (str_contains($this->link, 'drive.google.com')) {
            $this->link_type = 'google_drive';
        }
    }

    /**
     * Get category icon.
     */
    public function getCategoryIcon(): string
    {
        return match($this->category) {
            'brochure' => '📄',
            'floor_plans' => '📐',
            'layout_plan' => '🗺',
            'videos' => '🎥',
            'price_sheet' => '💰',
            'legal_approvals' => '📁',
            default => '📋',
        };
    }

    /**
     * Boot method to auto-detect link type.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($collateral) {
            if (!$collateral->link_type && $collateral->link) {
                $collateral->detectLinkType();
            }
        });
    }
}
