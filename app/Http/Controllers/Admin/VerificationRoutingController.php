<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\VerificationRoutingMapping;
use App\Services\VerificationRoutingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VerificationRoutingController extends Controller
{
    public function index(Request $request, VerificationRoutingService $routing)
    {
        $routing->ensureDefaultSettings();

        $settings = collect(array_keys(VerificationRoutingService::workflows()))
            ->mapWithKeys(fn (string $workflow) => [$workflow => $routing->settingFor($workflow)]);

        $roles = Role::query()->where('is_active', true)->orderBy('name')->get();
        $users = User::query()->where('is_active', true)->with('role')->orderBy('name')->get();
        $mappings = VerificationRoutingMapping::query()
            ->with(['sourceUser', 'sourceRole', 'sourceTeamUser', 'verifierUser'])
            ->orderBy('workflow_type')
            ->orderBy('is_active', 'desc')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $preview = null;
        if ($request->filled(['preview_workflow_type', 'preview_item_id'])) {
            $preview = $this->buildPreview($request, $routing);
        }

        return view('admin.verification-routing.index', [
            'workflows' => VerificationRoutingService::workflows(),
            'modes' => VerificationRoutingService::modes(),
            'settings' => $settings,
            'roles' => $roles,
            'users' => $users,
            'mappings' => $mappings,
            'preview' => $preview,
            'sourceTypes' => [
                VerificationRoutingService::SOURCE_USER => 'User',
                VerificationRoutingService::SOURCE_ROLE => 'Role',
                VerificationRoutingService::SOURCE_TEAM => 'Team',
            ],
            'verifierTypes' => [
                VerificationRoutingService::VERIFIER_USER => 'User',
                VerificationRoutingService::VERIFIER_ROLE => 'Role',
            ],
        ]);
    }

    public function updateSettings(Request $request, VerificationRoutingService $routing)
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.mode' => ['required', Rule::in(array_keys(VerificationRoutingService::modes()))],
            'settings.*.fixed_role_ids' => ['nullable', 'array'],
            'settings.*.fixed_role_ids.*' => ['integer', 'exists:roles,id'],
            'settings.*.fixed_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'settings.*.fallback_role_ids' => ['required', 'array', 'min:1'],
            'settings.*.fallback_role_ids.*' => ['integer', 'exists:roles,id'],
            'settings.*.fallback_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        foreach (VerificationRoutingService::workflows() as $workflowType => $label) {
            $payload = $validated['settings'][$workflowType] ?? null;
            if (!$payload) {
                continue;
            }

            $setting = $routing->settingFor($workflowType);
            $old = $setting->toArray();

            $setting->fill([
                'mode' => $payload['mode'],
                'fixed_role_ids' => array_values($payload['fixed_role_ids'] ?? []),
                'fixed_user_id' => $payload['fixed_user_id'] ?? null,
                'fallback_role_ids' => array_values($payload['fallback_role_ids'] ?? []),
                'fallback_user_id' => $payload['fallback_user_id'] ?? null,
                'updated_by' => $request->user()->id,
            ])->save();

            $routing->auditRuleChange('verification_routing_rule_updated', $request->user(), $setting, $old, $setting->fresh()->toArray());
        }

        return back()->with('success', 'Verification routing settings updated.');
    }

    public function storeMapping(Request $request, VerificationRoutingService $routing)
    {
        $payload = $this->mappingPayload($request);
        $payload['created_by'] = $request->user()->id;
        $payload['updated_by'] = $request->user()->id;

        $mapping = VerificationRoutingMapping::create($payload);
        $routing->auditRuleChange('verification_routing_mapping_created', $request->user(), $mapping, [], $mapping->toArray());

        return back()->with('success', 'Custom mapping created.');
    }

    public function updateMapping(Request $request, VerificationRoutingMapping $mapping, VerificationRoutingService $routing)
    {
        $old = $mapping->toArray();
        $payload = $this->mappingPayload($request);
        $payload['updated_by'] = $request->user()->id;

        $mapping->update($payload);
        $routing->auditRuleChange('verification_routing_mapping_updated', $request->user(), $mapping, $old, $mapping->fresh()->toArray());

        return back()->with('success', 'Custom mapping updated.');
    }

    public function disableMapping(Request $request, VerificationRoutingMapping $mapping, VerificationRoutingService $routing)
    {
        $old = $mapping->toArray();
        $mapping->update([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        $routing->auditRuleChange('verification_routing_mapping_disabled', $request->user(), $mapping, $old, $mapping->fresh()->toArray());

        return back()->with('success', 'Custom mapping disabled.');
    }

    private function mappingPayload(Request $request): array
    {
        $payload = $request->validate([
            'workflow_type' => ['required', Rule::in(array_keys(VerificationRoutingService::workflows()))],
            'source_type' => ['required', Rule::in([
                VerificationRoutingService::SOURCE_USER,
                VerificationRoutingService::SOURCE_ROLE,
                VerificationRoutingService::SOURCE_TEAM,
            ])],
            'source_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'source_role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'source_team_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'verifier_type' => ['required', Rule::in([
                VerificationRoutingService::VERIFIER_USER,
                VerificationRoutingService::VERIFIER_ROLE,
            ])],
            'verifier_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'verifier_role_ids' => ['nullable', 'array'],
            'verifier_role_ids.*' => ['integer', 'exists:roles,id'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($payload['source_type'] === VerificationRoutingService::SOURCE_USER && empty($payload['source_user_id'])) {
            abort(422, 'Source user is required for user mapping.');
        }
        if ($payload['source_type'] === VerificationRoutingService::SOURCE_ROLE && empty($payload['source_role_id'])) {
            abort(422, 'Source role is required for role mapping.');
        }
        if ($payload['source_type'] === VerificationRoutingService::SOURCE_TEAM && empty($payload['source_team_user_id'])) {
            abort(422, 'Team owner is required for team mapping.');
        }
        if ($payload['verifier_type'] === VerificationRoutingService::VERIFIER_USER && empty($payload['verifier_user_id'])) {
            abort(422, 'Verifier user is required for user verifier.');
        }
        if ($payload['verifier_type'] === VerificationRoutingService::VERIFIER_ROLE && empty($payload['verifier_role_ids'])) {
            abort(422, 'At least one verifier role is required.');
        }

        $payload['source_user_id'] = $payload['source_type'] === VerificationRoutingService::SOURCE_USER ? ($payload['source_user_id'] ?? null) : null;
        $payload['source_role_id'] = $payload['source_type'] === VerificationRoutingService::SOURCE_ROLE ? ($payload['source_role_id'] ?? null) : null;
        $payload['source_team_user_id'] = $payload['source_type'] === VerificationRoutingService::SOURCE_TEAM ? ($payload['source_team_user_id'] ?? null) : null;
        $payload['verifier_user_id'] = $payload['verifier_type'] === VerificationRoutingService::VERIFIER_USER ? ($payload['verifier_user_id'] ?? null) : null;
        $payload['verifier_role_ids'] = $payload['verifier_type'] === VerificationRoutingService::VERIFIER_ROLE ? array_values($payload['verifier_role_ids'] ?? []) : [];
        $payload['is_active'] = (bool) ($payload['is_active'] ?? true);

        return $payload;
    }

    private function buildPreview(Request $request, VerificationRoutingService $routing): ?array
    {
        $workflowType = $request->input('preview_workflow_type');
        $itemId = $request->integer('preview_item_id');
        $actorId = $request->integer('preview_actor_id');

        if (!array_key_exists($workflowType, VerificationRoutingService::workflows())) {
            return ['error' => 'Invalid workflow type.'];
        }

        $model = $workflowType === VerificationRoutingService::WORKFLOW_MEETING
            ? Meeting::with(['creator.role', 'creator.manager.role'])->find($itemId)
            : SiteVisit::with(['creator.role', 'creator.manager.role'])->find($itemId);

        if (!$model) {
            return ['error' => 'Item not found for selected workflow.'];
        }

        $actor = $actorId ? User::with('role')->find($actorId) : null;

        return $routing->explain($model, $workflowType, $actor);
    }
}
