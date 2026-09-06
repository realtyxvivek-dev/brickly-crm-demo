<?php

namespace App\Http\Controllers\AdManager;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\FbForm;
use App\Models\Role;
use App\Models\SourceAutomationRule;
use App\Models\SourceAutomationRuleForm;
use App\Models\SourceAutomationRuleUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SourceAutomationController extends Controller
{
    private const META_SOURCES = ['meta', 'meta_awareness', 'facebook_lead_ads', 'whatsapp', 'instagram'];

    public function index()
    {
        $with = ['users.user', 'fbForm.page', 'singleUser', 'fallbackUser'];
        if ($this->hasRuleFormsTable()) {
            $with[] = 'fbForms.page';
        }

        $rules = $this->metaRuleQuery()
            ->with($with)
            ->latest()
            ->get();

        return view('ad-manager.automation.index', compact('rules'));
    }

    public function create(Request $request)
    {
        $fbForms = FbForm::with('page')->orderBy('form_name')->get();
        $assignableUsers = $this->getAssignableUsers();
        $rule = null;
        $prefill = [
            'source_type' => $request->query('source_type', 'facebook_lead_ads'),
            'source_id' => $request->query('source_id'),
            'source_label' => $request->query('source_label'),
        ];

        return view('ad-manager.automation.form', compact('rule', 'fbForms', 'assignableUsers', 'prefill'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data = $this->normalizePayload($data);
        $data['is_active'] = $request->boolean('is_active', true);
        $this->validateMetaSource($data);
        $this->validateFormConflicts($data);
        $this->validateAutomationUsers($data);

        $rule = DB::transaction(function () use ($request, $data) {
            $rule = SourceAutomationRule::create($this->rulePayload([
                'name' => $data['name'],
                'source' => $data['source'],
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'] ?? null,
                'source_label' => $data['source_label'] ?? null,
                'fb_form_id' => $data['source_type'] === 'facebook_lead_ads' ? ($data['fb_form_id'] ?? null) : null,
                'google_sheet_config_id' => null,
                'assignment_method' => $data['assignment_method'],
                'distribution_method' => $data['assignment_method'],
                'single_user_id' => $data['single_user_id'] ?? null,
                'auto_create_task' => $request->boolean('task_enabled', true),
                'task_enabled' => $request->boolean('task_enabled', true),
                'notification_enabled' => $request->boolean('notification_enabled', true),
                'daily_limit' => $data['daily_limit'] ?? null,
                'fallback_user_id' => $data['fallback_user_id'] ?? null,
                'skip_lead_off_users' => $request->boolean('skip_lead_off_users', true),
                'duplicate_handling' => $data['duplicate_handling'] ?? 'keep_existing_owner_mark_reenquiry',
                'is_active' => $request->boolean('is_active', true),
                'created_by' => auth()->id(),
            ]));

            $this->syncRuleForms($rule, $data);
            $this->syncRuleUsers($rule, $data);

            return $rule;
        });

        $this->audit('ad_manager_meta_automation_created', $request, ['rule_id' => $rule->id]);

        return redirect()->route('ad-manager.automation.index')->with('success', 'Meta automation rule created successfully.');
    }

    public function edit(SourceAutomationRule $rule)
    {
        $this->guardMetaRule($rule);

        $with = ['users.user', 'fbForm.page', 'singleUser', 'fallbackUser'];
        if ($this->hasRuleFormsTable()) {
            $with[] = 'fbForms.page';
        }

        $rule->load($with);
        $fbForms = FbForm::with('page')->orderBy('form_name')->get();
        $assignableUsers = $this->getAssignableUsers();
        $prefill = [];

        return view('ad-manager.automation.form', compact('rule', 'fbForms', 'assignableUsers', 'prefill'));
    }

    public function update(Request $request, SourceAutomationRule $rule)
    {
        $this->guardMetaRule($rule);

        $data = $this->validatedData($request);
        $data = $this->normalizePayload($data);
        $data['is_active'] = $request->boolean('is_active', true);
        $this->validateMetaSource($data);
        $this->validateFormConflicts($data, $rule);
        $this->validateAutomationUsers($data);

        DB::transaction(function () use ($request, $rule, $data) {
            $rule->update($this->rulePayload([
                'name' => $data['name'],
                'source' => $data['source'],
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'] ?? null,
                'source_label' => $data['source_label'] ?? null,
                'fb_form_id' => $data['source_type'] === 'facebook_lead_ads' ? ($data['fb_form_id'] ?? null) : null,
                'google_sheet_config_id' => null,
                'assignment_method' => $data['assignment_method'],
                'distribution_method' => $data['assignment_method'],
                'single_user_id' => $data['single_user_id'] ?? null,
                'auto_create_task' => $request->boolean('task_enabled', true),
                'task_enabled' => $request->boolean('task_enabled', true),
                'notification_enabled' => $request->boolean('notification_enabled', true),
                'daily_limit' => $data['daily_limit'] ?? null,
                'fallback_user_id' => $data['fallback_user_id'] ?? null,
                'skip_lead_off_users' => $request->boolean('skip_lead_off_users', true),
                'duplicate_handling' => $data['duplicate_handling'] ?? 'keep_existing_owner_mark_reenquiry',
                'is_active' => $request->boolean('is_active', true),
            ]));

            $rule->users()->delete();
            $this->syncRuleForms($rule, $data);
            $this->syncRuleUsers($rule, $data);
        });

        $this->audit('ad_manager_meta_automation_updated', $request, ['rule_id' => $rule->id]);

        return redirect()->route('ad-manager.automation.index')->with('success', 'Meta automation rule updated successfully.');
    }

    public function toggle(Request $request, SourceAutomationRule $rule)
    {
        $this->guardMetaRule($rule);

        if (!$rule->is_active) {
            $rule->load('fbForms');
            $formIds = $rule->fbForms->pluck('id')->values()->all();
            if (empty($formIds) && $rule->fb_form_id) {
                $formIds = [(int) $rule->fb_form_id];
            }

            $this->validateFormConflicts([
                'source_type' => $rule->source_type ?: $rule->source,
                'fb_form_ids' => $formIds,
                'is_active' => true,
            ], $rule);
        }

        $rule->update(['is_active' => !$rule->is_active]);
        $this->audit('ad_manager_meta_automation_toggled', $request, ['rule_id' => $rule->id, 'is_active' => $rule->is_active]);

        return response()->json(['is_active' => $rule->is_active]);
    }

    private function metaRuleQuery()
    {
        return SourceAutomationRule::query()
            ->where(function ($query) {
                $query->whereIn('source', self::META_SOURCES)
                    ->orWhereIn('source_type', self::META_SOURCES)
                    ->orWhereNotNull('fb_form_id');

                if ($this->hasRuleFormsTable()) {
                    $query->orWhereHas('fbForms');
                }
            });
    }

    private function guardMetaRule(SourceAutomationRule $rule): void
    {
        abort_unless($this->isMetaRule($rule), 403);
    }

    private function isMetaRule(SourceAutomationRule $rule): bool
    {
        return $rule->fb_form_id !== null
            || ($this->hasRuleFormsTable() && $rule->fbForms()->exists())
            || in_array($rule->source, self::META_SOURCES, true)
            || in_array($rule->source_type, self::META_SOURCES, true);
    }

    private function validatedData(Request $request): array
    {
        $assignableUserIds = $this->getAssignableUsers()->pluck('id')->all();

        return $request->validate([
            'name' => 'nullable|string|max:255',
            'source_type' => ['required', Rule::in(self::META_SOURCES)],
            'source_id' => 'nullable|string|max:120',
            'source_label' => 'nullable|string|max:255',
            'source' => ['nullable', Rule::in(self::META_SOURCES)],
            'fb_form_id' => 'nullable|exists:fb_forms,id',
            'fb_form_ids' => 'nullable|array',
            'fb_form_ids.*' => 'nullable|exists:fb_forms,id',
            'assignment_method' => 'required|in:round_robin,first_available,percentage,single_user',
            'single_user_id' => ['nullable', 'required_if:assignment_method,single_user', Rule::in($assignableUserIds)],
            'task_enabled' => 'boolean',
            'notification_enabled' => 'boolean',
            'daily_limit' => 'nullable|integer|min:1',
            'fallback_user_id' => ['nullable', Rule::in($assignableUserIds)],
            'skip_lead_off_users' => 'boolean',
            'duplicate_handling' => 'nullable|in:keep_existing_owner_mark_reenquiry,keep_existing_owner,reassign',
            'is_active' => 'boolean',
            'users' => 'nullable|array',
            'users.*.user_id' => ['nullable', 'required_unless:assignment_method,single_user', Rule::in($assignableUserIds)],
            'users.*.percentage' => 'nullable|numeric|min:0|max:100',
            'users.*.daily_limit' => 'nullable|integer|min:1',
        ]);
    }

    private function normalizePayload(array $data): array
    {
        $sourceType = $data['source_type'];
        $sourceId = $data['source_id'] ?? null;

        if ($sourceType === 'facebook_lead_ads') {
            $data['fb_form_ids'] = $this->normalizedFormIds($data);
            $data['fb_form_id'] = $data['fb_form_ids'][0] ?? null;
            if (empty($data['fb_form_id']) && is_numeric($sourceId) && FbForm::whereKey((int) $sourceId)->exists()) {
                $data['fb_form_id'] = (int) $sourceId;
                $data['fb_form_ids'] = [(int) $sourceId];
            }
            $sourceId = !empty($data['fb_form_id']) ? (string) $data['fb_form_id'] : $sourceId;
        } else {
            $data['fb_form_id'] = null;
            $data['fb_form_ids'] = [];
        }

        $data['source'] = $data['source'] ?? $sourceType;
        $data['source_id'] = $sourceId ?: null;
        $data['source_label'] = $data['source_label'] ?? null;
        $data['duplicate_handling'] = $data['duplicate_handling'] ?? 'keep_existing_owner_mark_reenquiry';

        if (blank($data['name'] ?? null)) {
            $data['name'] = ($data['source_label'] ?: SourceAutomationRule::getSourceLabel($sourceType)) . ' Automation';
        }

        return $data;
    }

    private function normalizedFormIds(array $data): array
    {
        $ids = collect($data['fb_form_ids'] ?? [])
            ->merge(!empty($data['fb_form_id']) ? [$data['fb_form_id']] : [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $ids;
    }

    private function validateMetaSource(array $data): void
    {
        abort_unless(in_array($data['source_type'], self::META_SOURCES, true), 403);
        abort_unless(in_array($data['source'], self::META_SOURCES, true), 403);
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

        $currentRuleId = $currentRule?->id;
        $conflict = SourceAutomationRule::query()
            ->with('fbForm')
            ->where('is_active', true)
            ->when($currentRuleId, fn ($query) => $query->whereKeyNot($currentRuleId))
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
        if (($data['source_type'] ?? null) !== 'facebook_lead_ads') {
            if ($this->hasRuleFormsTable()) {
                $rule->fbForms()->detach();
            }
            return;
        }

        if ($this->hasRuleFormsTable()) {
            $rule->fbForms()->sync($data['fb_form_ids'] ?? []);
        }
    }

    private function hasRuleFormsTable(): bool
    {
        return Schema::hasTable('source_automation_rule_forms');
    }

    private function syncRuleUsers(SourceAutomationRule $rule, array $data): void
    {
        if ($data['assignment_method'] === 'single_user') {
            return;
        }

        foreach ($this->normalizedRuleUsers($data['users'] ?? []) as $user) {
            SourceAutomationRuleUser::create([
                'rule_id' => $rule->id,
                'user_id' => $user['user_id'],
                'percentage' => $data['assignment_method'] === 'percentage' ? ($user['percentage'] ?? 0) : null,
                'daily_limit' => $user['daily_limit'] ?? null,
            ]);
        }
    }

    private function validateAutomationUsers(array $data): void
    {
        if (($data['assignment_method'] ?? null) === 'single_user') {
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

        if (($data['assignment_method'] ?? null) === 'percentage') {
            $total = collect($users)->sum(fn ($user) => (float) ($user['percentage'] ?? 0));
            validator(['percentage_total' => $total], [
                'percentage_total' => ['numeric', 'between:99.9,100.1'],
            ], [
                'percentage_total.between' => 'Percentage split total must be exactly 100%.',
            ])->validate();
        }
    }

    private function normalizedRuleUsers(array $users): array
    {
        return collect($users)
            ->filter(fn ($user) => !empty($user['user_id']))
            ->unique(fn ($user) => (int) $user['user_id'])
            ->map(fn ($user) => [
                'user_id' => (int) $user['user_id'],
                'percentage' => isset($user['percentage']) && $user['percentage'] !== '' ? (float) $user['percentage'] : null,
                'daily_limit' => isset($user['daily_limit']) && $user['daily_limit'] !== '' ? (int) $user['daily_limit'] : null,
            ])
            ->values()
            ->all();
    }

    private function getAssignableUsers()
    {
        return User::with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', [
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

    private function rulePayload(array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, $column) => Schema::hasColumn('source_automation_rules', $column))
            ->all();
    }

    private function audit(string $action, Request $request, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'AdManagerSourceAutomation',
            'model_id' => auth()->id(),
            'description' => 'Ad Manager source automation action: ' . $action,
            'new_values' => array_merge($metadata, [
                'route' => optional($request->route())->getName(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
