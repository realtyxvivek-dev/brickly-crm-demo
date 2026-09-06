<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchFacebookLeadDetailsJob;
use App\Models\FbForm;
use App\Models\FbLeadAdsSettings;
use App\Models\FbPage;
use App\Models\FbWebhookEvent;
use App\Models\MetaOauthEvent;
use App\Models\MetaOauthPage;
use App\Services\FacebookGraphService;
use App\Services\MetaOauthLeadProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookPageWebhookDispatcherController extends Controller
{
    public function __construct(private readonly MetaOauthLeadProcessor $oauthLeadProcessor)
    {
    }

    public function verify(Request $request)
    {
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
        $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));

        if ($mode === 'subscribe' && in_array($token, $this->verifyTokens(), true)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        if (!$this->hasValidSignature($request)) {
            Log::warning('Facebook page dispatcher signature verification failed');

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
                $leadgenId = $value['leadgen_id'] ?? null;
                $formId = $value['form_id'] ?? null;
                $pageId = $value['page_id'] ?? ($entry['id'] ?? null);

                if (!$leadgenId) {
                    continue;
                }

                if ($this->dispatchToOldManualFlow($payload, (string) $leadgenId, $formId ? (string) $formId : null, $pageId ? (string) $pageId : null)) {
                    continue;
                }

                $this->dispatchToOauthFlow($payload, (string) $leadgenId, $formId ? (string) $formId : null, $pageId ? (string) $pageId : null);
            }
        }

        return response()->json(['success' => true]);
    }

    private function dispatchToOldManualFlow(array $payload, string $leadgenId, ?string $formId, ?string $pageId): bool
    {
        $fbForm = $this->resolveOldForm($formId, $pageId);
        if (!$fbForm) {
            return false;
        }

        if (!$fbForm->is_enabled) {
            FbWebhookEvent::create([
                'raw_payload' => $payload,
                'leadgen_id' => $leadgenId,
                'status' => 'ignored_disabled',
                'error' => 'Form disabled: ' . ($formId ?? 'null'),
            ]);

            return true;
        }

        $event = FbWebhookEvent::create([
            'raw_payload' => $payload,
            'leadgen_id' => $leadgenId,
            'status' => 'received',
        ]);

        try {
            FetchFacebookLeadDetailsJob::dispatch($leadgenId, $fbForm->id);
        } catch (\Throwable $e) {
            $event->update(['status' => 'failed', 'error' => $e->getMessage()]);
            Log::warning('Facebook page dispatcher old flow failed', [
                'leadgen_id' => $leadgenId,
                'form_id' => $formId,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    private function dispatchToOauthFlow(array $payload, string $leadgenId, ?string $formId, ?string $pageId): void
    {
        $page = $pageId ? MetaOauthPage::where('page_id', $pageId)->latest()->first() : null;

        $event = MetaOauthEvent::create([
            'meta_oauth_page_id' => $page?->id,
            'page_id' => $pageId,
            'form_id' => $formId,
            'leadgen_id' => $leadgenId,
            'raw_payload' => $payload,
            'status' => $page ? 'received' : 'unmatched',
            'error' => $page ? null : 'No old manual form or OAuth Page matched this webhook event.',
        ]);

        if ($page?->lead_mode === 'create_leads') {
            $this->oauthLeadProcessor->process($event);
        }
    }

    private function resolveOldForm(?string $formId, ?string $pageId): ?FbForm
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
        if (!$page || !$page->page_access_token) {
            return null;
        }

        $formName = 'Meta Form ' . $formId;

        try {
            $settings = FbLeadAdsSettings::getSettings();
            $client = FacebookGraphService::fromToken($page->page_access_token, $settings->graph_version ?? 'v18.0');
            $metaForm = $client->getForm($formId);

            if (($metaForm['success'] ?? false) && !empty($metaForm['form']['name'])) {
                $formName = $metaForm['form']['name'];
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook page dispatcher old form fetch failed', [
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

    private function verifyTokens(): array
    {
        return array_values(array_filter(array_unique([
            FbLeadAdsSettings::getSettings()->webhook_verify_token,
            config('meta_oauth.webhook_verify_token'),
        ])));
    }

    private function hasValidSignature(Request $request): bool
    {
        $secrets = $this->signatureSecrets();
        if (empty($secrets)) {
            return true;
        }

        $signature = (string) $request->header('X-Hub-Signature-256');
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }

        foreach ($secrets as $secret) {
            $hash = hash_hmac('sha256', $request->getContent(), $secret);
            if (hash_equals('sha256=' . $hash, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function signatureSecrets(): array
    {
        $settings = FbLeadAdsSettings::getSettings();
        $secrets = [];

        if ($settings->signature_verification_enabled && filled($settings->app_secret)) {
            $secrets[] = (string) $settings->app_secret;
        }

        if ((bool) config('meta_oauth.webhook_signature_enabled') && filled(config('meta_oauth.app_secret'))) {
            $secrets[] = (string) config('meta_oauth.app_secret');
        }

        return array_values(array_unique($secrets));
    }
}
