<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\UnitType;
use App\Services\PricingService;
use Illuminate\Http\Request;

class UnitTypeController extends Controller
{
    protected $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Display a listing of unit types for a project.
     */
    public function index(Project $project)
    {
        $unitTypes = $project->unitTypes()->latest()->get();
        return response()->json($unitTypes);
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
            'unit_type' => 'required|string|max:255',
            'area_sqft' => 'required|numeric|min:0.01',
        ]);

        $validated['project_id'] = $project->id;

        $unitType = UnitType::create($validated);

        // Calculate price if BSP exists
        if ($project->pricingConfig) {
            $price = $this->pricingService->calculateUnitPrice($unitType);
            $unitType->calculated_price = $price;
            $unitType->save();
        }

        // Mark starting from
        $this->pricingService->markStartingFrom($project);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($unitType->fresh(), 201);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Unit type added successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UnitType $unitType)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'unit_type' => 'sometimes|string|max:255',
            'area_sqft' => 'sometimes|numeric|min:0.01',
        ]);

        $unitType->update($validated);

        // Recalculate price if BSP exists
        if ($unitType->project->pricingConfig) {
            $price = $this->pricingService->calculateUnitPrice($unitType);
            $unitType->calculated_price = $price;
            $unitType->save();
        }

        // Mark starting from
        $this->pricingService->markStartingFrom($unitType->project);

        return response()->json($unitType->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UnitType $unitType)
    {
        $user = request()->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $project = $unitType->project;
        $unitType->delete();

        // Re-mark starting from
        $this->pricingService->markStartingFrom($project);

        return response()->json(['message' => 'Unit type deleted successfully']);
    }
}
