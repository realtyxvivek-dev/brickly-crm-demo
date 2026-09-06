<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebsiteIntegration extends Model
{
    public const FALLBACK_DEFAULT_USER = 'default_user';
    public const FALLBACK_UNASSIGNED_CRM_QUEUE = 'unassigned_crm_queue';

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'source',
        'default_status',
        'api_key',
        'description',
        'fallback_type',
        'fallback_user_id',
        'allowed_domains',
        'rate_limit',
        'sample_payload_json',
        'last_tested_at',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_domains' => 'array',
        'sample_payload_json' => 'array',
        'last_tested_at' => 'datetime',
    ];

    public static function fallbackOptions(): array
    {
        return [
            self::FALLBACK_DEFAULT_USER => 'Default User',
            self::FALLBACK_UNASSIGNED_CRM_QUEUE => 'Unassigned CRM Queue',
        ];
    }

    public static function generateApiKey(): string
    {
        return 'wsi_' . Str::lower(Str::random(40));
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(WebsiteIntegrationFieldMapping::class)->orderBy('display_order');
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(WebsiteIntegrationRequestLog::class)->latest();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
