<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Private Proposal - {{ brand_name() }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --brand: {{ company_setting('primary_color', '#007aff') }};
            --brand-dark: {{ company_setting('secondary_color', '#0057b8') }};
            --ink: #071426;
            --line: #dbe7f3;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: var(--ink); background: #f6f9fd; overflow-x: hidden; }
        .proposal-shell { padding-bottom: 92px; }
        .brand-gradient { background: linear-gradient(135deg, var(--brand), var(--brand-dark)); }
        .surface { background: #fff; border: 1px solid var(--line); box-shadow: 0 16px 38px rgba(15, 23, 42, .07); }
        .snap-x { scroll-snap-type: x mandatory; }
        .snap-item { scroll-snap-align: start; }
        .unit-panel { display: none; }
        .unit-panel.active { display: block; }
        .media-thumb.active { outline: 3px solid rgba(0, 122, 255, .42); outline-offset: 3px; }
        .sticky-cta { padding-bottom: env(safe-area-inset-bottom); }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .map-frame iframe { width: 100%; min-height: 260px; border: 0; border-radius: 22px; }
        @media (min-width: 768px) {
            .sticky-cta { display: none; }
            .proposal-shell { padding-bottom: 0; }
        }
    </style>
</head>
<body>
@php
    $projects = collect($projects ?? []);
    $leadName = trim((string) ($lead?->name ?: 'Customer'));
    $advisorName = trim((string) ($advisor?->name ?: brand_name()));
    $firstProject = $projects->first();
    $firstPublicPage = $firstProject?->publicPage;
    $advisorPhone = preg_replace('/\D+/', '', (string) ($advisor?->phone ?? $firstPublicPage?->whatsapp_number ?? $firstPublicPage?->call_phone ?? ''));
    $firstWhatsapp = preg_replace('/\D+/', '', (string) ($firstPublicPage?->whatsapp_number ?? $advisorPhone));
    $assetUrl = fn ($asset) => $asset->external_url ?: $asset->file_url;
    $formatPrice = function ($project) {
        $variants = $project->publicUnitTypes->flatMap->sizeVariants;
        $starting = $variants->where('is_price_on_request', false)->whereNotNull('final_price')->sortBy('final_price')->first();
        return $starting?->formatted_final_price ?: 'Price on request';
    };
@endphp

<main class="proposal-shell">
    <section class="brand-gradient text-white">
        <div class="mx-auto max-w-6xl px-4 pb-7 pt-5 sm:px-6 sm:py-10">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-white/70">Private proposal</p>
                    <p class="mt-1 text-lg font-bold">{{ brand_name() }}</p>
                </div>
                <div class="rounded-2xl bg-white/12 px-3 py-2 text-right text-xs font-bold">
                    <span class="block text-white/65">Valid till</span>
                    <span>{{ $proposal->expires_at ? $proposal->expires_at->format('d M') : 'No expiry' }}</span>
                </div>
            </div>

            <div class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
                <div>
                    <h1 class="max-w-3xl text-3xl font-bold leading-tight sm:text-5xl">
                        {{ $leadName }}, your shortlisted property options
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-white/82">
                        Compare pricing, floor plans, gallery, location and site visit options in one private link.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2 text-xs font-bold">
                        <span class="rounded-full bg-white px-4 py-2 text-blue-700">{{ $projects->count() }} project{{ $projects->count() === 1 ? '' : 's' }}</span>
                        <span class="rounded-full border border-white/20 px-4 py-2 text-white">{{ $advisorName }}</span>
                    </div>
                </div>
                <div class="rounded-[28px] border border-white/15 bg-white/10 p-4 backdrop-blur">
                    <p class="text-sm font-bold">Need help choosing?</p>
                    <p class="mt-1 text-xs leading-5 text-white/72">Ask for latest price sheet, available inventory, payment plan and site visit slot.</p>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        @if($firstWhatsapp)
                            <a href="https://wa.me/{{ $firstWhatsapp }}?text={{ rawurlencode('Hi, I reviewed the proposal. Please share latest price and availability.') }}" target="_blank" rel="noopener" class="js-customer-project-track rounded-2xl bg-white px-4 py-3 text-center text-sm font-bold text-blue-700" data-event="whatsapp_click" data-section="hero_cta" data-meta='{{ e(json_encode(["cta" => "hero_whatsapp"])) }}'>WhatsApp</a>
                        @endif
                        @if($firstPublicPage?->call_phone)
                            <a href="tel:{{ $firstPublicPage->call_phone }}" class="js-customer-project-track rounded-2xl border border-white/25 px-4 py-3 text-center text-sm font-bold text-white" data-event="call_click" data-section="hero_cta" data-meta='{{ e(json_encode(["cta" => "hero_call"])) }}'>Call</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if($projects->count() > 1)
        <section class="mx-auto max-w-6xl px-4 py-4 sm:px-6" data-track-section="proposal_comparison">
            <div class="flex gap-3 overflow-x-auto pb-1 hide-scrollbar">
                @foreach($projects as $project)
                    @php
                        $variants = $project->publicUnitTypes->flatMap->sizeVariants->where('visible_on_public_page', true)->where('status', '!=', 'hidden');
                        $location = collect([$project->area, $project->city])->filter()->implode(', ');
                    @endphp
                    <a href="#project-{{ $project->id }}" class="surface min-w-[78vw] rounded-[24px] p-4 sm:min-w-[280px]">
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-600">Shortlisted for you</p>
                        <h2 class="mt-1 text-lg font-bold leading-6 text-slate-950">{{ $project->publicPage?->hero_title ?: $project->name }}</h2>
                        <p class="mt-2 line-clamp-1 text-sm text-slate-500">{{ $location ?: 'Location on request' }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ $formatPrice($project) }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $variants->count() ?: 'Plans' }} plans</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section id="proposal-projects" class="mx-auto grid max-w-6xl gap-7 px-4 py-4 sm:px-6">
        @foreach($projects as $project)
            @include('projects.public.partials.customer-project-block', [
                'project' => $project,
                'advisorPhone' => $advisorPhone,
                'assetUrl' => $assetUrl,
                'formatPrice' => $formatPrice,
                'trackingClass' => 'js-customer-project-track',
            ])
        @endforeach
    </section>
