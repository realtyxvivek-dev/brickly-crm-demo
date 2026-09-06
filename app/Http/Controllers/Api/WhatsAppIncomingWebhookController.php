<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppApiSettings;
use App\Services\WhatsAppLeadAutomationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppIncomingWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppLeadAutomationService $automationService
    ) {
    }

    public function verify(Request $request)
    {
        $click2ApiChallenge = $request->query('challange', $request->query('challenge'));
        if (filled($click2ApiChallenge)) {
            return response($click2ApiChallenge, 200)->header('Content-Type', 'text/plain');
        }

        $settings = WhatsAppApiSettings::getSettings();
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode === 'subscribe' && $challenge && filled($settings->webhook_verify_token) && hash_equals((string) $settings->webhook_verify_token, (string) $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        try {
            $payload = $request->all();
            Log::info('WhatsApp incoming webhook payload received', $payload);

            $parsed = $this->parseIncomingPayload($payload);

            if (!$parsed['processable']) {
                Log::info('WhatsApp incoming webhook ignored', [
                    'reason' => $parsed['reason'],
                    'payload' => $payload,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $parsed['reason'],
                ], 200);
            }

            $phone = $this->normalizePhone($parsed['phone']);
            $contactName = $parsed['contact_name'];
            $automationContext = $this->automationService->resolveInboundConversation($phone, $contactName);
            /** @var WhatsAppConversation $conversation */
            $conversation = $automationContext['conversation'];

            $message = WhatsAppMessage::updateOrCreate(
                [
                    'message_id' => (string) $parsed['message_id'],
                    'conversation_id' => $conversation->id,
                ],
                [
                    'user_id' => $conversation->user_id,
                    'direction' => 'received',
                    'message' => $parsed['message'],
                    'status' => 'delivered',
                    'api_response' => [
                        'webhook_payload' => $payload,
                        'parsed_message' => $parsed['raw_message'],
                        'parsed_contact' => $parsed['raw_contact'],
                        'message_type' => $parsed['type'],
                    ],
                    'provider' => $parsed['provider'],
                    'external_message_id' => (string) $parsed['message_id'],
                    'provider_status' => 'received',
                    'sent_at' => $parsed['sent_at'],
                ]
            );

            $conversation->forceFill(['last_inbound_at' => $parsed['sent_at']])->save();
            $conversation->touch();
            $this->automationService->notifyInboundMessage($conversation, $message, $automationContext);

            Log::info('WhatsApp incoming webhook message saved', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'external_message_id' => $parsed['message_id'],
                'message_type' => $parsed['type'],
                'lead_id' => $conversation->lead_id,
                'owner_id' => $conversation->user_id,
            ]);

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook parse failure', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json(['success' => false], 200);
        }
    }

    private function parseIncomingPayload(array $payload): array
    {
        $value = data_get($payload, 'entry.0.changes.0.value');
        $field = data_get($payload, 'entry.0.changes.0.field');
        $contact = data_get($value, 'contacts.0', []);
        $message = data_get($value, 'messages.0', []);

        // Fallback to older flat structure if needed.
        if (!$value && empty($message)) {
            return $this->parseFlatPayload($payload);
        }

        if ($field !== 'messages') {
            return [
                'processable' => false,
                'reason' => 'Ignored webhook field',
            ];
        }

        if (empty($message)) {
            return [
                'processable' => false,
                'reason' => 'No incoming messages found',
            ];
        }

        $phone = $message['from'] ?? ($contact['wa_id'] ?? null);
        $messageId = $message['id'] ?? null;
        $type = $message['type'] ?? 'unknown';
        $messageText = $this->extractMessageText($message, $type);

        if (!$phone || !$messageId || !$messageText) {
            return [
                'processable' => false,
                'reason' => 'Missing phone, message id, or usable content',
            ];
        }

        return [
            'processable' => true,
            'phone' => $phone,
            'contact_name' => data_get($contact, 'profile.name'),
            'message_id' => $messageId,
            'type' => $type,
            'message' => $messageText,
            'sent_at' => $this->normalizeTimestamp($message['timestamp'] ?? null),
            'raw_message' => $message,
            'raw_contact' => $contact,
            'provider' => 'meta_waba',
        ];
    }

    private function parseFlatPayload(array $payload): array
    {
        $phone = $payload['from'] ?? $payload['phone'] ?? $payload['sender'] ?? null;
        $message = $payload['message'] ?? $payload['body'] ?? $payload['text'] ?? null;
        $messageId = $payload['id'] ?? $payload['message_id'] ?? null;

        if (!$phone || !$message) {
            return [
                'processable' => false,
                'reason' => 'Missing phone or message',
            ];
        }

        return [
            'processable' => true,
            'phone' => $phone,
            'contact_name' => $payload['name'] ?? $payload['contact_name'] ?? null,
            'message_id' => (string) ($messageId ?? uniqid('flat_', true)),
            'type' => 'text',
            'message' => is_array($message) ? ($message['body'] ?? json_encode($message)) : $message,
            'sent_at' => $this->normalizeTimestamp($payload['timestamp'] ?? $payload['created_at'] ?? null),
            'raw_message' => $payload,
            'raw_contact' => [],
            'provider' => 'third_party',
        ];
    }

    private function extractMessageText(array $message, string $type): ?string
    {
        return match ($type) {
            'text' => data_get($message, 'text.body'),
            'interactive' => $this->extractInteractiveText($message),
            'audio' => data_get($message, 'audio.voice') ? 'Voice message' : 'Audio message',
            'image' => 'Image',
            'sticker' => 'Sticker',
            'video' => 'Video',
            'document' => data_get($message, 'document.filename') ?: 'Document',
            'location' => data_get($message, 'location.address') ?: 'Shared location',
            'unsupported' => 'Unsupported WhatsApp message',
            default => '[Unsupported message type: ' . $type . ']',
        };
    }

    private function extractInteractiveText(array $message): string
    {
        $interactive = is_array($message['interactive'] ?? null) ? $message['interactive'] : [];

        $buttonReplyTitle = trim((string) data_get($interactive, 'button_reply.title', ''));
        if ($buttonReplyTitle !== '') {
            return $buttonReplyTitle;
        }

        $listReplyTitle = trim((string) data_get($interactive, 'list_reply.title', ''));
        $listReplyDescription = trim((string) data_get($interactive, 'list_reply.description', ''));
        if ($listReplyTitle !== '') {
            return $listReplyDescription !== ''
                ? ($listReplyTitle . ' - ' . $listReplyDescription)
                : $listReplyTitle;
        }

        $flowTitle = trim((string) data_get($interactive, 'nfm_reply.body', ''));
        if ($flowTitle !== '') {
            return $flowTitle;
        }

        $responseJson = data_get($interactive, 'nfm_reply.response_json');
        if (is_string($responseJson) && trim($responseJson) !== '') {
            return 'Interactive response';
        }

        return 'Interactive reply';
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 10) {
            return '91' . $phone;
        }

        return $phone;
    }

    private function normalizeTimestamp(mixed $timestamp): Carbon
    {
        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp((int) $timestamp);
        }

        return $timestamp ? Carbon::parse($timestamp) : now();
    }

}
