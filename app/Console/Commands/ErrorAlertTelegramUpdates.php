<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ErrorAlertTelegramUpdates extends Command
{
    protected $signature = 'error-alerts:telegram-updates';

    protected $description = 'Show recent Telegram bot updates to find chat id for CRM error alerts.';

    public function handle(): int
    {
        $token = trim((string) config('error_alerts.telegram.bot_token', ''));

        if ($token === '') {
            $this->error('ERROR_ALERT_TELEGRAM_BOT_TOKEN is missing.');
            return self::FAILURE;
        }

        $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getUpdates");

        if (!$response->successful()) {
            $this->error('Telegram getUpdates failed: ' . $response->body());
            return self::FAILURE;
        }

        $rows = collect($response->json('result', []))
            ->map(function (array $update) {
                $message = $update['message'] ?? $update['edited_message'] ?? null;
                $chat = $message['chat'] ?? null;

                if (!$chat) {
                    return null;
                }

                return [
                    'chat_id' => $chat['id'] ?? null,
                    'type' => $chat['type'] ?? null,
                    'name' => $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')),
                    'text' => $message['text'] ?? '',
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($rows === []) {
            $this->warn('No updates found. Send /start to the bot, then run this command again.');
            return self::SUCCESS;
        }

        $this->table(['chat_id', 'type', 'name', 'text'], $rows);

        return self::SUCCESS;
    }
}
