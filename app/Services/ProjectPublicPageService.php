<?php

namespace App\Services;

use App\Models\Builder;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\ProjectLandmark;
use App\Models\ProjectPageEvent;
use App\Models\ProjectPublicPage;
use App\Models\ProjectShareLink;
use App\Models\ProjectSizeVariant;
use App\Models\ProjectUnitType;
use App\Models\User;
use App\Services\TravelTime\TravelTimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectPublicPageService
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly SimpleProjectPdfService $simpleProjectPdfService,
        private readonly VisitorInsightService $visitorInsightService,
        private readonly ?TravelTimeService $travelTimeService = null,
    ) {
    }

    public function getWizardPayload(?Project $project): array
    {
        $builders = Builder::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        if (!$project) {
            return [
                'project' => null,
                'publicPage' => null,
                'unitTypes' => [],
                'landmarks' => [],
                'assets' => [],
                'shareLink' => null,
                'builders' => $builders,
                'variantPdfMeta' => [],
                'travelTimePreview' => ['origin_source' => 'search', 'popular_origins' => []],
            ];
        }

        $project = $this->loadProject($project);
        $variantPdfMeta = [];
        foreach ($project->publicUnitTypes as $unitType) {
            foreach ($unitType->sizeVariants as $variant) {
                $variantPdfMeta[$variant->id] = $this->getVariantPdfMeta($project, $variant);
            }
        }

        return [
            'project' => $project,
            'publicPage' => $project->publicPage,
            'unitTypes' => $project->publicUnitTypes,
            'landmarks' => $project->publicLandmarks,
            'assets' => $project->publicAssets,
                'shareLink' => $project->shareLinks->first(),
                'builders' => $builders,
                'variantPdfMeta' => $variantPdfMeta,
                'travelTimePreview' => $this->travelTimeService?->widgetPayload($project) ?? ['origin_source' => 'search', 'popular_origins' => []],
            ];
        }

    public function loadProject(Project $project): Project
    {
        return $project->load([
            'builder',
            'publicPage',
            'publicUnitTypes.sizeVariants',
            'publicAssets',
            'publicLandmarks',
            'shareLinks' => fn ($query) => $query->latest(),
        ]);
    }

    public function saveDraft(Request $request, ?Project $project, User $user): Project
    {
        return DB::transaction(function () use ($request, $project, $user) {
            $project = $this->saveProjectCore($request, $project);
            $publicPage = $this->savePublicPage($request, $project, $user);
            $this->syncLegacyPricingConfig($project, $publicPage);
            $this->syncUnitTypesAndVariants($request, $project, $publicPage);
            $this->syncAssets($request, $project);
            $this->syncLandmarks($request, $project);
            $this->syncShareLink($request, $project);

            return $this->loadProject($project->fresh());
        });
    }

    /**
     * Persist the public presentation fields exposed by the consolidated project
     * builder. Existing wizard data is preserved when a field is left untouched.
     */
    public function syncFromMainBuilder(Request $request, Project $project, User $user): ProjectPublicPage
    {
        return DB::transaction(function () use ($request, $project, $user) {
            $page = $project->publicPage()->firstOrNew();
            $isNew = !$page->exists;

            $values = [
                'hero_title' => $request->input('hero_title'),
                'hero_subtitle' => $request->input('hero_subtitle'),
                'short_intro' => $request->input('short_intro'),
                'map_embed' => $request->input('map_embed'),
                'location_summary' => $request->input('location_summary'),
                'latitude' => $this->numericOrNull($request->input('latitude')),
                'longitude' => $this->numericOrNull($request->input('longitude')),
                'map_zoom' => $request->filled('map_zoom') ? (int) $request->input('map_zoom') : null,
                'call_phone' => $request->input('call_phone'),
                'whatsapp_number' => $request->input('whatsapp_number'),
                'book_visit_url' => $request->input('book_visit_url'),
                'callback_url' => $request->input('callback_url'),
            ];

            foreach ($values as $field => $value) {
                if ($request->exists($field)) {
                    $page->{$field} = $value;
                }
            }

            if ($request->exists('featured_badges')) {
                $page->featured_badges = $this->splitLinesOrCsv($request->input('featured_badges'));
            }

            if ($request->boolean('presentation_fields_present')) {
                foreach (['show_call', 'show_whatsapp', 'show_book_visit', 'show_request_callback', 'show_downloads', 'show_video', 'show_tour_360'] as $field) {
                    $page->{$field} = $request->boolean($field);
                }
            }

            if ($isNew) {
                $page->hero_title = $page->hero_title ?: $project->name;
                $page->hero_subtitle = $page->hero_subtitle ?: collect([$project->city, $project->area])->filter()->join(', ');
                $page->short_intro = $page->short_intro ?: $project->short_overview;
                $page->location_summary = $page->location_summary ?: $project->short_overview;
                $page->status = 'draft';
                $page->preview_token = Str::random(32);
                $page->show_call = $request->boolean('show_call', true);
                $page->show_whatsapp = $request->boolean('show_whatsapp', true);
                $page->show_book_visit = $request->boolean('show_book_visit', true);
                $page->show_downloads = $request->boolean('show_downloads', true);
                $page->show_video = $request->boolean('show_video', true);
                $page->show_tour_360 = $request->boolean('show_tour_360', true);
            }

            $page->base_rate_per_sqft = $project->pricingConfig?->bsp_per_sqft;
            $page->rounding_rule = $project->pricingConfig?->price_rounding_rule ?: 'none';
            $page->last_saved_at = now();
            $page->last_saved_by = $user->id;

            if ($request->hasFile('hero_cover_image')) {
                $this->deletePublicFile($page->hero_cover_path);
                $page->hero_cover_path = $request->file('hero_cover_image')->store('project-public/hero', 'public');
            }

            if ($request->hasFile('builder_logo_image')) {
                $this->deletePublicFile($page->builder_logo_path);
                $page->builder_logo_path = $request->file('builder_logo_image')->store('project-public/logos', 'public');
            }

            $page->project()->associate($project);
            $page->save();

            foreach ($request->file('gallery_images', []) as $index => $image) {
                $order = (int) $project->publicAssets()->where('asset_type', 'gallery_image')->max('display_order') + 1;
                $project->publicAssets()->create([
                    'asset_type' => 'gallery_image',
                    'title' => 'Gallery Image ' . ($order + $index),
                    'mime_type' => $image->getClientMimeType(),
                    'source_type' => 'uploaded',
                    'file_size' => $image->getSize(),
                    'file_path' => $image->store('project-public/gallery', 'public'),
                    'tracking_key' => 'gallery-image-' . Str::random(8),
                    'display_order' => $order + $index,
                    'meta' => ['category' => 'real'],
                ]);
            }

            $this->syncCoreInventoryToPublic($project->fresh(['unitTypes', 'towers.unitTypes', 'pricingConfig']));

            return $page->fresh();
        });
    }

    private function syncCoreInventoryToPublic(Project $project): void
    {
        $coreUnits = $project->unitTypes
            ->concat($project->towers->flatMap(fn ($tower) => $tower->unitTypes))
            ->filter(fn ($unit) => filled($unit->unit_type) && filled($unit->area_sqft));

        // Preserve legacy wizard inventory when the consolidated builder has no units yet.
        if ($coreUnits->isEmpty()) {
            return;
        }

        $keptUnitTypeIds = [];
        $baseRate = $project->pricingConfig?->bsp_per_sqft;
        $roundingRule = $project->pricingConfig?->price_rounding_rule ?: 'none';

        foreach ($coreUnits->groupBy(fn ($unit) => trim((string) $unit->unit_type)) as $order => $units) {
            $name = (string) $units->first()->unit_type;
            $publicUnitType = $project->publicUnitTypes()->firstOrNew(['name' => $name]);
            $publicUnitType->fill([
                'display_order' => is_numeric($order) ? (int) $order : count($keptUnitTypeIds),
                'is_primary' => $keptUnitTypeIds === [],
            ]);
            $publicUnitType->save();
            $keptUnitTypeIds[] = $publicUnitType->id;
            $keptVariantIds = [];

            foreach ($units->values() as $variantOrder => $coreUnit) {
                $sizeLabel = number_format((float) $coreUnit->area_sqft, 0) . ' Sq.ft.';
                $variant = $publicUnitType->sizeVariants()->firstOrNew(['size_label' => $sizeLabel]);
                $variant->fill([
                    'project_id' => $project->id,
                    'carpet_area_sqft' => $coreUnit->area_sqft,
                    'builtup_area_sqft' => $coreUnit->area_sqft,
                    'base_rate_per_sqft' => $baseRate,
                    'rounding_rule' => $roundingRule,
                    'calculated_price' => $coreUnit->calculated_price,
                    'final_price' => $coreUnit->calculated_price,
                    'is_price_on_request' => !$coreUnit->calculated_price,
                    'status' => 'available',
                    'visible_on_public_page' => true,
                    'floor_plan_image_path' => $coreUnit->floor_plan_image ?: $variant->floor_plan_image_path,
                    'display_order' => $variantOrder,
                    'is_featured' => $variantOrder === 0,
                ]);
                $variant->save();
                $keptVariantIds[] = $variant->id;
            }

            $publicUnitType->sizeVariants()->whereNotIn('id', $keptVariantIds)->delete();
        }

        $staleUnitTypes = $project->publicUnitTypes()->whereNotIn('id', $keptUnitTypeIds)->with('sizeVariants')->get();
        foreach ($staleUnitTypes as $staleUnitType) {
            $staleUnitType->sizeVariants()->delete();
            $staleUnitType->delete();
        }
    }

    public function publish(Project $project, User $user, bool $force = false): ProjectPublicPage
    {
        $project = $this->loadProject($project);
        $page = $project->publicPage;

        if (!$page) {
            throw ValidationException::withMessages([
                'public_page' => 'Public page draft missing.',
            ]);
        }

        $visibleVariants = $this->visibleVariants($project);
        $visibleUnitTypes = $project->publicUnitTypes
            ->filter(fn (ProjectUnitType $unitType) => $unitType->sizeVariants->contains(
                fn (ProjectSizeVariant $variant) => $variant->visible_on_public_page && $variant->status !== 'hidden'
            ));

        $errors = [];

        if (!$page->hero_cover_path) {
            $errors['hero_cover_image'] = 'Hero image is required before publish.';
        }

        if ($visibleUnitTypes->isEmpty()) {
            $errors['unit_types'] = 'At least one visible unit type is required.';
        }

        if ($visibleVariants->isEmpty()) {
            $errors['variants'] = 'At least one visible size variant is required.';
        }

        foreach ($visibleVariants as $variant) {
            if (!$this->canResolveVariantPdf($project, $variant)) {
                $errors['variant_' . $variant->id . '_pdf'] = "{$variant->unitType?->name} {$variant->size_label} needs an uploaded details PDF or enough data for auto PDF generation.";
            }

            if (!$variant->final_price && !$variant->is_price_on_request) {
                $errors['variant_' . $variant->id . '_price'] = "{$variant->unitType?->name} {$variant->size_label} needs a final price or on-request pricing.";
            }
        }

        if ($page->show_call && blank($page->call_phone)) {
            $errors['call_phone'] = 'Call CTA is enabled but call phone is missing.';
        }

        if ($page->show_whatsapp && blank($page->whatsapp_number)) {
            $errors['whatsapp_number'] = 'WhatsApp CTA is enabled but WhatsApp number is missing.';
        }

        if ($page->show_book_visit && !$this->isValidUrl($page->book_visit_url)) {
            $errors['book_visit_url'] = 'Book visit CTA is enabled but URL is invalid.';
        }

        if ($page->show_request_callback && !$this->isValidUrl($page->callback_url)) {
            $errors['callback_url'] = 'Callback CTA is enabled but URL is invalid.';
        }

        if ($page->show_video && !$this->hasValidAsset($project, 'video')) {
            $errors['video'] = 'Video section is enabled but no valid video URL exists.';
        }

        if ($page->show_tour_360 && !$this->hasValidAsset($project, 'tour_360')) {
            $errors['tour_360'] = '360 section is enabled but no valid 360 URL exists.';
        }

        $shareLink = $project->shareLinks->first() ?: $this->ensureShareLink($project);
        if (!$shareLink->isUsable()) {
            $errors['share_link'] = 'At least one active share link is required before publish.';
        }

        if ($errors !== [] && !$force) {
            throw ValidationException::withMessages($errors);
        }

        $page->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $user->id,
            'last_saved_at' => now(),
            'last_saved_by' => $user->id,
        ]);

        return $page->fresh();
    }

    public function ensureShareLink(Project $project): ProjectShareLink
    {
        return $project->shareLinks()->firstOrCreate(
            ['advisor_id' => null, 'lead_id' => null],
            [
                'token' => Str::random(40),
                'status' => 'active',
            ]
        );
    }

    public function syncShareLink(Request $request, Project $project): ProjectShareLink
    {
        $shareLink = $this->ensureShareLink($project);
        $status = (string) $request->input('share_link_status', $shareLink->status);
        $expiresAt = $request->input('share_link_expires_at');

        $shareLink->fill([
            'status' => in_array($status, ['active', 'revoked', 'expired'], true) ? $status : 'active',
            'expires_at' => $expiresAt ?: null,
            'max_visits' => $request->input('share_link_max_visits') ?: null,
            'notes' => $request->input('share_link_notes'),
            'revoked_at' => $status === 'revoked' ? now() : null,
        ]);
        $shareLink->save();

        return $shareLink->fresh();
    }

    public function revokeShareLink(ProjectShareLink $shareLink): ProjectShareLink
    {
        $shareLink->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        return $shareLink->fresh();
    }

    public function reactivateShareLink(ProjectShareLink $shareLink): ProjectShareLink
    {
        $shareLink->update([
            'status' => 'active',
            'revoked_at' => null,
        ]);

        return $shareLink->fresh();
    }

    public function resolveShareLink(string $token): ?ProjectShareLink
    {
        $shareLink = ProjectShareLink::query()
            ->with(['project.builder', 'project.publicPage', 'project.publicUnitTypes.sizeVariants', 'project.publicAssets', 'project.publicLandmarks', 'advisor'])
            ->where('token', $token)
            ->first();

        if (!$shareLink) {
            return null;
        }

        if ($shareLink->status === 'active' && $shareLink->expires_at && $shareLink->expires_at->isPast()) {
            $shareLink->update(['status' => 'expired']);
            $shareLink->refresh();
        }

        if ($shareLink->status === 'active' && $shareLink->max_visits && $shareLink->view_count >= $shareLink->max_visits) {
            $shareLink->update(['status' => 'expired']);
            $shareLink->refresh();
        }

        return $shareLink;
    }

    public function recordEvent(Project $project, ?ProjectShareLink $shareLink, Request $request, array $data): void
    {
        $project->pageEvents()->create([
            'project_share_link_id' => $shareLink?->id,
            'event_name' => Str::slug((string) Arr::get($data, 'event_name', 'unknown'), '_'),
            'section' => Arr::get($data, 'section'),
            'session_id' => Arr::get($data, 'session_id'),
            'duration_ms' => max(0, (int) Arr::get($data, 'duration_ms', 0)),
            'meta' => $this->visitorInsightService->enrich(
                $request,
                Str::slug((string) Arr::get($data, 'event_name', 'unknown'), '_'),
                Arr::get($data, 'meta', [])
            ),
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip() . '|' . config('app.key')) : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'occurred_at' => now(),
        ]);

        if ($shareLink && Str::slug((string) Arr::get($data, 'event_name', ''), '_') === 'page_view') {
            $shareLink->forceFill([
                'view_count' => (int) $shareLink->view_count + 1,
                'last_viewed_at' => now(),
                'first_viewed_at' => $shareLink->first_viewed_at ?: now(),
            ])->save();
        }
    }

    public function analyticsSummary(Project $project): array
    {
        $project = Project::query()
            ->with([
                'shareLinks.advisor:id,name,email',
                'shareLinks.events' => fn ($query) => $query->orderBy('occurred_at', 'desc'),
                'pageEvents' => fn ($query) => $query->orderBy('occurred_at', 'desc'),
            ])
            ->findOrFail($project->id);

        $events = $project->pageEvents;
        $liveEvents = $events->reject(fn (ProjectPageEvent $event) => (bool) data_get($event->meta, 'preview', false))->values();
        $summaryEvents = $liveEvents->isNotEmpty() ? $liveEvents : $events;

        $viewEvents = $summaryEvents->where('event_name', 'page_view');
        $durationEvents = $summaryEvents->where('event_name', 'duration');
        $downloadEvents = $summaryEvents->whereIn('event_name', ['price_sheet_download', 'brochure_download', 'details_pdf_download']);
        $ctaEvents = $summaryEvents->where('event_name', 'cta_click');
        $sectionRows = $summaryEvents->where('event_name', 'section_view')
            ->filter(fn (ProjectPageEvent $event) => filled($event->section))
            ->groupBy('section')
            ->map(fn (Collection $rows, string $section) => [
                'section' => $section,
                'count' => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values();

        $shareLinkRows = $project->shareLinks
            ->map(function (ProjectShareLink $shareLink) use ($summaryEvents) {
                $rows = $summaryEvents->where('project_share_link_id', $shareLink->id)->values();
                $latest = $rows->sortByDesc('occurred_at')->first();
                $downloads = $rows->whereIn('event_name', ['price_sheet_download', 'brochure_download', 'details_pdf_download']);
                $topDownload = $downloads
                    ->map(fn (ProjectPageEvent $event) => $this->downloadLabel($event->meta ?? []))
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first();

                $latestVisitor = $this->visitorSummary($rows);

                return [
                    'id' => $shareLink->id,
                    'token' => $shareLink->token,
                    'status' => $shareLink->status,
                    'advisor_name' => $shareLink->advisor?->name,
                    'advisor_email' => $shareLink->advisor?->email,
                    'lead_id' => $shareLink->lead_id,
                    'notes' => $shareLink->notes,
                    'opens' => $rows->where('event_name', 'page_view')->count(),
                    'views' => $rows->count(),
                    'downloads' => $downloads->count(),
                    'cta_clicks' => $rows->where('event_name', 'cta_click')->count(),
                    'first_viewed_at' => $shareLink->first_viewed_at,
                    'last_viewed_at' => $shareLink->last_viewed_at,
                    'total_duration_ms' => (int) $rows->where('event_name', 'duration')->sum('duration_ms'),
                    'top_download' => $topDownload,
                    'latest_device' => $latestVisitor['device'],
                    'latest_browser' => $latestVisitor['browser'],
                    'latest_location' => collect([$latestVisitor['city'], $latestVisitor['region'], $latestVisitor['country']])->filter()->implode(', ') ?: 'Unavailable',
                    'latest_activity_at' => $latest?->occurred_at,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['last_viewed_at']?->timestamp ?? 0)
            ->values();

        $downloadRows = $downloadEvents
            ->groupBy(fn (ProjectPageEvent $event) => implode('|', [
                $event->event_name,
                (string) data_get($event->meta, 'asset_id', ''),
                (string) data_get($event->meta, 'variant_id', ''),
                (string) $this->downloadLabel($event->meta ?? []),
            ]))
            ->map(function (Collection $rows) {
                /** @var ProjectPageEvent $sample */
                $sample = $rows->first();
                return [
                    'event_name' => $sample->event_name,
                    'label' => $this->downloadLabel($sample->meta ?? []),
                    'asset_type' => data_get($sample->meta, 'asset_type'),
                    'count' => $rows->count(),
                    'section' => $sample->section,
                    'cta' => data_get($sample->meta, 'cta'),
                    'last_at' => $rows->max('occurred_at'),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $visitRows = $this->visitTimeline($summaryEvents);
        $visitor = $this->visitorSummary($summaryEvents);
        $ctaBreakdown = $ctaEvents
            ->map(fn (ProjectPageEvent $event) => (string) data_get($event->meta, 'cta', 'open'))
            ->filter()
            ->countBy()
            ->sortDesc();

        return [
            'totals' => [
                'share_links' => $project->shareLinks->count(),
                'opens' => $viewEvents->count(),
                'unique_visits' => $visitRows->count(),
                'total_active_time_ms' => (int) $durationEvents->sum('duration_ms'),
                'cta_clicks' => $ctaEvents->count(),
                'downloads' => $downloadEvents->count(),
                'top_cta' => $ctaBreakdown->keys()->first(),
                'top_section' => $sectionRows->first()['section'] ?? null,
                'top_price_sheet' => $downloadRows->firstWhere('event_name', 'price_sheet_download')['label'] ?? null,
                'top_details_pdf' => $downloadRows->firstWhere('event_name', 'details_pdf_download')['label'] ?? null,
                'last_activity_at' => $summaryEvents->max('occurred_at'),
            ],
            'cta_breakdown' => $ctaBreakdown,
            'section_rows' => $sectionRows,
            'download_rows' => $downloadRows,
            'share_link_rows' => $shareLinkRows,
            'visit_rows' => $visitRows,
            'visitor' => $visitor,
            'uses_preview_fallback' => $liveEvents->isEmpty() && $events->isNotEmpty(),
        ];
    }

    private function visitTimeline(Collection $events): Collection
    {
        return $events
            ->groupBy(function (ProjectPageEvent $event) {
                return data_get($event->meta, 'visit_id')
                    ?: $event->session_id
                    ?: 'legacy_' . optional($event->occurred_at)->format('YmdHi');
            })
            ->map(function (Collection $rows, string $visitId) {
                $rows = $rows->sortBy('occurred_at')->values();
                /** @var ProjectPageEvent|null $latest */
                $latest = $rows->last();
                $downloadLabels = $rows
                    ->whereIn('event_name', ['price_sheet_download', 'brochure_download', 'details_pdf_download'])
                    ->map(fn (ProjectPageEvent $event) => $this->downloadLabel($event->meta ?? []))
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'visit_id' => $visitId,
                    'started_at' => $rows->first()?->occurred_at,
                    'ended_at' => $latest?->occurred_at,
                    'duration_ms' => (int) $rows->where('event_name', 'duration')->sum('duration_ms'),
                    'event_count' => $rows->count(),
                    'actions_count' => $rows->whereIn('event_name', ['cta_click', 'price_sheet_download', 'brochure_download', 'details_pdf_download', 'video_start', 'tour_360_open'])->count(),
                    'last_cta' => $rows->where('event_name', 'cta_click')->last()?->meta['cta'] ?? null,
                    'downloads' => $downloadLabels,
                    'visitor' => $this->visitorSummary($rows),
                    'share_link_id' => $rows->first()?->project_share_link_id,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['started_at'] instanceof Carbon ? $row['started_at']->timestamp : 0)
            ->values();
    }

    private function visitorSummary(Collection $events): array
    {
        /** @var ProjectPageEvent|null $openEvent */
        $openEvent = $events
            ->where('event_name', 'page_view')
            ->sortByDesc('occurred_at')
            ->first();

        $meta = $openEvent?->meta ?? [];
        $device = $meta['device'] ?? [];
        $location = $meta['location'] ?? [];
        $hints = $meta['client_hints'] ?? [];

        return [
            'device' => $this->deviceLabel($device),
            'browser' => (string) ($device['browser'] ?? 'Unknown'),
            'os' => (string) ($device['os'] ?? 'Unknown'),
            'city' => (string) ($location['city'] ?? ''),
            'region' => (string) ($location['region'] ?? ''),
            'country' => (string) ($location['country'] ?? ''),
            'screen' => $this->formatScreen($hints),
            'language' => (string) ($hints['language'] ?? ''),
            'location_status' => (string) ($location['status'] ?? 'unavailable'),
        ];
    }

    private function deviceLabel(array $device): string
    {
        $type = match ((string) ($device['type'] ?? '')) {
            'mobile' => 'Mobile',
            'tablet' => 'Tablet',
            'desktop' => 'Desktop',
            default => 'Device',
        };

        $os = (string) ($device['os'] ?? '');

        return trim(collect([$os !== '' && $os !== 'Unknown' ? $os : null, $type])->filter()->implode(' ')) ?: 'Unknown';
    }

    private function formatScreen(array $hints): ?string
    {
        $width = (int) ($hints['screen_width'] ?? 0);
        $height = (int) ($hints['screen_height'] ?? 0);

        return $width > 0 && $height > 0 ? "{$width} x {$height}" : null;
    }

    private function downloadLabel(array $meta): string
    {
        return trim((string) (
            $meta['asset_title']
            ?? $meta['variant_label']
            ?? $meta['label']
            ?? $meta['title']
            ?? $meta['unit_type']
            ?? 'Untitled file'
        ));
    }

    public function visibleVariants(Project $project): Collection
    {
        return $project->publicUnitTypes
            ->flatMap(fn (ProjectUnitType $unitType) => $unitType->sizeVariants)
            ->filter(fn (ProjectSizeVariant $variant) => $variant->visible_on_public_page && $variant->status !== 'hidden')
            ->values();
    }

    public function startingFromPrice(Project $project): ?float
    {
        return $this->visibleVariants($project)
            ->filter(fn (ProjectSizeVariant $variant) => in_array($variant->status, ['available', 'hold'], true))
            ->whereNotNull('final_price')
            ->min('final_price');
    }

    public function downloadVariantPdf(ProjectSizeVariant $variant): ?string
    {
        if ($variant->details_pdf_path) {
            $fullPath = storage_path('app/public/' . $variant->details_pdf_path);
            if (is_file($fullPath)) {
                return $fullPath;
            }
        }

        $project = $variant->relationLoaded('project')
            ? $variant->project
            : Project::query()->with(['builder', 'publicPage', 'publicUnitTypes.sizeVariants'])->find($variant->project_id);

        if (!$project || !$this->canGenerateVariantPdf($project, $variant)) {
            return null;
        }

        return $this->ensureGeneratedVariantPdf($project, $variant);
    }

    public function getVariantPdfMeta(Project $project, ProjectSizeVariant $variant): array
    {
        $uploadedPath = $variant->details_pdf_path ? storage_path('app/public/' . $variant->details_pdf_path) : null;
        $generatedRelativePath = $this->generatedVariantPdfRelativePath($project, $variant);
        $generatedPath = storage_path('app/public/' . $generatedRelativePath);

        $source = 'missing';
        if ($uploadedPath && is_file($uploadedPath)) {
            $source = 'uploaded';
        } elseif (is_file($generatedPath)) {
            $source = 'generated';
        } elseif ($this->canGenerateVariantPdf($project, $variant)) {
            $source = 'auto_available';
        }

        return [
            'source' => $source,
            'uploaded' => $uploadedPath && is_file($uploadedPath),
            'generated' => is_file($generatedPath),
            'generated_at' => is_file($generatedPath) ? date('c', filemtime($generatedPath) ?: time()) : null,
            'can_generate' => $this->canGenerateVariantPdf($project, $variant),
        ];
    }

    public function regenerateVariantPdf(Project $project, ProjectSizeVariant $variant): ?string
    {
        if (!$this->canGenerateVariantPdf($project, $variant)) {
            return null;
        }

        $disk = Storage::disk('public');
        $this->cleanupGeneratedVariantPdfs($variant, $disk);

        return $this->ensureGeneratedVariantPdf($project, $variant);
    }

    public function regenerateAllVariantPdfs(Project $project): int
    {
        $project = $this->loadProject($project);
        $count = 0;

        foreach ($project->publicUnitTypes as $unitType) {
            foreach ($unitType->sizeVariants as $variant) {
                if ($variant->details_pdf_path) {
                    $uploadedPath = storage_path('app/public/' . $variant->details_pdf_path);
                    if (is_file($uploadedPath)) {
                        continue;
                    }
                }

                if ($this->regenerateVariantPdf($project, $variant)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function resolveAssetTarget(ProjectAsset $asset): ?array
    {
        if ($asset->source_type === 'external' && $this->isValidUrl($asset->external_url)) {
            return ['type' => 'redirect', 'target' => $asset->external_url];
        }

        if ($asset->file_path) {
            $fullPath = storage_path('app/public/' . $asset->file_path);
            if (is_file($fullPath)) {
                return ['type' => 'download', 'target' => $fullPath];
            }
        }

        return null;
    }

    private function saveProjectCore(Request $request, ?Project $project): Project
    {
        $projectData = [
            'builder_id' => $request->input('builder_id'),
            'name' => $request->input('name'),
            'project_type' => 'residential',
            'residential_sub_type' => $request->input('residential_sub_type') ?: 'flat',
            'project_status' => $request->input('project_status') ?: 'under_construction',
            'city' => $request->input('city'),
            'area' => $request->input('area'),
            'short_overview' => $request->input('short_overview'),
            'project_highlights' => $request->input('project_highlights'),
            'rera_no' => $request->input('rera_no'),
            'possession_date' => $request->input('possession_date'),
            'availability_type' => 'fresh',
            'is_active' => $request->boolean('is_active', true),
        ];

        if (!$project && (!$projectData['builder_id'] || !$projectData['name'] || !$projectData['city'] || !$projectData['area'])) {
            throw ValidationException::withMessages([
                'project' => 'Builder, project name, city, and locality are required to create a public page draft.',
            ]);
        }

        if ($project) {
            return $this->projectService->updateProject($project, array_filter($projectData, fn ($value) => $value !== null));
        }

        return $this->projectService->createProject(array_filter($projectData, fn ($value) => $value !== null));
    }

    private function savePublicPage(Request $request, Project $project, User $user): ProjectPublicPage
    {
        $baseRate = $this->numericOrNull($request->input('base_rate_per_sqft'));
        if ($baseRate === null) {
            throw ValidationException::withMessages([
                'base_rate_per_sqft' => 'Company Rate / Sq.ft. is required.',
            ]);
        }

        $latitude = $this->numericOrNull($request->input('latitude'));
        $longitude = $this->numericOrNull($request->input('longitude'));

        if (($latitude === null) xor ($longitude === null)) {
            throw ValidationException::withMessages([
                'latitude' => 'Latitude and longitude must be provided together.',
                'longitude' => 'Latitude and longitude must be provided together.',
            ]);
        }

        $page = $project->publicPage()->firstOrNew();

        $page->fill([
            'hero_title' => $request->input('hero_title'),
            'hero_subtitle' => $request->input('hero_subtitle'),
            'short_intro' => $request->input('short_intro'),
            'featured_badges' => $this->splitLinesOrCsv($request->input('featured_badges')),
            'map_embed' => $request->input('map_embed'),
            'location_summary' => $request->input('location_summary'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'map_zoom' => $request->filled('map_zoom') ? (int) $request->input('map_zoom') : null,
            'popular_origins' => $this->normalizePopularOrigins($request->input('popular_origins', [])),
            'other_charges' => $this->normalizeOtherCharges($request->input('other_charges', [])),
            'base_rate_per_sqft' => $baseRate,
            'rounding_rule' => $this->normalizePricingRule($request->input('rounding_rule')),
            'show_call' => $request->boolean('show_call', true),
            'show_whatsapp' => $request->boolean('show_whatsapp', true),
            'show_book_visit' => $request->boolean('show_book_visit', true),
            'show_request_callback' => $request->boolean('show_request_callback', false),
            'show_downloads' => $request->boolean('show_downloads', true),
            'show_video' => $request->boolean('show_video', true),
            'show_tour_360' => $request->boolean('show_tour_360', true),
            'call_phone' => $request->input('call_phone'),
            'whatsapp_number' => $request->input('whatsapp_number'),
            'book_visit_url' => $request->input('book_visit_url'),
            'callback_url' => $request->input('callback_url'),
            'status' => $request->input('status') ?: ($page->exists ? $page->status : 'draft'),
            'preview_token' => $page->preview_token ?: Str::random(32),
            'last_saved_at' => now(),
            'last_saved_by' => $user->id,
        ]);

        if ($request->hasFile('hero_cover_image')) {
            $this->deletePublicFile($page->hero_cover_path);
            $page->hero_cover_path = $request->file('hero_cover_image')->store('project-public/hero', 'public');
        }

        if ($request->hasFile('builder_logo_image')) {
            $this->deletePublicFile($page->builder_logo_path);
            $page->builder_logo_path = $request->file('builder_logo_image')->store('project-public/logos', 'public');
        }

        $page->project()->associate($project);
        $page->save();

        return $page;
    }

    private function syncLegacyPricingConfig(Project $project, ProjectPublicPage $page): void
    {
        if ($page->base_rate_per_sqft === null) {
            return;
        }

        $project->pricingConfig()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'bsp_per_sqft' => $page->base_rate_per_sqft,
                'price_rounding_rule' => $page->rounding_rule ?: 'none',
            ]
        );
    }

    private function syncUnitTypesAndVariants(Request $request, Project $project, ProjectPublicPage $publicPage): void
    {
        $unitTypeInput = $request->input('unit_types', []);
        $unitTypeFiles = $request->file('unit_types', []);
        $keepUnitTypeIds = [];

        foreach ($unitTypeInput as $unitIndex => $unitPayload) {
            if (blank(Arr::get($unitPayload, 'name'))) {
                continue;
            }

            $unitType = ProjectUnitType::query()->firstOrNew([
                'id' => Arr::get($unitPayload, 'id'),
            ]);

            $unitType->fill([
                'project_id' => $project->id,
                'name' => Arr::get($unitPayload, 'name'),
                'display_order' => (int) Arr::get($unitPayload, 'display_order', $unitIndex),
                'is_primary' => Arr::has($unitPayload, 'is_primary'),
            ]);
            $unitType->save();

            $keepUnitTypeIds[] = $unitType->id;
            $keepVariantIds = [];

            foreach (Arr::get($unitPayload, 'variants', []) as $variantIndex => $variantPayload) {
                if (blank(Arr::get($variantPayload, 'size_label'))) {
                    continue;
                }

                $variant = ProjectSizeVariant::query()->firstOrNew([
                    'id' => Arr::get($variantPayload, 'id'),
                ]);

                $builtup = $this->numericOrNull(Arr::get($variantPayload, 'builtup_area_sqft'));
                $baseRate = $publicPage->base_rate_per_sqft;
                $roundingRule = Arr::get($variantPayload, 'rounding_rule') ?: $publicPage->rounding_rule;
                $calculatedPrice = $this->calculatePrice($builtup, $baseRate, $roundingRule);
                $useManualPrice = Arr::has($variantPayload, 'use_manual_price');
                $manualPrice = $useManualPrice ? $this->numericOrNull(Arr::get($variantPayload, 'manual_price_override')) : null;
                $isOnRequest = Arr::has($variantPayload, 'is_price_on_request');

                $variant->fill([
                    'project_id' => $project->id,
                    'project_unit_type_id' => $unitType->id,
                    'size_label' => Arr::get($variantPayload, 'size_label'),
                    'carpet_area_sqft' => $this->numericOrNull(Arr::get($variantPayload, 'carpet_area_sqft')),
                    'builtup_area_sqft' => $builtup,
                    'base_rate_per_sqft' => $baseRate,
                    'rounding_rule' => $roundingRule ?: null,
                    'calculated_price' => $calculatedPrice,
                    'manual_price_override' => $manualPrice,
                    'final_price' => $isOnRequest ? null : ($manualPrice ?? $calculatedPrice),
                    'is_price_on_request' => $isOnRequest,
                    'status' => $this->normalizeVariantStatus((string) Arr::get($variantPayload, 'status', 'available')),
                    'visible_on_public_page' => Arr::has($variantPayload, 'visible_on_public_page'),
                    'display_order' => (int) Arr::get($variantPayload, 'display_order', $variantIndex),
                    'is_featured' => Arr::has($variantPayload, 'is_featured'),
                ]);

                $floorPlanFile = data_get($unitTypeFiles, "{$unitIndex}.variants.{$variantIndex}.floor_plan_image");
                if ($floorPlanFile) {
                    $this->deletePublicFile($variant->floor_plan_image_path);
                    $variant->floor_plan_image_path = $floorPlanFile->store('project-public/floor-plans', 'public');
                }

                $detailsFile = data_get($unitTypeFiles, "{$unitIndex}.variants.{$variantIndex}.details_pdf");
                if ($detailsFile) {
                    $this->deletePublicFile($variant->details_pdf_path);
                    $variant->details_pdf_path = $detailsFile->store('project-public/details-pdfs', 'public');
                }

                $variant->save();
                $keepVariantIds[] = $variant->id;
            }

            $staleVariants = $unitType->sizeVariants()->whereNotIn('id', $keepVariantIds ?: [0])->get();
            foreach ($staleVariants as $staleVariant) {
                $this->deletePublicFile($staleVariant->floor_plan_image_path);
                $this->deletePublicFile($staleVariant->details_pdf_path);
                $staleVariant->delete();
            }
        }

        $staleUnitTypes = $project->publicUnitTypes()->whereNotIn('id', $keepUnitTypeIds ?: [0])->with('sizeVariants')->get();
        foreach ($staleUnitTypes as $staleUnitType) {
            foreach ($staleUnitType->sizeVariants as $staleVariant) {
                $this->deletePublicFile($staleVariant->floor_plan_image_path);
                $this->deletePublicFile($staleVariant->details_pdf_path);
            }
            $staleUnitType->delete();
        }
    }

    private function syncAssets(Request $request, Project $project): void
    {
        $assetInput = $request->input('assets', []);
        $assetFiles = $request->file('assets', []);
        $keepIds = [];
        $featuredVideoId = null;

        foreach ($assetInput as $index => $assetPayload) {
            if (blank(Arr::get($assetPayload, 'asset_type'))) {
                continue;
            }

            $asset = ProjectAsset::query()->firstOrNew(['id' => Arr::get($assetPayload, 'id')]);
            $file = data_get($assetFiles, "{$index}.file");
            $preview = data_get($assetFiles, "{$index}.preview_image");
            $sourceType = $file ? 'uploaded' : ($this->isValidUrl(Arr::get($assetPayload, 'external_url')) ? 'external' : (Arr::get($assetPayload, 'source_type') ?: 'uploaded'));

            $asset->fill([
                'project_id' => $project->id,
                'asset_type' => Arr::get($assetPayload, 'asset_type'),
                'title' => Arr::get($assetPayload, 'title'),
                'mime_type' => $file ? $file->getClientMimeType() : Arr::get($assetPayload, 'mime_type'),
                'source_type' => $sourceType,
                'file_size' => $file ? $file->getSize() : Arr::get($assetPayload, 'file_size'),
                'external_url' => Arr::get($assetPayload, 'external_url'),
                'tracking_key' => Arr::get($assetPayload, 'tracking_key') ?: Str::slug((string) Arr::get($assetPayload, 'title', Arr::get($assetPayload, 'asset_type'))),
                'display_order' => (int) Arr::get($assetPayload, 'display_order', $index),
                'is_featured' => Arr::has($assetPayload, 'is_featured'),
                'meta' => Arr::get($assetPayload, 'meta', []),
            ]);

            if ($file) {
                $this->deletePublicFile($asset->file_path);
                $asset->file_path = $file->store('project-public/assets', 'public');
            }

            if ($preview) {
                $this->deletePublicFile($asset->preview_image_path);
                $asset->preview_image_path = $preview->store('project-public/asset-previews', 'public');
            }

            $asset->save();
            if ($asset->asset_type === 'video' && $asset->is_featured) {
                $featuredVideoId = $asset->id;
            }
            $keepIds[] = $asset->id;
        }

        if ($featuredVideoId) {
            $project->publicAssets()
                ->where('asset_type', 'video')
                ->where('id', '!=', $featuredVideoId)
                ->update(['is_featured' => false]);
        }

        $existingGalleryAssets = $project->publicAssets()->where('asset_type', 'gallery_image')->orderBy('display_order')->get()->keyBy('id');
        foreach ($request->input('gallery_asset_meta', []) as $assetId => $galleryMeta) {
            $galleryAsset = $existingGalleryAssets->get((int) $assetId);

            if (!$galleryAsset) {
                continue;
            }

            $galleryAsset->title = Arr::get($galleryMeta, 'title') ?: $galleryAsset->title;
            $galleryAsset->tracking_key = $galleryAsset->tracking_key ?: 'gallery-image-' . $galleryAsset->id;
            $galleryAsset->meta = array_merge($galleryAsset->meta ?? [], [
                'category' => Arr::get($galleryMeta, 'category', 'real'),
            ]);
            $galleryAsset->save();
            $keepIds[] = $galleryAsset->id;
        }

        $galleryFiles = $request->file('gallery_images', []);
        if ($galleryFiles) {
            $nextGalleryOrder = (int) $project->publicAssets()->where('asset_type', 'gallery_image')->max('display_order') + 1;

            foreach ($galleryFiles as $order => $galleryFile) {
                $asset = $project->publicAssets()->create([
                    'asset_type' => 'gallery_image',
                    'title' => 'Gallery Image ' . ($order + 1),
                    'mime_type' => $galleryFile->getClientMimeType(),
                    'source_type' => 'uploaded',
                    'file_size' => $galleryFile->getSize(),
                    'file_path' => $galleryFile->store('project-public/gallery', 'public'),
                    'tracking_key' => 'gallery-image-' . ($order + 1),
                    'display_order' => $nextGalleryOrder + $order,
                    'meta' => ['category' => 'real'],
                ]);
                $keepIds[] = $asset->id;
            }
        }

        $staleAssets = $project->publicAssets()
            ->where('asset_type', '!=', 'gallery_image')
            ->whereNotIn('id', $keepIds ?: [0])
            ->get();

        foreach ($staleAssets as $staleAsset) {
            $this->deletePublicFile($staleAsset->file_path);
            $this->deletePublicFile($staleAsset->preview_image_path);
            $staleAsset->delete();
        }
    }

    private function syncLandmarks(Request $request, Project $project): void
    {
        $keepIds = [];
        foreach ($request->input('landmarks', []) as $index => $payload) {
            if (blank(Arr::get($payload, 'label'))) {
                continue;
            }

            $landmark = ProjectLandmark::query()->firstOrNew(['id' => Arr::get($payload, 'id')]);
            $landmark->fill([
                'project_id' => $project->id,
                'label' => Arr::get($payload, 'label'),
                'type' => Arr::get($payload, 'type'),
                'distance_text' => Arr::get($payload, 'distance_text'),
                'display_order' => (int) Arr::get($payload, 'display_order', $index),
            ]);
            $landmark->save();
            $keepIds[] = $landmark->id;
        }

        $project->publicLandmarks()->whereNotIn('id', $keepIds ?: [0])->delete();
    }

    private function splitLinesOrCsv(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $value) ?: [];
        return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $parts)));
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizePopularOrigins(array $origins): array
    {
        return collect($origins)
            ->map(function ($origin, $index) {
                $label = trim((string) Arr::get($origin, 'label'));
                $latitude = $this->numericOrNull(Arr::get($origin, 'latitude'));
                $longitude = $this->numericOrNull(Arr::get($origin, 'longitude'));
                $category = trim((string) Arr::get($origin, 'category'));

                if ($label === '' || $latitude === null || $longitude === null) {
                    return null;
                }

                return [
                    'label' => $label,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'category' => $category,
                    'display_order' => (int) Arr::get($origin, 'display_order', $index),
                ];
            })
            ->filter()
            ->sortBy('display_order')
            ->values()
            ->all();
    }

    private function normalizeOtherCharges(array $charges): array
    {
        return collect($charges)
            ->map(function ($charge) {
                $name = trim((string) Arr::get($charge, 'name'));
                $value = trim((string) Arr::get($charge, 'value'));
                $type = $this->normalizeOtherChargeType(Arr::get($charge, 'type'));

                if ($name === '' && $value === '') {
                    return null;
                }

                if ($name === '') {
                    throw ValidationException::withMessages([
                        'other_charges' => 'Charge name is required for each additional charge row.',
                    ]);
                }

                if ($type === null) {
                    throw ValidationException::withMessages([
                        'other_charges' => 'Charge type is required for each additional charge row.',
                    ]);
                }

                if ($type !== 'on_request' && $value === '') {
                    throw ValidationException::withMessages([
                        'other_charges' => "Value is required for the {$name} charge.",
                    ]);
                }

                return [
                    'name' => $name,
                    'value' => $type === 'on_request' ? null : $value,
                    'type' => $type,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function calculatePrice(?float $builtupArea, ?float $baseRate, ?string $roundingRule): ?float
    {
        if (!$builtupArea || !$baseRate) {
            return null;
        }

        $price = $builtupArea * $baseRate;

        return match ($this->normalizePricingRule($roundingRule)) {
            'nearest_1000' => round($price / 1000) * 1000,
            'nearest_10000' => round($price / 10000) * 10000,
            'nearest_100000' => round($price / 100000) * 100000,
            default => $price,
        };
    }

    private function normalizePricingRule(?string $rule): string
    {
        return match ($rule) {
            'nearest_1000', '1000' => 'nearest_1000',
            'nearest_10000', '10000' => 'nearest_10000',
            'nearest_100000', '100000' => 'nearest_100000',
            default => 'none',
        };
    }

    private function normalizeOtherChargeType(mixed $type): ?string
    {
        return match ((string) $type) {
            'fixed' => 'fixed',
            'per_sqft', 'per_sq_ft', 'per-sqft' => 'per_sqft',
            'percent', 'percentage' => 'percent',
            'on_request', 'on-request' => 'on_request',
            default => null,
        };
    }

    private function normalizeVariantStatus(string $status): string
    {
        return in_array($status, ['available', 'hold', 'sold_out', 'hidden'], true) ? $status : 'available';
    }

    private function hasValidAsset(Project $project, string $assetType): bool
    {
        return $project->publicAssets
            ->where('asset_type', $assetType)
            ->contains(function (ProjectAsset $asset) {
                if ($asset->source_type === 'external') {
                    return $this->isValidUrl($asset->external_url);
                }

                return $asset->file_path && Storage::disk('public')->exists($asset->file_path);
            });
    }

    private function canResolveVariantPdf(Project $project, ProjectSizeVariant $variant): bool
    {
        if ($variant->details_pdf_path) {
            $uploadedPath = storage_path('app/public/' . $variant->details_pdf_path);
            if (is_file($uploadedPath)) {
                return true;
            }
        }

        return $this->canGenerateVariantPdf($project, $variant);
    }

    private function canGenerateVariantPdf(Project $project, ProjectSizeVariant $variant): bool
    {
        $page = $project->publicPage;

        if (!$page) {
            return false;
        }

        if (blank($project->name) && blank($page->hero_title)) {
            return false;
        }

        if (blank($variant->size_label)) {
            return false;
        }

        if (!$variant->builtup_area_sqft && !$variant->carpet_area_sqft) {
            return false;
        }

        if (!$variant->final_price && !$variant->is_price_on_request) {
            return false;
        }

        return true;
    }

    private function ensureGeneratedVariantPdf(Project $project, ProjectSizeVariant $variant): ?string
    {
        $relativePath = $this->generatedVariantPdfRelativePath($project, $variant);
        $disk = Storage::disk('public');

        if (!$disk->exists($relativePath)) {
            $this->cleanupGeneratedVariantPdfs($variant, $disk);
            $pdfBinary = $this->simpleProjectPdfService->generateVariantPdf($project, $variant);
            $disk->put($relativePath, $pdfBinary);
        }

        $fullPath = storage_path('app/public/' . $relativePath);

        return is_file($fullPath) ? $fullPath : null;
    }

    private function generatedVariantPdfRelativePath(Project $project, ProjectSizeVariant $variant): string
    {
        $hash = $this->generatedVariantPdfHash($project, $variant);

        return 'project-public/generated-pdfs/variant-' . $variant->id . '-' . $hash . '.pdf';
    }

    private function generatedVariantPdfHash(Project $project, ProjectSizeVariant $variant): string
    {
        return substr(sha1(json_encode([
            'pdf_template_version' => 'v2_3',
            'project_name' => $project->name,
            'hero_title' => $project->publicPage?->hero_title,
            'builder_name' => $project->builder?->name,
            'location_summary' => $project->publicPage?->location_summary,
            'hero_subtitle' => $project->publicPage?->hero_subtitle,
            'hero_cover_path' => $project->publicPage?->hero_cover_path,
            'builder_logo_path' => $project->publicPage?->builder_logo_path,
            'short_intro' => $project->publicPage?->short_intro,
            'featured_badges' => $project->publicPage?->featured_badges,
            'rera_no' => $project->rera_no,
            'possession_date' => optional($project->possession_date)?->format('Y-m-d'),
            'call_phone' => $project->publicPage?->call_phone,
            'whatsapp_number' => $project->publicPage?->whatsapp_number,
            'project_highlights' => $project->project_highlights,
            'unit_type' => $variant->unitType?->name,
            'size_label' => $variant->size_label,
            'builtup_area_sqft' => $variant->builtup_area_sqft,
            'carpet_area_sqft' => $variant->carpet_area_sqft,
            'base_rate_per_sqft' => $variant->base_rate_per_sqft,
            'final_price' => $variant->final_price,
            'is_price_on_request' => $variant->is_price_on_request,
            'floor_plan_image_path' => $variant->floor_plan_image_path,
        ])), 0, 12);
    }

    private function cleanupGeneratedVariantPdfs(ProjectSizeVariant $variant, $disk): void
    {
        foreach ($disk->files('project-public/generated-pdfs') as $path) {
            if (str_starts_with($path, 'project-public/generated-pdfs/variant-' . $variant->id . '-')) {
                $disk->delete($path);
            }
        }
    }

    private function isValidUrl(?string $value): bool
    {
        if (!$value) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function deletePublicFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
