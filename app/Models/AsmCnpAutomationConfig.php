<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsmCnpAutomationConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_enabled',
        'is_active',
        'create_retry_tasks',
        'transfer_rule_mode',
        'retry_delay_minutes',
        'transfer_window_hours',
        'transfer_threshold_hours',
        'max_cnp_attempts',
        'fallback_routing',
        'quarantine_enabled',
        'quarantine_after_unique_users',
        'quarantine_action',
        'last_round_robin_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_active' => 'boolean',
        'create_retry_tasks' => 'boolean',
        'transfer_window_hours' => 'integer',
        'quarantine_enabled' => 'boolean',
        'quarantine_after_unique_users' => 'integer',
    ];

    public function poolUsers(): HasMany
    {
        return $this->hasMany(AsmCnpAutomationPoolUser::class, 'config_id')->orderBy('sort_order');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(AsmCnpAutomationUserOverride::class, 'config_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(AsmCnpAutomationState::class, 'config_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AsmCnpAutomationAudit::class, 'config_id');
    }

    public function lastRoundRobinUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_round_robin_user_id');
    }
}
