<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MetaWabaAccount extends Model
{
    protected $fillable = [
        'name',
        'connected_by_user_id',
        'is_active',
        'is_default',
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
        'is_default' => 'boolean',
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

    public static function defaultAccount(): ?self
    {
        if (!Schema::hasTable('meta_waba_accounts')) {
            return null;
        }

        return self::query()
            ->where('is_default', true)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first()
            ?: self::query()->where('is_active', true)->orderBy('id')->first();
    }

    public static function fromSettings(MetaWabaSettings $settings): self
    {
        $account = self::query()
            ->where('phone_number_id', $settings->phone_number_id)
            ->first()
            ?: new self();

        $account->fill([
            'name' => $settings->display_phone_number ?: $settings->verified_name ?: 'Default Meta WABA',
            'connected_by_user_id' => $settings->connected_by_user_id,
            'is_active' => (bool) $settings->is_active,
            'is_default' => true,
            'is_verified' => (bool) $settings->is_verified,
            'verified_at' => $settings->verified_at,
            'graph_version' => $settings->graph_version ?: 'v20.0',
            'phone_number_id' => $settings->phone_number_id,
            'waba_id' => $settings->waba_id,
            'business_account_id' => $settings->business_account_id,
            'meta_business_id' => $settings->meta_business_id,
            'meta_app_id' => $settings->meta_app_id,
            'embedded_signup_configuration_id' => $settings->embedded_signup_configuration_id,
            'embedded_signup_response' => $settings->embedded_signup_response,
            'connection_status' => $settings->connection_status ?: 'manual',
            'last_error' => $settings->last_error,
            'access_token' => $settings->access_token,
            'webhook_verify_token' => $settings->webhook_verify_token,
            'app_secret' => $settings->app_secret,
            'privacy_policy_url' => $settings->privacy_policy_url,
            'terms_url' => $settings->terms_url,
            'data_deletion_url' => $settings->data_deletion_url,
            'display_phone_number' => $settings->display_phone_number,
            'verified_name' => $settings->verified_name,
            'quality_rating' => $settings->quality_rating,
            'last_verified_response' => $settings->last_verified_response,
            'templates_synced_at' => $settings->templates_synced_at,
            'last_template_sync_count' => $settings->last_template_sync_count,
            'voice_calls_enabled' => (bool) $settings->voice_calls_enabled,
            'display_call_buttons' => (bool) $settings->display_call_buttons,
            'callbacks_enabled' => (bool) $settings->callbacks_enabled,
            'call_hours' => $settings->call_hours,
            'call_pause_until' => $settings->call_pause_until,
            'call_settings_last_response' => $settings->call_settings_last_response,
        ])->save();

        self::query()->whereKeyNot($account->id)->update(['is_default' => false]);

        return $account->fresh();
    }

    public function syncToSettings(): void
    {
        MetaWabaSettings::getSettings()->update([
            'connected_by_user_id' => $this->connected_by_user_id,
            'is_active' => (bool) $this->is_active,
            'is_verified' => (bool) $this->is_verified,
            'verified_at' => $this->verified_at,
            'graph_version' => $this->graph_version ?: 'v20.0',
            'phone_number_id' => $this->phone_number_id,
            'waba_id' => $this->waba_id,
            'business_account_id' => $this->business_account_id,
            'meta_business_id' => $this->meta_business_id,
            'meta_app_id' => $this->meta_app_id,
            'embedded_signup_configuration_id' => $this->embedded_signup_configuration_id,
            'embedded_signup_response' => $this->embedded_signup_response,
            'connection_status' => $this->connection_status ?: 'manual',
            'last_error' => $this->last_error,
            'access_token' => $this->access_token,
            'webhook_verify_token' => $this->webhook_verify_token,
            'app_secret' => $this->app_secret,
            'privacy_policy_url' => $this->privacy_policy_url,
            'terms_url' => $this->terms_url,
            'data_deletion_url' => $this->data_deletion_url,
            'display_phone_number' => $this->display_phone_number,
            'verified_name' => $this->verified_name,
            'quality_rating' => $this->quality_rating,
            'last_verified_response' => $this->last_verified_response,
            'templates_synced_at' => $this->templates_synced_at,
            'last_template_sync_count' => $this->last_template_sync_count,
            'voice_calls_enabled' => (bool) $this->voice_calls_enabled,
            'display_call_buttons' => (bool) $this->display_call_buttons,
            'callbacks_enabled' => (bool) $this->callbacks_enabled,
            'call_hours' => $this->call_hours,
            'call_pause_until' => $this->call_pause_until,
            'call_settings_last_response' => $this->call_settings_last_response,
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
