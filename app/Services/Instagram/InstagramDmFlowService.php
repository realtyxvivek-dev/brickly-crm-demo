<?php

namespace App\Services\Instagram;

use App\Models\IgConversation;
use App\Models\IgConversationState;
use App\Models\IgDmFlowStep;
use App\Models\IgMessage;
use App\Models\IgWebhookEvent;
use App\Models\InstagramAccount;
use App\Services\DuplicateDetectionService;

class InstagramDmFlowService
{
    public function __construct(
        private readonly InstagramGraphService $graphService,
        private readonly InstagramLeadCreationService $leadCreationService,
        private readonly DuplicateDetectionService $duplicateDetectionService,
        private readonly InstagramAutomationSettings $settings,
    ) {
    }

    public function handleMessageEvent(IgWebhookEvent $event, InstagramAccount $account): void
    {
        $message = $this->parseMessagePayload($event->payload ?? []);

        if (!($message['valid'] ?? false)) {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => $message['error'] ?? 'Invalid Instagram message payload.',
            ]);
            return;
        }

        if ($message['recipient_id'] !== $account->ig_user_id) {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'Message recipient did not match the connected Instagram account.',
            ]);
            return;
        }

        $conversation = $this->findConversation($account, $message['sender_id']);

        if (!$conversation) {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'No open Instagram conversation matched this inbound message.',
            ]);
            return;
        }

        $this->recordReceivedMessage($conversation, $message);

        if ($conversation->is_human_taken_over) {
            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => 'human_takeover_active',
            ]);
            return;
        }

        if ($conversation->status === 'needs_human') {
            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => 'conversation_needs_human',
            ]);
            return;
        }

        $state = $conversation->state;

        if (!$state || $state->completed || $conversation->completed_at) {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'Conversation flow is already completed or missing state.',
            ]);
            return;
        }

        if (!$state->current_field) {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'Conversation state has no field waiting for reply.',
            ]);
            return;
        }

        $saved = $this->saveCurrentReply($conversation, $state, $message['text']);

        if (!($saved['success'] ?? false)) {
            $this->handleInvalidReply($event, $conversation, $state, $saved['error'] ?? 'Invalid reply.');
            return;
        }

        $nextStep = $this->nextStep($state);

        if ($nextStep && $nextStep->save_reply_as) {
            $send = $this->sendAndRecord($conversation, $nextStep->message_text ?: '', $nextStep);

            $state->update([
                'current_step' => $nextStep->step_order,
                'current_field' => $nextStep->save_reply_as,
                'last_reply_at' => now(),
                'retries' => 0,
            ]);

            $event->update([
                'status' => ($send['success'] ?? false) ? 'processed' : 'failed',
                'processed_at' => now(),
                'error_message' => ($send['success'] ?? false) ? 'dm_step_advanced' : ($send['error'] ?? 'DM send failed.'),
            ]);
            return;
        }

        if ($nextStep && trim((string) $nextStep->message_text) !== '') {
            $this->sendAndRecord($conversation, $nextStep->message_text, $nextStep);
        }

        $leadResult = $this->leadCreationService->createOrLinkLead($conversation);

        if (!$nextStep) {
            $this->sendAndRecord($conversation, $this->settings->defaultFinalMessage(), null);
        }

        $state->update([
            'current_step' => $nextStep?->step_order ?? ((int) $state->current_step + 1),
            'current_field' => null,
            'completed' => true,
            'last_reply_at' => now(),
            'retries' => 0,
        ]);

        $event->update([
            'status' => ($leadResult['success'] ?? false) ? 'processed' : 'failed',
            'processed_at' => now(),
            'error_message' => $this->leadEventMessage($leadResult),
        ]);
    }

    public function parseMessagePayload(array $payload): array
    {
        $message = data_get($payload, 'entry.0.messaging.0', []);
        $senderId = data_get($message, 'sender.id');
        $recipientId = data_get($message, 'recipient.id');
        $text = data_get($message, 'message.text');

        if (!$senderId) {
            return ['valid' => false, 'error' => 'Missing sender id in message webhook payload.'];
        }

        if (!$recipientId) {
            return ['valid' => false, 'error' => 'Missing recipient id in message webhook payload.'];
        }

        if (!is_string($text) || trim($text) === '') {
            return ['valid' => false, 'error' => 'Inbound Instagram message did not contain text.'];
        }

        return [
            'valid' => true,
            'sender_id' => (string) $senderId,
            'recipient_id' => (string) $recipientId,
            'message_id' => data_get($message, 'message.mid'),
            'text' => trim($text),
            'timestamp' => data_get($message, 'timestamp'),
            'payload' => $message,
        ];
    }

    private function findConversation(InstagramAccount $account, string $instagramUserId): ?IgConversation
    {
        return IgConversation::query()
            ->with(['state.flow.steps', 'instagramAccount', 'automationRule', 'lead'])
            ->where('instagram_account_id', $account->id)
            ->where('instagram_user_id', $instagramUserId)
            ->whereIn('status', ['open', 'needs_human'])
            ->latest('id')
            ->first();
    }

    private function recordReceivedMessage(IgConversation $conversation, array $message): void
    {
        $payload = [
            'direction' => 'received',
            'message_text' => $message['text'],
            'message_type' => 'text',
            'status' => 'received',
            'payload' => $message['payload'],
            'received_at' => $this->timestampToDate($message['timestamp']),
            'error_message' => null,
        ];

        if ($message['message_id']) {
            IgMessage::query()->updateOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'meta_message_id' => $message['message_id'],
                ],
                $payload
            );
            return;
        }

        IgMessage::query()->create($payload + [
            'conversation_id' => $conversation->id,
            'meta_message_id' => null,
        ]);
    }

    private function saveCurrentReply(IgConversation $conversation, IgConversationState $state, string $text): array
    {
        $field = (string) $state->current_field;
        $fields = $conversation->collected_fields ?? [];

        if ($field === 'phone') {
            $normalized = $this->duplicateDetectionService->normalizeLeadPhone($text);
            $digits = preg_replace('/[^0-9]/', '', $normalized) ?? '';

            if (strlen($digits) < 10 || strlen($digits) > 15) {
                $fields['phone_attempt'] = $text;
                $conversation->update(['collected_fields' => $fields]);

                return [
                    'success' => false,
                    'error' => 'invalid_phone',
                ];
            }

            $fields['phone'] = $normalized;
            $fields['phone_raw'] = $text;
        } else {
            $fields[$field] = $text;
        }

        $conversation->update([
            'collected_fields' => $fields,
            'status' => 'open',
        ]);

        return ['success' => true];
    }

    private function handleInvalidReply(IgWebhookEvent $event, IgConversation $conversation, IgConversationState $state, string $error): void
    {
        $retries = (int) $state->retries + 1;
        $maxRetries = $this->settings->maxPhoneRetries();

        $state->update([
            'retries' => $retries,
            'last_reply_at' => now(),
        ]);

        if ($retries >= $maxRetries) {
            $conversation->update(['status' => 'needs_human']);
            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => $error . '_max_retries_reached',
            ]);
            return;
        }

        $send = $this->sendAndRecord($conversation, $this->settings->invalidPhoneRetryMessage(), null);

        $event->update([
            'status' => ($send['success'] ?? false) ? 'processed' : 'failed',
            'processed_at' => now(),
            'error_message' => ($send['success'] ?? false) ? $error . '_retry_sent' : ($send['error'] ?? 'Retry DM send failed.'),
        ]);
    }

    private function nextStep(IgConversationState $state): ?IgDmFlowStep
    {
        if (!$state->flow_id) {
            return null;
        }

        return IgDmFlowStep::query()
            ->where('flow_id', $state->flow_id)
            ->where('step_order', '>', $state->current_step)
            ->orderBy('step_order')
            ->first();
    }

    private function sendAndRecord(IgConversation $conversation, string $message, ?IgDmFlowStep $step): array
    {
        $message = trim($message);

        if ($message === '') {
            return ['success' => true, 'data' => null];
        }

        $result = $this->graphService->sendTextMessage(
            $conversation->instagramAccount,
            (string) $conversation->instagram_user_id,
            $message
        );

        IgMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'sent',
            'message_text' => $message,
            'message_type' => $step?->message_type ?: 'text',
            'media_url' => $step?->media_url,
            'attachment_type' => $step?->attachment_type,
            'meta_message_id' => data_get($result, 'data.message_id') ?: data_get($result, 'data.id'),
            'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
            'payload' => $result['data'] ?? $result,
            'error_message' => ($result['success'] ?? false) ? null : ($result['error'] ?? 'Instagram DM send failed.'),
        ]);

        return $result;
    }

    private function timestampToDate(mixed $timestamp): mixed
    {
        if (!$timestamp || !is_numeric($timestamp)) {
            return now();
        }

        $value = (int) $timestamp;
        $seconds = $value > 9999999999 ? (int) floor($value / 1000) : $value;

        return now()->setTimestamp($seconds);
    }

    private function leadEventMessage(array $leadResult): string
    {
        if (!($leadResult['success'] ?? false)) {
            return $leadResult['error'] ?? 'lead_creation_failed';
        }

        if ($leadResult['was_duplicate'] ?? false) {
            return 'duplicate_lead_skipped';
        }

        return ($leadResult['was_created'] ?? false) ? 'lead_created' : 'lead_linked';
    }
}
