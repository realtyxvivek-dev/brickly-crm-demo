<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetaOauthEvent;
use App\Models\MetaOauthPage;
use App\Services\MetaOauthLeadProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaOauthWebhookController extends Controller
{
    public function __construct(private readonly MetaOauthLeadProcessor $leadProcessor)
    {
    }

    public function verify(Request $request)
    {
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
        $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));

        if ($mode === 'subscribe' && $token === (string) config('meta_oauth.webhook_verify_token')) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        if ($this->signatureVerificationEnabled() && !$this->hasValidSignature($request)) {
            Log::warning('Meta OAuth webhook signature verification failed');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        if (($payload['object'] ?? null) !== 'page') {
            return response()->json(['success' => true]);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $pageId = $value['page_id'] ?? ($entry['id'] ?? null);
                $page = $pageId ? MetaOauthPage::where('page_id', (string) $pageId)->latest()->first() : null;

                $event = MetaOauthEvent::create([
                    'meta_oauth_page_id' => $page?->id,
                    'page_id' => $pageId,
                    'form_id' => $value['form_id'] ?? null,
                    'leadgen_id' => $value['leadgen_id'] ?? null,
                    'raw_payload' => $payload,
                    'status' => 'received',
                ]);

                if ($page?->lead_mode === 'create_leads') {
                    $this->leadProcessor->process($event);
                }
            }
        }

        return response()->json(['success' => true]);
    }

    private function signatureVerificationEnabled(): bool
    {
        return (bool) config('meta_oauth.webhook_signature_enabled') && filled(config('meta_oauth.app_secret'));
    }

    private function hasValidSignature(Request $request): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256');
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $hash = hash_hmac('sha256', $request->getContent(), (string) config('meta_oauth.app_secret'));

        return hash_equals('sha256=' . $hash, $signature);
    }
}
