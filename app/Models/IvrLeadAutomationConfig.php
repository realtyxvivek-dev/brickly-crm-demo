<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IvrLeadAutomationConfig extends Model
{
    use HasFactory;

    protected $table = 'ivr_lead_automation_configs';

    public const MODE_RECEIVER = 'assign_to_receiver';
    public const MODE_RECEIVER_TEAM = 'assign_to_receiver_team';
    public const MODE_CUSTOM_POOL = 'assign_to_custom_pool';
    public const MODE_FIXED_USER = 'assign_to_fixed_user';

    public const METHOD_RECEIVER = 'receiver';
    public const METHOD_ROUND_ROBIN = 'round_robin';
    public const METHOD_FIRST_AVAILABLE = 'first_available';
    public const METHOD_PERCENTAGE = 'percentage';
    public const METHOD_FIXED_USER = 'fixed_user';

    public const FALLBACK_RECEIVER = 'receiver';
    public const FALLBACK_BACKUP_USER = 'backup_user';
    public const FALLBACK_UNASSIGNED = 'unassigned';

    protected $fillable = [
        'is_enabled',
        'default_mode',
        'distribution_method',
        'fallback_mode',
        'fallback_user_id',
        'fixed_user_id',
        'default_team_manager_user_id',
        'receiver_assignment_allowed',
        'last_round_robin_user_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'receiver_assignment_allowed' => 'boolean',
    ];

    public function poolUsers(): HasMany
    {
        return $this->hasMany(IvrLeadAutomationPoolUser::class, 'config_id')->orderBy('sort_order');
    }

    public function receiverOverrides(): HasMany
    {
        return $this->hasMany(IvrLeadAutomationReceiverOverride::class, 'config_id')->orderBy('id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(IvrLeadAutomationAudit::class, 'config_id');
    }

    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }

    public function fixedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fixed_user_id');
    }

    public function defaultTeamManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_team_manager_user_id');
    }

    public function lastRoundRobinUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_round_robin_user_id');
    }

    public static function modeOptions(): array
    {
        return [
            self::MODE_RECEIVER => 'Assign To Call Receiver',
            self::MODE_RECEIVER_TEAM => 'Assign To Receiver Team',
            self::MODE_CUSTOM_POOL => 'Assign To Custom Pool',
            self::MODE_FIXED_USER => 'Assign To Fixed User',
        ];
    }

    public static function methodOptions(): array
    {
        return [
            self::METHOD_RECEIVER => 'Call Receiver',
            self::METHOD_ROUND_ROBIN => 'Round Robin',
            self::METHOD_FIRST_AVAILABLE => 'First Available',
            self::METHOD_PERCENTAGE => '% Based',
            self::METHOD_FIXED_USER => 'Fixed User',
        ];
    }

    public static function fallbackOptions(): array
    {
        return [
            self::FALLBACK_RECEIVER => 'Fallback To Call Receiver',
            self::FALLBACK_BACKUP_USER => 'Fallback To Backup User',
            self::FALLBACK_UNASSIGNED => 'Leave Unassigned',
        ];
    }
}
