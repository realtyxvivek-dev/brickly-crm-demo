<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkSmsPlansIvrSetting extends Model
{
    protected $table = 'bulksmsplans_ivr_settings';

    protected $fillable = [
        'token',
        'is_enabled',
        'default_source',
        'auto_create_lead',
        'create_missed_call_task',
        'fallback_user_id',
        'allow_tokenless_testing',
        'tokenless_testing_expires_at',
        'allowed_webhook_ips',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'auto_create_lead' => 'boolean',
        'create_missed_call_task' => 'boolean',
        'allow_tokenless_testing' => 'boolean',
        'tokenless_testing_expires_at' => 'datetime',
        'allowed_webhook_ips' => 'array',
    ];

    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'token' => null,
            'is_enabled' => false,
            'default_source' => 'ivr',
            'auto_create_lead' => true,
            'create_missed_call_task' => true,
            'fallback_user_id' => null,
            'allow_tokenless_testing' => false,
            'tokenless_testing_expires_at' => null,
            'allowed_webhook_ips' => [],
        ]);
    }

    public function acceptsTokenlessTest(): bool
    {
        return $this->allow_tokenless_testing
            && $this->tokenless_testing_expires_at
            && $this->tokenless_testing_expires_at->isFuture();
    }

    public function allowedWebhookIps(): array
    {
        return collect($this->allowed_webhook_ips ?: [])
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function hasWebhookIpWhitelist(): bool
    {
        return !empty($this->allowedWebhookIps());
    }

    public function isAllowedWebhookIp(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }

        return in_array(trim($ip), $this->allowedWebhookIps(), true);
    }

    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }
}
