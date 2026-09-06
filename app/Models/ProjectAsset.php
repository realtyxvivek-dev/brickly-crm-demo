<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'asset_type',
        'title',
        'mime_type',
        'source_type',
        'file_size',
        'file_path',
        'external_url',
        'preview_image_path',
        'tracking_key',
        'display_order',
        'is_featured',
        'meta',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'file_size' => 'integer',
        'meta' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function getPreviewImageUrlAttribute(): ?string
    {
        if ($this->preview_image_path) {
            return asset('storage/' . $this->preview_image_path);
        }

        if ($this->asset_type === 'gallery_image' && $this->file_path) {
            return asset('storage/' . $this->file_path);
        }

        return match ($this->asset_type) {
            'gallery_image' => SystemSettings::getPublicPageDefaultAssetUrl('gallery_image', config('project_public_page.defaults.gallery_image')),
            default => SystemSettings::getPublicPageDefaultAssetUrl('media_preview', config('project_public_page.defaults.media_preview')),
        };
    }
}
