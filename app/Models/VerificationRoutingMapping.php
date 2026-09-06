<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationRoutingMapping extends Model
{
    protected $fillable = [
        'workflow_type',
        'source_type',
        'source_user_id',
        'source_role_id',
        'source_team_user_id',
        'verifier_type',
        'verifier_user_id',
        'verifier_role_ids',
        'is_active',
        'priority',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'verifier_role_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function sourceRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'source_role_id');
    }

    public function sourceTeamUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_team_user_id');
    }

    public function verifierUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_user_id');
    }
}
