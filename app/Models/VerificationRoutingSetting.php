<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationRoutingSetting extends Model
{
    protected $fillable = [
        'workflow_type',
        'mode',
        'fixed_role_ids',
        'fixed_user_id',
        'fallback_role_ids',
        'fallback_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fixed_role_ids' => 'array',
        'fallback_role_ids' => 'array',
    ];

    public function fixedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fixed_user_id');
    }

    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }
}
