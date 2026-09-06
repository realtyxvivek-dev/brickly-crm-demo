<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWebPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public string $title;
    public string $body;
    public string $url;
    public string $tag;

    public function __construct(int $userId, string $title, string $body, string $url, string $tag = 'crm-notification')
    {
        $this->userId = $userId;
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
        $this->tag = $tag;
    }

    public function handle(): void
    {
        $subscriptions = PushSubscription::where('user_id', $this->userId)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $vapidPublic = config('webpush.vapid_public');
        $vapidPrivate = config('webpush.vapid_private');
        if (!$vapidPublic || !$vapidPrivate) {
            Log::debug('Web Push skipped: VAPID keys not configured');
            return;
        }

        if (!class_exists(\Minishlink\WebPush\WebPush::class)) {
            Log::debug('Web Push skipped: minishlink/web-push not installed');
            return;
        }

        $payload = json_encode([
            'title' => $this->title,
            'body' => $this->body,
            'message' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
            'recipient_user_id' => (string) $this->userId,
            'requireInteraction' => true,
        ]);

        try {
            $webPush = new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject' => config('app.url'),
                    'publicKey' => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ]);

            foreach ($subscriptions as $sub) {
                try {
                    $subscription = \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'keys' => $sub->keys ?? [],
                    ]);
                    $report = $webPush->sendOneNotification($subscription, $payload);
                    if (!$report->isSuccess()) {
                        if ($report->isSubscriptionExpired()) {
                            $sub->delete();
                        }
                        Log::warning('Web Push send failed', [
                            'user_id' => $this->userId,
                            'reason' => $report->getReason(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Web Push send error', [
                        'user_id' => $this->userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Web Push job error', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