</main>

@if(($proposal->lead_capture_mode ?? 'off') === 'soft_prompt')
    <div class="fixed bottom-20 left-4 right-4 z-40 mx-auto max-w-xl rounded-[26px] border border-blue-100 bg-white p-4 shadow-2xl">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold text-slate-950">Need updated price?</p>
                <p class="text-xs text-slate-500">Ask your advisor without filling any form.</p>
            </div>
            @if($firstWhatsapp)
                <a href="https://wa.me/{{ $firstWhatsapp }}?text={{ rawurlencode('Hi, I need updated price and availability for the proposal projects.') }}" target="_blank" rel="noopener" class="js-customer-project-track shrink-0 rounded-2xl bg-blue-600 px-4 py-2 text-sm font-bold text-white" data-event="whatsapp_click" data-section="soft_prompt" data-meta='{{ e(json_encode(["cta" => "soft_prompt_whatsapp"])) }}'>WhatsApp</a>
            @endif
        </div>
    </div>
@endif

<div class="sticky-cta fixed bottom-0 left-0 right-0 z-30 border-t border-slate-200 bg-white/96 p-2 shadow-[0_-10px_30px_rgba(15,23,42,.1)] backdrop-blur">
    <div class="grid grid-cols-5 gap-1 text-[11px] font-bold">
        <a href="#price-{{ $firstProject?->id }}" class="js-customer-project-track rounded-xl bg-blue-600 px-1 py-3 text-center text-white" data-event="price_click" data-section="sticky_cta" data-meta='{{ e(json_encode(["cta" => "sticky_price"])) }}'><i class="fas fa-indian-rupee-sign block text-sm"></i>Price</a>
        <a href="#plans-{{ $firstProject?->id }}" class="js-customer-project-track rounded-xl border border-slate-200 px-1 py-3 text-center text-slate-800" data-event="floor_plan_click" data-section="sticky_cta" data-meta='{{ e(json_encode(["cta" => "sticky_floor_plan"])) }}'><i class="fas fa-ruler-combined block text-sm"></i>Plan</a>
        @if($firstWhatsapp)
            <a href="https://wa.me/{{ $firstWhatsapp }}?text={{ rawurlencode('Hi, I reviewed the proposal. Please share next details.') }}" target="_blank" class="js-customer-project-track rounded-xl border border-blue-200 bg-blue-50 px-1 py-3 text-center text-blue-700" data-event="whatsapp_click" data-section="sticky_cta" data-meta='{{ e(json_encode(["cta" => "sticky_whatsapp"])) }}'><i class="fab fa-whatsapp block text-sm"></i>WhatsApp</a>
        @else
            <a href="#proposal-projects" class="rounded-xl border border-slate-200 px-1 py-3 text-center text-slate-500"><i class="fab fa-whatsapp block text-sm"></i>WhatsApp</a>
        @endif
        @if($firstPublicPage?->call_phone)
            <a href="tel:{{ $firstPublicPage->call_phone }}" class="js-customer-project-track rounded-xl border border-slate-200 px-1 py-3 text-center text-slate-800" data-event="call_click" data-section="sticky_cta" data-meta='{{ e(json_encode(["cta" => "sticky_call"])) }}'><i class="fas fa-phone block text-sm"></i>Call</a>
        @else
            <a href="#proposal-projects" class="rounded-xl border border-slate-200 px-1 py-3 text-center text-slate-500"><i class="fas fa-phone block text-sm"></i>Call</a>
        @endif
        @if($firstPublicPage?->book_visit_url)
            <a href="{{ $firstPublicPage->book_visit_url }}" target="_blank" class="js-customer-project-track rounded-xl border border-slate-200 px-1 py-3 text-center text-slate-800" data-event="site_visit_click" data-section="sticky_cta" data-meta='{{ e(json_encode(["cta" => "sticky_site_visit"])) }}'><i class="fas fa-calendar-check block text-sm"></i>Visit</a>
        @else
            <a href="#proposal-projects" class="rounded-xl border border-slate-200 px-1 py-3 text-center text-slate-800"><i class="fas fa-calendar-check block text-sm"></i>Visit</a>
        @endif
    </div>
</div>

@include('projects.public.partials.customer-project-scripts', [
    'eventUrl' => $eventUrl,
    'sessionKey' => 'lead-proposal-session-' . $proposal->token,
    'openedEvent' => 'proposal_opened',
    'openedSection' => 'proposal',
    'openedMeta' => ['project_count' => $projects->count()],
    'includeVisitId' => true,
])
</body>
</html>
