@php
    $trackingClass = $trackingClass ?? 'js-customer-project-track';
    $advisorPhone = preg_replace('/\D+/', '', (string) ($advisorPhone ?? ''));
    $assetUrl = $assetUrl ?? fn ($asset) => $asset->external_url ?: $asset->file_url;
    $formatPrice = $formatPrice ?? function ($project) {
        $variants = $project->publicUnitTypes->flatMap->sizeVariants;
        $starting = $variants->where('is_price_on_request', false)->whereNotNull('final_price')->sortBy('final_price')->first();
        return $starting?->formatted_final_price ?: 'Price on request';
    };
    $projectImages = $projectImages ?? function ($project) use ($assetUrl) {
        $publicPage = $project->publicPage;
        $gallery = $project->publicAssets
            ->filter(fn ($asset) => $assetUrl($asset) && in_array($asset->asset_type, ['gallery_image', 'video', 'tour_360'], true))
            ->values();
        $images = collect();
        if ($publicPage?->hero_cover_url) {
            $images->push(['url' => $publicPage->hero_cover_url, 'title' => $project->name, 'type' => 'hero']);
        }
        foreach ($gallery as $asset) {
            $images->push(['url' => $assetUrl($asset), 'title' => $asset->title ?: $project->name, 'type' => $asset->asset_type, 'asset' => $asset]);
        }
        if ($images->isEmpty() && $project->logo_url) {
            $images->push(['url' => $project->logo_url, 'title' => $project->name, 'type' => 'logo']);
        }
        return $images->unique('url')->values();
    };
    $splitHighlights = $splitHighlights ?? function ($project, $publicPage) {
        $badges = collect($publicPage?->featured_badges ?? []);
        $lines = preg_split('/\r\n|\r|\n/', (string) ($project->project_highlights ?? '')) ?: [];
        return $badges->merge($lines)->map(fn ($item) => trim((string) $item))->filter()->unique()->take(10)->values();
    };
    $amenityIcon = $amenityIcon ?? function ($label) {
        $text = strtolower((string) $label);
        return match (true) {
            str_contains($text, 'club') => 'fa-champagne-glasses',
            str_contains($text, 'pool') || str_contains($text, 'swim') => 'fa-water-ladder',
            str_contains($text, 'gym') || str_contains($text, 'fitness') => 'fa-dumbbell',
            str_contains($text, 'park') || str_contains($text, 'green') || str_contains($text, 'garden') => 'fa-tree',
            str_contains($text, 'security') || str_contains($text, 'safe') => 'fa-shield-halved',
            str_contains($text, 'parking') => 'fa-square-parking',
            str_contains($text, 'school') => 'fa-graduation-cap',
            str_contains($text, 'hospital') => 'fa-house-medical',
            default => 'fa-circle-check',
        };
    };

    $publicPage = $project->publicPage;
    $images = $projectImages($project);
    $gallery = $project->publicAssets
        ->filter(fn ($asset) => $assetUrl($asset) && in_array($asset->asset_type, ['gallery_image', 'video', 'tour_360'], true))
        ->values();
    $downloads = $project->publicAssets
        ->filter(fn ($asset) => $assetUrl($asset) && in_array($asset->asset_type, ['brochure', 'price_sheet'], true))
        ->values();
    $variants = $project->publicUnitTypes->flatMap->sizeVariants->where('visible_on_public_page', true)->where('status', '!=', 'hidden')->values();
    $unitGroups = $project->publicUnitTypes
        ->filter(fn ($unit) => $unit->sizeVariants->contains(fn ($variant) => $variant->visible_on_public_page && $variant->status !== 'hidden'))
        ->values();
    $location = collect([$project->area, $project->city])->filter()->implode(', ');
    $whatsapp = preg_replace('/\D+/', '', (string) ($publicPage?->whatsapp_number ?? $advisorPhone));
    $callPhone = preg_replace('/\D+/', '', (string) ($publicPage?->call_phone ?? ''));
    $highlights = $splitHighlights($project, $publicPage);
    $unitNames = $unitGroups->pluck('name')->filter()->values();
