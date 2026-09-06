<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\AttendanceRecord;
use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;

class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const PUNCH_OUT_REMINDER_TIME = '19:15';

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public string $url,
        public string $tag = 'crm-notification',
        public array $data = []
    ) {}

    public function handle(): void
    {
        if ($this->shouldSkipAttendanceReminder()) {
            return;
        }

        $recipient = User::query()->find($this->userId);
        $privacy = app(\App\Services\PhonePrivacyService::class);
        $this->data = $privacy->maskPhoneFields($this->data, $recipient);
        $this->title = (string) $privacy->maskText($this->title, $recipient);
        $this->body = (string) $privacy->maskText($this->body, $recipient);

        $tokens = FcmToken::where('user_id', $this->userId)
            ->get(['fcm_token', 'device_type']);
        if ($tokens->isEmpty()) {
            return;
        }

        $credentialsPath = config('firebase.credentials');
        if (!file_exists($credentialsPath)) {
            Log::debug('FCM skipped: service account file not found', ['path' => $credentialsPath]);
            return;
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $extraData = $this->normalizeDataPayload($this->data);

            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'crm_updates_v2',
                    'sound' => 'default',
                    'notification_priority' => 'PRIORITY_HIGH',
                    'visibility' => 'PUBLIC',
                    'tag' => $this->tag,
                ],
            ]);

            $data = array_merge([
                'title' => $this->title,
                'body' => $this->body,
                'url' => $this->url,
                'tag' => $this->tag,
                'click_action' => $this->url,
                'full_screen' => '1',
            ], $extraData, [
                'recipient_user_id' => (string) $this->userId,
            ]);

            $invalidTokens = [];

            foreach ($tokens as $tokenRecord) {
                $token = $tokenRecord->fcm_token;
                $isAndroidApp = $tokenRecord->device_type === 'android';

                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withData($data);

                    if ($isAndroidApp) {
                        $message = $message->withAndroidConfig(AndroidConfig::fromArray([
                            'priority' => 'high',
                            'ttl' => '60s',
                        ]));
                    } else {
                        $message = $message
                            ->withAndroidConfig($androidConfig);
                    }

                    $messaging->send($message);
                } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
                    $invalidTokens[] = $token;
                } catch (\Kreait\Firebase\Exception\Messaging\InvalidMessage $e) {
                    $invalidTokens[] = $token;
                    Log::warning('FCM invalid token', ['user_id' => $this->userId, 'error' => $e->getMessage()]);
                } catch (\Exception $e) {
                    Log::warning('FCM send error', [
                        'user_id' => $this->userId,
                        'token_hash' => hash('sha256', $token),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!empty($invalidTokens)) {
                FcmToken::where('user_id', $this->userId)
                    ->whereIn('fcm_token', $invalidTokens)
                    ->delete();
            }
        } catch (\Exception $e) {
            Log::error('FCM job error', ['user_id' => $this->userId, 'error' => $e->getMessage()]);
        }
    }

    private function normalizeDataPayload(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $normalized[$key] = $value ? '1' : '0';
                continue;
            }

            if (is_scalar($value)) {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }

    private function shouldSkipAttendanceReminder(): bool
    {
        $type = $this->data['notification_type'] ?? $this->data['kind'] ?? null;

        if ($type !== 'attendance_reminder') {
            return false;
        }

        $slot = (string) ($this->data['slot'] ?? '');
        $date = (string) ($this->data['attendance_date'] ?? now()->toDateString());

        $record = AttendanceRecord::query()
            ->where('user_id', $this->userId)
            ->whereDate('attendance_date', $date)
            ->first();

        $skip = false;

        if ($slot === 'punchout') {
            $skip = now()->lt(now()->copy()->setTimeFromTimeString(self::PUNCH_OUT_REMINDER_TIME))
                || !$record?->first_punch_in_at
                || $record->last_punch_out_at !== null;
        } else {
            $skip = $record?->first_punch_in_at !== null
                || $record?->manual_first_punch_in_at !== null
                || in_array($record?->status, [
                    AttendanceRecord::STATUS_PRESENT,
                    AttendanceRecord::STATUS_LATE,
                    AttendanceRecord::STATUS_HALF_DAY,
                    AttendanceRecord::STATUS_WEEK_OFF,
                    AttendanceRecord::STATUS_HOLIDAY,
                    AttendanceRecord::STATUS_LEAVE,
                ], true);
        }

        if ($skip && !empty($this->data['notification_id'])) {
            AppNotification::query()
                ->where('id', $this->data['notification_id'])
                ->where('user_id', $this->userId)
                ->where('type', 'attendance_reminder')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        if ($skip) {
            Log::info('Attendance FCM skipped after latest attendance state check', [
                'user_id' => $this->userId,
                'slot' => $slot,
                'attendance_date' => $date,
            ]);
        }

        return $skip;
    }
}
