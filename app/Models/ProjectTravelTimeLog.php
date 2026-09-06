<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTravelTimeLog extends Model
{
    protected $fillable = [
        'project_id',
        'share_token',
        'action',
        'origin_source',
        'query',
        'origin_label',
        'status',
        'drive_available',
        'walk_available',
        'cache_hit',
        'provider',
        'meta',
    ];

    protected $casts = [
        'drive_available' => 'boolean',
        'walk_available' => 'boolean',
        'cache_hit' => 'boolean',
        'meta' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