@endphp

<article id="project-{{ $project->id }}" class="customer-project-block overflow-hidden rounded-[34px] border border-blue-100 bg-white shadow-[0_24px_70px_rgba(15,23,42,.10)] ring-1 ring-white/70" data-track-section="project_card" data-track-project="{{ $project->id }}">
    <div class="relative bg-slate-950">
        <div class="snap-x flex overflow-x-auto hide-scrollbar" data-carousel>
            @forelse($images as $image)
                <button type="button" class="snap-item {{ $trackingClass }} media-thumb min-w-full bg-slate-200 text-left" data-event="gallery_view" data-project-id="{{ $project->id }}" data-section="hero_gallery" data-image-url="{{ in_array($image['type'], ['gallery_image', 'hero', 'logo'], true) ? $image['url'] : '' }}" data-meta='{{ e(json_encode(["asset_type" => $image["type"], "title" => $image["title"]])) }}'>
                    <img src="{{ $image['url'] }}" alt="{{ $image['title'] }}" class="h-[360px] w-full object-cover sm:h-[500px]">
                </button>
            @empty
                <div class="grid h-[360px] min-w-full place-items-center bg-slate-100 text-sm font-bold text-slate-500 sm:h-[500px]">Project media will be shared by advisor</div>
            @endforelse
        </div>
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/70 via-slate-950/8 to-slate-950/10"></div>
        <div class="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-2 text-xs font-bold uppercase tracking-[0.1em] text-blue-700 shadow-lg shadow-slate-950/10">
            {{ $project->builder?->name ?: 'Builder' }}
        </div>
        <div class="absolute bottom-4 left-4 right-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl text-white">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-white/70">{{ $location ?: 'Location on request' }}</p>
                <h2 class="mt-1 text-3xl font-bold leading-tight sm:text-5xl">{{ $publicPage?->hero_title ?: $project->name }}</h2>
                @if($publicPage?->hero_subtitle)
                    <p class="mt-2 max-w-xl text-sm font-semibold leading-6 text-white/82">{{ $publicPage->hero_subtitle }}</p>
                @endif
            </div>
            <div class="rounded-[24px] border border-white/20 bg-white/95 p-4 text-slate-950 shadow-2xl shadow-slate-950/20 backdrop-blur sm:min-w-[220px]">
                <p class="text-[11px] font-black uppercase tracking-[0.14em] text-blue-600">Starting from</p>
                <p class="mt-1 text-2xl font-black text-blue-950">{{ $formatPrice($project) }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $unitNames->take(2)->implode(', ') ?: 'Unit details on request' }}</p>
            </div>
        </div>
        @if($images->count() > 1)
            <div class="absolute right-4 top-4 rounded-full bg-black/50 px-3 py-1.5 text-xs font-bold text-white backdrop-blur">
                {{ $images->count() }} photos
            </div>
        @endif
    </div>

    <div class="px-4 pb-6 pt-5 sm:px-7">
        <div class="-mt-1 flex flex-col gap-4 rounded-[28px] border border-slate-200 bg-white p-4 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2 text-xs font-bold text-slate-600">
                <span class="rounded-full bg-blue-50 px-3 py-2 text-blue-700"><i class="fas fa-location-dot mr-1"></i>{{ $location ?: 'Location on request' }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-700">{{ ucwords(str_replace('_', ' ', (string) ($project->project_status ?: 'Available'))) }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-2 text-slate-700">{{ $unitNames->take(3)->implode(', ') ?: 'Unit details on request' }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Hi, I reviewed ' . $project->name . '. Please share latest price and availability.') }}" target="_blank" rel="noopener" class="{{ $trackingClass }} inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20" data-event="whatsapp_click" data-project-id="{{ $project->id }}" data-section="project_cta" data-meta='{{ e(json_encode(["cta" => "project_whatsapp"])) }}'><i class="fab fa-whatsapp"></i> WhatsApp</a>
                @endif
                @if($callPhone)
                    <a href="tel:{{ $callPhone }}" class="{{ $trackingClass }} inline-flex items-center justify-center gap-2 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-bold text-blue-700" data-event="call_click" data-project-id="{{ $project->id }}" data-section="project_cta" data-meta='{{ e(json_encode(["cta" => "project_call"])) }}'><i class="fas fa-phone"></i> Call</a>
                @endif
            </div>
        </div>

        <div id="price-{{ $project->id }}" class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4" data-track-section="quick_facts" data-track-project="{{ $project->id }}">
            <div class="rounded-[22px] border border-blue-100 bg-blue-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.12em] text-blue-600"><i class="fas fa-indian-rupee-sign mr-1"></i>Starting</p>
                <p class="mt-1 text-lg font-bold text-blue-950">{{ $formatPrice($project) }}</p>
            </div>
            <div class="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500"><i class="fas fa-building-circle-check mr-1"></i>Status</p>
                <p class="mt-1 text-sm font-bold text-slate-950">{{ ucwords(str_replace('_', ' ', (string) ($project->project_status ?: 'Available'))) }}</p>
            </div>
            <div class="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500"><i class="fas fa-ruler-combined mr-1"></i>Units</p>
                <p class="mt-1 text-sm font-bold text-slate-950">{{ $unitNames->take(2)->implode(', ') ?: 'On request' }}</p>
            </div>
            <div class="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                <p class="text-[11px] font-black uppercase tracking-[0.12em] text-slate-500"><i class="fas fa-shield-halved mr-1"></i>RERA</p>
                <p class="mt-1 truncate text-sm font-bold text-slate-950">{{ $project->rera_no ?: 'On request' }}</p>
            </div>
        </div>

        <div class="sticky top-0 z-20 -mx-4 mt-5 flex gap-2 overflow-x-auto border-y border-slate-100 bg-white/95 px-4 py-3 text-sm font-bold backdrop-blur hide-scrollbar sm:-mx-7 sm:px-7">
            <a href="#overview-{{ $project->id }}" class="shrink-0 rounded-full bg-slate-100 px-4 py-2 text-slate-700">Overview</a>
            <a href="#plans-{{ $project->id }}" class="{{ $trackingClass }} shrink-0 rounded-full bg-blue-50 px-4 py-2 text-blue-700" data-event="floor_plan_click" data-project-id="{{ $project->id }}" data-section="quick_nav" data-meta='{{ e(json_encode(["cta" => "quick_floor_plan"])) }}'>Floor Plans</a>
            <a href="#gallery-{{ $project->id }}" class="shrink-0 rounded-full bg-slate-100 px-4 py-2 text-slate-700">Gallery</a>
            <a href="#location-{{ $project->id }}" class="shrink-0 rounded-full bg-slate-100 px-4 py-2 text-slate-700">Location</a>
        </div>

        <section id="overview-{{ $project->id }}" class="mt-8 rounded-[28px] border border-slate-200 bg-white p-5" data-track-section="overview" data-track-project="{{ $project->id }}">
            <h3 class="text-xl font-bold text-slate-950">Overview</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600">{{ $publicPage?->short_intro ?: 'Project overview and latest details will be shared by your advisor.' }}</p>
        </section>

        @if($highlights->isNotEmpty())
            <section class="mt-7 rounded-[28px] border border-slate-200 bg-slate-50 p-5" data-track-section="highlights" data-track-project="{{ $project->id }}">
                <h3 class="text-xl font-bold text-slate-950">Why this project</h3>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach($highlights as $highlight)
                        <div class="rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm">
                            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-blue-50 text-blue-700"><i class="fas {{ $amenityIcon($highlight) }}"></i></span>
                            <p class="mt-3 text-sm font-bold leading-5 text-slate-800">{{ $highlight }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section id="plans-{{ $project->id }}" class="mt-7 rounded-[28px] border border-blue-100 bg-blue-50/40 p-5" data-track-section="unit_plans" data-track-project="{{ $project->id }}">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <h3 class="text-xl font-bold text-slate-950">Floor plans</h3>
                    <p class="mt-1 text-sm text-slate-500">Tap a plan to mark your interest.</p>
                </div>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ $variants->count() ?: 0 }} options</span>
            </div>
            @if($unitGroups->isNotEmpty())
                <div class="mt-4 flex gap-2 overflow-x-auto pb-1 hide-scrollbar">
                    @foreach($unitGroups as $unit)
                        <button type="button" class="unit-tab shrink-0 rounded-full border px-4 py-2 text-sm font-bold {{ $loop->first ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700' }}" data-unit-target="project-{{ $project->id }}-unit-{{ $unit->id }}">
                            {{ $unit->name }}
                        </button>
                    @endforeach
                </div>
                <div class="mt-4">
                    @foreach($unitGroups as $unit)
                        <div class="unit-panel {{ $loop->first ? 'active' : '' }}" data-unit-panel="project-{{ $project->id }}-unit-{{ $unit->id }}">
                            <div class="grid gap-3 md:grid-cols-2">
                                @foreach($unit->sizeVariants->where('visible_on_public_page', true)->where('status', '!=', 'hidden') as $variant)
                                    @php
                                        $planLabel = $variant->size_label ?: $unit->name;
                                        $areaText = $variant->builtup_area_sqft ? number_format((float) $variant->builtup_area_sqft, 0) . ' sq.ft. built-up' : 'Area on request';
                                    @endphp
                                    <button type="button" class="{{ $trackingClass }} rounded-[24px] border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:bg-white hover:shadow-lg" data-event="floor_plan_view" data-project-id="{{ $project->id }}" data-section="unit_plans" data-meta='{{ e(json_encode(["variant_id" => $variant->id, "label" => $planLabel, "unit_type" => $unit->name, "project_id" => $project->id])) }}'>
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-base font-bold text-slate-950">{{ $planLabel }}</p>
                                                <p class="mt-1 text-sm text-slate-500">{{ $areaText }}</p>
                                            </div>
                                            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-blue-50 text-blue-700"><i class="fas fa-ruler-combined"></i></span>
                                        </div>
                                        <p class="mt-4 text-lg font-bold text-blue-700">{{ $variant->is_price_on_request ? 'Price on request' : ($variant->formatted_final_price ?: 'Price on request') }}</p>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4 rounded-[24px] border border-dashed border-slate-300 bg-slate-50 p-5 text-sm font-semibold text-slate-500">Floor plan shared by advisor.</div>
            @endif
        </section>

        <section id="gallery-{{ $project->id }}" class="mt-7 rounded-[28px] border border-slate-200 bg-white p-5" data-track-section="gallery" data-track-project="{{ $project->id }}">
            <h3 class="text-xl font-bold text-slate-950">Gallery and downloads</h3>
            @if($gallery->isNotEmpty())
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach($gallery->take(9) as $asset)
                        @php $url = $assetUrl($asset); @endphp
                        <button type="button" class="media-thumb {{ $trackingClass }} overflow-hidden rounded-[22px] border border-slate-200 bg-slate-50 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg" data-event="gallery_view" data-project-id="{{ $project->id }}" data-section="gallery" data-image-url="{{ $asset->asset_type === 'gallery_image' ? $url : '' }}" data-meta='{{ e(json_encode(["asset_id" => $asset->id, "asset_type" => $asset->asset_type, "title" => $asset->title])) }}'>
                            <div class="aspect-[4/3] bg-slate-200">
                                @if($asset->asset_type === 'gallery_image')
                                    <img src="{{ $url }}" alt="{{ $asset->title ?: $project->name }}" class="h-full w-full object-cover">
                                @else
                                    <div class="grid h-full place-items-center text-blue-700"><i class="fas fa-play text-2xl"></i></div>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="mt-4 rounded-[24px] border border-dashed border-slate-300 bg-slate-50 p-5 text-sm font-semibold text-slate-500">Gallery will be shared by advisor.</div>
            @endif
            @if($downloads->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($downloads as $asset)
                        @php $url = $assetUrl($asset); @endphp
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="{{ $trackingClass }} inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700" data-event="{{ $asset->asset_type === 'price_sheet' ? 'cost_sheet_click' : 'brochure_download' }}" data-project-id="{{ $project->id }}" data-section="downloads" data-meta='{{ e(json_encode(["asset_id" => $asset->id, "asset_type" => $asset->asset_type, "title" => $asset->title, "cta" => $asset->asset_type])) }}'>
                            <i class="fas fa-file-arrow-down"></i>{{ $asset->title ?: ucwords(str_replace('_', ' ', $asset->asset_type)) }}
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section id="location-{{ $project->id }}" class="mt-7 rounded-[28px] border border-slate-200 bg-white p-5" data-track-section="location" data-track-project="{{ $project->id }}">
            <h3 class="text-xl font-bold text-slate-950">Location and connectivity</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600">{{ $publicPage?->location_summary ?: 'Location details available on request.' }}</p>
            @if($project->publicLandmarks->isNotEmpty())
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach($project->publicLandmarks->take(8) as $landmark)
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                            <span class="font-semibold text-slate-700">{{ $landmark->label }}</span>
                            <span class="font-bold text-blue-700">{{ $landmark->distance_text ?: 'Nearby' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
            @if($publicPage?->map_embed)
                <div class="map-frame mt-4 overflow-hidden rounded-[24px] border border-slate-200">
                    {!! $publicPage->map_embed !!}
                </div>
            @elseif($publicPage?->latitude && $publicPage?->longitude)
                <a href="https://www.google.com/maps/search/?api=1&query={{ $publicPage->latitude }},{{ $publicPage->longitude }}" target="_blank" rel="noopener" class="{{ $trackingClass }} mt-4 inline-flex items-center gap-2 rounded-2xl bg-blue-50 px-4 py-3 text-sm font-bold text-blue-700" data-event="location_map_click" data-project-id="{{ $project->id }}" data-section="location" data-meta='{{ e(json_encode(["cta" => "google_maps"])) }}'><i class="fas fa-map-location-dot"></i> Open location map</a>
            @endif
        </section>

        <section class="mt-7 rounded-[30px] bg-gradient-to-br from-blue-600 to-blue-900 p-5 text-white shadow-2xl shadow-blue-900/20" data-track-section="advisor_cta" data-track-project="{{ $project->id }}">
            <h3 class="text-lg font-bold text-white">Need latest price or site visit slot?</h3>
            <p class="mt-2 text-sm leading-6 text-white/82">Your advisor can share inventory, payment plan and next available visit time.</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                @if($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Please share latest price sheet and availability for ' . $project->name) }}" target="_blank" rel="noopener" class="{{ $trackingClass }} rounded-2xl bg-white px-4 py-3 text-center text-sm font-bold text-blue-700" data-event="cost_sheet_click" data-project-id="{{ $project->id }}" data-section="price_cta" data-meta='{{ e(json_encode(["cta" => "price_sheet_whatsapp"])) }}'>Get Price</a>
                @endif
                @if($publicPage?->book_visit_url)
                    <a href="{{ $publicPage->book_visit_url }}" target="_blank" rel="noopener" class="{{ $trackingClass }} rounded-2xl border border-white/25 bg-white/10 px-4 py-3 text-center text-sm font-bold text-white backdrop-blur" data-event="site_visit_click" data-project-id="{{ $project->id }}" data-section="site_visit_cta" data-meta='{{ e(json_encode(["cta" => "book_site_visit"])) }}'>Book Visit</a>
                @endif
                <button type="button" class="{{ $trackingClass }} proposal-share-location rounded-2xl border border-white/25 bg-white/10 px-4 py-3 text-center text-sm font-bold text-white backdrop-blur" data-event="location_request_click" data-project-id="{{ $project->id }}" data-section="location_permission" data-meta='{{ e(json_encode(["cta" => "share_location"])) }}'>Share Location</button>
            </div>
        </section>
    </div>
</article>
