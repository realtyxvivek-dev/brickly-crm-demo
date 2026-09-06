<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetaWabaAccount;
use App\Models\MetaWabaSettings;
use App\Models\Lead;
use App\Models\WabaCampaignRecipient;
use App\Models\WhatsAppAutomationLog;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WabaCallEventService;
use App\Services\WabaCampaignService;
use App\Services\WhatsAppAutomationService;
use App\Services\WhatsAppLeadAutomationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaWabaWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppLeadAutomationService $automationService,
        private readonly WabaCallEventService $callEventService,
        private readonly WabaCampaignService $campaignService,
        private readonly WhatsAppAutomationService $advancedAutomationService
    )
    {
    }

    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $settings = MetaWabaSettings::query()
            ->whereNotNull('webhook_verify_token')
            ->get()
            ->first(fn (MetaWabaSettings $setting) => hash_equals((string) $setting->webhook_verify_token, (string) $token));
        if (!$settings) {
            $settings = MetaWabaAccount::query()
                ->whereNotNull('webhook_verify_token')
                ->get()
                ->first(fn (MetaWabaAccount $account) => hash_equals((string) $account->webhook_verify_token, (string) $token));
        }

        if ($mode === 'subscribe' && $challenge && $settings) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        try {
            $payload = $request->all();
            $value = data_get($payload, 'entry.0.changes.0.value');
            $this->webhookTrace('received', [
                'field' => data_get($payload, 'entry.0.changes.0.field'),
                'waba_id' => data_get($payload, 'entry.0.id'),
                'phone_number_id' => data_get($value, 'metadata.phone_number_id'),
                'from' => data_get($value, 'messages.0.from'),
                'message_id' => data_get($value, 'messages.0.id'),
                'message_type' => data_get($value, 'messages.0.type'),
                'status_id' => data_get($value, 'statuses.0.id'),
                'status' => data_get($value, 'statuses.0.status'),
            ]);
            $settings = $this->resolveSettingsForPayload($value);
            if (!$settings) {
                $this->webhookTrace('ignored_unknown_phone_number', [
                    'phone_number_id' => data_get($value, 'metadata.phone_number_id'),
                    'display_phone_number' => data_get($value, 'metadata.display_phone_number'),
                    'waba_id' => data_get($payload, 'entry.0.id'),
                ]);

                return response()->json(['success' => true, 'message' => 'Webhook ignored for unknown phone number.']);
            }

            $field = data_get($payload, 'entry.0.changes.0.field');
            $message = data_get($value, 'messages.0', []);
            $status = data_get($value, 'statuses.0', []);
            $contact = data_get($value, 'contacts.0', []);
            $processedCallEvents = $this->callEventService->processWebhookPayload($payload);

            if ($processedCallEvents > 0 && empty($status) && empty($message)) {
                return response()->json(['success' => true, 'call_events' => $processedCallEvents]);
            }

            if ($field === 'messages' && !empty($status)) {
                $this->processStatus($status);

                return response()->json(['success' => true]);
            }

            if ($field !== 'messages' || empty($message)) {
                return response()->json(['success' => true, 'message' => 'No incoming message to process.']);
            }

            $phone = $this->normalizePhone($message['from'] ?? data_get($contact, 'wa_id'));
            $messageId = $message['id'] ?? null;
            $type = $message['type'] ?? 'unknown';
            $messageText = $this->extractMessageText($message, $type);

            if (!$phone || !$messageId || !$messageText) {
                return response()->json(['success' => true, 'message' => 'Missing usable message content.']);
            }

            $context = $this->automationService->resolveInboundConversation($phone, data_get($contact, 'profile.name'));
            /** @var WhatsAppConversation $conversation */
            $conversation = $context['conversation'];
            if ($settings instanceof MetaWabaAccount && (int) $conversation->meta_waba_account_id !== (int) $settings->id) {
                $conversation->forceFill(['meta_waba_account_id' => $settings->id])->save();
            }
            $sentAt = $this->normalizeTimestamp($message['timestamp'] ?? null);

            $stored = WhatsAppMessage::updateOrCreate(
                [
                    'message_id' => (string) $messageId,
                    'conversation_id' => $conversation->id,
                ],
                [
                    'user_id' => $conversation->user_id,
                    'direction' => 'received',
                    'message' => $messageText,
                    'status' => 'delivered',
                    'provider' => 'meta_waba',
                    'meta_waba_account_id' => $settings instanceof MetaWabaAccount ? $settings->id : null,
                    'external_message_id' => (string) $messageId,
                    'provider_status' => 'received',
                    'api_response' => [
                        'webhook_payload' => $payload,
                        'parsed_message' => $message,
                        'parsed_contact' => $contact,
                        'message_type' => $type,
                    ],
                    'sent_at' => $sentAt,
                ]
            );
            $this->webhookTrace('message_saved', [
                'conversation_id' => $conversation->id,
                'message_db_id' => $stored->id,
                'meta_waba_account_id' => $settings instanceof MetaWabaAccount ? $settings->id : null,
                'phone_number_id' => $settings->phone_number_id ?? null,
                'from' => $phone,
                'message_id' => $messageId,
                'message_type' => $type,
            ]);

            $conversation->forceFill([
                'last_inbound_at' => $sentAt,
                'status' => $conversation->status === 'resolved' ? 'open' : 'pending_reply',
                'resolved_at' => null,
                'resolved_by' => null,
            ])->save();
            $conversation->touch();
            $this->processOptOutIfNeeded($phone, $messageText);
            $this->automationService->notifyInboundMessage($conversation, $stored, $context);
            if ($context['lead'] ?? null) {
                $this->advancedAutomationService->handleTrigger('inbound_keyword', [
                    'lead' => $context['lead'],
                    'related_type' => 'whatsapp_message',
                    'related_id' => $stored->id,
                    'actor_type' => 'customer',
                    'actor_id' => null,
                ]);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Meta WABA webhook failure', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['success' => false], 200);
        }
    }

    private function resolveSettingsForPayload(?array $value): MetaWabaSettings|MetaWabaAccount|null
    {
        $phoneNumberId = data_get($value, 'metadata.phone_number_id');
        if (filled($phoneNumberId)) {
            $account = MetaWabaAccount::query()
                ->where('phone_number_id', (string) $phoneNumberId)
                ->where('is_active', true)
                ->first();
            if ($account) {
                return $account;
            }

            return MetaWabaSettings::query()
                ->where('phone_number_id', (string) $phoneNumberId)
                ->where('is_active', true)
                ->first();
        }

        $account = MetaWabaAccount::defaultAccount();
        if ($account?->is_active) {
            return $account;
        }

        $settings = MetaWabaSettings::getSettings();

        return $settings->is_active ? $settings : null;
    }

    private function webhookTrace(string $event, array $context = []): void
    {
        try {
            Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/meta-waba-webhook.log'),
                'level' => 'debug',
            ])->info('Meta WABA webhook ' . $event, $context);
        } catch (\Throwable $e) {
            Log::error('Meta WABA webhook trace failure', ['error' => $e->getMessage()]);
        }
    }

    private function extractMessageText(array $message, string $type): ?string
    {
        return match ($type) {
            'text' => data_get($message, 'text.body'),
            'interactive' => data_get($message, 'interactive.button_reply.title')
                ?: data_get($message, 'interactive.list_reply.title')
                ?: 'Interactive reply',
            'audio' => data_get($message, 'audio.voice') ? 'Voice message' : 'Audio message',
            'image' => 'Image',
            'sticker' => 'Sticker',
            'video' => 'Video',
            'document' => data_get($message, 'document.filename') ?: 'Document',
            'location' => data_get($message, 'location.address') ?: 'Shared location',
            default => '[Unsupported message type: ' . $type . ']',
        };
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);

        return strlen($phone) === 10 ? '91' . $phone : $phone;
    }

    private function normalizeTimestamp(mixed $timestamp): Carbon
    {
        return is_numeric($timestamp)
            ? Carbon::createFromTimestamp((int) $timestamp)
            : ($timestamp ? Carbon::parse($timestamp) : now());
    }

    private function processStatus(array $status): void
    {
        $messageId = $status['id'] ?? null;
        $providerStatus = strtolower((string) ($status['status'] ?? ''));
        if (!$messageId || !$providerStatus) {
            return;
        }

        $messageStatus = match ($providerStatus) {
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            default => 'sent',
        };

        WhatsAppMessage::query()
            ->where('external_message_id', $messageId)
            ->orWhere('message_id', $messageId)
            ->update([
                'provider_status' => $providerStatus,
                'status' => $messageStatus,
                'error_message' => data_get($status, 'errors.0.title') ?: data_get($status, 'errors.0.message'),
            ]);

        $logUpdates = [
            'failure_reason' => $messageStatus === 'failed'
                ? (data_get($status, 'errors.0.title') ?: data_get($status, 'errors.0.message') ?: 'Meta delivery failed.')
                : null,
        ];
        if ($messageStatus === 'delivered') {
            $logUpdates['delivered_at'] = now();
        } elseif ($messageStatus === 'read') {
            $logUpdates['read_at'] = now();
        } elseif ($messageStatus === 'failed') {
            $logUpdates['status'] = WhatsAppAutomationLog::STATUS_FAILED;
            $logUpdates['processed_at'] = now();
        }

        WhatsAppAutomationLog::query()
            ->where('provider_message_id', $messageId)
            ->update($logUpdates);

        $recipient = WabaCampaignRecipient::query()
            ->where('provider_message_id', $messageId)
            ->first();

        if (!$recipient) {
            return;
        }

        $updates = ['status' => $messageStatus];
        if ($messageStatus === 'delivered') {
            $updates['delivered_at'] = now();
        } elseif ($messageStatus === 'read') {
            $updates['read_at'] = now();
        } elseif ($messageStatus === 'failed') {
            $updates['failed_at'] = now();
            $updates['error_message'] = data_get($status, 'errors.0.title') ?: data_get($status, 'errors.0.message') ?: 'Meta delivery failed.';
        }

        $recipient->update($updates);
        $this->campaignService->refreshCampaignCounters($recipient->campaign()->first());

        if ($recipient->lead_id && in_array($messageStatus, ['delivered', 'read'], true)) {
            $this->advancedAutomationService->handleTrigger('campaign_' . $messageStatus, [
                'lead_id' => $recipient->lead_id,
                'related_type' => 'waba_campaign_recipient',
                'related_id' => $recipient->id,
                'actor_type' => 'system',
                'actor_id' => null,
            ]);
        }
    }

    private function processOptOutIfNeeded(string $phone, string $messageText): void
    {
        $normalized = strtolower(trim($messageText));
        if (!in_array($normalized, ['stop', 'unsubscribe', 'opt out', 'opt-out', 'cancel'], true)) {
            return;
        }

        $last10 = substr($phone, -10);
        Lead::query()
            ->where(function ($query) use ($phone, $last10) {
                $query->where('phone', $phone)
                    ->orWhere('phone', $last10)
                    ->orWhere('phone', 'like', '%' . $last10);
            })
            ->update([
                'whatsapp_opted_out_at' => now(),
                'whatsapp_opt_out_reason' => 'Inbound opt-out keyword: ' . $messageText,
            ]);
    }
}
