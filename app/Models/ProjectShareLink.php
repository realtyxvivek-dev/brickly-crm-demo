<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectShareLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'advisor_id',
        'lead_id',
        'token',
        'status',
        'expires_at',
        'revoked_at',
        'max_visits',
        'notes',
        'first_viewed_at',
        'last_viewed_at',
        'view_count',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'max_visits' => 'integer',
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProjectPageEvent::class, 'project_share_link_id');
    }

    public function isUsable(): bool
    {
        if ($this->status === 'revoked' || $this->revoked_at) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_visits && $this->view_count >= $this->max_visits) {
            return false;
        }

        return $this->status === 'active';
    }
}
