<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\IgApiLog;
use App\Models\IgAutomationRule;
use App\Models\IgConversation;
use App\Models\IgDmFlow;
use App\Models\IgDmFlowStep;
use App\Models\IgWebhookEvent;
use App\Models\InstagramAccount;
use App\Services\Instagram\InstagramAutomationSettings;
use App\Services\Instagram\InstagramOAuthService;
use App\Services\Instagram\InstagramReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstagramAutomationController extends Controller
{
    public function __construct(
        private readonly InstagramOAuthService $oauthService,
        private readonly InstagramAutomationSettings $automationSettings,
        private readonly InstagramReadinessService $readinessService,
    )
    {
    }

    public function index(Request $request): View
    {
        $configStatus = $this->oauthService->configStatus();

        $tab = $request->string('tab')->toString() ?: 'accounts';

        if (!array_key_exists($tab, $this->tabs())) {
            $tab = 'accounts';
        }

        $webhookLogs = IgWebhookEvent::query()
            ->with('instagramAccount')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('event_type'), fn ($query) => $query->where('event_type', $request->string('event_type')->toString()))
            ->when($request->filled('account_id'), fn ($query) => $query->where('instagram_account_id', $request->integer('account_id')))
            ->latest('received_at')
            ->latest()
            ->paginate(15, ['*'], 'webhook_page')
            ->withQueryString();

        return view('admin.instagram-automation.index', [
            'tab' => $tab,
            'tabs' => $this->tabs(),
            'routeBase' => $this->routeBase($request),
            'accounts' => InstagramAccount::query()->with(['connectedBy'])->withCount(['automationRules', 'conversations'])->latest()->paginate(15, ['*'], 'accounts_page'),
            'rules' => IgAutomationRule::query()->with(['instagramAccount', 'dmFlow'])->orderBy('priority')->latest()->paginate(15, ['*'], 'rules_page'),
            'flows' => IgDmFlow::query()->with(['steps'])->withCount(['steps', 'automationRules'])->latest()->paginate(15, ['*'], 'flows_page'),
            'accountOptions' => InstagramAccount::query()->orderBy('ig_username')->get(['id', 'ig_username', 'ig_user_id', 'status']),
            'flowOptions' => IgDmFlow::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'conversations' => IgConversation::query()
                ->with(['instagramAccount', 'lead', 'duplicateLead', 'automationRule', 'state'])
                ->latest()
                ->paginate(15, ['*'], 'conversations_page'),
            'webhookLogs' => $webhookLogs,
            'apiLogs' => IgApiLog::query()->with('instagramAccount')->latest()->limit(20)->get(),
            'logFilters' => [
                'status' => $request->string('status')->toString(),
                'event_type' => $request->string('event_type')->toString(),
                'account_id' => $request->string('account_id')->toString(),
            ],
            'settings' => $this->automationSettings->all(),
            'readiness' => $this->readinessService->report(),
            'configStatus' => $configStatus,
            'isConfigured' => $this->oauthService->isConfigured(),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        if (!$this->oauthService->isConfigured()) {
            return redirect()
                ->route($this->routeBase($request) . '.index', ['tab' => 'settings'])
                ->with('error', 'Instagram app credentials are not configured.');
        }

        $state = $this->oauthService->makeState();
        session([
            'instagram_oauth_state' => $state,
            'instagram_oauth_return_route_base' => $this->routeBase($request),
        ]);

        return redirect()->away($this->oauthService->buildAuthorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $routeBase = in_array(session('instagram_oauth_return_route_base'), ['admin.instagram-automation', 'crm.instagram-automation', 'ad-manager.meta.instagram'], true)
            ? session('instagram_oauth_return_route_base')
            : $this->routeBase($request);

        if ($request->filled('error')) {
            $errorDescription = $request->string('error_description')->toString()
                ?: $request->string('error')->toString();

            return redirect()
                ->route($routeBase . '.index', ['tab' => 'accounts'])
                ->with('error', 'Instagram connection cancelled or denied: ' . $errorDescription);
        }

        if (!$request->filled('code')) {
            return redirect()
                ->route($routeBase . '.index', ['tab' => 'accounts'])
                ->with('error', 'Instagram callback did not include an authorization code.');
        }

        if (!hash_equals((string) session('instagram_oauth_state'), (string) $request->query('state'))) {
            return redirect()
                ->route($routeBase . '.index', ['tab' => 'accounts'])
                ->with('error', 'Instagram connection failed because the OAuth state was invalid.');
        }

        session()->forget(['instagram_oauth_state', 'instagram_oauth_return_route_base']);

        $result = $this->oauthService->connectFromCode($request->string('code')->toString(), (int) auth()->id());

        if (!($result['success'] ?? false)) {
            return redirect()
                ->route($routeBase . '.index', ['tab' => 'accounts'])
                ->with('error', $result['error'] ?? 'Instagram connection failed.');
        }

        $account = $result['account'];

        return redirect()
            ->route($routeBase . '.index', ['tab' => 'accounts'])
            ->with('success', 'Instagram account @' . ($account->ig_username ?: $account->ig_user_id) . ' connected.');
    }

    public function disconnect(Request $request, InstagramAccount $account): RedirectResponse
    {
        $this->auditAdManagerAction('ad_manager_instagram_account_disconnected', $request, ['account_id' => $account->id]);

        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'status' => 'disconnected',
        ]);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'accounts'])
            ->with('success', 'Instagram account disconnected. Rules and conversation history were kept.');
    }

    public function refreshToken(Request $request, InstagramAccount $account): RedirectResponse
    {
        $this->auditAdManagerAction('ad_manager_instagram_token_refreshed', $request, ['account_id' => $account->id]);

        $result = $this->oauthService->refreshAccountToken($account);

        if (!($result['success'] ?? false)) {
            return redirect()
                ->route($this->routeBase($request) . '.index', ['tab' => 'accounts'])
                ->with('error', $result['error'] ?? 'Instagram token refresh failed.');
        }

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'accounts'])
            ->with('success', 'Instagram token refreshed successfully.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $rule = IgAutomationRule::query()->create($this->rulePayload($request) + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $this->auditAdManagerAction('ad_manager_instagram_rule_created', $request, ['rule_id' => $rule->id]);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'automations'])
            ->with('success', 'Instagram automation rule created.');
    }

    public function updateRule(Request $request, IgAutomationRule $rule): RedirectResponse
    {
        $rule->update($this->rulePayload($request) + [
            'updated_by' => auth()->id(),
        ]);
        $this->auditAdManagerAction('ad_manager_instagram_rule_updated', $request, ['rule_id' => $rule->id]);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'automations'])
            ->with('success', 'Instagram automation rule updated.');
    }

    public function toggleRule(Request $request, IgAutomationRule $rule): RedirectResponse
    {
        $nextActive = !$rule->is_active;
        $rule->update([
            'is_active' => $nextActive,
            'status' => $nextActive ? 'active' : 'paused',
            'updated_by' => auth()->id(),
        ]);
        $this->auditAdManagerAction('ad_manager_instagram_rule_toggled', $request, ['rule_id' => $rule->id, 'is_active' => $rule->is_active]);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'automations'])
            ->with('success', 'Instagram automation rule ' . ($nextActive ? 'activated.' : 'paused.'));
    }

    public function storeFlow(Request $request): RedirectResponse
    {
        $flowId = null;
        DB::transaction(function () use ($request, &$flowId) {
            $flow = IgDmFlow::query()->create($this->flowPayload($request) + [
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncFlowSteps($flow, $request);
            $flowId = $flow->id;
        });
        $this->auditAdManagerAction('ad_manager_instagram_flow_created', $request, ['flow_id' => $flowId]);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'dm-flows'])
            ->with('success', 'Instagram DM flow created.');
    }

    public function updateFlow(Request $request, IgDmFlow $flow): RedirectResponse
    {
        DB::transaction(function () use ($request, $flow) {
            $flow->update($this->flowPayload($request) + [
                'updated_by' => auth()->id(),
            ]);

            $this->syncFlowSteps($flow, $request);
        });
        $this->auditAdManagerAction('ad_manager_instagram_flow_updated', $request, ['flow_id' => $flow->id]);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'dm-flows'])
            ->with('success', 'Instagram DM flow updated.');
    }

    public function toggleFlow(Request $request, IgDmFlow $flow): RedirectResponse
    {
        $nextActive = !$flow->is_active;
        $flow->update([
            'is_active' => $nextActive,
            'status' => $nextActive ? 'active' : 'paused',
            'updated_by' => auth()->id(),
        ]);
        $this->auditAdManagerAction('ad_manager_instagram_flow_toggled', $request, ['flow_id' => $flow->id, 'is_active' => $flow->is_active]);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'dm-flows'])
            ->with('success', 'Instagram DM flow ' . ($nextActive ? 'activated.' : 'paused.'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'duplicate_days_limit' => ['required', 'integer', 'min:1', 'max:365'],
            'invalid_phone_retry_message' => ['required', 'string', 'max:1000'],
            'max_phone_retries' => ['required', 'integer', 'min:1', 'max:10'],
            'default_final_message' => ['required', 'string', 'max:1000'],
        ]);

        $this->automationSettings->save($validated + [
            'duplicate_check_enabled' => $request->boolean('duplicate_check_enabled'),
        ], auth()->id());
        $this->auditAdManagerAction('ad_manager_instagram_settings_updated', $request);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'settings'])
            ->with('success', 'Instagram automation settings updated.');
    }

    public function takeOverConversation(Request $request, IgConversation $conversation): RedirectResponse
    {
        $this->auditAdManagerAction('ad_manager_instagram_conversation_takeover', $request, ['conversation_id' => $conversation->id]);

        $conversation->update([
            'is_human_taken_over' => true,
            'human_taken_over_by' => auth()->id(),
            'human_taken_over_at' => now(),
            'status' => $conversation->status === 'completed' ? 'completed' : 'needs_human',
        ]);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'conversations'])
            ->with('success', 'Conversation moved to human takeover.');
    }

    public function resumeConversation(Request $request, IgConversation $conversation): RedirectResponse
    {
        $this->auditAdManagerAction('ad_manager_instagram_conversation_resumed', $request, ['conversation_id' => $conversation->id]);

        $conversation->update([
            'is_human_taken_over' => false,
            'human_taken_over_by' => null,
            'human_taken_over_at' => null,
            'status' => $conversation->completed_at ? 'completed' : 'open',
        ]);

        return redirect()
            ->route($this->routeBase($request) . '.index', ['tab' => 'conversations'])
            ->with('success', 'Conversation automation resumed.');
    }

    private function tabs(): array
    {
        return [
            'accounts' => 'Accounts',
            'automations' => 'Automations',
            'dm-flows' => 'DM Flows',
            'conversations' => 'Conversations',
            'logs' => 'Logs',
            'settings' => 'Settings',
        ];
    }

    private function routeBase(Request $request): string
    {
        if (str_starts_with((string) $request->route()?->getName(), 'ad-manager.meta.instagram.')) {
            return 'ad-manager.meta.instagram';
        }

        return str_starts_with((string) $request->route()?->getName(), 'crm.')
            ? 'crm.instagram-automation'
            : 'admin.instagram-automation';
    }

    private function auditAdManagerAction(string $action, Request $request, array $metadata = []): void
    {
        if (!str_starts_with((string) $request->route()?->getName(), 'ad-manager.meta.instagram.')) {
            return;
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => 'Ad Manager Instagram Ops action: ' . $action,
            'new_values' => array_merge($metadata, [
                'route' => optional($request->route())->getName(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function rulePayload(Request $request): array
    {
        $validated = $request->validate([
            'instagram_account_id' => ['required', 'exists:instagram_accounts,id'],
            'name' => ['required', 'string', 'max:255'],
            'media_id' => ['nullable', 'string', 'max:255'],
            'keywords' => ['required', 'string', 'max:1000'],
            'public_reply_message' => ['required', 'string', 'max:1000'],
            'dm_flow_id' => ['nullable', 'exists:ig_dm_flows,id'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:999'],
            'status' => ['required', Rule::in(['draft', 'active', 'paused'])],
        ]);

        $keywords = collect(explode(',', $validated['keywords']))
            ->map(fn ($keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();

        if (empty($keywords)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'keywords' => 'At least one keyword is required.',
            ]);
        }

        return [
            'instagram_account_id' => (int) $validated['instagram_account_id'],
            'name' => $validated['name'],
            'media_id' => $validated['media_id'] ?: null,
            'keywords' => $keywords,
            'public_reply_message' => $validated['public_reply_message'],
            'dm_flow_id' => $validated['dm_flow_id'] ?? null,
            'priority' => (int) ($validated['priority'] ?? 100),
            'status' => $validated['status'],
            'is_active' => $request->boolean('is_active') && $validated['status'] === 'active',
        ];
    }

    private function flowPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'active', 'paused'])],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'is_active' => $request->boolean('is_active') && $validated['status'] === 'active',
        ];
    }

    private function syncFlowSteps(IgDmFlow $flow, Request $request): void
    {
        $steps = collect($request->input('steps', []))
            ->map(function ($step, $index) {
                return [
                    'step_order' => (int) ($step['step_order'] ?? ($index + 1)),
                    'message_text' => trim((string) ($step['message_text'] ?? '')),
                    'save_reply_as' => trim((string) ($step['save_reply_as'] ?? '')) ?: null,
                ];
            })
            ->filter(fn ($step) => $step['message_text'] !== '')
            ->sortBy('step_order')
            ->values();

        if ($steps->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'steps' => 'At least one text step is required.',
            ]);
        }

        $flow->steps()->delete();

        foreach ($steps as $index => $step) {
            IgDmFlowStep::query()->create([
                'flow_id' => $flow->id,
                'step_order' => $index + 1,
                'message_text' => $step['message_text'],
                'save_reply_as' => $step['save_reply_as'],
                'message_type' => 'text',
            ]);
        }
    }
}
