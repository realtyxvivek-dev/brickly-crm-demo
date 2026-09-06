<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvrLeadAutomationReceiverOverride extends Model
{
    use HasFactory;

    protected $table = 'ivr_lead_automation_receiver_overrides';

    public const TARGET_SELF = 'self';
    public const TARGET_TEAM = 'team';
    public const TARGET_POOL = 'pool';
    public const TARGET_FIXED_USER = 'fixed_user';

    protected $fillable = [
        'config_id',
        'user_id',
        'is_enabled',
        'can_assign_to_self',
        'target_type',
        'distribution_method',
        'team_manager_user_id',
        'fixed_user_id',
        'fallback_mode',
        'fallback_user_id',
        'last_round_robin_user_id',
        'notes',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'can_assign_to_self' => 'boolean',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(IvrLeadAutomationConfig::class, 'config_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function teamManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_manager_user_id');
    }

    public function fixedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fixed_user_id');
    }

    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }

    public function lastRoundRobinUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_round_robin_user_id');
    }

    public static function targetOptions(): array
    {
        return [
            self::TARGET_SELF => 'Assign To Call Receiver',
            self::TARGET_TEAM => 'Assign To Team',
            self::TARGET_POOL => 'Assign To Pool',
            self::TARGET_FIXED_USER => 'Assign To Fixed User',
        ];
    }
}
