<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadProposalEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_proposal_id',
        'project_id',
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

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(LeadProposal::class, 'lead_proposal_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
