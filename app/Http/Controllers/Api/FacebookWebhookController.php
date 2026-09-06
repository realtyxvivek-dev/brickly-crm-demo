<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchFacebookLeadDetailsJob;
use App\Models\FbForm;
use App\Models\FbLeadAdsSettings;
use App\Models\FbPage;
use App\Models\FbWebhookEvent;
use App\Services\FacebookGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookWebhookController extends Controller
{
    /**
     * GET: Meta webhook verification (hub.mode=subscribe, hub.verify_token, hub.challenge).
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
        $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));

        $settings = FbLeadAdsSettings::getSettings();
        $expectedToken = $settings->webhook_verify_token ?? '';

        if ($mode === 'subscribe' && $token === $expectedToken) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * POST: Receive webhook payload. Store event and queue leadgen processing.
     */
    public function receive(Request $request)
    {
        $payload = $request->all();

        if (isset($payload['hub_mode']) && $payload['hub_mode'] === 'subscribe') {
            return response()->json(['success' => true]);
        }

        $settings = FbLeadAdsSettings::getSettings();
        if ($settings->signature_verification_enabled && $settings->app_secret) {
            $signature = $request->header('X-Hub-Signature-256');
            if (!$signature || !$this->verifySignature($request->getContent(), $settings->app_secret, $signature)) {
                Log::warning('Facebook webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $object = $payload['object'] ?? null;
        if ($object !== 'page') {
            return response()->json(['success' => true]);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }
                $value = $change['value'] ?? [];
                $leadgenId = $value['leadgen_id'] ?? null;
                $formId = $value['form_id'] ?? null;
                $pageId = $value['page_id'] ?? ($entry['id'] ?? null);

                if (!$leadgenId) {
                    continue;
                }

                $event = FbWebhookEvent::create([
                    'raw_payload' => $payload,
                    'leadgen_id' => $leadgenId,
                    'status' => 'received',
                ]);

                $fbForm = $this->resolveWebhookForm($formId, $pageId);
                if (!$fbForm) {
                    $event->update(['status' => 'failed', 'error' => 'Form not found: ' . ($formId ?? 'null')]);
                    continue;
                }

                if (!$fbForm->is_enabled) {
                    $event->update(['status' => 'ignored_disabled', 'error' => 'Form disabled: ' . ($formId ?? 'null')]);
                    continue;
                }

                FetchFacebookLeadDetailsJob::dispatch($leadgenId, $fbForm->id);
            }
        }

        return response()->json(['success' => true]);
    }

    protected function verifySignature(string $payload, string $appSecret, string $signature): bool
    {
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }
        $hash = hash_hmac('sha256', $payload, $appSecret);
        return hash_equals('sha256=' . $hash, $signature);
    }

    protected function resolveWebhookForm(?string $formId, ?string $pageId): ?FbForm
    {
        if (!$formId) {
            return null;
        }

        $existing = FbForm::where('form_id', $formId)->first();
        if ($existing) {
            return $existing;
        }

        if (!$pageId) {
            return null;
        }

        $page = FbPage::where('page_id', $pageId)->first();
        if (!$page) {
            return null;
        }

        $formName = 'Meta Form ' . $formId;

        try {
            $token = $page->page_access_token;
            if (!$token) {
                return null;
            }

            $settings = FbLeadAdsSettings::getSettings();
            $client = FacebookGraphService::fromToken($token, $settings->graph_version ?? 'v18.0');
            $metaForm = $client->getForm($formId);

            if (($metaForm['success'] ?? false) && !empty($metaForm['form']['name'])) {
                $formName = $metaForm['form']['name'];
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook webhook form name fetch failed', [
                'form_id' => $formId,
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }

        return FbForm::firstOrCreate(
            ['form_id' => $formId],
            [
                'fb_page_id' => $page->id,
                'form_name' => $formName,
                'is_enabled' => true,
            ]
        );
    }
}
