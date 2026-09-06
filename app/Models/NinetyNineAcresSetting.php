<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NinetyNineAcresSetting extends Model
{
    public const FALLBACK_DEFAULT_USER = 'default_user';
    public const FALLBACK_UNASSIGNED_CRM_QUEUE = 'unassigned_crm_queue';

    protected $fillable = [
        'is_enabled',
        'api_key',
        'default_status',
        'fallback_type',
        'fallback_user_id',
        'last_tested_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'is_enabled' => false,
            'api_key' => self::generateApiKey(),
            'default_status' => 'new',
            'fallback_type' => self::FALLBACK_UNASSIGNED_CRM_QUEUE,
        ]);
    }

    public static function generateApiKey(): string
    {
        return 'nna_' . Str::lower(Str::random(40));
    }

    public static function fallbackOptions(): array
    {
        return [
            self::FALLBACK_DEFAULT_USER => 'Default User',
            self::FALLBACK_UNASSIGNED_CRM_QUEUE => 'Unassigned CRM Queue',
        ];
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(NinetyNineAcresRequestLog::class)->latest();
    }

    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }
}
