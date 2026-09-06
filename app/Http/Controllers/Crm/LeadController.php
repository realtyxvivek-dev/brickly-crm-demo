<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Project;
use App\Models\User;
use App\Models\LeadAssignment;
use App\Events\LeadAssigned;
use App\Services\LeadDuplicateGuardService;
use App\Services\DynamicFormService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function create()
    {
        $users = User::where('is_active', true)
            ->whereHas('role', function($q) {
                $q->whereIn('slug', ['sales_manager', 'sales_executive']);
            })
            ->with('role')
            ->get();

        $projects = Project::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
        $leadDetailRequirementsForm = app(DynamicFormService::class)->getPublishedFormByLocation('lead-detail.requirements');
        $interestedProjectOptions = $this->buildInterestedProjectOptions($projects, $leadDetailRequirementsForm);

        return view('crm.automation.create-lead', compact('users', 'projects', 'interestedProjectOptions'));
    }

    public function checkDuplicate(Request $request, LeadDuplicateGuardService $leadDuplicateGuardService)
    {
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

    public function store(Request $request, LeadDuplicateGuardService $leadDuplicateGuardService)
    {
        // For CRM users, only name and phone are required
        // Detailed requirements will be filled later via centralized form
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'phone_country_iso' => 'nullable|string|size:2',
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
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $parsedPhone = app(\App\Services\DuplicateDetectionService::class)
            ->parsedLeadPhone($validated['phone'], $validated['phone_country_iso'] ?? null);
        if (!$parsedPhone) {
            return back()->withErrors(['phone' => 'Enter a valid phone number. Use + country code for non-Indian numbers.'])->withInput();
        }

        return $leadDuplicateGuardService->withPhoneLock($parsedPhone['normalized'], function (string $normalizedPhone) use ($validated, $request, $leadDuplicateGuardService, $parsedPhone) {
            if ($normalizedPhone !== '') {
                $validated['phone'] = $parsedPhone['e164'];
                $validated['normalized_phone'] = $normalizedPhone;
                $validated['phone_country_iso'] = $parsedPhone['country_iso'];
                $existingLead = $leadDuplicateGuardService->findExistingLeadByPhone($normalizedPhone);

                if ($existingLead) {
                    return back()
                        ->withErrors(['phone' => 'Lead already exists for this phone number.'])
                        ->with('duplicate_lead', $leadDuplicateGuardService->buildDuplicatePayload($existingLead))
                        ->withInput();
                }
            }

        DB::beginTransaction();
        try {
            $validated['created_by'] = $request->user()->id;
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

            $formFields = [
                'source' => $request->filled('source')
                    ? Lead::displaySourceLabel($request->input('source'))
                    : null,
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

                $lead->setFormFieldValue($fieldKey, $fieldValue, $request->user()->id);
            }

            if (!empty($validated['assigned_to'])) {
                $this->assignLead($lead, (int) $validated['assigned_to'], $request->user()->id);
            }

            DB::commit();

            $successMessage = $request->filled('assigned_to')
                ? "Lead '{$lead->name}' created successfully and assigned. A calling task has been created for the assigned user. Please fill the detailed requirements below."
                : "Lead '{$lead->name}' created successfully. Please fill the detailed requirements below.";

            return redirect()
                ->route('leads.edit', $lead->id)
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to create lead: ' . $e->getMessage()])
                ->withInput();
        }
        });
    }

    private function parseInterestedProjects($rawValue): array
    {
        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rawValue = $decoded;
            } else {
                $rawValue = [$rawValue];
            }
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

    private function assignLead(Lead $lead, int $assignedTo, int $assignedBy, bool $createCallingTask = false): void
    {
        $oldOwnerIds = $lead->assignments()
            ->where('is_active', true)
            ->pluck('assigned_to')
            ->filter(fn ($ownerId) => (int) $ownerId !== (int) $assignedTo)
            ->unique()
            ->values();

        // Deactivate existing assignments
        $lead->assignments()->update(['is_active' => false, 'unassigned_at' => now()]);

        // Create new assignment
        $assignment = LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedBy,
            'assignment_type' => 'primary',
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        app(\App\Services\NewLeadSlaAutomationService::class)->syncForAssignment($lead, $assignment);

        if ($oldOwnerIds->isNotEmpty()) {
            $lead->markAsFreshTransfer((int) $oldOwnerIds->first(), $assignedTo, $assignedBy);
        }

        // Create calling task if requested (for any role, not just telecallers)
        if ($createCallingTask) {
            try {
                // Check if task already exists
                $existingTask = \App\Models\Task::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedTo)
                    ->where('type', 'phone_call')
                    ->where('status', 'pending')
                    ->first();

                if (!$existingTask) {
                    $this->taskService->createPhoneCallTask($lead, $assignedTo, $assignedBy);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the lead creation
                \Illuminate\Support\Facades\Log::error("Failed to create calling task for lead {$lead->id}: " . $e->getMessage());
            }
        }

        // Fire event (listener CreateTelecallerTask will create calling task)
        event(new LeadAssigned($lead, $assignedTo, $assignedBy));

        // Fallback: ensure calling task exists for assignee (when admin/CRM assigns, task must appear for user)
        try {
            $assignee = \App\Models\User::with('role')->find($assignedTo);
            if ($assignee && $assignee->role) {
                $slug = $assignee->role->slug ?? '';
                $hasTask = false;
                if ($slug === \App\Models\Role::SALES_EXECUTIVE) {
                    $hasTask = \App\Models\TelecallerTask::where('lead_id', $lead->id)
                        ->where('assigned_to', $assignedTo)
                        ->whereIn('status', ['pending', 'in_progress'])->exists();
                    if (!$hasTask) {
                        $this->taskService->createPhoneCallTask($lead, $assignedTo, $assignedBy);
                    }
                } elseif (in_array($slug, [\App\Models\Role::SALES_MANAGER, \App\Models\Role::ASSISTANT_SALES_MANAGER])) {
                    $hasTask = \App\Models\Task::where('lead_id', $lead->id)
                        ->where('assigned_to', $assignedTo)
                        ->where('type', 'phone_call')
                        ->whereIn('status', ['pending', 'in_progress'])->exists();
                    if (!$hasTask) {
                        \App\Models\Task::create([
                            'lead_id' => $lead->id,
                            'assigned_to' => $assignedTo,
                            'type' => 'phone_call',
                            'title' => "Call lead: {$lead->name}",
                            'description' => "Phone call task for lead: {$lead->name} ({$lead->phone})",
                            'status' => 'pending',
                            'scheduled_at' => now()->addMinutes(10),
                            'created_by' => $assignedBy,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("CRM assignLead: fallback task creation failed for lead {$lead->id}: " . $e->getMessage());
        }
    }
}
