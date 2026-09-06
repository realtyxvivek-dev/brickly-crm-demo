<?php

namespace App\Http\Controllers;

use App\Models\Builder;
use App\Models\Project;
use App\Services\ProjectService;
use App\Services\PricingService;
use App\Services\CollateralService;
use App\Services\ProjectPublicPageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    protected $projectService;
    protected $pricingService;
    protected $collateralService;
    protected $publicPageService;

    public function __construct(
        ProjectService $projectService,
        PricingService $pricingService,
        CollateralService $collateralService,
        ProjectPublicPageService $publicPageService
    ) {
        $this->middleware('auth');
        $this->projectService = $projectService;
        $this->pricingService = $pricingService;
        $this->collateralService = $collateralService;
        $this->publicPageService = $publicPageService;
    }

    public function index(Request $request)
    {
        // All authenticated users can view projects list
        $query = Project::query()->with([
            'builder',
            'pricingConfig',
            'publicPage',
            'shareLinks' => fn ($shareLinks) => $shareLinks->orderByDesc('id'),
        ]);

        // Filter by builder
        if ($request->has('builder_id') && $request->builder_id) {
            $query->where('builder_id', $request->builder_id);
        }

        // Filter by project type
        if ($request->has('project_type') && $request->project_type) {
            $query->where('project_type', $request->project_type);
        }

        // Filter by status
        if ($request->has('project_status') && $request->project_status) {
            $query->where('project_status', $request->project_status);
        }

        // Filter by city
        if ($request->has('city') && $request->city) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        $projects = $query->latest()->paginate(15);
        $builders = \App\Models\Builder::where('status', 'active')->get();

        return view('projects.index', compact('projects', 'builders'));
    }

    public function create()
    {
        $currentUser = request()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        // Get all active builders, or include the selected one even if inactive
        $builders = Builder::where('status', 'active')->with('activeContacts')->get();
        
        // If a builder was just created, make sure it's in the list
        if (session('selected_builder_id')) {
            $selectedBuilder = Builder::with('activeContacts')->find(session('selected_builder_id'));
            if ($selectedBuilder && !$builders->contains('id', $selectedBuilder->id)) {
                $builders->push($selectedBuilder);
            }
        }

        return view('projects.form', ['project' => null, 'builders' => $builders]);
    }

    protected function resolveProjectRedirect(Request $request, Project $project, bool $created = false)
    {
        $nextAction = $request->input('next_action', 'stay');
        $requestedAnchor = ltrim((string) $request->input('next_anchor', 'project-core'), '#');
        $allowedAnchors = [
            'project-core',
            'project-location',
            'project-pricing',
            'project-media',
            'project-cta',
            'project-publish',
        ];

        if (!in_array($requestedAnchor, $allowedAnchors, true)) {
            $requestedAnchor = 'project-core';
        }

        $anchor = match ($nextAction) {
            'continue' => '#' . $requestedAnchor,
            'preview' => '#project-publish',
            'publish' => '#project-publish',
            default => '#' . $requestedAnchor,
        };

        return redirect()
            ->to(route('projects.edit', $project) . $anchor)
            ->with('success', $created ? 'Project draft saved successfully. Continue adding details on this page.' : 'Project draft updated successfully.');
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'builder_id' => 'nullable|exists:builders,id',
            'name' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'short_overview' => 'nullable|string|max:2000',
            'project_type' => 'nullable|in:residential,commercial,mixed',
            'residential_sub_type' => 'nullable|in:plot,flat,villa',
            'project_status' => 'nullable|in:prelaunch,under_construction,ready',
            'availability_type' => 'nullable|in:fresh,resale,both',
            'city' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'land_area' => 'nullable|numeric|min:0',
            'land_area_unit' => 'nullable|in:acres,sq_ft',
            'rera_no' => 'nullable|string|max:255',
            'rera_qr' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'possession_date' => 'nullable|date',
            'project_highlights' => 'nullable|string',
            'configuration_summary' => 'nullable|array',
            'configuration_summary.*' => 'in:studio,1bhk,2bhk,3bhk,4bhk,other',
            'contacts.primary' => 'nullable|exists:builder_contacts,id',
            'contacts.secondary' => 'nullable|exists:builder_contacts,id',
            'contacts.escalation' => 'nullable|exists:builder_contacts,id',
            'bsp_per_sqft' => 'nullable|numeric|min:0',
            'price_rounding_rule' => 'nullable|in:none,nearest_1000,nearest_10000',
            'unit_types' => 'nullable|array',
            'unit_types.*.unit_type' => 'nullable|string|max:255',
            'unit_types.*.area_sqft' => 'nullable|numeric|min:0',
            'unit_types.*.floor_plan_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'towers' => 'nullable|array',
            'towers.*.tower_name' => 'nullable|string|max:255',
            'towers.*.tower_number' => 'nullable|integer',
            'towers.*.floor_count' => 'nullable|integer|min:1|max:300',
            'towers.*.unit_count' => 'nullable|integer|min:1|max:10000',
            'towers.*.unit_types' => 'nullable|array',
            'towers.*.unit_types.*.unit_type' => 'nullable|string|max:255',
            'towers.*.unit_types.*.area_sqft' => 'nullable|numeric|min:0',
            'towers.*.unit_types.*.floor_plan_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'collaterals' => 'nullable|array',
            'collaterals.*.category' => 'nullable|in:brochure,floor_plans,layout_plan,price_sheet,videos,legal_approvals,other',
            'collaterals.*.title' => 'nullable|string|max:255',
            'collaterals.*.link' => 'nullable|url|max:500',
            'collaterals.*.is_latest' => 'nullable|boolean',
            'presentation_fields_present' => 'nullable|boolean',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:500',
            'short_intro' => 'nullable|string|max:2000',
            'featured_badges' => 'nullable|string|max:1000',
            'hero_cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:8192',
            'builder_logo_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_images' => 'nullable|array|max:20',
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:8192',
            'map_embed' => 'nullable|string|max:2000',
            'location_summary' => 'nullable|string|max:2000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'map_zoom' => 'nullable|integer|between:1,20',
            'show_call' => 'nullable|boolean',
            'show_whatsapp' => 'nullable|boolean',
            'show_book_visit' => 'nullable|boolean',
            'show_request_callback' => 'nullable|boolean',
            'show_downloads' => 'nullable|boolean',
            'show_video' => 'nullable|boolean',
            'show_tour_360' => 'nullable|boolean',
            'call_phone' => 'nullable|string|max:30',
            'whatsapp_number' => 'nullable|string|max:30',
            'book_visit_url' => 'nullable|string|max:2000',
            'callback_url' => 'nullable|string|max:2000',
        ]);

        $validated['name'] = trim((string) ($validated['name'] ?? '')) ?: 'Untitled Project';
        $validated = $this->withoutPresentationData($validated);

        $contactIds = array_filter($validated['contacts'] ?? []);
        $contactIds = $contactIds ?: null;
        unset($validated['contacts']);

        $logo = $request->hasFile('logo') ? $request->file('logo') : null;
        unset($validated['logo']);

        $reraQrPath = $this->storeUploadedFile($request, 'rera_qr', 'project-rera-qr');
        if ($reraQrPath) {
            $validated['rera_qr_path'] = $reraQrPath;
        }
        unset($validated['rera_qr']);

        $bspPerSqft = $validated['bsp_per_sqft'] ?? null;
        $roundingRule = $validated['price_rounding_rule'] ?? 'none';
        unset($validated['bsp_per_sqft'], $validated['price_rounding_rule']);

        $unitTypes = $validated['unit_types'] ?? [];
        unset($validated['unit_types']);

        $towers = $validated['towers'] ?? [];
        unset($validated['towers']);

        $collaterals = $validated['collaterals'] ?? [];
        unset($validated['collaterals']);

        $project = $this->projectService->createProject($validated, $contactIds, $logo);

        // Set BSP if provided
        if ($bspPerSqft) {
            $this->pricingService->setBSP($project, $bspPerSqft, $roundingRule);
        }

        // Save unit types
        if (!empty($unitTypes)) {
            foreach ($unitTypes as $key => $unitTypeData) {
                if ($this->hasCompleteUnitType($unitTypeData)) {
                    $payload = $this->unitTypePayload($unitTypeData);
                    $floorPlanPath = $this->storeUploadedFile($request, "unit_types.$key.floor_plan_image", 'project-floor-plans');
                    if ($floorPlanPath) {
                        $payload['floor_plan_image'] = $floorPlanPath;
                    }

                    $unitType = $project->unitTypes()->create($payload);

                    // Calculate price if BSP is set
                    if ($bspPerSqft) {
                        $this->pricingService->calculateUnitPrice($unitType, $bspPerSqft);
                        $unitType->save();
                    }
                }
            }

            // Mark starting from if BSP is set
            if ($bspPerSqft) {
                $this->pricingService->markStartingFrom($project);
            }
        }

        // Handle towers (for flats)
        if (!empty($towers)) {
            foreach ($towers as $towerKey => $towerData) {
                if (filled($towerData['tower_name'] ?? null)) {
                    $tower = $this->projectService->createTower($project, [
                        'tower_name' => $towerData['tower_name'],
                        'tower_number' => $towerData['tower_number'] ?? null,
                        'floor_count' => filled($towerData['floor_count'] ?? null) ? (int) $towerData['floor_count'] : null,
                        'unit_count' => filled($towerData['unit_count'] ?? null) ? (int) $towerData['unit_count'] : null,
                    ]);

                    // Add unit types to tower
                    if (isset($towerData['unit_types']) && !empty($towerData['unit_types'])) {
                        foreach ($towerData['unit_types'] as $unitKey => $unitTypeData) {
                            if ($this->hasCompleteUnitType($unitTypeData)) {
                                $payload = $this->unitTypePayload($unitTypeData);
                                $payload['project_id'] = $project->id;
                                $floorPlanPath = $this->storeUploadedFile($request, "towers.$towerKey.unit_types.$unitKey.floor_plan_image", 'project-floor-plans');
                                if ($floorPlanPath) {
                                    $payload['floor_plan_image'] = $floorPlanPath;
                                }

                                $unitType = $tower->unitTypes()->create($payload);

                                // Calculate price if BSP is set
                                if ($bspPerSqft) {
                                    $this->pricingService->calculateUnitPrice($unitType, $bspPerSqft);
                                    $unitType->save();
                                }
                            }
                        }
                    }
                }
            }

            // Mark starting from if BSP is set
            if ($bspPerSqft) {
                $this->pricingService->markStartingFrom($project);
            }
        }

        // Handle collaterals
        if (!empty($collaterals)) {
            foreach ($collaterals as $collateralData) {
                if ($this->hasCompleteCollateral($collateralData)) {
                    $collateral = $project->collaterals()->create([
                        'category' => $collateralData['category'],
                        'title' => $collateralData['title'],
                        'link' => $collateralData['link'],
                        'is_latest' => isset($collateralData['is_latest']) && $collateralData['is_latest'] == '1',
                    ]);
                    
                    // If this is marked as latest price sheet, unmark others
                    if ($collateralData['category'] === 'price_sheet' && isset($collateralData['is_latest']) && $collateralData['is_latest'] == '1') {
                        $this->collateralService->markLatestPriceSheet($project, $collateral);
                    }
                }
            }
        }

        $this->publicPageService->syncFromMainBuilder($request, $project, $currentUser);

        return $this->resolveProjectRedirect($request, $project, true);
    }

    public function show(Project $project)
    {
        // All authenticated users can view project details
        $project->load([
            'builder',
            'projectContacts.builderContact',
            'pricingConfig',
            'unitTypes',
            'towers.unitTypes',
            'collaterals',
        ]);

        // Get collateral buttons
        $collateralButtons = $this->collateralService->generateButtonData($project);

        return view('projects.show', compact('project', 'collateralButtons'));
    }

    public function edit(Project $project)
    {
        $currentUser = request()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        $builders = Builder::where('status', 'active')->with('activeContacts')->get();
        $project->load(['projectContacts.builderContact', 'unitTypes', 'towers.unitTypes', 'pricingConfig', 'publicPage', 'publicAssets']);

        return view('projects.form', ['project' => $project, 'builders' => $builders]);
    }

    public function update(Request $request, Project $project)
    {
        $currentUser = $request->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'builder_id' => 'nullable|exists:builders,id',
            'name' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'short_overview' => 'nullable|string|max:2000',
            'project_type' => 'nullable|in:residential,commercial,mixed',
            'residential_sub_type' => 'nullable|in:plot,flat,villa',
            'project_status' => 'nullable|in:prelaunch,under_construction,ready',
            'availability_type' => 'nullable|in:fresh,resale,both',
            'city' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'land_area' => 'nullable|numeric|min:0',
            'land_area_unit' => 'nullable|in:acres,sq_ft',
            'rera_no' => 'nullable|string|max:255',
            'rera_qr' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'possession_date' => 'nullable|date',
            'project_highlights' => 'nullable|string',
            'configuration_summary' => 'nullable|array',
            'configuration_summary.*' => 'in:studio,1bhk,2bhk,3bhk,4bhk,other',
            'contacts.primary' => 'nullable|exists:builder_contacts,id',
            'contacts.secondary' => 'nullable|exists:builder_contacts,id',
            'contacts.escalation' => 'nullable|exists:builder_contacts,id',
            'bsp_per_sqft' => 'nullable|numeric|min:0',
            'price_rounding_rule' => 'nullable|in:none,nearest_1000,nearest_10000',
            'unit_types' => 'nullable|array',
            'unit_types.*.unit_type' => 'nullable|string|max:255',
            'unit_types.*.area_sqft' => 'nullable|numeric|min:0',
            'unit_types.*.floor_plan_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'towers' => 'nullable|array',
            'towers.*.tower_name' => 'nullable|string|max:255',
            'towers.*.tower_number' => 'nullable|integer',
            'towers.*.floor_count' => 'nullable|integer|min:1|max:300',
            'towers.*.unit_count' => 'nullable|integer|min:1|max:10000',
            'towers.*.unit_types' => 'nullable|array',
            'towers.*.unit_types.*.unit_type' => 'nullable|string|max:255',
            'towers.*.unit_types.*.area_sqft' => 'nullable|numeric|min:0',
            'towers.*.unit_types.*.floor_plan_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'collaterals' => 'nullable|array',
            'collaterals.*.category' => 'nullable|in:brochure,floor_plans,layout_plan,price_sheet,videos,legal_approvals,other',
            'collaterals.*.title' => 'nullable|string|max:255',
            'collaterals.*.link' => 'nullable|url|max:500',
            'collaterals.*.is_latest' => 'nullable|boolean',
            'presentation_fields_present' => 'nullable|boolean',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:500',
            'short_intro' => 'nullable|string|max:2000',
            'featured_badges' => 'nullable|string|max:1000',
            'hero_cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:8192',
            'builder_logo_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_images' => 'nullable|array|max:20',
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:8192',
            'map_embed' => 'nullable|string|max:2000',
            'location_summary' => 'nullable|string|max:2000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'map_zoom' => 'nullable|integer|between:1,20',
            'show_call' => 'nullable|boolean',
            'show_whatsapp' => 'nullable|boolean',
            'show_book_visit' => 'nullable|boolean',
            'show_request_callback' => 'nullable|boolean',
            'show_downloads' => 'nullable|boolean',
            'show_video' => 'nullable|boolean',
            'show_tour_360' => 'nullable|boolean',
            'call_phone' => 'nullable|string|max:30',
            'whatsapp_number' => 'nullable|string|max:30',
            'book_visit_url' => 'nullable|string|max:2000',
            'callback_url' => 'nullable|string|max:2000',
        ]);

        if (array_key_exists('name', $validated) && !filled($validated['name'])) {
            unset($validated['name']);
        }
        $validated = $this->withoutPresentationData($validated);

        $contactIds = array_filter($validated['contacts'] ?? []);
        $contactIds = $contactIds ?: null;
        unset($validated['contacts']);

        $logo = $request->hasFile('logo') ? $request->file('logo') : null;
        unset($validated['logo']);

        $reraQrPath = $this->storeUploadedFile($request, 'rera_qr', 'project-rera-qr', $project->rera_qr_path);
        if ($reraQrPath) {
            $validated['rera_qr_path'] = $reraQrPath;
        }
        unset($validated['rera_qr']);

        $bspPerSqft = $validated['bsp_per_sqft'] ?? null;
        $roundingRule = $validated['price_rounding_rule'] ?? null;
        unset($validated['bsp_per_sqft'], $validated['price_rounding_rule']);

        $unitTypes = $validated['unit_types'] ?? [];
        unset($validated['unit_types']);

        $towers = $validated['towers'] ?? [];
        unset($validated['towers']);

        $collaterals = $validated['collaterals'] ?? [];
        unset($validated['collaterals']);

        $project = $this->projectService->updateProject($project, $validated, $contactIds, $logo);

        // Update BSP if provided
        if ($bspPerSqft !== null) {
            $finalRoundingRule = $roundingRule ?? ($project->pricingConfig?->price_rounding_rule ?? 'none');
            $this->pricingService->setBSP($project, $bspPerSqft, $finalRoundingRule);
        } elseif ($roundingRule !== null && $project->pricingConfig) {
            // Update only rounding rule if BSP not changed
            $project->pricingConfig->update(['price_rounding_rule' => $roundingRule]);
            $this->pricingService->recalculateAllPrices($project);
        }

        // Handle unit types
        if (!empty($unitTypes)) {
            $existingUnitTypeIds = [];
            $newlyCreatedUnitTypeIds = [];
            
            foreach ($unitTypes as $key => $unitTypeData) {
                if ($this->hasCompleteUnitType($unitTypeData)) {
                    $payload = $this->unitTypePayload($unitTypeData);
                    $floorPlanPath = $this->storeUploadedFile($request, "unit_types.$key.floor_plan_image", 'project-floor-plans');

                    if ($floorPlanPath) {
                        $payload['floor_plan_image'] = $floorPlanPath;
                    }

                    // Check if key is numeric (existing ID) or starts with 'new_' (new entry)
                    if (is_numeric($key)) {
                        // Update existing unit type
                        $unitType = $project->unitTypes()->find($key);
                        if ($unitType) {
                            if ($floorPlanPath && $unitType->floor_plan_image && Storage::disk('public')->exists($unitType->floor_plan_image)) {
                                Storage::disk('public')->delete($unitType->floor_plan_image);
                            }

                            $unitType->update($payload);
                            $existingUnitTypeIds[] = $key;
                            
                            // Recalculate price
                            if ($project->pricingConfig) {
                                $this->pricingService->calculateUnitPrice($unitType);
                                $unitType->save();
                            }
                        }
                    } else {
                        // Create new unit type (key starts with 'new_')
                        $unitType = $project->unitTypes()->create($payload);
                        
                        // Track newly created ID
                        $newlyCreatedUnitTypeIds[] = $unitType->id;
                        
                        // Calculate price if BSP is set
                        if ($project->pricingConfig) {
                            $this->pricingService->calculateUnitPrice($unitType);
                            $unitType->save();
                        }
                    }
                }
            }
            
            // Delete unit types that were removed (not in the submitted list)
            // Merge existing and newly created IDs to preserve both
            $allPreservedIds = array_merge($existingUnitTypeIds, $newlyCreatedUnitTypeIds);
            if (!empty($allPreservedIds)) {
                $project->unitTypes()->whereNotIn('id', $allPreservedIds)->delete();
            } elseif (empty($unitTypes)) {
                // If no unit types submitted, delete all
                $project->unitTypes()->delete();
            }
            
            // Mark starting from
            if ($project->pricingConfig) {
                $this->pricingService->markStartingFrom($project);
            }
        }

        // Handle towers (for flats)
        if (!empty($towers)) {
            $existingTowerIds = [];
            $newlyCreatedTowerIds = [];
            
            foreach ($towers as $key => $towerData) {
                if (filled($towerData['tower_name'] ?? null)) {
                    if (is_numeric($key)) {
                        // Update existing tower
                        $tower = $project->towers()->find($key);
                        if ($tower) {
                            $tower->update([
                                'tower_name' => $towerData['tower_name'],
                                'tower_number' => $towerData['tower_number'] ?? null,
                                'floor_count' => filled($towerData['floor_count'] ?? null) ? (int) $towerData['floor_count'] : null,
                                'unit_count' => filled($towerData['unit_count'] ?? null) ? (int) $towerData['unit_count'] : null,
                            ]);
                            $existingTowerIds[] = $key;
                        }
                    } else {
                        // Create new tower
                        $tower = $this->projectService->createTower($project, [
                            'tower_name' => $towerData['tower_name'],
                            'tower_number' => $towerData['tower_number'] ?? null,
                            'floor_count' => filled($towerData['floor_count'] ?? null) ? (int) $towerData['floor_count'] : null,
                            'unit_count' => filled($towerData['unit_count'] ?? null) ? (int) $towerData['unit_count'] : null,
                        ]);
                        // Track newly created tower ID
                        $newlyCreatedTowerIds[] = $tower->id;
                    }

                    // Handle unit types for this tower
                    if (isset($towerData['unit_types']) && !empty($towerData['unit_types'])) {
                        $existingTowerUnitIds = [];
                        $newlyCreatedTowerUnitIds = [];
                        
                        foreach ($towerData['unit_types'] as $unitKey => $unitTypeData) {
                            if ($this->hasCompleteUnitType($unitTypeData)) {
                                $payload = $this->unitTypePayload($unitTypeData);
                                $floorPlanPath = $this->storeUploadedFile($request, "towers.$key.unit_types.$unitKey.floor_plan_image", 'project-floor-plans');

                                if ($floorPlanPath) {
                                    $payload['floor_plan_image'] = $floorPlanPath;
                                }

                                if (is_numeric($unitKey)) {
                                    // Update existing unit type
                                    $unitType = $tower->unitTypes()->find($unitKey);
                                    if ($unitType) {
                                        if ($floorPlanPath && $unitType->floor_plan_image && Storage::disk('public')->exists($unitType->floor_plan_image)) {
                                            Storage::disk('public')->delete($unitType->floor_plan_image);
                                        }

                                        $unitType->update($payload);
                                        $existingTowerUnitIds[] = $unitKey;
                                        
                                        // Recalculate price
                                        if ($project->pricingConfig) {
                                            $this->pricingService->calculateUnitPrice($unitType);
                                            $unitType->save();
                                        }
                                    }
                                } else {
                                    // Create new unit type
                                    $unitType = $tower->unitTypes()->create(array_merge($payload, [
                                        'project_id' => $project->id,
                                    ]));
                                    
                                    // Track newly created ID
                                    $newlyCreatedTowerUnitIds[] = $unitType->id;
                                    
                                    // Calculate price if BSP is set
                                    if ($project->pricingConfig) {
                                        $this->pricingService->calculateUnitPrice($unitType);
                                        $unitType->save();
                                    }
                                }
                            }
                        }
                        
                        // Delete removed unit types from tower
                        // Merge existing and newly created IDs to preserve both
                        $allPreservedTowerUnitIds = array_merge($existingTowerUnitIds, $newlyCreatedTowerUnitIds);
                        if (!empty($allPreservedTowerUnitIds)) {
                            $tower->unitTypes()->whereNotIn('id', $allPreservedTowerUnitIds)->delete();
                        } elseif (empty($towerData['unit_types'])) {
                            // If no unit types submitted, delete all
                            $tower->unitTypes()->delete();
                        }
                    }
                }
            }
            
            // Delete removed towers
            // Merge existing and newly created IDs to preserve both
            $allPreservedTowerIds = array_merge($existingTowerIds, $newlyCreatedTowerIds);
            if (!empty($allPreservedTowerIds)) {
                $project->towers()->whereNotIn('id', $allPreservedTowerIds)->delete();
            } elseif (empty($towers)) {
                // If no towers submitted, delete all
                $project->towers()->delete();
            }
            
            // Mark starting from if BSP is set
            if ($project->pricingConfig) {
                $this->pricingService->markStartingFrom($project);
            }
        }

        // Handle collaterals
        if (!empty($collaterals)) {
            $existingCollateralIds = [];
            $newlyCreatedCollateralIds = [];
            
            foreach ($collaterals as $key => $collateralData) {
                if ($this->hasCompleteCollateral($collateralData)) {
                    if (is_numeric($key)) {
                        // Update existing collateral
                        $collateral = $project->collaterals()->find($key);
                        if ($collateral) {
                            $collateral->update([
                                'category' => $collateralData['category'],
                                'title' => $collateralData['title'],
                                'link' => $collateralData['link'],
                                'is_latest' => isset($collateralData['is_latest']) && $collateralData['is_latest'] == '1',
                            ]);
                            $existingCollateralIds[] = $key;
                            
                            // If this is marked as latest price sheet, unmark others
                            if ($collateralData['category'] === 'price_sheet' && isset($collateralData['is_latest']) && $collateralData['is_latest'] == '1') {
                                $this->collateralService->markLatestPriceSheet($project, $collateral);
                            }
                        }
                    } else {
                        // Create new collateral
                        $collateral = $project->collaterals()->create([
                            'category' => $collateralData['category'],
                            'title' => $collateralData['title'],
                            'link' => $collateralData['link'],
                            'is_latest' => isset($collateralData['is_latest']) && $collateralData['is_latest'] == '1',
                        ]);
                        $newlyCreatedCollateralIds[] = $collateral->id;
                        
                        // If this is marked as latest price sheet, unmark others
                        if ($collateralData['category'] === 'price_sheet' && isset($collateralData['is_latest']) && $collateralData['is_latest'] == '1') {
                            $this->collateralService->markLatestPriceSheet($project, $collateral);
                        }
                    }
                }
            }
            
            // Delete collaterals that were removed
            $allPreservedCollateralIds = array_merge($existingCollateralIds, $newlyCreatedCollateralIds);
            if (!empty($allPreservedCollateralIds)) {
                $project->collaterals()->whereNotIn('id', $allPreservedCollateralIds)->delete();
            } elseif (empty($collaterals)) {
                // If no collaterals submitted, delete all
                $project->collaterals()->delete();
            }
        }

        $this->publicPageService->syncFromMainBuilder($request, $project, $currentUser);

        return $this->resolveProjectRedirect($request, $project);
    }

    protected function storeUploadedFile(Request $request, string $key, string $directory, ?string $oldPath = null): ?string
    {
        if (!$request->hasFile($key)) {
            return null;
        }

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return $request->file($key)->store($directory, 'public');
    }

    protected function withoutPresentationData(array $validated): array
    {
        foreach ([
            'presentation_fields_present',
            'hero_title',
            'hero_subtitle',
            'short_intro',
            'featured_badges',
            'hero_cover_image',
            'builder_logo_image',
            'gallery_images',
            'map_embed',
            'location_summary',
            'latitude',
            'longitude',
            'map_zoom',
            'show_call',
            'show_whatsapp',
            'show_book_visit',
            'show_request_callback',
            'show_downloads',
            'show_video',
            'show_tour_360',
            'call_phone',
            'whatsapp_number',
            'book_visit_url',
            'callback_url',
        ] as $key) {
            unset($validated[$key]);
        }

        return $validated;
    }

    protected function hasCompleteUnitType(array $unitTypeData): bool
    {
        return filled($unitTypeData['unit_type'] ?? null) && filled($unitTypeData['area_sqft'] ?? null);
    }

    protected function unitTypePayload(array $unitTypeData): array
    {
        return [
            'unit_type' => $unitTypeData['unit_type'],
            'area_sqft' => $unitTypeData['area_sqft'],
        ];
    }

    protected function hasCompleteCollateral(array $collateralData): bool
    {
        return filled($collateralData['category'] ?? null)
            && filled($collateralData['title'] ?? null)
            && filled($collateralData['link'] ?? null);
    }

    public function destroy(Project $project)
    {
        $currentUser = request()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }
}
