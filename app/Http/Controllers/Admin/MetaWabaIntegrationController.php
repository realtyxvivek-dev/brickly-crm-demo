<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaWabaAccount;
use App\Models\MetaWabaSettings;
use App\Models\SystemSettings;
use App\Models\Lead;
use App\Models\LeadTag;
use App\Models\WabaCampaign;
use App\Models\WabaCallEvent;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\MetaWabaApiService;
use App\Services\WabaCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MetaWabaIntegrationController extends Controller
{
    public function index()
    {
        $settings = MetaWabaSettings::getSettings();
        if (MetaWabaAccount::query()->count() === 0 && ($settings->phone_number_id || $settings->waba_id || $settings->access_token)) {
            MetaWabaAccount::fromSettings($settings);
        }

        $accounts = MetaWabaAccount::query()->orderByDesc('is_default')->orderBy('id')->get();
        $baseSettings = $settings;
        $selectedAccount = request()->boolean('new_account')
            ? null
            : ($this->selectedAccount(request()) ?: MetaWabaAccount::defaultAccount());
        if ($selectedAccount) {
            $settings = $selectedAccount;
        } elseif (request()->boolean('new_account')) {
            $settings = new MetaWabaAccount([
                'is_active' => true,
                'is_verified' => false,
                'graph_version' => $baseSettings->graph_version ?: 'v20.0',
                'meta_app_id' => $baseSettings->meta_app_id,
                'embedded_signup_configuration_id' => $baseSettings->embedded_signup_configuration_id,
                'business_account_id' => $baseSettings->business_account_id,
                'meta_business_id' => $baseSettings->meta_business_id,
                'webhook_verify_token' => $baseSettings->webhook_verify_token,
                'app_secret' => $baseSettings->app_secret,
                'privacy_policy_url' => $baseSettings->privacy_policy_url,
                'terms_url' => $baseSettings->terms_url,
                'data_deletion_url' => $baseSettings->data_deletion_url,
                'connection_status' => 'manual',
            ]);
        }
        $defaultSender = SystemSettings::get('whatsapp_default_sender', 'third_party');
        $templates = WhatsAppTemplate::query()
            ->where('provider', 'meta_waba')
            ->when($selectedAccount, fn ($query) => $query->where('meta_waba_account_id', $selectedAccount->id))
            ->orderBy('name')
            ->limit(50)
            ->get();
        $approvedTemplates = WhatsAppTemplate::query()
            ->where('provider', 'meta_waba')
            ->where('is_active', true)
            ->when($selectedAccount, fn ($query) => $query->where('meta_waba_account_id', $selectedAccount->id))
            ->orderBy('name')
            ->get();
        $campaigns = WabaCampaign::query()
            ->with(['template', 'metaWabaAccount'])
            ->when($selectedAccount, fn ($query) => $query->where('meta_waba_account_id', $selectedAccount->id))
            ->latest()
            ->limit(20)
            ->get();
        $tags = LeadTag::query()
            ->where('is_folder', true)
            ->withCount('leads')
            ->orderBy('name')
            ->get();
        $sources = Lead::sourceOptions();
        $recentCallEvents = WabaCallEvent::query()
            ->with(['lead:id,name,phone', 'assignedTo:id,name', 'telecallerTask:id,status'])
            ->latest('occurred_at')
            ->latest('id')
            ->limit(25)
            ->get();
        $unmatchedCallEvents = WabaCallEvent::query()
            ->whereNull('lead_id')
            ->latest('occurred_at')
            ->latest('id')
            ->limit(25)
            ->get();
        $callStats = [
            'today_missed' => WabaCallEvent::query()
                ->whereDate('occurred_at', today())
                ->whereIn('status', ['missed', 'missed_call', 'no_answer', 'unanswered'])
                ->count(),
            'pending_callbacks' => WabaCallEvent::query()
                ->where(function ($query) {
                    $query->where('event_type', 'callback_request')
                        ->orWhere('status', 'callback_requested');
                })
                ->whereNull('telecaller_task_id')
                ->count(),
            'unmatched' => WabaCallEvent::query()->whereNull('lead_id')->count(),
        ];

        return view('integrations.meta-waba', compact(
            'settings',
            'accounts',
            'selectedAccount',
            'defaultSender',
            'templates',
            'approvedTemplates',
            'campaigns',
            'tags',
            'sources',
            'recentCallEvents',
            'unmatchedCallEvents',
            'callStats'
        ));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'is_active' => 'nullable|boolean',
            'make_default_sender' => 'nullable|boolean',
            'graph_version' => 'nullable|string|max:20',
            'phone_number_id' => 'nullable|string|max:255',
            'waba_id' => 'nullable|string|max:255',
            'business_account_id' => 'nullable|string|max:255',
            'meta_business_id' => 'nullable|string|max:255',
            'meta_app_id' => 'nullable|string|max:255',
            'embedded_signup_configuration_id' => 'nullable|string|max:255',
            'access_token' => 'nullable|string',
            'webhook_verify_token' => 'nullable|string|max:255',
            'app_secret' => 'nullable|string',
            'privacy_policy_url' => 'nullable|url|max:2000',
            'terms_url' => 'nullable|url|max:2000',
            'data_deletion_url' => 'nullable|url|max:2000',
            'name' => 'nullable|string|max:255',
            'meta_waba_account_id' => 'nullable|integer|exists:meta_waba_accounts,id',
        ]);

        $account = $this->selectedAccount($request);
        if (!$account) {
            $account = new MetaWabaAccount();
        }
        $previousStatus = $account->exists ? ($account->connection_status ?: 'manual') : 'manual';

        $account->fill([
            'name' => $validated['name'] ?? null,
            'connected_by_user_id' => auth()->id(),
            'is_active' => $request->boolean('is_active'),
            'is_verified' => false,
            'verified_at' => null,
            'graph_version' => trim((string) ($validated['graph_version'] ?? '')) ?: 'v20.0',
            'phone_number_id' => $validated['phone_number_id'] ?? null,
            'waba_id' => $validated['waba_id'] ?? null,
            'business_account_id' => $validated['business_account_id'] ?? null,
            'meta_business_id' => $validated['meta_business_id'] ?? null,
            'meta_app_id' => $validated['meta_app_id'] ?? null,
            'embedded_signup_configuration_id' => $validated['embedded_signup_configuration_id'] ?? null,
            'connection_status' => filled($validated['phone_number_id'] ?? null) ? 'manual' : $previousStatus,
            'last_error' => null,
            'access_token' => $validated['access_token'] ?? null,
            'webhook_verify_token' => $validated['webhook_verify_token'] ?? null,
            'app_secret' => $validated['app_secret'] ?? null,
            'privacy_policy_url' => $validated['privacy_policy_url'] ?? null,
            'terms_url' => $validated['terms_url'] ?? null,
            'data_deletion_url' => $validated['data_deletion_url'] ?? null,
        ])->save();

        if ($request->boolean('make_default_sender') || !$account->wasRecentlyCreated && $account->is_default) {
            $this->makeDefaultAccount($account);
        }

        SystemSettings::set('whatsapp_default_sender', $request->boolean('make_default_sender') ? 'meta_waba' : 'third_party');

        return response()->json([
            'success' => true,
            'message' => 'Meta WABA settings saved successfully.',
        ]);
    }

    public function startEmbeddedSignup()
    {
        $settings = $this->selectedAccount(request()) ?: MetaWabaSettings::getSettings();

        return response()->json([
            'success' => true,
            'app_id' => $settings->meta_app_id,
            'configuration_id' => $settings->embedded_signup_configuration_id,
            'graph_version' => $settings->graph_version ?: 'v20.0',
            'finish_url' => route($this->routePrefix(request()) . 'embedded.finish'),
        ]);
    }

    public function finishEmbeddedSignup(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:2000',
            'waba_id' => 'nullable|string|max:255',
            'phone_number_id' => 'nullable|string|max:255',
            'business_id' => 'nullable|string|max:255',
            'display_phone_number' => 'nullable|string|max:255',
            'raw_response' => 'nullable',
            'meta_waba_account_id' => 'nullable|integer|exists:meta_waba_accounts,id',
            'create_new_account' => 'nullable|boolean',
        ]);

        $settings = $this->selectedAccount($request) ?: MetaWabaAccount::defaultAccount() ?: MetaWabaAccount::fromSettings(MetaWabaSettings::getSettings());
        $rawResponse = $this->decodeEmbeddedSignupResponse($validated['raw_response'] ?? null);
        $wabaId = $validated['waba_id'] ?? data_get($rawResponse, 'data.waba_id') ?? data_get($rawResponse, 'waba_id');
        $phoneNumberId = $validated['phone_number_id'] ?? data_get($rawResponse, 'data.phone_number_id') ?? data_get($rawResponse, 'phone_number_id');
        $businessId = $validated['business_id'] ?? data_get($rawResponse, 'data.business_id') ?? data_get($rawResponse, 'business_id');
        $displayPhone = $validated['display_phone_number'] ?? data_get($rawResponse, 'data.display_phone_number') ?? data_get($rawResponse, 'display_phone_number');

        $updates = [
            'connected_by_user_id' => auth()->id(),
            'name' => $displayPhone ?: $settings->name ?: 'Meta WABA Account',
            'is_active' => true,
            'is_verified' => false,
            'verified_at' => null,
            'waba_id' => $wabaId ?: $settings->waba_id,
            'phone_number_id' => $phoneNumberId ?: $settings->phone_number_id,
            'business_account_id' => $businessId ?: $settings->business_account_id,
            'meta_business_id' => $businessId ?: $settings->meta_business_id,
            'display_phone_number' => $displayPhone ?: $settings->display_phone_number,
            'embedded_signup_response' => $rawResponse ?: $request->all(),
            'connection_status' => 'embedded_connected',
            'last_error' => null,
        ];

        if (filled($validated['code'] ?? null) && filled($settings->meta_app_id) && filled($settings->app_secret)) {
            $tokenResult = $this->exchangeEmbeddedSignupCode($settings, (string) $validated['code']);
            if ($tokenResult['success'] ?? false) {
                $updates['access_token'] = data_get($tokenResult, 'data.access_token');
            } else {
                $updates['connection_status'] = 'failed';
                $updates['last_error'] = $tokenResult['error'] ?? 'Embedded Signup code exchange failed.';
            }
        }

        $settings->update($updates);
        if (!$request->boolean('create_new_account') && !$settings->is_default) {
            $this->makeDefaultAccount($settings);
        } elseif ($settings->is_default) {
            $settings->syncToSettings();
        }

        return response()->json([
            'success' => true,
            'message' => $updates['connection_status'] === 'failed'
                ? 'WhatsApp connected, but token exchange failed. Check App ID/App Secret and try Verify.'
                : 'WhatsApp account connected. Next: click Verify, then Sync Templates.',
            'data' => $settings->fresh(),
        ]);
    }

    public function disconnect()
    {
        $settings = $this->selectedAccount(request());
        if (!$settings) {
            $settings = MetaWabaAccount::defaultAccount();
        }
        if (!$settings) {
            return response()->json(['success' => false, 'message' => 'No WABA account found.'], 422);
        }
        $settings->update([
            'connected_by_user_id' => null,
            'is_active' => false,
            'is_verified' => false,
            'verified_at' => null,
            'phone_number_id' => null,
            'waba_id' => null,
            'business_account_id' => null,
            'meta_business_id' => null,
            'access_token' => null,
            'display_phone_number' => null,
            'verified_name' => null,
            'quality_rating' => null,
            'last_verified_response' => null,
            'embedded_signup_response' => null,
            'connection_status' => 'manual',
            'last_error' => null,
        ]);
        if ($settings instanceof MetaWabaAccount && $settings->is_default) {
            $settings->fresh()->syncToSettings();
        }

        return response()->json([
            'success' => true,
            'message' => 'WABA disconnected from CRM. Old conversations and messages were kept.',
        ]);
    }

    public function verify(Request $request, MetaWabaApiService $service)
    {
        $account = $this->selectedAccount($request);
        if ($account) {
            $service = $service->forAccount($account);
        }
        $result = $service->verifyConnection();
        $settings = $account ?: MetaWabaSettings::getSettings();
        $error = $result['error'] ?? $result['message'] ?? 'Meta WABA verification failed.';
        $settings->update([
            'connection_status' => ($result['success'] ?? false) ? 'verified' : 'failed',
            'last_error' => ($result['success'] ?? false) ? null : $this->friendlyMetaError($error),
        ]);
        if ($settings instanceof MetaWabaAccount && $settings->is_default) {
            $settings->fresh()->syncToSettings();
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function syncTemplates(Request $request, MetaWabaApiService $service)
    {
        $account = $this->selectedAccount($request) ?: MetaWabaAccount::defaultAccount();
        if ($account) {
            $service = $service->forAccount($account);
        }
        $result = $service->getTemplates();
        if (!($result['success'] ?? false)) {
            $message = $this->friendlyMetaError($result['error'] ?? 'Template sync failed.');
            ($account ?: MetaWabaSettings::getSettings())->update([
                'connection_status' => 'failed',
                'last_error' => $message,
            ]);
            return response()->json($result + ['message' => $message], 422);
        }

        $synced = 0;
        $remoteTemplateIds = [];
        foreach (($result['data'] ?? []) as $template) {
            try {
                $templateId = $template['id'] ?? $template['name'] ?? null;
                if (!$templateId) {
                    continue;
                }
                $remoteTemplateIds[] = (string) $templateId;

                WhatsAppTemplate::updateOrCreate(
                    [
                        'template_id' => (string) $templateId,
                        'meta_waba_account_id' => $account?->id,
                    ],
                    [
                        'provider' => 'meta_waba',
                        'meta_waba_account_id' => $account?->id,
                        'name' => $template['name'] ?? 'Untitled Template',
                        'content' => WhatsAppTemplate::extractContent($template),
                        'components' => $template['components'] ?? [],
                        'raw_payload' => $template,
                        'category' => $template['category'] ?? null,
                        'language' => $template['language'] ?? 'en_US',
                        'status' => strtoupper((string) ($template['status'] ?? 'APPROVED')),
                        'rejection_reason' => data_get($template, 'rejected_reason'),
                        'is_active' => strtoupper((string) ($template['status'] ?? 'APPROVED')) === 'APPROVED',
                    ]
                );
                $synced++;
            } catch (\Throwable $e) {
                Log::warning('Meta WABA template sync failed for template', [
                    'template' => $template,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($account && !empty($remoteTemplateIds)) {
            WhatsAppTemplate::query()
                ->where('provider', 'meta_waba')
                ->where('meta_waba_account_id', $account->id)
                ->whereIn('status', ['APPROVED', 'PENDING', 'IN_REVIEW', 'REJECTED'])
                ->whereNotIn('template_id', array_unique($remoteTemplateIds))
                ->update([
                    'status' => 'REMOVED',
                    'is_active' => false,
                    'rejection_reason' => 'Removed from latest Meta template sync for this API number.',
                ]);
        }

        ($account ?: MetaWabaSettings::getSettings())->update([
            'templates_synced_at' => now(),
            'last_template_sync_count' => $synced,
            'last_error' => null,
        ]);
        if ($account && $account->is_default) {
            $account->fresh()->syncToSettings();
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$synced} Meta WABA template(s).",
            'synced_count' => $synced,
        ]);
    }

    public function createTemplate(Request $request, MetaWabaApiService $service)
    {
        $account = $this->selectedAccount($request) ?: MetaWabaAccount::defaultAccount();
        if ($account) {
            $service = $service->forAccount($account);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:512|regex:/^[a-z0-9_]+$/',
            'category' => 'required|string|in:MARKETING,UTILITY,AUTHENTICATION',
            'language' => 'required|string|max:20',
            'header_text' => 'nullable|string|max:60',
            'body_text' => 'required|string|max:1024',
            'footer_text' => 'nullable|string|max:60',
            'body_samples' => 'nullable|string|max:1000',
            'quick_reply_buttons' => 'nullable|array|max:3',
            'quick_reply_buttons.*' => 'nullable|string|max:25',
            'phone_button_text' => 'nullable|string|max:25',
            'phone_button_number' => 'nullable|string|max:20',
            'url_button_text' => 'nullable|string|max:25',
            'url_button_url' => 'nullable|url|max:2000',
        ]);

        $components = $this->buildTemplateComponents($validated);
        $payload = [
            'name' => $validated['name'],
            'category' => $validated['category'],
            'language' => $validated['language'],
            'components' => $components,
        ];

        $result = $service->createTemplate($payload);

        if ($result['success'] ?? false) {
            WhatsAppTemplate::updateOrCreate(
                ['template_id' => (string) (data_get($result, 'data.id') ?: $validated['name'])],
                [
                    'provider' => 'meta_waba',
                    'meta_waba_account_id' => $account?->id,
                    'name' => $validated['name'],
                    'content' => $validated['body_text'],
                    'components' => $components,
                    'raw_payload' => $result['data'] ?? $payload,
                    'category' => $validated['category'],
                    'language' => $validated['language'],
                    'status' => strtoupper((string) (data_get($result, 'data.status') ?: 'PENDING')),
                    'is_active' => strtoupper((string) (data_get($result, 'data.status') ?: 'PENDING')) === 'APPROVED',
                ]
            );
        }

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => ($result['success'] ?? false)
                ? 'Template submitted to Meta. Sync templates after Meta review to refresh status.'
                : $this->friendlyMetaError($result['error'] ?? 'Template submission failed.'),
            'data' => $result['data'] ?? null,
            'error' => $result['error'] ?? null,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function deleteTemplate(Request $request, WhatsAppTemplate $template, MetaWabaApiService $service)
    {
        $account = $template->metaWabaAccount ?: $this->selectedAccount($request) ?: MetaWabaAccount::defaultAccount();
        if ($account) {
            $service = $service->forAccount($account);
        }

        $result = ['success' => true, 'provider' => 'local'];
        if ($template->provider === 'meta_waba' && filled($template->name) && $account) {
            $result = $service->deleteTemplate($template->name);
        }

        $remoteDeleted = (bool) ($result['success'] ?? false);
        $remoteError = $result['error'] ?? null;
        $notFound = str_contains(strtolower((string) $remoteError), 'not found')
            || (int) ($result['status'] ?? 0) === 404;

        $templateName = $template->name;
        $template->delete();

        $warning = ($remoteDeleted || $notFound || !$account)
            ? null
            : ' Meta remote delete failed: ' . $this->friendlyMetaError($remoteError ?? 'Template delete failed.');

        return response()->json([
            'success' => true,
            'message' => "Template {$templateName} removed from CRM." . ($warning ?: ''),
            'warning' => $warning,
        ]);
    }

    public function previewCampaign(Request $request, WabaCampaignService $campaignService)
    {
        $criteria = $this->campaignCriteriaFromRequest($request);

        return response()->json([
            'success' => true,
            'preview' => $campaignService->previewAudience($criteria),
        ]);
    }

    public function storeCampaign(Request $request, WabaCampaignService $campaignService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'template_id' => 'required|exists:whatsapp_templates,id',
            'rate_limit_per_minute' => 'nullable|integer|min:1|max:1000',
            'scheduled_at' => 'nullable|date',
            'variable_mapping' => 'nullable|string|max:2000',
            'meta_waba_account_id' => 'nullable|integer|exists:meta_waba_accounts,id',
        ]);

        $criteria = $this->campaignCriteriaFromRequest($request);
        $mapping = $this->parseVariableMapping($validated['variable_mapping'] ?? '');
        $preview = $campaignService->previewAudience($criteria);

        if (($preview['valid'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'tag_id' => 'Is folder me send karne layak valid leads nahi hain.',
            ]);
        }

        $campaign = $campaignService->createCampaign([
            'name' => $validated['name'],
            'template_id' => $validated['template_id'],
            'rate_limit_per_minute' => $validated['rate_limit_per_minute'] ?? 30,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'meta_waba_account_id' => $validated['meta_waba_account_id'] ?? null,
            'audience_criteria' => $criteria,
            'variable_mapping' => $mapping,
        ], (int) auth()->id());

        return response()->json([
            'success' => true,
            'message' => "Campaign queued with {$campaign->total_recipients} recipient(s).",
            'campaign_id' => $campaign->id,
        ]);
    }

    private function campaignCriteriaFromRequest(Request $request): array
    {
        return collect($request->only([
            'audience_type',
            'tag_id',
            'assigned_to',
            'city',
            'source',
            'status',
            'search',
        ]))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function parseVariableMapping(string $mappingText): array
    {
        $mapping = [];
        foreach (preg_split('/\r\n|\r|\n/', $mappingText) ?: [] as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }
            [$position, $field] = array_map('trim', explode('=', $line, 2));
            if (ctype_digit($position) && $field !== '') {
                $mapping[(int) $position] = $field;
            }
        }

        ksort($mapping);

        return $mapping;
    }

    private function buildTemplateComponents(array $validated): array
    {
        $components = [];

        if (filled($validated['header_text'] ?? null)) {
            $components[] = [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $validated['header_text'],
            ];
        }

        $body = [
            'type' => 'BODY',
            'text' => $validated['body_text'],
        ];

        $samples = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['body_samples'] ?? '')) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        if (!empty($samples)) {
            $body['example'] = ['body_text' => [$samples]];
        }

        $components[] = $body;

        if (filled($validated['footer_text'] ?? null)) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $validated['footer_text'],
            ];
        }

        $quickReplySource = $validated['quick_reply_buttons'] ?? [];
        if (is_string($quickReplySource)) {
            $quickReplySource = preg_split('/\r\n|\r|\n/', $quickReplySource) ?: [];
        }

        $buttons = collect($quickReplySource)
            ->map(fn ($line) => trim($line))
            ->filter()
            ->take(3)
            ->map(fn ($text) => ['type' => 'QUICK_REPLY', 'text' => Str::limit($text, 25, '')])
            ->values()
            ->all();

        if (filled($validated['phone_button_text'] ?? null) && filled($validated['phone_button_number'] ?? null) && count($buttons) < 3) {
            $buttons[] = [
                'type' => 'PHONE_NUMBER',
                'text' => Str::limit($validated['phone_button_text'], 25, ''),
                'phone_number' => preg_replace('/[^0-9+]/', '', (string) $validated['phone_button_number']),
            ];
        }

        if (filled($validated['url_button_text'] ?? null) && filled($validated['url_button_url'] ?? null) && count($buttons) < 3) {
            $buttons[] = [
                'type' => 'URL',
                'text' => Str::limit($validated['url_button_text'], 25, ''),
                'url' => $validated['url_button_url'],
            ];
        }

        if (!empty($buttons)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        }

        return $components;
    }

    public function testTemplate(Request $request, MetaWabaApiService $service)
    {
        $validated = $request->validate([
            'phone' => 'nullable|string',
            'recipient_phone' => 'nullable|string',
            'country_code' => 'nullable|string|max:6',
            'template_name' => 'nullable|string',
            'template_id' => 'nullable|integer|exists:whatsapp_templates,id',
            'language' => 'nullable|string',
            'parameters' => 'nullable|array',
            'parameters.*' => 'nullable|string|max:500',
            'meta_waba_account_id' => 'nullable|integer|exists:meta_waba_accounts,id',
        ]);

        $template = filled($validated['template_id'] ?? null)
            ? \App\Models\WhatsAppTemplate::query()->find((int) $validated['template_id'])
            : null;
        $phone = $this->combineMetaTestPhone(
            $validated['recipient_phone'] ?? $validated['phone'] ?? null,
            $validated['country_code'] ?? '91'
        );
        $templateName = $validated['template_name'] ?? $template?->name;
        if (!$phone || !$templateName) {
            return response()->json(['success' => false, 'message' => 'Phone and approved template are required.'], 422);
        }

        $account = $this->selectedAccount($request) ?: MetaWabaAccount::defaultAccount();
        if ($account) {
            $service = $service->forAccount($account);
        }
        $result = $service->sendTemplateMessage(
            $phone,
            $templateName,
            collect($validated['parameters'] ?? [])->filter(fn ($value) => filled($value))->map(fn ($value) => ['type' => 'text', 'text' => (string) $value])->values()->all(),
            ($validated['language'] ?? null) ?: ($template?->language ?: 'en_US')
        );

        $messageId = data_get($result, 'data.message_id') ?: data_get($result, 'data.messages.0.id') ?: data_get($result, 'data.id');
        $sent = (bool) ($result['success'] ?? false);
        $normalizedPhone = $this->normalizeMetaTestPhone((string) $phone);

        $conversation = WhatsAppConversation::query()
            ->where(function ($query) use ($normalizedPhone) {
                $query->where('phone_number', $normalizedPhone)
                    ->orWhere('phone_number', substr($normalizedPhone, -10));
            })
            ->first();

        if (!$conversation) {
            $conversation = WhatsAppConversation::create([
                'user_id' => auth()->id(),
                'assigned_to' => auth()->id(),
                'status' => 'open',
                'phone_number' => $normalizedPhone,
                'contact_name' => 'Test recipient',
                'meta_waba_account_id' => $account?->id,
            ]);
        }

        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'direction' => 'sent',
            'message' => $template?->content ?: ('Template: ' . $templateName),
            'message_id' => $messageId,
            'template_id' => $template?->id,
            'provider' => 'meta_waba',
            'meta_waba_account_id' => $result['meta_waba_account_id'] ?? $account?->id,
            'external_message_id' => $messageId,
            'provider_status' => $sent ? 'accepted' : 'failed',
            'status' => $sent ? 'sent' : 'failed',
            'error_message' => $sent ? null : $this->friendlyMetaError($result['error'] ?? 'Template send failed.'),
            'api_response' => $result,
            'sent_at' => $sent ? now() : null,
        ]);
        $conversation->touch();

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? 'Meta accepted test message. Message ID: ' . ($messageId ?: 'pending')
                : $this->friendlyMetaError($result['error'] ?? 'Template send failed.'),
            'data' => $result['data'] ?? null,
            'error' => $result['error'] ?? null,
        ], $sent ? 200 : 422);
    }

    public function registerPhone(Request $request, MetaWabaApiService $service)
    {
        $validated = $request->validate([
            'pin' => ['nullable', 'digits:6'],
            'meta_waba_account_id' => 'nullable|integer|exists:meta_waba_accounts,id',
        ]);

        $account = $this->selectedAccount($request) ?: MetaWabaAccount::defaultAccount();
        if ($account) {
            $service = $service->forAccount($account);
        }
        $result = $service->registerPhoneNumber($validated['pin'] ?? null);
        ($account ?: MetaWabaSettings::getSettings())->update([
            'last_error' => ($result['success'] ?? false) ? null : ($result['error'] ?? 'Phone registration failed.'),
        ]);
        if ($account && $account->is_default) {
            $account->fresh()->syncToSettings();
        }

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => ($result['success'] ?? false)
                ? 'Phone number registered for Meta Cloud API successfully.'
                : $this->friendlyMetaError($result['error'] ?? 'Phone registration failed.'),
            'data' => $result['data'] ?? null,
            'error' => $result['error'] ?? null,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function refreshCallSettings(Request $request, MetaWabaApiService $service)
    {
        if ($account = $this->selectedAccount($request)) {
            $service = $service->forAccount($account);
        }
        $result = $service->getPhoneCallSettings();

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function updateCallSettings(Request $request, MetaWabaApiService $service)
    {
        $account = $this->selectedAccount($request);
        if ($account) {
            $service = $service->forAccount($account);
        }
        $validated = $request->validate([
            'voice_calls_enabled' => 'nullable|boolean',
            'display_call_buttons' => 'nullable|boolean',
            'callbacks_enabled' => 'nullable|boolean',
            'call_hours' => 'nullable|string|max:2000',
            'call_pause_until' => 'nullable|date',
        ]);

        $callHours = null;
        if (filled($validated['call_hours'] ?? null)) {
            $decoded = json_decode((string) $validated['call_hours'], true);
            $callHours = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : ['note' => (string) $validated['call_hours']];
        }

        $result = $service->savePhoneCallPreferences([
            'voice_calls_enabled' => $request->boolean('voice_calls_enabled'),
            'display_call_buttons' => $request->boolean('display_call_buttons'),
            'callbacks_enabled' => $request->boolean('callbacks_enabled'),
            'call_hours' => $callHours,
            'call_pause_until' => $validated['call_pause_until'] ?? null,
        ]);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function setDefault(Request $request)
    {
        $account = $this->selectedAccount($request);
        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Select a valid WABA account.'], 422);
        }

        $this->makeDefaultAccount($account);
        SystemSettings::set('whatsapp_default_sender', 'meta_waba');

        return response()->json([
            'success' => true,
            'message' => ($account->display_phone_number ?: $account->name ?: 'WABA account') . ' is now the default Meta sender.',
        ]);
    }

    private function selectedAccount(Request $request): ?MetaWabaAccount
    {
        $accountId = $request->input('meta_waba_account_id') ?: $request->query('account_id');
        if (!$accountId) {
            return null;
        }

        return MetaWabaAccount::query()->find((int) $accountId);
    }

    private function routePrefix(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'ad-manager.meta-waba.')
            ? 'ad-manager.meta-waba.'
            : 'integrations.meta-waba.';
    }

    private function normalizeMetaTestPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?: '';

        return strlen($digits) === 10 ? '91' . $digits : $digits;
    }

    private function combineMetaTestPhone(?string $phone, ?string $countryCode): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?: '';
        if ($digits === '') {
            return null;
        }

        $code = preg_replace('/[^0-9]/', '', (string) $countryCode) ?: '91';
        if (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 6) {
            return $digits;
        }

        return $code . ltrim($digits, '0');
    }

    private function makeDefaultAccount(MetaWabaAccount $account): void
    {
        MetaWabaAccount::query()->whereKeyNot($account->id)->update(['is_default' => false]);
        $account->forceFill(['is_default' => true, 'is_active' => true])->save();
        $account->fresh()->syncToSettings();
    }

    private function decodeEmbeddedSignupResponse(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return json_last_error() === JSON_ERROR_NONE ? (array) $decoded : ['raw' => $raw];
        }

        return [];
    }

    private function exchangeEmbeddedSignupCode(MetaWabaSettings $settings, string $code): array
    {
        try {
            $response = Http::timeout(20)->get("https://graph.facebook.com/{$settings->graph_version}/oauth/access_token", [
                'client_id' => $settings->meta_app_id,
                'client_secret' => $settings->app_secret,
                'code' => $code,
            ]);

            $data = $response->json();
            if ($response->successful() && filled(data_get($data, 'access_token'))) {
                return ['success' => true, 'data' => $data];
            }

            return [
                'success' => false,
                'error' => data_get($data, 'error.message') ?: 'Meta did not return an access token.',
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::warning('Meta Embedded Signup token exchange failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function friendlyMetaError(string $error): string
    {
        $lower = strtolower($error);
        if (str_contains($lower, 'session has been invalidated')
            || str_contains($lower, 'error validating access token')
            || str_contains($lower, 'password')
            || str_contains($lower, 'security reasons')) {
            return 'Meta access token invalid ho gaya hai. Facebook password/security change ke baad purana token revoke ho jata hai. API Settings me new token save/reconnect karo, Verify click karo, phir Sync Templates chalao.';
        }
        if (str_contains($lower, 'pin') && str_contains($lower, 'required')) {
            return 'Meta needs the 6-digit two-step verification PIN. Enter the same PIN from WhatsApp Manager and try again.';
        }
        if (str_contains($lower, 'too many') || str_contains($lower, 'temporarily')) {
            return 'Meta has temporarily blocked registration attempts. Wait before trying again, then use the correct 6-digit PIN.';
        }
        if (str_contains($error, '#200') || str_contains($lower, 'permission')) {
            return 'Permission error #200: make sure this app/system user has full WhatsApp Account access and the token includes whatsapp_business_management and whatsapp_business_messaging.';
        }
        if (str_contains($lower, 'expired') || str_contains($lower, 'invalid token')) {
            return 'Access token is expired or invalid. Generate a new token, save WABA, then verify again.';
        }

        return $error;
    }
}
