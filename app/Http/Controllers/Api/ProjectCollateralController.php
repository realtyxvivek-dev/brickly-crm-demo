<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCollateral;
use App\Services\CollateralService;
use Illuminate\Http\Request;

class ProjectCollateralController extends Controller
{
    protected $collateralService;

    public function __construct(CollateralService $collateralService)
    {
        $this->collateralService = $collateralService;
    }

    /**
     * Display a listing of collaterals for a project.
     */
    public function index(Project $project)
    {
        $collaterals = $project->collaterals()->latest()->get();
        return response()->json($collaterals);
    }

    /**
     * Get collaterals as buttons data.
     */
    public function buttons(Project $project)
    {
        $buttons = $this->collateralService->generateButtonData($project);
        return response()->json($buttons);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Project $project)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'category' => 'required|in:brochure,floor_plans,layout_plan,price_sheet,videos,legal_approvals,other',
            'title' => 'required|string|max:255',
            'link' => 'required|url',
            'is_latest' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        // Validate link type
        if (!$this->collateralService->validateLink($validated['link'])) {
            return response()->json(['message' => 'Invalid link. Must be YouTube or Google Drive URL.'], 400);
        }

        $collateral = $this->collateralService->addCollateral($project, $validated);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($collateral, 201);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Collateral added successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProjectCollateral $collateral)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'category' => 'sometimes|in:brochure,floor_plans,layout_plan,price_sheet,videos,legal_approvals,other',
            'title' => 'sometimes|string|max:255',
            'link' => 'sometimes|url',
            'is_latest' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        // Validate link type if link is being updated
        if (isset($validated['link']) && !$this->collateralService->validateLink($validated['link'])) {
            return response()->json(['message' => 'Invalid link. Must be YouTube or Google Drive URL.'], 400);
        }

        $collateral = $this->collateralService->updateCollateral($collateral, $validated);

        return response()->json($collateral);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectCollateral $collateral)
    {
        $user = request()->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $collateral->delete();

        return response()->json(['message' => 'Collateral deleted successfully']);
    }
}
