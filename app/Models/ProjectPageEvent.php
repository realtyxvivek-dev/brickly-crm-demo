<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPageEvent extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'project_id',
        'project_share_link_id',
        'event_name',
        'section',
        'session_id',
        'duration_ms',
        'meta',
        'ip_hash',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'duration_ms' => 'integer',
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shareLink(): BelongsTo
    {
        return $this->belongsTo(ProjectShareLink::class, 'project_share_link_id');
    }
}
