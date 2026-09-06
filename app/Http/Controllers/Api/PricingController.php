<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\PricingService;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    protected $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Get pricing config for project.
     */
    public function show(Project $project)
    {
        $pricingConfig = $project->pricingConfig;

        if (!$pricingConfig) {
            return response()->json(['message' => 'Pricing not configured'], 404);
        }

        return response()->json($pricingConfig);
    }

    /**
     * Update or create pricing config.
     */
    public function update(Request $request, Project $project)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'bsp_per_sqft' => 'required|numeric|min:0',
            'price_rounding_rule' => 'nullable|in:none,nearest_1000,nearest_10000',
        ]);

        $pricingConfig = $this->pricingService->setBSP(
            $project,
            $validated['bsp_per_sqft'],
            $validated['price_rounding_rule'] ?? 'none'
        );

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($pricingConfig->load('project'));
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'BSP updated successfully!');
    }
}
