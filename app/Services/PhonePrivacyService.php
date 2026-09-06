<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\PhonePrivacyAudit;
use App\Models\User;
use App\Models\UserPhonePrivacySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PhonePrivacyService
{
    private array $settings = [];
    private ?bool $settingsTableExists = null;

    public function settingFor(?User $user): ?UserPhonePrivacySetting
    {
        if (!$user) {
            return null;
        }

        $this->settingsTableExists ??= Schema::hasTable('user_phone_privacy_settings');
        if (!$this->settingsTableExists) {
            return null;
        }

        if (!array_key_exists($user->id, $this->settings)) {
            $this->settings[$user->id] = $user->phonePrivacySetting
                ?: UserPhonePrivacySetting::query()->where('user_id', $user->id)->first();
        }

        return $this->settings[$user->id];
    }

    public function forget(?User $user = null): void
    {
        if ($user) {
            unset($this->settings[$user->id]);
            $user->unsetRelation('phonePrivacySetting');
            return;
        }

        $this->settings = [];
    }

    public function shouldMask(?User $user): bool
    {
        if (!$user || $user->isAdmin() || $user->isCrm()) {
            return false;
        }

        return (bool) $this->settingFor($user)?->mask_enabled;
    }

    public function isCloudOnly(?User $user): bool
    {
        return $this->shouldMask($user)
            && $this->settingFor($user)?->call_mode === UserPhonePrivacySetting::CALL_CLOUD_ONLY;
    }

    public function isWhatsAppApiOnly(?User $user): bool
    {
        return $this->shouldMask($user)
            && $this->settingFor($user)?->whatsapp_mode === UserPhonePrivacySetting::WHATSAPP_API_ONLY;
    }

    public function mask(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return $phone;
        }

        $parsed = app(InternationalPhoneService::class)->parse($phone);
        if ($parsed && ($parsed['country_iso'] ?? null) !== InternationalPhoneService::DEFAULT_COUNTRY) {
            $countryCode = (string) ($parsed['country_code'] ?? '');
            $local = substr($parsed['normalized'], strlen($countryCode));
            if ($countryCode !== '' && strlen($local) >= 6) {
                return '+' . $countryCode . ' ' . substr($local, 0, 1)
                    . str_repeat('X', max(1, strlen($local) - 5)) . substr($local, -4);
            }
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) >= 6) {
            $local = strlen($digits) > 10 ? substr($digits, -10) : $digits;
            return substr($local, 0, 2) . str_repeat('X', max(0, strlen($local) - 6)) . substr($local, -4);
        }

        return str_repeat('X', max(0, strlen($digits) - 2)) . substr($digits, -2);
    }

    public function display(?string $phone, ?User $viewer): ?string
    {
        if ($this->shouldMask($viewer)) {
            return $this->mask($phone);
        }

        return app(InternationalPhoneService::class)->displayLocal($phone);
    }

    public function maskPhoneFields(array $payload, ?User $viewer): array
    {
        if (!$this->shouldMask($viewer)) {
            return $payload;
        }

        $phoneKeys = ['phone', 'mobile', 'phone_number', 'customer_phone', 'contact_phone', 'lead_phone', 'alternate_phone', 'secondary_phone', 'alternate_number', 'dialer_phone'];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->maskPhoneFields($value, $viewer);
                continue;
            }

            if (in_array(strtolower((string) $key), $phoneKeys, true) && is_scalar($value)) {
                $payload[$key] = $this->mask((string) $value);
                continue;
            }

            if (is_string($value)) {
                $payload[$key] = $this->maskText($value, $viewer);
            }
        }

        return $payload;
    }

    public function maskText(?string $text, ?User $viewer): ?string
    {
        if ($text === null || !$this->shouldMask($viewer)) {
            return $text;
        }

        return preg_replace_callback(
            '/(?<!\d)(?:\+[1-9]\d{7,14}|(?:\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5})(?!\d)/',
            fn (array $match) => (string) $this->mask($match[0]),
            $text
        );
    }

    public function rawLeadPhone(Lead $lead, string $slot = 'primary'): string
    {
        if ($slot === 'alternate') {
            return (string) $lead->formFieldValues()
                ->whereIn('field_key', ['alternate_phone', 'secondary_phone', 'alternate_number'])
                ->whereNotNull('field_value')
                ->latest('id')
                ->value('field_value');
        }

        return (string) $lead->getRawOriginal('phone');
    }

    public function audit(
        string $action,
        ?User $actor = null,
        ?User $target = null,
        ?Lead $lead = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?Request $request = null
    ): PhonePrivacyAudit {
        return PhonePrivacyAudit::create([
            'actor_user_id' => $actor?->id,
            'target_user_id' => $target?->id,
            'lead_id' => $lead?->id,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
