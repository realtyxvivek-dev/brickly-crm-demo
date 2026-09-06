<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInstagramWebhookEventJob;
use App\Models\IgWebhookEvent;
use App\Services\Instagram\InstagramConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstagramWebhookController extends Controller
{
    public function __construct(private readonly InstagramConfig $config)
    {
    }

    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode === 'subscribe' && filled($this->config->webhookVerifyToken()) && hash_equals((string) $this->config->webhookVerifyToken(), (string) $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        if ($this->config->webhookSignatureEnabled() && !$this->signatureIsValid($rawBody, (string) $request->header('X-Hub-Signature-256'))) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            $event = IgWebhookEvent::query()->create([
                'payload' => ['raw_body' => $rawBody],
                'event_type' => 'unknown',
                'object_type' => null,
                'status' => 'ignored',
                'received_at' => now(),
                'processed_at' => now(),
                'error_message' => 'Invalid JSON payload.',
            ]);

            return response()->json(['success' => true, 'event_id' => $event->id]);
        }

        $summary = $this->summarizePayload($payload);
        $event = IgWebhookEvent::query()->create([
            'external_event_id' => $summary['external_event_id'],
            'event_type' => $summary['event_type'],
            'object_type' => $summary['object_type'],
            'field' => $summary['field'],
            'payload' => $payload,
            'status' => 'received',
            'received_at' => now(),
        ]);

        ProcessInstagramWebhookEventJob::dispatch($event->id)->onQueue('instagram');
        $event->update(['status' => 'queued']);

        return response()->json(['success' => true, 'event_id' => $event->id]);
    }

    private function signatureIsValid(string $rawBody, string $signature): bool
    {
        $secret = $this->config->appSecret();

        if (!filled($secret) || !str_starts_with($signature, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, (string) $secret), $signature);
    }

    private function summarizePayload(array $payload): array
    {
        $entry = data_get($payload, 'entry.0', []);
        $change = data_get($entry, 'changes.0', []);
        $messaging = data_get($entry, 'messaging.0', []);
        $field = data_get($change, 'field') ?: (empty($messaging) ? null : 'messages');

        return [
            'external_event_id' => data_get($change, 'value.id')
                ?: data_get($messaging, 'message.mid')
                ?: data_get($entry, 'id'),
            'object_type' => data_get($payload, 'object'),
            'field' => $field,
            'event_type' => $this->normalizeEventType($field),
        ];
    }

    private function normalizeEventType(?string $field): string
    {
        return match ($field) {
            'comments', 'live_comments' => 'comments',
            'messages', 'messaging_postbacks' => 'messages',
            'message_reactions' => 'message_reactions',
            default => 'unknown',
        };
    }
}
