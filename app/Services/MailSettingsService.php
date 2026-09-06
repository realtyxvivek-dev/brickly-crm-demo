<?php

namespace App\Services;

use App\Models\SystemSettings;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class MailSettingsService
{
    public const ENABLED_KEY = 'mail_smtp_settings_enabled';
    public const MAILER_KEY = 'mail_smtp_mailer';
    public const HOST_KEY = 'mail_smtp_host';
    public const PORT_KEY = 'mail_smtp_port';
    public const ENCRYPTION_KEY = 'mail_smtp_encryption';
    public const USERNAME_KEY = 'mail_smtp_username';
    public const PASSWORD_KEY = 'mail_smtp_password';
    public const FROM_ADDRESS_KEY = 'mail_smtp_from_address';
    public const FROM_NAME_KEY = 'mail_smtp_from_name';

    public function apply(): void
    {
        try {
            if (!$this->enabled()) {
                return;
            }

            $settings = $this->payload(includePasswordState: true);
            $host = trim((string) $settings['host']);
            $fromAddress = trim((string) $settings['from_address']);

            if ($host === '' || $fromAddress === '') {
                return;
            }

            Config::set('mail.default', $settings['mailer'] ?: 'smtp');
            Config::set('mail.mailers.smtp.host', $host);
            Config::set('mail.mailers.smtp.port', (int) ($settings['port'] ?: 587));
            Config::set('mail.mailers.smtp.encryption', $settings['encryption'] ?: null);
            Config::set('mail.mailers.smtp.username', $settings['username'] ?: null);
            Config::set('mail.mailers.smtp.password', $this->password() ?: null);
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $settings['from_name'] ?: config('app.name', 'CRM'));
        } catch (\Throwable) {
            return;
        }
    }

    public function enabled(): bool
    {
        return SystemSettings::get(self::ENABLED_KEY, '0') === '1';
    }

    public function payload(bool $includePasswordState = false): array
    {
        $payload = [
            'enabled' => $this->enabled(),
            'mailer' => SystemSettings::get(self::MAILER_KEY, config('mail.default', 'smtp')),
            'host' => SystemSettings::get(self::HOST_KEY, config('mail.mailers.smtp.host', 'smtp.hostinger.com')),
            'port' => SystemSettings::get(self::PORT_KEY, (string) config('mail.mailers.smtp.port', 587)),
            'encryption' => SystemSettings::get(self::ENCRYPTION_KEY, (string) config('mail.mailers.smtp.encryption', 'tls')),
            'username' => SystemSettings::get(self::USERNAME_KEY, (string) config('mail.mailers.smtp.username', '')),
            'from_address' => SystemSettings::get(self::FROM_ADDRESS_KEY, (string) config('mail.from.address', 'support@crm.bihtech.in')),
            'from_name' => SystemSettings::get(self::FROM_NAME_KEY, (string) config('mail.from.name', config('app.name', 'CRM'))),
        ];

        if ($includePasswordState) {
            $payload['password_set'] = $this->password() !== '';
        }

        return $payload;
    }

    public function update(array $data): void
    {
        SystemSettings::set(self::ENABLED_KEY, !empty($data['enabled']) ? '1' : '0');
        SystemSettings::set(self::MAILER_KEY, (string) ($data['mailer'] ?? 'smtp'));
        SystemSettings::set(self::HOST_KEY, trim((string) ($data['host'] ?? '')));
        SystemSettings::set(self::PORT_KEY, (string) ((int) ($data['port'] ?? 587)));
        SystemSettings::set(self::ENCRYPTION_KEY, (string) ($data['encryption'] ?? 'tls'));
        SystemSettings::set(self::USERNAME_KEY, trim((string) ($data['username'] ?? '')));
        SystemSettings::set(self::FROM_ADDRESS_KEY, trim((string) ($data['from_address'] ?? '')));
        SystemSettings::set(self::FROM_NAME_KEY, trim((string) ($data['from_name'] ?? '')));

        $password = (string) ($data['password'] ?? '');
        if ($password !== '') {
            SystemSettings::set(self::PASSWORD_KEY, Crypt::encryptString($password));
        }

        $this->apply();
        Mail::purge('smtp');
    }

    public function password(): string
    {
        $stored = SystemSettings::get(self::PASSWORD_KEY, '');
        if (!$stored) {
            return (string) config('mail.mailers.smtp.password', '');
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return (string) $stored;
        }
    }
}
