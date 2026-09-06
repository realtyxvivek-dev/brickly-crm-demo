<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class MetaWabaSettings extends Model
{
    protected $table = 'meta_waba_settings';

    protected $fillable = [
        'connected_by_user_id',
        'is_active',
        'is_verified',
        'verified_at',
        'graph_version',
        'phone_number_id',
        'waba_id',
        'business_account_id',
        'meta_business_id',
        'meta_app_id',
        'embedded_signup_configuration_id',
        'embedded_signup_response',
        'connection_status',
        'last_error',
        'access_token',
        'webhook_verify_token',
        'app_secret',
        'privacy_policy_url',
        'terms_url',
        'data_deletion_url',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'last_verified_response',
        'templates_synced_at',
        'last_template_sync_count',
        'voice_calls_enabled',
        'display_call_buttons',
        'callbacks_enabled',
        'call_hours',
        'call_pause_until',
        'call_settings_last_response',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'embedded_signup_response' => 'array',
        'last_verified_response' => 'array',
        'templates_synced_at' => 'datetime',
        'last_template_sync_count' => 'integer',
        'voice_calls_enabled' => 'boolean',
        'display_call_buttons' => 'boolean',
        'callbacks_enabled' => 'boolean',
        'call_hours' => 'array',
        'call_pause_until' => 'datetime',
        'call_settings_last_response' => 'array',
    ];

    public static function getSettings(): self
    {
        return self::firstOrCreate([], [
            'is_active' => false,
            'is_verified' => false,
            'graph_version' => 'v20.0',
        ]);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $this->decryptSecret($value);
    }

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $this->encryptSecret($value);
    }

    public function getAppSecretAttribute($value): ?string
    {
        return $this->decryptSecret($value);
    }

    public function setAppSecretAttribute($value): void
    {
        $this->attributes['app_secret'] = $this->encryptSecret($value);
    }

    private function encryptSecret($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;
        if (blank($value)) {
            return null;
        }

        if (is_string($value) && Str::startsWith($value, 'enc:')) {
            return $value;
        }

        return 'enc:' . Crypt::encryptString((string) $value);
    }

    private function decryptSecret($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (!is_string($value) || !Str::startsWith($value, 'enc:')) {
            return $value;
        }

        try {
            return Crypt::decryptString(Str::after($value, 'enc:'));
        } catch (DecryptException) {
            return $value;
        }
    }
}
