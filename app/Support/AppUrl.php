<?php

namespace App\Support;

class AppUrl
{
    public static function root(): string
    {
        $configured = trim((string) config('app.url', ''));

        if ($configured === '') {
            $configured = url('/');
        }

        return rtrim($configured, '/');
    }

    public static function to(string $path = '/'): string
    {
        if ($path === '') {
            return static::root();
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        return static::root() . '/' . ltrim($path, '/');
    }
}
