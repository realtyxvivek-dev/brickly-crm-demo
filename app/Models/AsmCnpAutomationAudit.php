<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsmCnpAutomationAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'state_id',
        'lead_id',
        'config_id',
        'from_user_id',
        'to_user_id',
        'task_id',
        'stage',
        'stage_record_id',
        'cnp_count',
        'action',
        'message',
        'meta',
        'acted_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'acted_at' => 'datetime',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(AsmCnpAutomationState::class, 'state_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(AsmCnpAutomationConfig::class, 'config_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
