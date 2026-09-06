<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPublicPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'hero_title',
        'hero_subtitle',
        'short_intro',
        'featured_badges',
        'hero_cover_path',
        'builder_logo_path',
        'map_embed',
        'location_summary',
        'latitude',
        'longitude',
        'map_zoom',
        'popular_origins',
        'other_charges',
        'base_rate_per_sqft',
        'rounding_rule',
        'towers_enabled',
        'show_call',
        'show_whatsapp',
        'show_book_visit',
        'show_request_callback',
        'show_downloads',
        'show_video',
        'show_tour_360',
        'call_phone',
        'whatsapp_number',
        'book_visit_url',
        'callback_url',
        'status',
        'preview_token',
        'last_saved_at',
        'last_saved_by',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'featured_badges' => 'array',
        'popular_origins' => 'array',
        'other_charges' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'map_zoom' => 'integer',
        'base_rate_per_sqft' => 'decimal:2',
        'towers_enabled' => 'boolean',
        'show_call' => 'boolean',
        'show_whatsapp' => 'boolean',
        'show_book_visit' => 'boolean',
        'show_request_callback' => 'boolean',
        'show_downloads' => 'boolean',
        'show_video' => 'boolean',
        'show_tour_360' => 'boolean',
        'last_saved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getHeroCoverUrlAttribute(): ?string
    {
        if ($this->hero_cover_path) {
            return asset('storage/' . $this->hero_cover_path);
        }

        return SystemSettings::getPublicPageDefaultAssetUrl('hero_image', config('project_public_page.defaults.hero_image'));
    }

    public function getBuilderLogoUrlAttribute(): ?string
    {
        if ($this->builder_logo_path) {
            return asset('storage/' . $this->builder_logo_path);
        }

        return SystemSettings::getPublicPageDefaultAssetUrl('builder_logo', config('project_public_page.defaults.builder_logo'));
    }
}
