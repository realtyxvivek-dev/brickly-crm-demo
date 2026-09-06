<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\LoanPartnerBank;
use App\Models\ProjectShareLink;
use App\Models\ProjectSizeVariant;
use App\Services\ProjectPublicPageService;
use App\Services\TravelTime\TravelTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectPublicPageController extends Controller
{
    public function __construct(
        private readonly ProjectPublicPageService $service,
        private readonly TravelTimeService $travelTimeService,
    ) {
    }

    public function create()
    {
        return redirect()
            ->route('projects.create')
            ->with('status', 'Public page content is now managed from the single project builder page.');
    }

    public function edit(Project $project)
    {
        return $this->projectBuilderRedirect($project, 'project-core')
            ->with('status', 'Public page content is now managed on this project builder page.');
    }

    public function saveDraft(Request $request): RedirectResponse
    {
        $project = null;
        if ($request->filled('project_id')) {
            $project = Project::findOrFail($request->input('project_id'));
        }

        try {
            $project = $this->service->saveDraft($request, $project, $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'draft' => $exception->getMessage(),
            ]);
        }

        $step = (int) $request->input('next_step', $request->input('current_step', 1));
        $publishAfterSave = $request->boolean('publish_after_save');
        $forcePublish = $request->boolean('force_publish');

        if ($publishAfterSave) {
            try {
                $this->service->publish($project, $request->user(), $forcePublish);
            } catch (ValidationException $exception) {
                return $this->projectBuilderRedirect($project, 'project-publish')
                    ->with('publish_warnings', $exception->errors())
                    ->with('status', 'Draft saved successfully. Fix the remaining publish warnings or publish anyway.')
                    ->withInput();
            }

            $liveShareUrl = $this->resolveLiveShareUrl($project);

            return $this->projectBuilderRedirect($project, 'project-publish')
                ->with('status', $forcePublish ? 'Public page published with warnings. You can fix details later.' : 'Public page published successfully.')
                ->with('open_live_share_link', $liveShareUrl);
        }

        $anchor = match ($step) {
            3 => 'project-pricing',
            4 => 'project-media',
            5 => 'project-location',
            6 => 'project-publish',
            default => 'project-core',
        };

        return $this->projectBuilderRedirect($project, $anchor)
            ->with('status', 'Draft saved successfully.');
    }

    public function publish(Request $request, Project $project): RedirectResponse
    {
        $forcePublish = $request->boolean('force_publish');

        try {
            $this->service->publish($project, $request->user(), $forcePublish);
        } catch (ValidationException $exception) {
            return $this->projectBuilderRedirect($project, 'project-publish')
                ->with('publish_warnings', $exception->errors())
                ->withInput();
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'publish' => $exception->getMessage(),
            ]);
        }

        $liveShareUrl = $this->resolveLiveShareUrl($project);

        return $this->projectBuilderRedirect($project, 'project-publish')
            ->with('status', $forcePublish ? 'Public page published with warnings. You can fix details later.' : 'Public page published successfully.')
            ->with('open_live_share_link', $liveShareUrl);
    }

    public function resolveMapCoordinates(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'url' => 'required|string|max:2000',
        ]);

        $rawUrl = trim((string) $payload['url']);
        $host = strtolower((string) parse_url($rawUrl, PHP_URL_HOST));

        if (!$host || !collect(['google.com', 'google.co.in', 'goo.gl'])->contains(fn ($domain) => $host === $domain || Str::endsWith($host, '.' . $domain))) {
            return response()->json([
                'ok' => false,
                'message' => 'Google Maps URL required.',
            ], 422);
        }

        $effectiveUrl = $rawUrl;
        $coords = $this->parseCoordinatesFromMapString($effectiveUrl);

        if (!$coords) {
            try {
                $resolvedUri = null;

                Http::withOptions([
                    'allow_redirects' => true,
                    'on_stats' => static function ($stats) use (&$resolvedUri): void {
                        $resolvedUri = $stats->getEffectiveUri();
                    },
                ])
                    ->timeout(12)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123 Safari/537.36',
                    ])
                    ->get($rawUrl);

                if ($resolvedUri) {
                    $effectiveUrl = (string) $resolvedUri;
                    $coords = $this->parseCoordinatesFromMapString($effectiveUrl);
                }
            } catch (\Throwable) {
            }
        }

        if (!$coords) {
            return response()->json([
                'ok' => false,
                'message' => 'Valid Google Maps URL ya coordinates format nahi mila.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
            'resolved_url' => $effectiveUrl,
        ]);
    }

    public function fetchNearbyLandmarks(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'category' => 'nullable|string|in:school,hospital,hotel,bank,shopping_mall,post_office,bus_stop,temple,atm',
            'radius_km' => 'nullable|integer|in:2,5,10,20,50',
            'limit' => 'nullable|integer|in:5,10,20,50',
        ]);

        $latitude = (float) $payload['latitude'];
        $longitude = (float) $payload['longitude'];

        $categories = [
            'school' => ['type' => 'School', 'query' => 'school'],
            'hospital' => ['type' => 'Hospital', 'query' => 'hospital'],
            'hotel' => ['type' => 'Hotel', 'query' => 'hotel'],
            'bank' => ['type' => 'Bank', 'query' => 'bank'],
            'shopping_mall' => ['type' => 'Shopping Mall', 'query' => 'shopping mall'],
            'post_office' => ['type' => 'Post Office', 'query' => 'post office'],
            'bus_stop' => ['type' => 'Bus Stop', 'query' => 'bus stop'],
            'temple' => ['type' => 'Temple', 'query' => 'temple'],
            'atm' => ['type' => 'ATM', 'query' => 'atm'],
        ];

        $selectedCategory = (string) ($payload['category'] ?? 'school');
        $radiusKm = (int) ($payload['radius_km'] ?? 10);
        $limit = (int) ($payload['limit'] ?? 10);
        $selectedCategoryConfig = $categories[$selectedCategory] ?? $categories['school'];

        $latDelta = max(0.02, $radiusKm / 111);
        $lngDelta = max(0.02, $radiusKm / (111 * max(0.2, cos(deg2rad($latitude)))));
        $viewbox = implode(',', [
            $longitude - $lngDelta,
            $latitude + $latDelta,
            $longitude + $lngDelta,
            $latitude - $latDelta,
        ]);

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'CRM Nearby Landmarks/1.0',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'jsonv2',
                    'limit' => min(max($limit * 2, $limit), 50),
                    'q' => $selectedCategoryConfig['query'],
                    'bounded' => 1,
                    'viewbox' => $viewbox,
                    'addressdetails' => 1,
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Nearby search failed.');
            }

            $landmarks = collect($response->json() ?? [])
                ->filter(function (array $item) {
                    return filled($item['name'] ?? null) && is_numeric($item['lat'] ?? null) && is_numeric($item['lon'] ?? null);
                })
                ->map(function (array $item) use ($selectedCategoryConfig, $latitude, $longitude) {
                    $distanceKm = $this->distanceKm($latitude, $longitude, (float) $item['lat'], (float) $item['lon']);

                    return [
                        'label' => trim((string) ($item['name'] ?? '')),
                        'type' => $selectedCategoryConfig['type'],
                        'distance_text' => number_format($distanceKm, $distanceKm < 10 ? 2 : 1) . ' KM',
                        'distance_km' => $distanceKm,
                        'address' => trim((string) ($item['display_name'] ?? '')),
                    ];
                })
                ->filter(fn (array $landmark) => $landmark['label'] !== '')
                ->sortBy('distance_km')
                ->unique('label')
                ->take($limit)
                ->values()
                ->map(fn (array $landmark) => [
                    'label' => $landmark['label'],
                    'type' => $landmark['type'],
                    'distance_text' => $landmark['distance_text'],
                    'address' => $landmark['address'],
                ])
                ->all();
        } catch (\Throwable) {
            return response()->json([
                'ok' => false,
                'message' => 'Nearby landmarks fetch nahi ho paaya. Thodi der baad dubara try karo.',
            ], 502);
        }

        if (empty($landmarks)) {
            return response()->json([
                'ok' => false,
                'message' => 'Nearby landmarks nahi mile. Coordinates ya location area dobara check karo.',
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'category' => $selectedCategory,
            'category_label' => $selectedCategoryConfig['type'],
            'radius_km' => $radiusKm,
            'limit' => $limit,
            'landmarks' => $landmarks,
        ]);
    }

    private function resolveLiveShareUrl(Project $project): ?string
    {
        $project = $project->fresh(['shareLinks']);
        $shareLink = $project?->shareLinks->first();

        return $shareLink ? route('projects.public-share.show', $shareLink->token) : null;
    }

    public function preview(Project $project)
    {
        $project = $this->service->loadProject($project);

        return view('projects.public.share', $this->buildViewData($project, null, true));
    }

    public function showShare(string $token)
    {
        $shareLink = $this->service->resolveShareLink($token);

        if (!$shareLink || !$shareLink->isUsable()) {
            return response()->view('projects.public.unavailable', [
                'message' => 'This project link is no longer active.',
            ], 410);
        }

        $project = $shareLink->project;
        if (!$project->publicPage || $project->publicPage->status !== 'published') {
            return response()->view('projects.public.unavailable', [
                'message' => 'This project page is not available right now.',
            ], 404);
        }

        return view('projects.public.share', $this->buildViewData($project, $shareLink, false));
    }

    public function analytics(Project $project)
    {
        $summary = $this->service->analyticsSummary($project);
        $project = $this->service->loadProject($project);

        return view('projects.public.analytics', [
            'project' => $project,
            'publicPage' => $project->publicPage,
            'shareLink' => $project->shareLinks->first(),
            'summary' => $summary,
        ]);
    }

    public function recordPreviewEvent(Request $request, Project $project): JsonResponse
    {
        $this->service->recordEvent($project, null, $request, $this->validatedEventPayload($request));

        return response()->json(['ok' => true]);
    }

    public function recordShareEvent(Request $request, string $token): JsonResponse
    {
        $shareLink = $this->service->resolveShareLink($token);
        if (!$shareLink || !$shareLink->isUsable()) {
            return response()->json(['ok' => false], 410);
        }

        $this->service->recordEvent($shareLink->project, $shareLink, $request, $this->validatedEventPayload($request));

        return response()->json(['ok' => true]);
    }

    public function previewVariantPdf(Request $request, Project $project, ProjectSizeVariant $variant): BinaryFileResponse
    {
        abort_unless($variant->project_id === $project->id, 404);
        $path = $this->service->downloadVariantPdf($variant);
        abort_if(!$path, 404);

        $this->service->recordEvent($project, null, $request, $this->variantDownloadPayload($request, $variant));

        return response()->download($path);
    }

    public function shareVariantPdf(Request $request, string $token, ProjectSizeVariant $variant): BinaryFileResponse
    {
        $shareLink = $this->service->resolveShareLink($token);
        if (!$shareLink || !$shareLink->isUsable()) {
            abort(410);
        }

        abort_unless($variant->project_id === $shareLink->project_id, 404);
        $path = $this->service->downloadVariantPdf($variant);
        abort_if(!$path, 404);

        $this->service->recordEvent($shareLink->project, $shareLink, $request, $this->variantDownloadPayload($request, $variant));

        return response()->download($path);
    }

    public function regenerateVariantPdf(Project $project, ProjectSizeVariant $variant): RedirectResponse
    {
        abort_unless($variant->project_id === $project->id, 404);

        $project = $this->service->loadProject($project);
        $path = $this->service->regenerateVariantPdf($project, $variant);

        return $this->projectBuilderRedirect($project, 'project-pricing')
            ->with('status', $path ? 'Auto-generated PDF refreshed for the selected variant.' : 'Auto PDF could not be generated for this variant yet.');
    }

    public function regenerateAllVariantPdfs(Project $project): RedirectResponse
    {
        $count = $this->service->regenerateAllVariantPdfs($project);

        return $this->projectBuilderRedirect($project, 'project-pricing')
            ->with('status', $count > 0 ? "Auto-generated PDFs refreshed for {$count} variants." : 'No auto-generated PDFs were refreshed.');
    }

    public function previewAsset(Request $request, Project $project, ProjectAsset $asset)
    {
        abort_unless($asset->project_id === $project->id, 404);

        return $this->handleAssetResponse($request, $project, null, $asset);
    }

    public function shareAsset(Request $request, string $token, ProjectAsset $asset)
    {
        $shareLink = $this->service->resolveShareLink($token);
        if (!$shareLink || !$shareLink->isUsable()) {
            abort(410);
        }

        abort_unless($asset->project_id === $shareLink->project_id, 404);

        return $this->handleAssetResponse($request, $shareLink->project, $shareLink, $asset);
    }

    public function previewTravelSuggestions(Project $project, Request $request): JsonResponse
    {
        return response()->json([
            'results' => $this->travelTimeService->searchSuggestions($project, (string) $request->query('q', '')),
        ]);
    }

    public function shareTravelSuggestions(string $token, Request $request): JsonResponse
    {
        $shareLink = $this->service->resolveShareLink($token);
        if (!$shareLink || !$shareLink->isUsable()) {
            return response()->json(['results' => []], 404);
        }

        return response()->json([
            'results' => $this->travelTimeService->searchSuggestions($shareLink->project, (string) $request->query('q', ''), $shareLink->token),
        ]);
    }

    public function previewTravelRoute(Project $project, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'origin.label' => 'required|string|max:255',
            'origin.latitude' => 'required|numeric',
            'origin.longitude' => 'required|numeric',
            'origin.source' => 'nullable|string|max:40',
        ]);

        return response()->json(
            $this->travelTimeService->routeSummary($project, $payload['origin'], $payload['origin']['source'] ?? 'search')
        );
    }

    public function shareTravelRoute(string $token, Request $request): JsonResponse
    {
        $shareLink = $this->service->resolveShareLink($token);
        if (!$shareLink || !$shareLink->isUsable()) {
            return response()->json([
                'status' => 'route_unavailable',
                'message' => 'Travel time unavailable for this route.',
            ], 404);
        }

        $payload = $request->validate([
            'origin.label' => 'required|string|max:255',
            'origin.latitude' => 'required|numeric',
            'origin.longitude' => 'required|numeric',
            'origin.source' => 'nullable|string|max:40',
        ]);

        return response()->json(
            $this->travelTimeService->routeSummary($shareLink->project, $payload['origin'], $payload['origin']['source'] ?? 'search', $shareLink->token)
        );
    }

    public function revoke(ProjectShareLink $shareLink): RedirectResponse
    {
        $this->service->revokeShareLink($shareLink);

        return $this->projectBuilderRedirect($shareLink->project_id, 'project-publish')
            ->with('status', 'Share link revoked.');
    }

    public function reactivate(ProjectShareLink $shareLink): RedirectResponse
    {
        $this->service->reactivateShareLink($shareLink);

        return $this->projectBuilderRedirect($shareLink->project_id, 'project-publish')
            ->with('status', 'Share link reactivated.');
    }

    private function handleAssetResponse(Request $request, Project $project, ?ProjectShareLink $shareLink, ProjectAsset $asset)
    {
        $eventName = match ($asset->asset_type) {
            'video' => 'video_start',
            'tour_360' => 'tour_360_open',
            'price_sheet' => 'price_sheet_download',
            'brochure' => 'brochure_download',
            default => 'brochure_download',
        };

        $this->service->recordEvent($project, $shareLink, $request, [
            'event_name' => $eventName,
            'section' => (string) $request->query('section', 'media'),
            'session_id' => $request->query('session_id'),
            'meta' => array_filter([
                'asset_id' => $asset->id,
                'asset_type' => $asset->asset_type,
                'asset_title' => $asset->title ?: Str::headline(str_replace('_', ' ', $asset->asset_type)),
                'tracking_key' => $asset->tracking_key,
                'cta' => $request->query('cta'),
                'visit_id' => $request->query('visit_id'),
                'preview' => $request->boolean('preview'),
            ], fn ($value) => $value !== null && $value !== ''),
        ]);

        $target = $this->service->resolveAssetTarget($asset);
        abort_if(!$target, 404);

        if ($target['type'] === 'redirect') {
            return redirect()->away($target['target']);
        }

        return response()->download($target['target']);
    }

    private function buildViewData(Project $project, ?ProjectShareLink $shareLink, bool $isPreview): array
    {
        $project = $this->service->loadProject($project);
        $visibleVariants = $this->service->visibleVariants($project);
        $startingFrom = $this->service->startingFromPrice($project);
        $loanPartners = Schema::hasTable('loan_partner_banks')
            ? LoanPartnerBank::query()
                ->where('status', 'active')
                ->orderBy('display_order')
                ->orderBy('name')
                ->get()
            : collect();

        return [
            'project' => $project,
            'publicPage' => $project->publicPage,
            'unitTypes' => $project->publicUnitTypes->filter(fn ($unitType) => $unitType->sizeVariants->contains(
                fn ($variant) => $variant->visible_on_public_page && $variant->status !== 'hidden'
            )),
            'assets' => $project->publicAssets,
            'landmarks' => $project->publicLandmarks,
            'shareLink' => $shareLink,
            'isPreview' => $isPreview,
            'startingFrom' => $startingFrom,
            'advisorName' => $shareLink?->advisor?->name ?: 'Property Desk Advisor',
            'advisorPhone' => $project->publicPage?->call_phone,
            'visibleVariants' => $visibleVariants,
            'loanPartners' => $loanPartners,
            'travelTimeWidget' => array_merge(
                $this->travelTimeService->widgetPayload($project),
                [
                    'suggestions_url' => $isPreview
                        ? route('projects.public-pages.travel-time.suggestions', $project)
                        : route('projects.public-share.travel-time.suggestions', $shareLink->token),
                    'route_url' => $isPreview
                        ? route('projects.public-pages.travel-time.route', $project)
                        : route('projects.public-share.travel-time.route', $shareLink->token),
                ]
            ),
        ];
    }

    private function validatedEventPayload(Request $request): array
    {
        return $request->validate([
            'event_name' => 'required|in:page_view,section_view,floor_plan_view,details_pdf_download,price_sheet_download,brochure_download,video_start,tour_360_open,cta_click,duration',
            'section' => 'nullable|string|max:100',
            'session_id' => 'nullable|string|max:100',
            'duration_ms' => 'nullable|integer|min:0|max:600000',
            'meta' => 'nullable|array',
        ]);
    }

    private function variantDownloadPayload(Request $request, ProjectSizeVariant $variant): array
    {
        $variant->loadMissing('unitType');

        return [
            'event_name' => 'details_pdf_download',
            'section' => (string) $request->query('section', 'unit_plans'),
            'session_id' => $request->query('session_id'),
            'meta' => array_filter([
                'variant_id' => $variant->id,
                'variant_label' => trim(collect([$variant->unitType?->name, $variant->size_label])->filter()->implode(' - ')),
                'unit_type' => $variant->unitType?->name,
                'cta' => $request->query('cta', 'download_details_pdf'),
                'visit_id' => $request->query('visit_id'),
                'preview' => $request->boolean('preview'),
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function parseCoordinatesFromMapString(string $value): ?array
    {
        $patterns = [
            '/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
            '/[?&]q=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
            '/[?&]ll=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',
            '/(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $match)) {
                return [
                    'latitude' => (float) $match[1],
                    'longitude' => (float) $match[2],
                ];
            }
        }

        return null;
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1, sqrt($a)));
    }

    private function projectBuilderRedirect(Project|int $project, string $anchor = 'project-core'): RedirectResponse
    {
        $projectId = $project instanceof Project ? $project->id : $project;

        return redirect()->to(route('projects.edit', $projectId) . '#' . ltrim($anchor, '#'));
    }
}
