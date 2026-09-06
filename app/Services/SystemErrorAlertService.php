<?php

namespace App\Services;

use App\Models\SystemErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemErrorAlertService
{
    public function handle(Throwable $exception, Request $request, int $statusCode): void
    {
        if ($statusCode < 500) {
            return;
        }

        $payload = $this->payload($exception, $request, $statusCode);
        $this->store($payload);
        $this->sendTelegram($payload);
    }

    public function sendOperationalMessage(string $message): void
    {
        $this->sendTelegramText($message);
    }

    private function payload(Throwable $exception, Request $request, int $statusCode): array
    {
        $user = $request->user();

        return [
            'status_code' => $statusCode,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_role' => $user?->role?->name ?? $user?->role?->slug,
            'ip' => $request->ip(),
            'exception_class' => class_basename($exception),
            'message' => mb_strimwidth($exception->getMessage(), 0, 900, '...'),
            'file' => $this->relativePath($exception->getFile()),
            'line' => $exception->getLine(),
            'time' => now()->format('d M Y h:i A'),
        ];
    }

    private function store(array $payload): void
    {
        try {
            if (Schema::hasTable('system_error_logs')) {
                SystemErrorLog::create($payload);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to store system error log: ' . $e->getMessage());
        }
    }

    private function sendTelegram(array $payload): void
    {
        $this->sendTelegramText($this->message($payload));
    }

    private function sendTelegramText(string $message): void
    {
        if (!config('error_alerts.telegram.enabled')) {
            return;
        }

        $token = trim((string) config('error_alerts.telegram.bot_token', ''));
        $chatId = trim((string) config('error_alerts.telegram.chat_id', ''));

        if ($token === '' || $chatId === '') {
            Log::warning('Telegram error alerts enabled but token/chat id missing.');
            return;
        }

        try {
            $response = Http::timeout(5)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'disable_web_page_preview' => true,
            ]);

            if (!$response->successful()) {
                Log::warning('Telegram error alert failed', [
                    'status' => $response->status(),
                    'body' => mb_strimwidth($response->body(), 0, 500, '...'),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Telegram error alert exception: ' . $e->getMessage());
        }
    }

    private function message(array $payload): string
    {
        $user = $payload['user_name']
            ? "{$payload['user_name']} ({$payload['user_role']}) #{$payload['user_id']}"
            : 'Guest';

        return implode("\n", [
            "CRM SERVER ERROR {$payload['status_code']}",
            '',
            "User: {$user}",
            "URL: {$payload['method']} {$payload['path']}",
            "Time: {$payload['time']}",
            "Error: {$payload['exception_class']}",
            "Message: {$payload['message']}",
            "File: {$payload['file']}:{$payload['line']}",
            "IP: {$payload['ip']}",
        ]);
    }

    private function relativePath(string $path): string
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base)
            ? str_replace('\\', '/', substr($path, strlen($base)))
            : $path;
    }
}
