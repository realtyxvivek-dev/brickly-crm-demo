<?php

namespace App\Services\Instagram;

use App\Models\CompanySetting;

class InstagramAutomationSettings
{
    public function duplicateCheckEnabled(): bool
    {
        return filter_var(CompanySetting::get('instagram_duplicate_check_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function duplicateDaysLimit(): int
    {
        return max(1, (int) CompanySetting::get('instagram_duplicate_days_limit', 30));
    }

    public function invalidPhoneRetryMessage(): string
    {
        return (string) CompanySetting::get(
            'instagram_invalid_phone_retry_message',
            'Mobile number valid nahi lag raha. Please 10 digit mobile number share karein.'
        );
    }

    public function maxPhoneRetries(): int
    {
        return max(1, (int) CompanySetting::get('instagram_max_phone_retries', 3));
    }

    public function defaultFinalMessage(): string
    {
        return (string) CompanySetting::get(
            'instagram_default_final_message',
            'Thanks. Aapki details receive ho gayi hain, hamari team jald contact karegi.'
        );
    }

    public function all(): array
    {
        return [
            'duplicate_check_enabled' => $this->duplicateCheckEnabled(),
            'duplicate_days_limit' => $this->duplicateDaysLimit(),
            'invalid_phone_retry_message' => $this->invalidPhoneRetryMessage(),
            'max_phone_retries' => $this->maxPhoneRetries(),
            'default_final_message' => $this->defaultFinalMessage(),
            'default_message_type' => 'text',
            'default_rule_priority' => 100,
        ];
    }

    public function save(array $data, ?int $userId = null): void
    {
        $this->set('instagram_duplicate_check_enabled', (bool) ($data['duplicate_check_enabled'] ?? false), 'boolean', $userId);
        $this->set('instagram_duplicate_days_limit', (int) ($data['duplicate_days_limit'] ?? 30), 'number', $userId);
        $this->set('instagram_invalid_phone_retry_message', (string) ($data['invalid_phone_retry_message'] ?? ''), 'text', $userId);
        $this->set('instagram_max_phone_retries', (int) ($data['max_phone_retries'] ?? 3), 'number', $userId);
        $this->set('instagram_default_final_message', (string) ($data['default_final_message'] ?? ''), 'text', $userId);
    }

    private function set(string $key, mixed $value, string $type, ?int $userId): void
    {
        $setting = CompanySetting::query()->firstOrNew(['setting_key' => $key]);
        $setting->fill([
            'display_label' => str($key)->replace('_', ' ')->title()->toString(),
            'category' => 'instagram_automation',
            'group' => 'automation',
            'setting_type' => $type,
            'updated_by' => $userId,
        ]);
        $setting->value = $value;
        $setting->save();
    }
}
