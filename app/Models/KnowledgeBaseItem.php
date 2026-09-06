<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'project_id',
        'created_by',
        'updated_by',
        'title',
        'slug',
        'short_summary',
        'article_content',
        'video_url',
        'pdf_path',
        'thumbnail_path',
        'change_summary',
        'status',
        'visibility_type',
        'visible_role_slugs',
        'visible_user_ids',
        'is_featured',
        'display_order',
        'published_at',
        'content_updated_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'display_order' => 'integer',
        'published_at' => 'datetime',
        'content_updated_at' => 'datetime',
        'visible_role_slugs' => 'array',
        'visible_user_ids' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseCategory::class, 'category_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(KnowledgeBaseAssignment::class, 'knowledge_base_item_id');
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(KnowledgeBaseProgress::class, 'knowledge_base_item_id');
    }

    public function pathItems(): HasMany
    {
        return $this->hasMany(KnowledgeBasePathItem::class, 'knowledge_base_item_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeVisibleToUser($query, User $user)
    {
        $roleSlug = optional($user->role)->slug;

        return $query->where(function ($builder) use ($user, $roleSlug) {
            $builder
                ->where('visibility_type', 'all_users')
                ->orWhere(function ($nested) use ($roleSlug) {
                    $nested->where('visibility_type', 'selected_roles');

                    if ($roleSlug) {
                        $nested->whereJsonContains('visible_role_slugs', $roleSlug);
                    } else {
                        $nested->whereRaw('1 = 0');
                    }
                })
                ->orWhere(function ($nested) use ($user) {
                    $nested->where('visibility_type', 'selected_users')
                        ->whereJsonContains('visible_user_ids', $user->id);
                })
                ->orWhereHas('assignments', fn ($assignmentQuery) => $assignmentQuery->where('user_id', $user->id));
        });
    }

    public function getPdfUrlAttribute(): ?string
    {
        if (!$this->pdf_path) {
            return null;
        }

        if (str_starts_with($this->pdf_path, 'http')) {
            return $this->pdf_path;
        }

        return Storage::disk('public')->url($this->pdf_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        if (str_starts_with($this->thumbnail_path, 'http')) {
            return $this->thumbnail_path;
        }

        return Storage::disk('public')->url($this->thumbnail_path);
    }

    public function getContentTypesAttribute(): array
    {
        $types = [];

        if (filled($this->article_content)) {
            $types[] = 'Article';
        }

        if (filled($this->video_url)) {
            $types[] = 'Video';
        }

        if (filled($this->pdf_path)) {
            $types[] = 'PDF';
        }

        return $types;
    }

    public function getVisibleRoleSlugsForFormAttribute(): array
    {
        return collect($this->visible_role_slugs)->filter()->values()->all();
    }

    public function getVisibleUserIdsForFormAttribute(): array
    {
        return collect($this->visible_user_ids)->map(fn ($value) => (int) $value)->values()->all();
    }
}
