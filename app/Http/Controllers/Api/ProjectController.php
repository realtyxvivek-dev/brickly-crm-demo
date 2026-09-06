<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Builder;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    protected $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    /**
     * Display a listing of projects for a builder.
     */
    public function index(Request $request, ?Builder $builder = null)
    {
        $query = Project::with(['builder', 'primaryContact.builderContact', 'pricingConfig', 'unitTypes']);

        if ($builder) {
            $query->where('builder_id', $builder->id);
        }

        if ($request->has('builder_id')) {
            $query->where('builder_id', $request->builder_id);
        }

        if ($request->has('project_type')) {
            $query->where('project_type', $request->project_type);
        }

        if ($request->has('project_status')) {
            $query->where('project_status', $request->project_status);
        }

        if ($request->has('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('area', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $projects = $query->latest()->paginate($perPage);

        return response()->json($projects);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Builder $builder)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'project_type' => 'required|in:residential,commercial,mixed',
            'project_status' => 'required|in:prelaunch,under_construction,ready',
            'availability_type' => 'nullable|in:fresh,resale,both',
            'city' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            'land_area' => 'nullable|numeric|min:0',
            'land_area_unit' => 'nullable|in:acres,sq_ft',
            'rera_no' => 'nullable|string|max:255',
            'possession_date' => 'nullable|date',
            'project_highlights' => 'nullable|string',
            'configuration_summary' => 'nullable|array',
            'configuration_summary.*' => 'in:studio,1bhk,2bhk,3bhk,4bhk,other',
            'contacts' => 'nullable|array',
            'contacts.primary' => 'required_with:contacts|exists:builder_contacts,id',
            'contacts.secondary' => 'nullable|exists:builder_contacts,id',
            'contacts.escalation' => 'nullable|exists:builder_contacts,id',
        ]);

        $contactIds = $validated['contacts'] ?? null;
        unset($validated['contacts']);

        $validated['builder_id'] = $builder->id;

        // Validate contacts belong to builder
        if ($contactIds) {
            $builderContactIds = $builder->contacts()->pluck('id')->toArray();
            foreach ($contactIds as $contactId) {
                if ($contactId && !in_array($contactId, $builderContactIds)) {
                    return response()->json(['message' => 'Contact does not belong to this builder'], 400);
                }
            }
        }

        $project = $this->projectService->createProject($validated, $contactIds);

        return response()->json($project->load(['builder', 'projectContacts.builderContact', 'pricingConfig']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        $project->load([
            'builder',
            'projectContacts.builderContact',
            'pricingConfig',
            'unitTypes',
            'collaterals',
        ]);

        return response()->json($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'project_type' => 'sometimes|in:residential,commercial,mixed',
            'project_status' => 'sometimes|in:prelaunch,under_construction,ready',
            'availability_type' => 'sometimes|in:fresh,resale,both',
            'city' => 'sometimes|string|max:255',
            'area' => 'sometimes|string|max:255',
            'land_area' => 'nullable|numeric|min:0',
            'land_area_unit' => 'nullable|in:acres,sq_ft',
            'rera_no' => 'nullable|string|max:255',
            'possession_date' => 'nullable|date',
            'project_highlights' => 'nullable|string',
            'configuration_summary' => 'nullable|array',
            'configuration_summary.*' => 'in:studio,1bhk,2bhk,3bhk,4bhk,other',
            'contacts' => 'nullable|array',
            'contacts.primary' => 'required_with:contacts|exists:builder_contacts,id',
            'contacts.secondary' => 'nullable|exists:builder_contacts,id',
            'contacts.escalation' => 'nullable|exists:builder_contacts,id',
        ]);

        $contactIds = $validated['contacts'] ?? null;
        unset($validated['contacts']);

        // Validate contacts belong to builder
        if ($contactIds) {
            $builderContactIds = $project->builder->contacts()->pluck('id')->toArray();
            foreach ($contactIds as $contactId) {
                if ($contactId && !in_array($contactId, $builderContactIds)) {
                    return response()->json(['message' => 'Contact does not belong to this builder'], 400);
                }
            }
        }

        $project = $this->projectService->updateProject($project, $validated, $contactIds);

        return response()->json($project->load(['builder', 'projectContacts.builderContact', 'pricingConfig']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $user = request()->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }
}
