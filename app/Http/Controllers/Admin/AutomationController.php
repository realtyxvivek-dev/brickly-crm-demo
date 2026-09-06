<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsmCnpAutomationConfig;
use App\Models\FbForm;
use App\Models\GoogleSheetsConfig;
use App\Models\Role;
use App\Models\SourceAutomationRule;
use App\Models\SourceAutomationRuleForm;
use App\Models\SourceAutomationRuleUser;
use App\Models\User;
use App\Services\WhatsAppLeadAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AutomationController extends Controller
{
    public function index()
    {
        $with = ['users.user', 'fbForm', 'singleUser', 'fallbackUser'];
        if ($this->hasRuleFormsTable()) {
            $with[] = 'fbForms';
        }

        $rules = SourceAutomationRule::with($with)
            ->latest()
            ->get();

        $ninetyNineAcresRule = $rules
            ->first(fn(SourceAutomationRule $rule) => $rule->source === '99acres'
                && is_null($rule->fb_form_id)
                && is_null($rule->google_sheet_config_id));

        $ninetyNineAcresStats = $this->buildNinetyNineAcresStats($ninetyNineAcresRule);
        $rules = $rules
            ->reject(fn(SourceAutomationRule $rule) => $rule->source === '99acres'
                && is_null($rule->fb_form_id)
                && is_null($rule->google_sheet_config_id))
            ->values();

        $asmCnpAvailable =
            Schema::hasTable('asm_cnp_automation_configs') &&
            Schema::hasTable('asm_cnp_automation_pool_users') &&
            Schema::hasTable('asm_cnp_automation_user_overrides');

        $asmCnpConfig = null;
        if ($asmCnpAvailable) {
            $asmCnpConfig = AsmCnpAutomationConfig::query()
                ->withCount(['poolUsers', 'overrides'])
                ->first();
        }

        $whatsAppAutomationService = app(WhatsAppLeadAutomationService::class);
        $whatsAppAutomationSettings = $whatsAppAutomationService->settings();
        $whatsAppEligibleUsers = $whatsAppAutomationService->eligibleUsers();

        return view('admin.automation.index', compact(
            'rules',
            'asmCnpConfig',
            'asmCnpAvailable',
            'whatsAppAutomationSettings',
            'whatsAppEligibleUsers',
            'ninetyNineAcresRule',
            'ninetyNineAcresStats'
        ));
    }

    public function create()
    {
        $fbForms      = FbForm::with('page')->where('is_enabled', true)->get();
        $googleSheets = GoogleSheetsConfig::where('is_active', true)->where('is_draft', false)->orderBy('sheet_name')->get();
        $assignableUsers = $this->getAssignableUsers();
        $prefill = $this->wizardPrefill(request());

        return view('admin.automation.form', compact('fbForms', 'googleSheets', 'assignableUsers', 'prefill'));
    }

    public function editNinetyNineAcres()
    {
        $rule = SourceAutomationRule::with(['users.user', 'singleUser', 'fallbackUser'])
            ->where('source', '99acres')
            ->whereNull('fb_form_id')
            ->whereNull('google_sheet_config_id')
            ->latest()
            ->first();

        $assignableUsers = $this->getAssignableUsers();
        $assignmentQuery = $this->ninetyNineAcresAssignmentsQuery($rule);
        $recentAssignments = $assignmentQuery
            ? $assignmentQuery->with(['lead:id,name,phone,source', 'assignedTo:id,name'])
                ->latest('assigned_at')
                ->limit(12)
                ->get()
            : collect();

        return view('admin.automation.99acres', compact('rule', 'assignableUsers', 'recentAssignments'));
    }

    public function updateNinetyNineAcres(Request $request)
    {
        $assignableUserIds = $this->getAssignableUsers()->pluck('id')->all();

        $data = $request->validate([
            'assignment_method'       => 'required|in:round_robin,first_available,percentage,single_user',
            'single_user_id'          => ['nullable', 'required_if:assignment_method,single_user', Rule::in($assignableUserIds)],
            'auto_create_task'        => 'boolean',
            'daily_limit'             => 'nullable|integer|min:1',
            'fallback_user_id'        => ['nullable', Rule::in($assignableUserIds)],
            'is_active'               => 'boolean',
            'users'                   => 'nullable|array',
            'users.*.user_id'         => ['nullable', 'required_unless:assignment_method,single_user', Rule::in($assignableUserIds)],
            'users.*.percentage'      => 'nullable|numeric|min:0|max:100',
            'users.*.daily_limit'     => 'nullable|integer|min:1',
        ]);

        $this->validateAutomationUsers($data);

        DB::transaction(function () use ($request, $data) {
            $existingRules = SourceAutomationRule::where('source', '99acres')
                ->whereNull('fb_form_id')
                ->whereNull('google_sheet_config_id')
                ->latest()
                ->get();

            $existingRules
                ->skip(1)
                ->each(fn(SourceAutomationRule $rule) => $rule->update(['is_active' => false]));

            $rule = $existingRules->first();

            $payload = $this->sourceAutomationRulePayload([
                'name'                   => '99acres Lead Distribution',
                'source'                 => '99acres',
                'source_type'            => '99acres',
                'source_id'              => null,
                'source_label'           => '99acres Lead Distribution',
                'fb_form_id'             => null,
                'google_sheet_config_id' => null,
                'assignment_method'      => $data['assignment_method'],
                'distribution_method'    => $data['assignment_method'],
                'single_user_id'         => $data['assignment_method'] === 'single_user' ? ($data['single_user_id'] ?? null) : null,
                'auto_create_task'       => $request->boolean('auto_create_task', true),
                'task_enabled'           => $request->boolean('auto_create_task', true),
                'notification_enabled'   => true,
                'daily_limit'            => $data['daily_limit'] ?? null,
                'fallback_user_id'       => $data['fallback_user_id'] ?? null,
                'skip_lead_off_users'    => true,
                'duplicate_handling'     => 'keep_existing_owner_mark_reenquiry',
                'is_active'              => $request->boolean('is_active'),
                'created_by'             => auth()->id(),
            ]);

            if ($rule) {
                unset($payload['created_by']);
                $rule->update($payload);
            } else {
                $rule = SourceAutomationRule::create($payload);
            }

            $rule->users()->delete();

            if ($data['assignment_method'] !== 'single_user') {
                foreach ($this->normalizedRuleUsers($data['users'] ?? []) as $user) {
                    SourceAutomationRuleUser::create([
                        'rule_id' => $rule->id,
                        'user_id' => $user['user_id'],
                        'percentage' => $data['assignment_method'] === 'percentage' ? ($user['percentage'] ?? 0) : null,
                        'daily_limit' => $user['daily_limit'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('admin.automation.99acres.edit')
            ->with('success', '99acres lead distribution updated successfully.');
    }

    public function store(Request $request)
    {
        $assignableUserIds = $this->getAssignableUsers()->pluck('id')->all();
        $request->merge([
            'assignment_method' => $request->input('distribution_method', $request->input('assignment_method')),
        ]);

        $data = $request->validate([
            'name'              => 'nullable|string|max:255',
            'source_type'             => ['nullable', Rule::in($this->sourceAutomationSources())],
            'source_id'               => 'nullable|string|max:120',
            'source_label'            => 'nullable|string|max:255',
            'source'                  => ['nullable', Rule::in($this->sourceAutomationSources())],
            'fb_form_id'              => 'nullable|exists:fb_forms,id',
            'fb_form_ids'             => 'nullable|array',
            'fb_form_ids.*'           => 'nullable|exists:fb_forms,id',
            'google_sheet_config_id'  => 'nullable|exists:google_sheets_config,id',
            'assignment_method'       => 'nullable|in:round_robin,first_available,percentage,single_user',
            'distribution_method'     => 'nullable|in:round_robin,first_available,percentage,single_user',
            'single_user_id'          => ['nullable', 'required_if:assignment_method,single_user', Rule::in($assignableUserIds)],
            'auto_create_task'        => 'boolean',
            'task_enabled'            => 'boolean',
            'notification_enabled'    => 'boolean',
            'daily_limit'             => 'nullable|integer|min:1',
            'fallback_user_id'        => ['nullable', Rule::in($assignableUserIds)],
            'skip_lead_off_users'     => 'boolean',
            'duplicate_handling'      => 'nullable|in:keep_existing_owner_mark_reenquiry,keep_existing_owner,reassign',
            'is_active'               => 'boolean',
            'users'                   => 'nullable|array',
            'users.*.user_id'         => ['nullable', 'required_unless:assignment_method,single_user', Rule::in($assignableUserIds)],
            'users.*.percentage'      => 'nullable|numeric|min:0|max:100',
            'users.*.daily_limit'     => 'nullable|integer|min:1',
        ], [
            'single_user_id.required_if' => 'Select the user who should receive these leads.',
            'users.*.user_id.required_unless' => 'Select at least one user for this assignment method.',
            'users.*.user_id.in' => 'One of the selected users is not available for lead assignment.',
        ]);

        $data = $this->normalizeWizardPayload($request, $data);
        $data['is_active'] = $request->boolean('is_active', true);
        $this->validateFormConflicts($data);
        $this->validateAutomationUsers($data);

        DB::transaction(function () use ($request, $data, &$rule) {
            $rule = SourceAutomationRule::create($this->sourceAutomationRulePayload([
                'name'                   => $data['name'],
                'source'                 => $data['source'],
                'source_type'            => $data['source_type'],
                'source_id'              => $data['source_id'] ?? null,
                'source_label'           => $data['source_label'] ?? null,
                'fb_form_id'             => $data['source_type'] === 'facebook_lead_ads' ? ($data['fb_form_id'] ?? null) : null,
                'google_sheet_config_id' => $data['source_type'] === 'google_sheets' ? ($data['google_sheet_config_id'] ?? null) : null,
                'assignment_method'      => $data['assignment_method'],
                'distribution_method'    => $data['assignment_method'],
                'single_user_id'    => $data['single_user_id'] ?? null,
                'auto_create_task'  => $request->boolean('task_enabled', $request->boolean('auto_create_task', true)),
                'task_enabled'      => $request->boolean('task_enabled', $request->boolean('auto_create_task', true)),
                'notification_enabled' => $request->boolean('notification_enabled', true),
                'daily_limit'       => $data['daily_limit'] ?? null,
                'fallback_user_id'  => $data['fallback_user_id'] ?? null,
                'skip_lead_off_users' => $request->boolean('skip_lead_off_users', true),
                'duplicate_handling' => $data['duplicate_handling'] ?? 'keep_existing_owner_mark_reenquiry',
                'is_active'         => $request->boolean('is_active', true),
                'created_by'        => auth()->id(),
            ]));

            $this->syncRuleForms($rule, $data);

            if (!empty($data['users']) && $data['assignment_method'] !== 'single_user') {
                foreach ($this->normalizedRuleUsers($data['users']) as $u) {
                    SourceAutomationRuleUser::create([
                        'rule_id'    => $rule->id,
                        'user_id'    => $u['user_id'],
                        'percentage' => $data['assignment_method'] === 'percentage' ? ($u['percentage'] ?? 0) : null,
                        'daily_limit' => $u['daily_limit'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('admin.automation.index')
            ->with('success', 'Automation rule created successfully.');
    }

    public function edit(SourceAutomationRule $rule)
    {
        $with = ['users.user', 'fbForm', 'googleSheetConfig', 'singleUser', 'fallbackUser'];
        if ($this->hasRuleFormsTable()) {
            $with[] = 'fbForms';
        }

        $rule->load($with);
        $fbForms      = FbForm::with('page')->where('is_enabled', true)->get();
        $googleSheets = GoogleSheetsConfig::where('is_active', true)->where('is_draft', false)->orderBy('sheet_name')->get();
        $assignableUsers = $this->getAssignableUsers();
        $prefill = [];

        return view('admin.automation.form', compact('rule', 'fbForms', 'googleSheets', 'assignableUsers', 'prefill'));
    }

    public function update(Request $request, SourceAutomationRule $rule)
    {
        $assignableUserIds = $this->getAssignableUsers()->pluck('id')->all();
        $request->merge([
            'assignment_method' => $request->input('distribution_method', $request->input('assignment_method')),
        ]);

        $data = $request->validate([
            'name'                   => 'nullable|string|max:255',
            'source_type'            => ['nullable', Rule::in($this->sourceAutomationSources())],
            'source_id'              => 'nullable|string|max:120',
            'source_label'           => 'nullable|string|max:255',
            'source'                 => ['nullable', Rule::in($this->sourceAutomationSources())],
            'fb_form_id'             => 'nullable|exists:fb_forms,id',
            'fb_form_ids'            => 'nullable|array',
            'fb_form_ids.*'          => 'nullable|exists:fb_forms,id',
            'google_sheet_config_id' => 'nullable|exists:google_sheets_config,id',
            'assignment_method'      => 'nullable|in:round_robin,first_available,percentage,single_user',
            'distribution_method'    => 'nullable|in:round_robin,first_available,percentage,single_user',
            'single_user_id'         => ['nullable', 'required_if:assignment_method,single_user', Rule::in($assignableUserIds)],
            'auto_create_task'       => 'boolean',
            'task_enabled'           => 'boolean',
            'notification_enabled'   => 'boolean',
            'daily_limit'            => 'nullable|integer|min:1',
            'fallback_user_id'       => ['nullable', Rule::in($assignableUserIds)],
            'skip_lead_off_users'    => 'boolean',
            'duplicate_handling'     => 'nullable|in:keep_existing_owner_mark_reenquiry,keep_existing_owner,reassign',
            'is_active'              => 'boolean',
            'users'                  => 'nullable|array',
            'users.*.user_id'        => ['nullable', 'required_unless:assignment_method,single_user', Rule::in($assignableUserIds)],
            'users.*.percentage'     => 'nullable|numeric|min:0|max:100',
            'users.*.daily_limit'    => 'nullable|integer|min:1',
        ], [
            'single_user_id.required_if' => 'Select the user who should receive these leads.',
            'users.*.user_id.required_unless' => 'Select at least one user for this assignment method.',
            'users.*.user_id.in' => 'One of the selected users is not available for lead assignment.',
        ]);

        $data = $this->normalizeWizardPayload($request, $data);
        $data['is_active'] = $request->boolean('is_active', true);
        $this->validateFormConflicts($data, $rule);
        $this->validateAutomationUsers($data);

        DB::transaction(function () use ($request, $rule, $data) {
            $rule->update($this->sourceAutomationRulePayload([
                'name'                   => $data['name'],
                'source'                 => $data['source'],
                'source_type'            => $data['source_type'],
                'source_id'              => $data['source_id'] ?? null,
                'source_label'           => $data['source_label'] ?? null,
                'fb_form_id'             => $data['source_type'] === 'facebook_lead_ads' ? ($data['fb_form_id'] ?? null) : null,
                'google_sheet_config_id' => $data['source_type'] === 'google_sheets' ? ($data['google_sheet_config_id'] ?? null) : null,
                'assignment_method'      => $data['assignment_method'],
                'distribution_method'    => $data['assignment_method'],
                'single_user_id'         => $data['single_user_id'] ?? null,
                'auto_create_task'       => $request->boolean('task_enabled', $request->boolean('auto_create_task', true)),
                'task_enabled'           => $request->boolean('task_enabled', $request->boolean('auto_create_task', true)),
                'notification_enabled'   => $request->boolean('notification_enabled', true),
                'daily_limit'            => $data['daily_limit'] ?? null,
                'fallback_user_id'       => $data['fallback_user_id'] ?? null,
                'skip_lead_off_users'    => $request->boolean('skip_lead_off_users', true),
                'duplicate_handling'     => $data['duplicate_handling'] ?? 'keep_existing_owner_mark_reenquiry',
                'is_active'              => $request->boolean('is_active', true),
            ]));

            $this->syncRuleForms($rule, $data);

            // Sync users
            $rule->users()->delete();
            if (!empty($data['users']) && $data['assignment_method'] !== 'single_user') {
                foreach ($this->normalizedRuleUsers($data['users']) as $u) {
                    SourceAutomationRuleUser::create([
                        'rule_id'    => $rule->id,
                        'user_id'    => $u['user_id'],
                        'percentage' => $data['assignment_method'] === 'percentage' ? ($u['percentage'] ?? 0) : null,
                        'daily_limit' => $u['daily_limit'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('admin.automation.index')
            ->with('success', 'Automation rule updated successfully.');
    }

    public function destroy(SourceAutomationRule $rule)
    {
        $rule->users()->delete();
        $rule->delete();

        return redirect()->route('admin.automation.index')
            ->with('success', 'Automation rule deleted.');
    }

    public function toggle(SourceAutomationRule $rule)
    {
        if (!$rule->is_active) {
            $rule->load($this->hasRuleFormsTable() ? ['fbForms'] : []);
            $formIds = $this->hasRuleFormsTable() ? $rule->fbForms->pluck('id')->values()->all() : [];
            if (empty($formIds) && $rule->fb_form_id) {
                $formIds = [(int) $rule->fb_form_id];
            }
            if (empty($formIds) && $rule->source_type === 'facebook_lead_ads' && is_numeric($rule->source_id)) {
                $formIds = [(int) $rule->source_id];
            }

            $this->validateFormConflicts([
                'source_type' => $rule->source_type ?: $rule->source,
                'fb_form_ids' => $formIds,
                'is_active' => true,
            ], $rule);
        }

        $rule->update(['is_active' => !$rule->is_active]);

        return response()->json(['is_active' => $rule->is_active]);
    }

    protected function getAssignableUsers()
    {
        return User::with('role')
            ->where('is_active', true)
            ->whereHas('role', fn($q) => $q->whereIn('slug', [
                Role::SALES_EXECUTIVE,
                Role::SALES_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::HR_MANAGER,
                Role::JUNIOR_HR,
            ]))
            ->orderBy('name')
            ->get();
    }

    public function history(\App\Models\SourceAutomationRule $rule)
    {
        // Rule ke users ki IDs
        $ruleUserIds = \App\Models\SourceAutomationRuleUser::where('rule_id', $rule->id)
            ->pluck('user_id')
            ->toArray();

        if ($rule->assignment_method === 'single_user' && $rule->single_user_id) {
            $ruleUserIds[] = (int) $rule->single_user_id;
        }

        if ($rule->fallback_user_id) {
            $ruleUserIds[] = (int) $rule->fallback_user_id;
        }

        $ruleUserIds = array_values(array_unique(array_filter($ruleUserIds)));

        // Base query - rule ke method aur users se match karo
        $baseQuery = \App\Models\LeadAssignment::where('assignment_method', $rule->assignment_method);

        if (!empty($ruleUserIds)) {
            $baseQuery->whereIn('assigned_to', $ruleUserIds);
        }

        // Source filter - facebook leads ya all
        if ($rule->source !== 'all') {
            $baseQuery->whereHas('lead', function($q) use ($rule) {
                $q->where('source', $rule->source);
            });
        }

        // Search filters
        $search = request('search');
        $assignedToFilter = request('assigned_to');
        $dateFilter = request('date_filter');

        $filteredQuery = clone $baseQuery;

        if ($search) {
            $filteredQuery->where(function($q) use ($search) {
                $q->whereHas('lead', function($lq) use ($search) {
                    $lq->where('name', 'like', "%$search%")
                       ->orWhere('phone', 'like', "%$search%");
                })->orWhereHas('assignedTo', function($uq) use ($search) {
                    $uq->where('name', 'like', "%$search%");
                });
            });
        }

        if ($assignedToFilter) {
            $filteredQuery->where('assigned_to', $assignedToFilter);
        }

        if ($dateFilter === 'today') {
            $filteredQuery->whereDate('assigned_at', today());
        } elseif ($dateFilter === 'week') {
            $filteredQuery->whereBetween('assigned_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'month') {
            $filteredQuery->whereMonth('assigned_at', now()->month)->whereYear('assigned_at', now()->year);
        }

        $assignments = $filteredQuery
            ->with(['lead:id,name,phone,source', 'assignedTo:id,name', 'assignedBy:id,name'])
            ->latest('assigned_at')
            ->paginate(50)
            ->withQueryString();

        $totalAssignments = $baseQuery->count();
        $todayAssignments = (clone $baseQuery)->whereDate('assigned_at', today())->count();
        $thisWeek = (clone $baseQuery)->whereBetween('assigned_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $uniqueUsers = (clone $baseQuery)->distinct('assigned_to')->count('assigned_to');

        // Rule users for filter dropdown
        $ruleUsers = \App\Models\User::whereIn('id', $ruleUserIds)->get(['id','name']);
        $facebookCallbackForm = null;

        $ruleSource = $rule->source_type ?: $rule->source;
        if (in_array($ruleSource, ['facebook_lead_ads', 'facebook'], true)) {
            $sourceIds = array_values(array_unique(array_filter([
                $rule->fb_form_id ?? null,
                $rule->source_id ?? null,
            ])));

            if (!empty($sourceIds)) {
                $facebookCallbackForm = \App\Models\FbForm::with('page')
                    ->whereIn('id', $sourceIds)
                    ->orWhereIn('form_id', array_map('strval', $sourceIds))
                    ->first();
            }
        }

        return view('admin.automation.history', compact('rule','assignments','totalAssignments','todayAssignments','thisWeek','uniqueUsers','ruleUsers','facebookCallbackForm'));
    }

    private function sourceAutomationSources(): array
    {
        return [
            'facebook_lead_ads',
            'pabbly',
            'mcube',
            'ivr',
            'google_sheets',
            'csv',
            'manual_import',
            'website',
            'whatsapp',
            '99acres',
            'instagram',
            'all',
        ];
    }

    private function normalizedRuleUsers(array $users): array
    {
        return collect($users)
            ->filter(fn($user) => !empty($user['user_id']))
            ->unique(fn($user) => (int) $user['user_id'])
            ->map(fn($user) => [
                'user_id' => (int) $user['user_id'],
                'percentage' => isset($user['percentage']) && $user['percentage'] !== '' ? (float) $user['percentage'] : null,
                'daily_limit' => isset($user['daily_limit']) && $user['daily_limit'] !== '' ? (int) $user['daily_limit'] : null,
            ])
            ->values()
            ->all();
    }

    private function wizardPrefill(Request $request): array
    {
        return [
            'source_type' => $request->query('source_type'),
            'source_id' => $request->query('source_id'),
            'source_label' => $request->query('source_label'),
            'return_to' => $request->query('return_to'),
        ];
    }

    private function normalizeWizardPayload(Request $request, array $data): array
    {
        $sourceType = $data['source_type'] ?? $data['source'] ?? 'facebook_lead_ads';
        $sourceType = $sourceType === 'mcube' ? 'ivr' : $sourceType;
        $source = $sourceType === 'manual_import' ? 'csv' : $sourceType;

        $sourceId = $data['source_id'] ?? null;
        if ($sourceType === 'facebook_lead_ads') {
            $data['fb_form_ids'] = $this->normalizedFormIds($data);
            $data['fb_form_id'] = $data['fb_form_ids'][0] ?? null;
            if (empty($data['fb_form_id']) && is_numeric($sourceId) && FbForm::whereKey((int) $sourceId)->exists()) {
                $data['fb_form_id'] = (int) $sourceId;
                $data['fb_form_ids'] = [(int) $sourceId];
            }
            $sourceId = !empty($data['fb_form_id']) ? (string) $data['fb_form_id'] : $sourceId;
        } elseif ($sourceType === 'google_sheets') {
            $data['fb_form_id'] = null;
            $data['fb_form_ids'] = [];
            $data['google_sheet_config_id'] = $data['google_sheet_config_id'] ?? (is_numeric($sourceId) ? (int) $sourceId : null);
            $sourceId = $data['google_sheet_config_id'] ? (string) $data['google_sheet_config_id'] : $sourceId;
        } else {
            $data['fb_form_id'] = null;
            $data['fb_form_ids'] = [];
        }

        $data['source_type'] = $sourceType;
        $data['source'] = $source;
        $data['source_id'] = $sourceId ?: null;
        $data['assignment_method'] = $data['distribution_method'] ?? $data['assignment_method'] ?? 'round_robin';
        $data['source_label'] = $data['source_label'] ?? null;
        $data['duplicate_handling'] = $data['duplicate_handling'] ?? 'keep_existing_owner_mark_reenquiry';

        if (blank($data['name'] ?? null)) {
            $sourceLabel = $data['source_label'] ?: SourceAutomationRule::getSourceLabel($sourceType);
            $data['name'] = $sourceLabel . ' Automation';
        }

        return $data;
    }

    private function normalizedFormIds(array $data): array
    {
        return collect($data['fb_form_ids'] ?? [])
            ->merge(!empty($data['fb_form_id']) ? [$data['fb_form_id']] : [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function validateFormConflicts(array $data, ?SourceAutomationRule $currentRule = null): void
    {
        if (($data['source_type'] ?? null) !== 'facebook_lead_ads' || empty($data['fb_form_ids'])) {
            return;
        }

        if (array_key_exists('is_active', $data) && !$data['is_active']) {
            return;
        }

        $formIds = collect($data['fb_form_ids'])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($formIds->isEmpty()) {
            return;
        }

        $conflict = SourceAutomationRule::query()
            ->with('fbForm')
            ->where('is_active', true)
            ->when($currentRule?->id, fn ($query) => $query->whereKeyNot($currentRule->id))
            ->where(function ($query) use ($formIds) {
                $query->whereIn('fb_form_id', $formIds)
                    ->orWhere(function ($inner) use ($formIds) {
                        $inner->where('source_type', 'facebook_lead_ads')
                            ->whereIn('source_id', $formIds->map(fn ($id) => (string) $id)->all());
                    });

                if ($this->hasRuleFormsTable()) {
                    $query->orWhereHas('fbForms', fn ($forms) => $forms->whereIn('fb_forms.id', $formIds));
                }
            })
            ->first();

        if (!$conflict) {
            return;
        }

        $conflictingFormId = $conflict->fb_form_id && $formIds->contains((int) $conflict->fb_form_id)
            ? (int) $conflict->fb_form_id
            : (int) ($conflict->source_id && $formIds->contains((int) $conflict->source_id)
                ? (int) $conflict->source_id
                : ($this->hasRuleFormsTable() ? SourceAutomationRuleForm::where('rule_id', $conflict->id)
                    ->whereIn('fb_form_id', $formIds)
                    ->value('fb_form_id') : 0));

        $form = FbForm::find($conflictingFormId);
        $formName = $form?->form_name ?: $form?->form_id ?: 'Selected form';

        validator([], [])->after(function ($validator) use ($formName, $conflict) {
            $validator->errors()->add(
                'fb_form_ids',
                "{$formName} is already used in rule: {$conflict->name}. Disable that rule first or remove the form."
            );
        })->validate();
    }

    private function syncRuleForms(SourceAutomationRule $rule, array $data): void
    {
        if (!$this->hasRuleFormsTable()) {
            return;
        }

        if (($data['source_type'] ?? null) !== 'facebook_lead_ads') {
            $rule->fbForms()->detach();
            return;
        }

        $rule->fbForms()->sync($data['fb_form_ids'] ?? []);
    }

    private function hasRuleFormsTable(): bool
    {
        return Schema::hasTable('source_automation_rule_forms');
    }

    private function sourceAutomationRulePayload(array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, $column) => Schema::hasColumn('source_automation_rules', $column))
            ->all();
    }

    private function validateAutomationUsers(array $data): void
    {
        $method = $data['assignment_method'] ?? null;

        if ($method === 'single_user') {
            validator($data, [
                'single_user_id' => 'required',
            ], [
                'single_user_id.required' => 'Select a user for single user distribution.',
            ])->validate();
            return;
        }

        $users = $this->normalizedRuleUsers($data['users'] ?? []);

        validator(['users' => $users], [
            'users' => 'required|array|min:1',
        ], [
            'users.required' => 'Select at least one user for this assignment method.',
            'users.min' => 'Select at least one user for this assignment method.',
        ])->validate();

        if ($method === 'percentage') {
            $total = collect($users)->sum(fn($user) => (float) ($user['percentage'] ?? 0));
            validator(['percentage_total' => $total], [
                'percentage_total' => ['numeric', 'between:99.9,100.1'],
            ], [
                'percentage_total.between' => 'Percentage split total must be exactly 100%.',
            ])->validate();
        }
    }

    private function buildNinetyNineAcresStats(?SourceAutomationRule $rule): array
    {
        $query = $this->ninetyNineAcresAssignmentsQuery($rule);

        return [
            'is_configured' => (bool) $rule,
            'is_active' => (bool) ($rule?->is_active),
            'method' => $rule?->assignment_method ?? 'round_robin',
            'users_count' => $rule
                ? ($rule->assignment_method === 'single_user' ? (int) filled($rule->single_user_id) : $rule->users->count())
                : 0,
            'today_assigned' => $query ? (clone $query)->whereDate('assigned_at', today())->count() : 0,
            'week_assigned' => $query ? (clone $query)->whereBetween('assigned_at', [now()->startOfWeek(), now()->endOfWeek()])->count() : 0,
        ];
    }

    private function ninetyNineAcresAssignmentsQuery(?SourceAutomationRule $rule)
    {
        if (!Schema::hasTable('lead_assignments') || !Schema::hasTable('leads')) {
            return null;
        }

        $query = \App\Models\LeadAssignment::whereHas('lead', fn($leadQuery) => $leadQuery->where('source', '99acres'));

        if ($rule) {
            $query->where('assignment_method', $rule->assignment_method);
        }

        return $query;
    }
}
