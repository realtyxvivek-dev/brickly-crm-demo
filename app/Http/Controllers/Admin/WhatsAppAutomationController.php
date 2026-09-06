<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\MetaWabaAccount;
use App\Models\Project;
use App\Models\WhatsAppAutomationJourney;
use App\Models\WhatsAppAutomationLog;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppAutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WhatsAppAutomationController extends Controller
{
    public function __construct(
        private readonly WhatsAppAutomationService $automationService
    ) {
    }

    public function index(Request $request): View
    {
        $this->automationService->syncPresets(auth()->id());

        $tab = $request->string('tab')->toString() ?: 'overview';
        $routeBase = $this->routeBase($request);

        return view('admin.whatsapp-automation.index', [
            'tab' => $tab,
            'routeBase' => $routeBase,
            'overview' => $this->automationService->overviewMetrics(),
            'analytics' => $this->automationService->analytics(),
            'journeys' => WhatsAppAutomationJourney::query()->withCount(['rules', 'logs'])->with('template')->orderByDesc('is_active')->orderBy('name')->get(),
            'rules' => WhatsAppAutomationRule::query()->with(['journey', 'template'])->orderBy('priority')->orderBy('name')->get(),
            'logs' => WhatsAppAutomationLog::query()->with(['journey', 'rule', 'lead'])->latest('created_at')->paginate(20)->withQueryString(),
            'templates' => $this->automationService->templatesCatalog(),
            'availableSources' => $this->automationService->availableSources(),
            'availableProjects' => $this->automationService->availableProjects(),
            'availableTriggers' => $this->automationService->availableTriggers(),
            'variableCatalog' => $this->automationService->variableCatalog(),
        ]);
    }

    public function createJourney(Request $request): View
    {
        $this->automationService->syncPresets(auth()->id());

        return view('admin.whatsapp-automation.journey-form', [
            'routeBase' => $this->routeBase($request),
            'journey' => new WhatsAppAutomationJourney([
                'status' => 'draft',
                'is_active' => false,
                'test_mode' => false,
            ]),
            'templates' => WhatsAppTemplate::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeJourney(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_templates,id',
            'status' => ['required', Rule::in(['draft', 'active', 'paused'])],
            'is_active' => 'nullable|boolean',
            'test_mode' => 'nullable|boolean',
        ]);

        $journey = WhatsAppAutomationJourney::query()->create([
            'key' => 'custom-' . Str::slug($validated['name']) . '-' . Str::lower(Str::random(6)),
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::lower(Str::random(4)),
            'category' => $validated['category'] ?? 'custom',
            'description' => $validated['description'] ?? null,
            'template_id' => $validated['template_id'] ?? null,
            'status' => $validated['status'],
            'is_active' => $request->boolean('is_active'),
            'test_mode' => $request->boolean('test_mode'),
            'is_preset' => false,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route($this->routeBase($request) . '.index', ['tab' => 'journeys'])->with('success', "Journey '{$journey->name}' created.");
    }

    public function editJourney(Request $request, WhatsAppAutomationJourney $journey): View
    {
        return view('admin.whatsapp-automation.journey-form', [
            'routeBase' => $this->routeBase($request),
            'journey' => $journey,
            'templates' => WhatsAppTemplate::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateJourney(Request $request, WhatsAppAutomationJourney $journey): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'template_id' => 'nullable|exists:whatsapp_templates,id',
            'status' => ['required', Rule::in(['draft', 'active', 'paused'])],
            'is_active' => 'nullable|boolean',
            'test_mode' => 'nullable|boolean',
        ]);

        $journey->update([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? $journey->category,
            'description' => $validated['description'] ?? null,
            'template_id' => $validated['template_id'] ?? null,
            'status' => $validated['status'],
            'is_active' => $request->boolean('is_active'),
            'test_mode' => $request->boolean('test_mode'),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route($this->routeBase($request) . '.index', ['tab' => 'journeys'])->with('success', "Journey '{$journey->name}' updated.");
    }

    public function toggleJourney(Request $request, WhatsAppAutomationJourney $journey): RedirectResponse
    {
        $nextActive = !$journey->is_active;
        $journey->update([
            'is_active' => $nextActive,
            'status' => $nextActive ? 'active' : 'paused',
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route($this->routeBase($request) . '.index', ['tab' => 'journeys'])->with('success', "Journey '{$journey->name}' " . ($nextActive ? 'activated' : 'paused') . '.');
    }

    public function cloneJourney(Request $request, WhatsAppAutomationJourney $journey): RedirectResponse
    {
        $copy = $journey->replicate(['key', 'slug', 'is_active', 'status', 'created_by', 'updated_by']);
        $copy->key = 'custom-' . Str::slug($journey->name) . '-' . Str::lower(Str::random(6));
        $copy->slug = Str::slug($journey->name . ' copy') . '-' . Str::lower(Str::random(4));
        $copy->name = $journey->name . ' Copy';
        $copy->is_active = false;
        $copy->status = 'draft';
        $copy->created_by = auth()->id();
        $copy->updated_by = auth()->id();
        $copy->save();

        foreach ($journey->rules as $rule) {
            $newRule = $rule->replicate(['created_by', 'updated_by', 'status', 'is_active']);
            $newRule->journey_id = $copy->id;
            $newRule->status = 'draft';
            $newRule->is_active = false;
            $newRule->created_by = auth()->id();
            $newRule->updated_by = auth()->id();
            $newRule->save();
        }

        return redirect()->route($this->routeBase($request) . '.index', ['tab' => 'journeys'])->with('success', "Journey '{$journey->name}' cloned.");
    }

    public function createRule(Request $request): View
    {
        $this->automationService->syncPresets(auth()->id());

        return view('admin.whatsapp-automation.rule-form', [
            'routeBase' => $this->routeBase($request),
            'rule' => new WhatsAppAutomationRule([
                'status' => 'draft',
                'is_active' => false,
                'test_mode' => false,
                'priority' => 100,
                'once_per_lead' => true,
                'resend_cap' => 1,
                'daily_send_cap' => 3,
                'cooldown_minutes' => 0,
                'requires_session_window' => false,
                'send_timing' => ['mode' => 'immediate'],
                'quiet_hour_policy' => WhatsAppAutomationService::DEFAULT_QUIET_POLICY,
                'conditions' => [],
                'variable_map' => [],
            ]),
            'journeys' => WhatsAppAutomationJourney::query()->orderBy('name')->get(['id', 'name']),
            'templates' => WhatsAppTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'availableTriggers' => $this->automationService->availableTriggers(),
            'availableSources' => $this->automationService->availableSources(),
            'availableProjects' => $this->automationService->availableProjects(),
            'variableCatalog' => $this->automationService->variableCatalog(),
            'accounts' => MetaWabaAccount::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'lead' => Lead::query()->latest('id')->first(),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $rule = WhatsAppAutomationRule::query()->create($this->validateRule($request) + [
            'is_active' => $request->boolean('is_active'),
            'test_mode' => $request->boolean('test_mode'),
            'once_per_lead' => $request->boolean('once_per_lead', true),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route($this->routeBase($request) . '.index', ['tab' => 'rules'])->with('success', "Rule '{$rule->name}' created.");
    }

    public function editRule(Request $request, WhatsAppAutomationRule $rule): View
    {
        return view('admin.whatsapp-automation.rule-form', [
            'routeBase' => $this->routeBase($request),
            'rule' => $rule,
            'journeys' => WhatsAppAutomationJourney::query()->orderBy('name')->get(['id', 'name']),
            'templates' => WhatsAppTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'availableTriggers' => $this->automationService->availableTriggers(),
            'availableSources' => $this->automationService->availableSources(),
            'availableProjects' => $this->automationService->availableProjects(),
            'variableCatalog' => $this->automationService->variableCatalog(),
            'accounts' => MetaWabaAccount::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'lead' => Lead::query()->latest('id')->first(),
        ]);
    }

    public function updateRule(Request $request, WhatsAppAutomationRule $rule): RedirectResponse
    {
        $rule->update($this->validateRule($request) + [
            'is_active' => $request->boolean('is_active'),
            'test_mode' => $request->boolean('test_mode'),
            'once_per_lead' => $request->boolean('once_per_lead', true),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route($this->routeBase($request) . '.index', ['tab' => 'rules'])->with('success', "Rule '{$rule->name}' updated.");
    }

    public function toggleRule(Request $request, WhatsAppAutomationRule $rule): RedirectResponse
    {
        $nextActive = !$rule->is_active;
        $rule->update([
            'is_active' => $nextActive,
            'status' => $nextActive ? 'active' : 'paused',
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route($this->routeBase($request) . '.index', ['tab' => 'rules'])->with('success', "Rule '{$rule->name}' " . ($nextActive ? 'activated' : 'paused') . '.');
    }

    public function previewRule(Request $request): JsonResponse
    {
        $rule = new WhatsAppAutomationRule([
            'variable_map' => $request->input('variable_map', []),
        ]);
        $rule->setRelation('template', WhatsAppTemplate::find($request->input('template_id')));

        $lead = Lead::query()->with('activeAssignments.assignedTo.role')->latest('id')->first();
        $project = $request->filled('project_id') ? Project::query()->find($request->integer('project_id')) : null;

        return response()->json($this->automationService->previewRule($rule, [
            'lead' => $lead,
            'project_name' => $project?->name,
            'project_id' => $project?->id,
        ]));
    }

    protected function validateRule(Request $request): array
    {
        $validated = $request->validate([
            'journey_id' => 'required|exists:whatsapp_automation_journeys,id',
            'name' => 'required|string|max:255',
            'trigger' => ['required', Rule::in(array_keys($this->automationService->availableTriggers()))],
            'status' => ['required', Rule::in(['draft', 'active', 'paused'])],
            'priority' => 'required|integer|min:1|max:999',
            'template_id' => 'nullable|exists:whatsapp_templates,id',
            'meta_waba_account_id' => 'nullable|integer|exists:meta_waba_accounts,id',
            'resend_cap' => 'required|integer|min:1|max:25',
            'cooldown_minutes' => 'nullable|integer|min:0|max:10080',
            'daily_send_cap' => 'nullable|integer|min:1|max:50',
            'requires_session_window' => 'nullable|boolean',
            'fallback_action' => 'nullable|string|max:100',
            'conditions' => 'nullable|array',
            'conditions.source_mode' => 'nullable|in:exact,in_list,except',
            'conditions.source_values' => 'nullable|array',
            'conditions.source_values.*' => 'string|max:100',
            'conditions.project_ids' => 'nullable|array',
            'conditions.project_ids.*' => 'integer|exists:projects,id',
            'conditions.cities' => 'nullable|array',
            'conditions.cities.*' => 'string|max:100',
            'conditions.lead_statuses' => 'nullable|array',
            'conditions.lead_statuses.*' => 'string|max:100',
            'conditions.assigned_user_ids' => 'nullable|array',
            'conditions.assigned_user_ids.*' => 'integer|exists:users,id',
            'conditions.assigned_role_slugs' => 'nullable|array',
            'conditions.assigned_role_slugs.*' => 'string|max:100',
            'variable_map' => 'nullable|array',
            'variable_map.*' => ['nullable', Rule::in(array_keys($this->automationService->variableCatalog()))],
            'send_timing' => 'nullable|array',
            'send_timing.mode' => 'nullable|in:immediate,delay',
            'send_timing.value' => 'nullable|integer|min:0|max:10000',
            'send_timing.unit' => 'nullable|in:minutes,hours,days',
            'quiet_hour_policy' => 'nullable|array',
            'quiet_hour_policy.enabled' => 'nullable|boolean',
            'quiet_hour_policy.start' => 'nullable|date_format:H:i',
            'quiet_hour_policy.end' => 'nullable|date_format:H:i',
            'stop_statuses' => 'nullable|array',
            'stop_statuses.*' => 'string|max:100',
        ]);

        $validated['conditions'] = $validated['conditions'] ?? [];
        $validated['variable_map'] = collect($validated['variable_map'] ?? [])->filter(fn ($value) => filled($value))->all();
        $validated['send_timing'] = $validated['send_timing'] ?? ['mode' => 'immediate'];
        $validated['quiet_hour_policy'] = array_merge(WhatsAppAutomationService::DEFAULT_QUIET_POLICY, $validated['quiet_hour_policy'] ?? []);
        $validated['stop_statuses'] = $validated['stop_statuses'] ?? ['dead', 'closed', 'junk', 'not_interested'];
        $validated['cooldown_minutes'] = (int) ($validated['cooldown_minutes'] ?? 0);
        $validated['daily_send_cap'] = (int) ($validated['daily_send_cap'] ?? 3);
        $validated['requires_session_window'] = $request->boolean('requires_session_window');

        return $validated;
    }

    protected function routeBase(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'crm.')
            ? 'crm.whatsapp-automation'
            : 'admin.whatsapp-automation';
    }
}
