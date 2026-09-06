<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class SecurityLockControl
{
    public const DEFAULT_OWNER_EMAIL = 'vivek.baseinfra@gmail.com';
    public const DEFAULT_SECRET = 'fFHIJ97J8TK4oOFug69biqni2Ww5XaZy65h8JucJpenmf0kY';

    public static function path(): string
    {
        return storage_path('app/security-lock.json');
    }

    public static function read(): array
    {
        $path = self::path();

        if (!File::exists($path)) {
            return self::defaults();
        }

        $data = json_decode((string) File::get($path), true);

        if (!is_array($data)) {
            return self::defaults();
        }

        return array_merge(self::defaults(), $data);
    }

    public static function write(array $data): void
    {
        $payload = array_merge(self::defaults(), $data);

        File::ensureDirectoryExists(dirname(self::path()));
        File::put(self::path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    public static function ensureStartedAt(array $config): array
    {
        if (!empty($config['started_at'])) {
            return $config;
        }

        $config['started_at'] = now()->toIso8601String();
        self::write($config);

        return $config;
    }

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'owner_email' => self::DEFAULT_OWNER_EMAIL,
            'secret' => self::DEFAULT_SECRET,
            'started_at' => '',
        ];
    }
}
