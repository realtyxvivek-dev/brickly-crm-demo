<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBasePath extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'updated_by',
        'title',
        'slug',
        'short_summary',
        'status',
        'is_featured',
        'display_order',
        'published_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'display_order' => 'integer',
        'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function pathItems(): HasMany
    {
        return $this->hasMany(KnowledgeBasePathItem::class, 'knowledge_base_path_id')->orderBy('display_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(KnowledgeBasePathAssignment::class, 'knowledge_base_path_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
