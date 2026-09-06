<?php

namespace App\Services\Instagram;

use App\Models\IgAutomationRule;
use App\Models\IgConversation;
use App\Models\IgConversationState;
use App\Models\IgMessage;
use App\Models\IgWebhookEvent;
use App\Models\InstagramAccount;

class InstagramCommentAutomationService
{
    public function __construct(private readonly InstagramGraphService $graphService)
    {
    }

    public function handleCommentEvent(IgWebhookEvent $event, InstagramAccount $account): void
    {
        if (!$account->isConnected()) {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'Instagram account is not connected.',
            ]);
            return;
        }

        $comment = $this->parseCommentPayload($event->payload ?? []);

        if (!($comment['valid'] ?? false)) {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => $comment['error'] ?? 'Invalid Instagram comment payload.',
            ]);
            return;
        }

        $rule = $this->findMatchingRule($account, $comment);

        if (!$rule) {
            $event->update([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'no_rule_matched',
            ]);
            return;
        }

        $publicReply = $this->graphService->replyToComment($account, $comment['comment_id'], $rule->public_reply_message);
        $errors = [];

        if (!($publicReply['success'] ?? false)) {
            $errors[] = 'public_reply_failed: ' . ($publicReply['error'] ?? 'Unknown error');
        }

        $firstStep = $rule->dmFlow?->steps()->where('message_type', 'text')->orderBy('step_order')->first();

        if (!$firstStep || trim((string) $firstStep->message_text) === '') {
            $event->update([
                'status' => empty($errors) ? 'processed' : 'failed',
                'processed_at' => now(),
                'error_message' => empty($errors) ? 'keyword_matched_public_reply_only' : implode(' | ', $errors),
            ]);
            return;
        }

        $privateReply = $this->graphService->sendPrivateReply($account, $comment['comment_id'], $firstStep->message_text);

        if (!($privateReply['success'] ?? false)) {
            $errors[] = 'private_reply_failed: ' . ($privateReply['error'] ?? 'Unknown error');
            $this->recordFailedConversationMessage($account, $rule, $comment, $firstStep->message_text, $privateReply);
            $event->update([
                'status' => 'failed',
                'processed_at' => now(),
                'error_message' => implode(' | ', $errors),
            ]);
            return;
        }

        $conversation = $this->createConversation($account, $rule, $comment);
        $this->recordSentMessage($conversation, $firstStep->message_text, $privateReply);
        $this->createConversationState($conversation, $rule, (int) $firstStep->step_order);

        $event->update([
            'status' => empty($errors) ? 'processed' : 'failed',
            'processed_at' => now(),
            'error_message' => empty($errors) ? 'keyword_matched_private_reply_sent_conversation_created' : implode(' | ', $errors),
        ]);
    }

    public function parseCommentPayload(array $payload): array
    {
        $value = data_get($payload, 'entry.0.changes.0.value', []);
        $commentId = data_get($value, 'id');
        $text = data_get($value, 'text');
        $commenterId = data_get($value, 'from.id') ?: data_get($value, 'user_id');

        if (!$commentId) {
            return ['valid' => false, 'error' => 'Missing comment_id in comment webhook payload.'];
        }

        if (!is_string($text) || trim($text) === '') {
            return ['valid' => false, 'error' => 'Missing comment text in comment webhook payload.'];
        }

        if (!$commenterId) {
            return ['valid' => false, 'error' => 'Missing commenter Instagram user id in comment webhook payload.'];
        }

        return [
            'valid' => true,
            'comment_id' => (string) $commentId,
            'media_id' => data_get($value, 'media.id') ?: data_get($value, 'media_id'),
            'comment_text' => trim((string) $text),
            'commenter_ig_user_id' => (string) $commenterId,
            'commenter_username' => data_get($value, 'from.username') ?: data_get($value, 'username'),
            'created_time' => data_get($value, 'created_time'),
        ];
    }

    private function findMatchingRule(InstagramAccount $account, array $comment): ?IgAutomationRule
    {
        $text = strtolower($comment['comment_text']);

        return IgAutomationRule::query()
            ->with(['dmFlow.steps'])
            ->where('instagram_account_id', $account->id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->where(function ($query) use ($comment) {
                $query->whereNull('media_id')->orWhere('media_id', '')->orWhere('media_id', $comment['media_id']);
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->first(function (IgAutomationRule $rule) use ($text) {
                foreach ($rule->keywords ?? [] as $keyword) {
                    $needle = strtolower(trim((string) $keyword));
                    if ($needle !== '' && str_contains($text, $needle)) {
                        return true;
                    }
                }

                return false;
            });
    }

    private function createConversation(InstagramAccount $account, IgAutomationRule $rule, array $comment): IgConversation
    {
        return IgConversation::query()->updateOrCreate(
            [
                'instagram_account_id' => $account->id,
                'comment_id' => $comment['comment_id'],
            ],
            [
                'instagram_user_id' => $comment['commenter_ig_user_id'],
                'instagram_username' => $comment['commenter_username'],
                'original_comment' => $comment['comment_text'],
                'media_id' => $comment['media_id'],
                'automation_rule_id' => $rule->id,
                'status' => 'open',
            ]
        );
    }

    private function recordSentMessage(IgConversation $conversation, string $message, array $privateReply): void
    {
        IgMessage::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'direction' => 'sent',
                'message_text' => $message,
            ],
            [
                'message_type' => 'text',
                'meta_message_id' => data_get($privateReply, 'data.message_id') ?: data_get($privateReply, 'data.id'),
                'status' => 'sent',
                'payload' => $privateReply['data'] ?? $privateReply,
                'error_message' => null,
            ]
        );
    }

    private function recordFailedConversationMessage(InstagramAccount $account, IgAutomationRule $rule, array $comment, string $message, array $privateReply): void
    {
        $conversation = $this->createConversation($account, $rule, $comment);

        IgMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'sent',
            'message_text' => $message,
            'message_type' => 'text',
            'status' => 'failed',
            'error_message' => $privateReply['error'] ?? 'Instagram private reply failed.',
            'payload' => $privateReply,
        ]);
    }

    private function createConversationState(IgConversation $conversation, IgAutomationRule $rule, int $sentStepOrder): void
    {
        IgConversationState::query()->updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'flow_id' => $rule->dm_flow_id,
                'current_step' => $sentStepOrder,
                'current_field' => $rule->dmFlow?->steps()->where('step_order', $sentStepOrder)->value('save_reply_as'),
                'completed' => false,
                'last_reply_at' => null,
                'retries' => 0,
            ]
        );
    }
}
