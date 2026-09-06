<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McubeSetting extends Model
{
    protected $table = 'mcube_settings';

    public const OUTBOUND_AUTH_JSON = 'json_http_authorization';
    public const OUTBOUND_AUTH_HEADER = 'authorization_header';

    public const DEFAULT_OUTBOUND_API_URL = 'https://api.mcube.com/Restmcube-api/outbound-calls';

    protected $fillable = [
        'token',
        'is_enabled',
        'outbound_enabled',
        'outbound_api_url',
        'outbound_token',
        'outbound_auth_mode',
        'default_refurl',
        'auto_call_on_assignment',
        'auto_call_cooldown_minutes',
        'auto_call_quiet_start',
        'auto_call_quiet_end',
        'auto_call_allowed_sources',
        'auto_call_allowed_user_ids',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'outbound_enabled' => 'boolean',
        'auto_call_on_assignment' => 'boolean',
        'auto_call_allowed_sources' => 'array',
        'auto_call_allowed_user_ids' => 'array',
    ];

    /** Always return the single settings row (create if missing). */
    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'token'      => null,
            'is_enabled' => false,
            'outbound_enabled' => false,
            'outbound_api_url' => self::DEFAULT_OUTBOUND_API_URL,
            'outbound_token' => null,
            'outbound_auth_mode' => self::OUTBOUND_AUTH_JSON,
            'default_refurl' => '1',
            'auto_call_on_assignment' => false,
            'auto_call_cooldown_minutes' => 10,
            'auto_call_quiet_start' => '20:00:00',
            'auto_call_quiet_end' => '09:00:00',
            'auto_call_allowed_sources' => null,
            'auto_call_allowed_user_ids' => null,
        ]);
    }

    public function outboundApiUrl(): string
    {
        return $this->outbound_api_url ?: self::DEFAULT_OUTBOUND_API_URL;
    }

    public function outboundAuthMode(): string
    {
        return in_array($this->outbound_auth_mode, [self::OUTBOUND_AUTH_JSON, self::OUTBOUND_AUTH_HEADER], true)
            ? $this->outbound_auth_mode
            : self::OUTBOUND_AUTH_JSON;
    }

    public function autoCallCooldownMinutes(): int
    {
        return max(0, (int) ($this->auto_call_cooldown_minutes ?? 10));
    }
}
