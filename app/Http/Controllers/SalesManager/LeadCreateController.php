<?php

namespace App\Http\Controllers\SalesManager;

use App\Events\LeadAssigned;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Project;
use App\Models\User;
use App\Services\DynamicFormService;
use App\Services\LeadDuplicateGuardService;
use App\Services\LeadOwnerTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadCreateController extends Controller
{
    public function create(Request $request, DynamicFormService $dynamicFormService)
    {
        $user = $request->user()->loadMissing('role');
        $this->ensureCanCreate($user);

        $users = $this->allowedOwners($user);
        $projects = Project::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $leadDetailRequirementsForm = $dynamicFormService->getPublishedFormByLocation('lead-detail.requirements');
        $interestedProjectOptions = $this->buildInterestedProjectOptions($projects, $leadDetailRequirementsForm);

        return view('sales-manager.leads-create', compact('users', 'projects', 'interestedProjectOptions'));
    }

    public function checkDuplicate(Request $request, LeadDuplicateGuardService $leadDuplicateGuardService)
    {
        $this->ensureCanCreate($request->user()->loadMissing('role'));

        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'phone_country_iso' => 'nullable|string|size:2',
        ]);

        $normalizedPhone = $leadDuplicateGuardService->normalizePhone($validated['phone'], $validated['phone_country_iso'] ?? null);

        if ($normalizedPhone === '') {
            return response()->json([
                'success' => false,
                'duplicate' => false,
                'message' => 'Enter a valid phone number to check.',
            ], 422);
        }

        $existingLead = $leadDuplicateGuardService->findExistingLeadByPhone($normalizedPhone);

        if (!$existingLead) {
            return response()->json([
                'success' => true,
                'duplicate' => false,
                'normalized_phone' => $normalizedPhone,
                'message' => 'No duplicate lead found for this phone number.',
            ]);
        }

        return response()->json([
            'success' => true,
            'duplicate' => true,
            'normalized_phone' => $normalizedPhone,
            'message' => 'Lead already exists for this phone number.',
            ...$leadDuplicateGuardService->buildDuplicatePayload($existingLead),
        ]);
    }

    public function store(
        Request $request,
        LeadDuplicateGuardService $leadDuplicateGuardService,
        LeadOwnerTaskService $leadOwnerTaskService
    ) {
        $user = $request->user()->loadMissing('role');
        $this->ensureCanCreate($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'phone_country_iso' => 'nullable|string|size:2',
            'email' => 'nullable|email|max:255',
            'source' => 'nullable|in:' . implode(',', array_keys(Lead::sourceOptions())),
            'category' => 'nullable|string|max:100',
            'preferred_location' => 'nullable|string|max:255',
            'budget' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'property_type' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:255',
            'use_end_use' => 'nullable|string|in:End User,2nd Investments',
            'possession' => 'nullable|string|max:255',
            'possession_status' => 'nullable|string|max:255',
            'lead_status' => 'nullable|string|max:50',
            'lead_quality' => 'nullable|string|max:50',
            'customer_job' => 'nullable|string|max:255',
            'industry_sector' => 'nullable|string|max:255',
            'buying_frequency' => 'nullable|string|max:255',
            'living_city' => 'nullable|string|max:255',
            'city_type' => 'nullable|string|max:255',
            'manager_remark' => 'nullable|string',
            'interested_projects' => 'nullable',
            'preferred_projects' => 'nullable|array',
            'preferred_projects.*' => 'nullable|exists:projects,id',
            'requirements' => 'nullable|string',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        $parsedPhone = app(\App\Services\DuplicateDetectionService::class)
            ->parsedLeadPhone($validated['phone'] ?? null, $validated['phone_country_iso'] ?? null);
        if (!$parsedPhone) {
            return back()->withErrors(['phone' => 'Enter a valid phone number. Use + country code for non-Indian numbers.'])->withInput();
        }

        $normalizedPhone = $parsedPhone['normalized'];
        if ($normalizedPhone !== '') {
            $validated['phone'] = $parsedPhone['e164'];
            $validated['phone_country_iso'] = $parsedPhone['country_iso'];
            $existingLead = $leadDuplicateGuardService->findExistingLeadByPhone($normalizedPhone);

            if ($existingLead) {
                return back()
                    ->withErrors(['phone' => 'Lead already exists for this phone number.'])
                    ->with('duplicate_lead', $leadDuplicateGuardService->buildDuplicatePayload($existingLead))
                    ->withInput();
            }
        }

        $ownerId = (int) ($validated['assigned_to'] ?: $user->id);
        $allowedOwnerIds = $this->allowedOwners($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (!in_array($ownerId, $allowedOwnerIds, true)) {
            return back()
                ->withErrors(['assigned_to' => 'Please select a valid team member or yourself as owner.'])
                ->withInput();
        }

        $owner = User::with('role')->findOrFail($ownerId);

        DB::beginTransaction();
        try {
            $validated['created_by'] = $user->id;
            $validated['status'] = 'new';
            $validated['source'] = Lead::normalizeSource($validated['source'] ?? 'other');
            $interestedProjectNames = $this->parseInterestedProjects($request->input('interested_projects'));
            $matchedProjectIds = $this->resolveProjectIdsFromNames($interestedProjectNames);

            if (blank($validated['use_end_use'] ?? null) && filled($validated['purpose'] ?? null)) {
                $validated['use_end_use'] = $validated['purpose'] === 'End Use' ? 'End User' : '2nd Investments';
            }

            if (blank($validated['property_type'] ?? null) && filled($validated['type'] ?? null)) {
                $validated['property_type'] = $validated['type'];
            }

            if (blank($validated['possession_status'] ?? null) && filled($validated['possession'] ?? null)) {
                $validated['possession_status'] = $validated['possession'];
            }

            if (blank($validated['requirements'] ?? null) && filled($validated['manager_remark'] ?? null)) {
                $validated['requirements'] = $validated['manager_remark'];
            }

            $lead = Lead::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'phone_country_iso' => $validated['phone_country_iso'] ?? null,
                'created_by' => $validated['created_by'],
                'status' => $validated['status'],
                'source' => $validated['source'],
                'preferred_location' => $validated['preferred_location'] ?? null,
                'budget' => $validated['budget'] ?? null,
                'property_type' => $validated['property_type'] ?? null,
                'use_end_use' => $validated['use_end_use'] ?? null,
                'possession_status' => $validated['possession_status'] ?? null,
                'preferred_projects' => !empty($matchedProjectIds)
                    ? json_encode($matchedProjectIds)
                    : (isset($validated['preferred_projects']) && is_array($validated['preferred_projects'])
                        ? json_encode($validated['preferred_projects'])
                        : null),
                'requirements' => $validated['requirements'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->storeRequirementSnapshot($lead, $validated, $interestedProjectNames, $user->id, $request);
            $assignment = $this->assignLead($lead, $ownerId, $user->id);

            try {
                app(\App\Services\NewLeadSlaAutomationService::class)->syncForAssignment($lead, $assignment);
            } catch (\Throwable $e) {
                report($e);
            }

            event(new LeadAssigned($lead, $ownerId, $user->id));

            $leadOwnerTaskService->ensureOpenTaskForOwner(
                $lead,
                $owner,
                $user->id,
                'Lead created from sales manager workspace.'
            );

            DB::commit();

            return redirect()
                ->route('leads.show', $lead->id)
                ->with('success', 'Lead created and calling task assigned.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withErrors(['error' => 'Failed to create lead: ' . $e->getMessage()])
                ->withInput();
        }
    }

    private function ensureCanCreate(User $user): void
    {
        abort_unless(
            $user->isAssistantSalesManager() || $user->isSeniorManager() || $user->isSalesManager(),
            403
        );
    }

    private function allowedOwners(User $user)
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->where('id', $user->id)
                    ->orWhere('manager_id', $user->id);
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$user->id])
            ->orderBy('name')
            ->get();
    }

    private function assignLead(Lead $lead, int $assignedTo, int $assignedBy): LeadAssignment
    {
        $lead->assignments()->update([
            'is_active' => false,
            'unassigned_at' => now(),
        ]);

        return LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedBy,
            'assignment_type' => 'primary',
            'assignment_method' => 'manual',
            'notes' => 'Lead created from sales manager workspace.',
            'assigned_at' => now(),
            'is_active' => true,
        ]);
    }

    private function storeRequirementSnapshot(Lead $lead, array $validated, array $interestedProjectNames, int $userId, Request $request): void
    {
        $formFields = [
            'source' => $request->filled('source') ? Lead::displaySourceLabel($request->input('source')) : null,
            'category' => $validated['category'] ?? null,
            'preferred_location' => $validated['preferred_location'] ?? null,
            'budget' => $validated['budget'] ?? null,
            'type' => $validated['type'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
            'possession' => $validated['possession'] ?? null,
            'lead_status' => $validated['lead_status'] ?? null,
            'lead_quality' => $validated['lead_quality'] ?? null,
            'interested_projects' => !empty($interestedProjectNames)
                ? array_map(fn ($name) => ['name' => (string) $name], $interestedProjectNames)
                : null,
            'customer_job' => $validated['customer_job'] ?? null,
            'industry_sector' => $validated['industry_sector'] ?? null,
            'buying_frequency' => $validated['buying_frequency'] ?? null,
            'living_city' => $validated['living_city'] ?? null,
            'city_type' => $validated['city_type'] ?? null,
            'manager_remark' => $validated['manager_remark'] ?? null,
        ];

        foreach ($formFields as $fieldKey => $fieldValue) {
            if ($fieldValue === null || $fieldValue === '') {
                continue;
            }

            $lead->setFormFieldValue($fieldKey, $fieldValue, $userId);
        }
    }

    private function parseInterestedProjects($rawValue): array
    {
        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            $rawValue = json_last_error() === JSON_ERROR_NONE ? $decoded : [$rawValue];
        }

        if (!is_array($rawValue)) {
            return [];
        }

        return collect($rawValue)
            ->map(function ($project) {
                if (is_array($project)) {
                    return trim((string) ($project['name'] ?? ''));
                }

                return trim((string) $project);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveProjectIdsFromNames(array $projectNames): array
    {
        if (empty($projectNames)) {
            return [];
        }

        return Project::query()
            ->whereIn('name', $projectNames)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function buildInterestedProjectOptions($projects, $leadDetailRequirementsForm): array
    {
        $configuredOptions = collect($leadDetailRequirementsForm?->fields ?? [])
            ->firstWhere('field_key', 'interested_projects')
            ?->options ?? [];

        return collect($configuredOptions)
            ->merge(collect($projects)->pluck('name')->all())
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
