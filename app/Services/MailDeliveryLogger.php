<?php

namespace App\Services;

use App\Models\MailDeliveryLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class MailDeliveryLogger
{
    public function createQueued(array $attributes): MailDeliveryLog
    {
        return MailDeliveryLog::create(array_merge([
            'status' => MailDeliveryLog::STATUS_QUEUED,
            'created_by' => auth()->id(),
        ], $attributes));
    }

    public function markSent(MailDeliveryLog $log): MailDeliveryLog
    {
        $log->update([
            'status' => MailDeliveryLog::STATUS_SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ]);

        return $log->fresh();
    }

    public function markFailed(MailDeliveryLog $log, \Throwable $throwable): MailDeliveryLog
    {
        $log->update([
            'status' => MailDeliveryLog::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => mb_substr($throwable->getMessage(), 0, 5000),
        ]);

        return $log->fresh();
    }

    public function markSkipped(MailDeliveryLog $log, string $reason): MailDeliveryLog
    {
        $log->update([
            'status' => MailDeliveryLog::STATUS_SKIPPED,
            'error_message' => mb_substr($reason, 0, 5000),
        ]);

        return $log->fresh();
    }

    public function sendMailable(
        string $mailType,
        string $subject,
        User|string $recipient,
        Mailable $mailable,
        array $payloadSummary = [],
        ?Model $related = null,
        ?int $createdBy = null,
        ?int $resendOfLogId = null
    ): MailDeliveryLog {
        $recipientEmail = $recipient instanceof User ? (string) $recipient->email : (string) $recipient;
        $recipientUserId = $recipient instanceof User ? $recipient->id : null;

        $log = $this->createQueued([
            'mail_type' => $mailType,
            'subject' => $subject,
            'recipient_email' => $recipientEmail,
            'recipient_user_id' => $recipientUserId,
            'payload_summary' => $this->sanitizePayload($payloadSummary),
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
            'created_by' => $createdBy,
            'resend_of_log_id' => $resendOfLogId,
        ]);

        if ($recipientEmail === '') {
            return $this->markSkipped($log, 'Recipient email missing.');
        }

        try {
            Mail::to($recipientEmail)->send($mailable);
            return $this->markSent($log);
        } catch (\Throwable $throwable) {
            return $this->markFailed($log, $throwable);
        }
    }

    public function sendView(
        string $mailType,
        string $subject,
        User|string $recipient,
        string $view,
        array $viewData = [],
        array $payloadSummary = [],
        ?Model $related = null,
        ?int $createdBy = null,
        ?int $resendOfLogId = null
    ): MailDeliveryLog {
        $recipientEmail = $recipient instanceof User ? (string) $recipient->email : (string) $recipient;
        $recipientUserId = $recipient instanceof User ? $recipient->id : null;
        $recipientName = $recipient instanceof User ? (string) $recipient->name : null;

        $log = $this->createQueued([
            'mail_type' => $mailType,
            'subject' => $subject,
            'recipient_email' => $recipientEmail,
            'recipient_user_id' => $recipientUserId,
            'payload_summary' => $this->sanitizePayload($payloadSummary),
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
            'created_by' => $createdBy,
            'resend_of_log_id' => $resendOfLogId,
        ]);

        if ($recipientEmail === '') {
            return $this->markSkipped($log, 'Recipient email missing.');
        }

        try {
            Mail::send($view, $viewData, function ($message) use ($recipientEmail, $recipientName, $subject) {
                $message->to($recipientEmail, $recipientName)->subject($subject);
            });

            return $this->markSent($log);
        } catch (\Throwable $throwable) {
            return $this->markFailed($log, $throwable);
        }
    }

    private function sanitizePayload(array $payload): array
    {
        $blockedKeys = ['otp', 'password', 'token', 'remember_token'];

        return collect($payload)
            ->mapWithKeys(function ($value, $key) use ($blockedKeys) {
                $key = (string) $key;
                if (in_array(strtolower($key), $blockedKeys, true)) {
                    return [$key => '[hidden]'];
                }

                if (is_scalar($value) || $value === null) {
                    return [$key => $value];
                }

                return [$key => json_decode(json_encode($value), true)];
            })
            ->all();
    }
}
