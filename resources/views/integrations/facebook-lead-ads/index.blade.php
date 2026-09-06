@extends('layouts.app')

@php
    $fbLeadAdsRoutePrefix = request()->routeIs('ad-manager.meta.facebook-lead-ads.*') ? 'ad-manager.meta.facebook-lead-ads.' : 'integrations.facebook-lead-ads.';
    $metaOpsBackRoute = request()->routeIs('ad-manager.*') ? route('ad-manager.meta.index') : route('integrations.index');
@endphp

@section('title', 'Facebook Lead Ads - ' . brand_name())
@section('page-title', 'Facebook Lead Ads')

@section('header-actions')
    <a href="{{ $metaOpsBackRoute }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
        <i class="fas fa-arrow-left text-xs"></i>
        <span>Back to Integrations</span>
    </a>
@endsection

@section('content')
@php
    $formsByPage = $forms->groupBy('fb_page_id');
    $enabledFormsCount = $forms->where('is_enabled', true)->count();
    $processedCount = isset($webhookEvents) ? $webhookEvents->where('status', 'processed')->count() : 0;
    $failedCount = isset($webhookEvents) ? $webhookEvents->where('status', 'failed')->count() : 0;
    $pendingCount = isset($webhookEvents) ? $webhookEvents->where('status', 'received')->count() : 0;
    $ignoredDisabledCount = isset($webhookEvents) ? $webhookEvents->where('status', 'ignored_disabled')->count() : 0;
    $metaChecksByPage = collect($metaChecks ?? [])->keyBy(fn ($check) => (string) ($check['page_id'] ?? ''));
    $activePagesCount = $addedPages->filter(function ($page) use ($formsByPage, $metaChecksByPage) {
        $tokenOk = (bool) ($metaChecksByPage->get((string) $page->page_id)['success'] ?? false);

        return $tokenOk && $formsByPage->get($page->id, collect())->where('is_enabled', true)->count() > 0;
    })->count();
    $inactivePagesCount = max(0, $addedPages->count() - $activePagesCount);
    $pagesWithoutFormsCount = $addedPages->filter(fn ($page) => $formsByPage->get($page->id, collect())->isEmpty())->count();
    $disabledFormsCount = max(0, $forms->count() - $enabledFormsCount);
    $mappedFormsCount = $forms->filter(fn ($form) => $form->mapping)->count();
    $unmappedFormsCount = max(0, $forms->count() - $mappedFormsCount);
    $tokenOkCount = collect($metaChecks ?? [])->filter(fn ($check) => (bool) ($check['success'] ?? false))->count();
    $tokenIssueCount = max(0, $addedPages->count() - $tokenOkCount);
    $latestLeadAt = $lastLeadByForm->filter()->sortDesc()->first();
    $webhookIssueCount = $failedCount + $pendingCount;
    $attentionCount = $tokenIssueCount + $unmappedFormsCount + $webhookIssueCount;
    $briefText = sprintf(
        '%d pages connected: %d active, %d non-active. %d forms configured: %d active, %d non-active. %d mapped, %d mapping pending.',
        $addedPages->count(),
        $activePagesCount,
        $inactivePagesCount,
        $forms->count(),
        $enabledFormsCount,
        $disabledFormsCount,
        $mappedFormsCount,
        $unmappedFormsCount
    );
    $healthToneClasses = [
        'green' => 'bg-green-50 text-green-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'red' => 'bg-red-50 text-red-700',
    ];
    $healthTone = $healthSnapshot['tone'] ?? 'green';
    $portfolioGroups = collect([[
        'id' => '',
        'name' => 'Unassigned Portfolio',
        'description' => 'Pages jinka portfolio assign nahi hai.',
        'pages' => $addedPages->filter(fn ($page) => empty($page->facebook_portfolio_id))->values(),
        'is_default' => true,
    ]]);
    foreach(($portfolios ?? collect()) as $portfolio) {
        $portfolioGroups->push([
            'id' => (string) $portfolio->id,
            'name' => $portfolio->name,
            'description' => $portfolio->description,
            'pages' => $addedPages->filter(fn ($page) => (int) $page->facebook_portfolio_id === (int) $portfolio->id)->values(),
            'is_default' => false,
        ]);
    }
    $portfolioGroups = $portfolioGroups->filter(fn ($group) => $group['pages']->isNotEmpty() || !empty($group['is_default']) || $addedPages->isEmpty())->values();
@endphp

@once
    <style>
        .meta-accordion-chevron{transition:transform .18s ease}
        details[open] > summary .meta-accordion-chevron{transform:rotate(180deg)}
    </style>
@endonce

<div class="w-full space-y-6">
    @foreach(['warning' => 'amber', 'error' => 'red', 'success' => 'green'] as $type => $color)
        @if(session($type))
            <div class="rounded-2xl border border-{{ $color }}-200 bg-{{ $color }}-50 px-5 py-4 text-sm text-{{ $color }}-800 shadow-sm">
                {{ session($type) }}
            </div>
        @endif
    @endforeach

    @if(($healthSnapshot['status'] ?? 'healthy') !== 'healthy')
        <section class="rounded-[24px] border {{ ($healthSnapshot['tone'] ?? 'amber') === 'red' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-5 py-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-sm font-black">Facebook Lead Ads {{ $healthSnapshot['label'] ?? 'Warning' }}</div>
                    <div class="mt-1 text-sm">{{ $healthSnapshot['reasons'][0] ?? 'Open diagnostics for details.' }}</div>
                </div>
                <a href="{{ route($fbLeadAdsRoutePrefix . 'diagnostics') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50">
                    <i class="fas fa-heart-pulse text-xs"></i>
                    <span>Open Diagnostics</span>
                </a>
            </div>
        </section>
    @endif

    <section class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                        <i class="fab fa-facebook-f text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-2xl font-black text-slate-900">Meta Lead Ads Integration</h2>
                        <p class="mt-1 text-sm text-slate-500">Pages, forms, webhook status, active/non-active split, and recent leads.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Pages</div>
                        <div class="mt-2 text-2xl font-black text-slate-900">{{ $addedPages->count() }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $activePagesCount }} active, {{ $inactivePagesCount }} inactive</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Forms</div>
                        <div class="mt-2 flex items-end gap-2">
                            <span class="text-2xl font-black text-slate-900">{{ $forms->count() }}</span>
                            <span class="pb-1 text-xs font-semibold text-slate-500">{{ $enabledFormsCount }} enabled</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">{{ $disabledFormsCount }} disabled</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Mapping</div>
                        <div class="mt-2 text-2xl font-black text-slate-900">{{ $mappedFormsCount }}</div>
                        <div class="mt-1 text-xs {{ $unmappedFormsCount > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ $unmappedFormsCount }} pending</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Needs Attention</div>
                        <div class="mt-2 text-2xl font-black {{ $attentionCount > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $attentionCount }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $tokenIssueCount }} token, {{ $failedCount + $pendingCount }} webhook</div>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,0.75fr)]">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Short Overview</div>
                                <p class="mt-2 text-sm font-semibold leading-6 text-slate-800">{{ $briefText }}</p>
                            </div>
                            <a href="{{ route($fbLeadAdsRoutePrefix . 'diagnostics') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                                <i class="fas fa-chart-simple text-[11px]"></i>
                                <span>Full Report</span>
                            </a>
                        </div>
                        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <div class="font-black text-slate-900">{{ $tokenOkCount }}/{{ $addedPages->count() }}</div>
                                <div class="text-xs text-slate-500">page tokens OK</div>
                            </div>
                            <div>
                                <div class="font-black text-slate-900">{{ $enabledFormsCount }}/{{ $forms->count() }}</div>
                                <div class="text-xs text-slate-500">active forms</div>
                            </div>
                            <div>
                                <div class="font-black text-slate-900">{{ $inactivePagesCount }}</div>
                                <div class="text-xs text-slate-500">non-active pages</div>
                            </div>
                            <div>
                                <div class="font-black text-slate-900">{{ $latestLeadAt ? \Illuminate\Support\Carbon::parse($latestLeadAt)->format('d M, h:i A') : 'Never' }}</div>
                                <div class="text-xs text-slate-500">latest Meta lead</div>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">{{ $mappedFormsCount }} mapped</span>
                            <span class="rounded-full px-2.5 py-1 {{ $unmappedFormsCount > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600' }}">{{ $unmappedFormsCount }} mapping pending</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $pagesWithoutFormsCount }} pages without forms</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $ignoredDisabledCount }} ignored disabled</span>
                        </div>
                    </div>
                    <div class="rounded-2xl border {{ $attentionCount > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] {{ $attentionCount > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Suggested Next Step</div>
                        <div class="mt-2 text-sm font-black {{ $attentionCount > 0 ? 'text-amber-900' : 'text-emerald-900' }}">
                            @if($tokenIssueCount > 0)
                                Refresh expired page tokens
                            @elseif($unmappedFormsCount > 0)
                                Review pending mappings
                            @elseif($webhookIssueCount > 0)
                                Check pending/failed webhooks
                            @elseif($pagesWithoutFormsCount > 0)
                                Refresh forms for empty pages
                            @elseif($disabledFormsCount > 0)
                                Enable forms only when ready
                            @else
                                Integration is ready
                            @endif
                        </div>
                        <div class="mt-1 text-xs {{ $attentionCount > 0 ? 'text-amber-800' : 'text-emerald-800' }}">
                            {{ $activePagesCount }} page(s) can receive live leads now. Non-active pages/forms stay connected but will not create CRM leads until token, mapping, and enable status are OK.
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Webhook URL</div>
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <code class="min-w-0 flex-1 overflow-hidden text-ellipsis whitespace-nowrap rounded-xl bg-slate-100 px-3 py-2 text-sm text-slate-700">{{ $webhookUrl }}</code>
                        <button onclick="navigator.clipboard.writeText('{{ $webhookUrl }}');this.innerHTML='<i class=\'fas fa-check text-xs\'></i> Copied';setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy text-xs\'></i> Copy URL',2000)" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            <i class="fas fa-copy text-xs"></i>
                            <span>Copy URL</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid w-full gap-3 sm:grid-cols-2 xl:w-[360px] xl:grid-cols-1">
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="openAddPageModal()" class="inline-flex items-center gap-2 rounded-xl bg-[#063A1C] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#205A44]">
                        <i class="fas fa-plus text-xs"></i>
                        <span>Add Page</span>
                    </button>
                    <a href="{{ route($fbLeadAdsRoutePrefix . 'forms') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        <i class="fas fa-layer-group text-xs"></i>
                        <span>Forms</span>
                    </a>
                    <a href="{{ route($fbLeadAdsRoutePrefix . 'settings') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        <i class="fas fa-cog text-xs"></i>
                        <span>Settings</span>
                    </a>
                    <a href="{{ route($fbLeadAdsRoutePrefix . 'diagnostics') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        <i class="fas fa-heart-pulse text-xs"></i>
                        <span>Diagnostics</span>
                    </a>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="text-sm font-semibold text-slate-900">Webhook Health</div>
                        <a href="{{ route($fbLeadAdsRoutePrefix . 'diagnostics') }}" class="rounded-full px-2.5 py-1 text-xs font-black {{ $healthToneClasses[$healthTone] ?? $healthToneClasses['green'] }}">
                            {{ $healthSnapshot['label'] ?? 'Healthy' }}
                        </a>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-green-50 px-2.5 py-1 font-medium text-green-700">{{ $processedCount }} processed</span>
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-700">{{ $pendingCount }} pending</span>
                        <span class="rounded-full bg-red-50 px-2.5 py-1 font-medium text-red-700">{{ $failedCount }} failed</span>
                    </div>
                    @if(($retryableFailedWebhookCount ?? 0) > 0)
                        <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'retry-failed-webhooks') }}" class="mt-3 js-confirm-form" data-confirm-title="Retry failed webhooks?" data-confirm-message="Existing CRM leads will be skipped. Retry old failed Meta webhooks now?">
                            @csrf
                            <input type="hidden" name="limit" value="10">
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700">
                                <i class="fas fa-rotate text-[11px]"></i>
                                <span>Retry {{ $retryableFailedWebhookCount }} Failed</span>
                            </button>
                            <div class="mt-2 text-[11px] leading-5 text-slate-500">10 at a time. Mapping enabled hone par old failed leads recover hongi.</div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-xl font-black text-slate-900">Trace Lead</h3>
                    <p class="mt-1 text-sm text-slate-500">Phone, lead ID, ya leadgen ID daal ke Meta se CRM tak ka path dekho.</p>
                </div>
                <form method="GET" action="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="flex w-full max-w-2xl flex-col gap-3 sm:flex-row">
                    <input
                        type="text"
                        name="trace"
                        value="{{ $traceQuery ?? '' }}"
                        placeholder="Search by phone, lead ID, or leadgen ID"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100"
                    >
                    <div class="flex gap-3">
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                            <i class="fas fa-magnifying-glass text-xs"></i>
                            <span>Trace Lead</span>
                        </button>
                        @if(!empty($traceQuery))
                            <a href="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                <i class="fas fa-rotate-left text-xs"></i>
                                <span>Clear</span>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if(!empty($traceQuery))
            <div class="grid gap-6 px-6 py-6 sm:px-8 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                <div class="space-y-4">
                    <div class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-5">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Trace Query</div>
                        <div class="mt-2 text-lg font-black text-slate-900">{{ $traceReport['query'] }}</div>
                        @if(!empty($traceReport['normalized']))
                            <div class="mt-2 text-sm text-slate-500">Normalized: {{ $traceReport['normalized'] }}</div>
                        @endif
                    </div>

                    <div class="rounded-[24px] border border-emerald-200 bg-emerald-50/70 p-5">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Source Guess</div>
                        <div class="mt-2 text-lg font-black text-slate-900">{{ $traceReport['summary']['source_guess'] }}</div>
                        <p class="mt-2 text-sm text-slate-600">{{ $traceReport['summary']['root_cause'] }}</p>
                    </div>

                    <div class="rounded-[24px] border border-slate-200 bg-white p-5">
                        <div class="mb-4 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Trace Timeline</div>
                        <div class="space-y-3">
                            @foreach($traceReport['timeline'] as $step)
                                @php
                                    $statusColors = $step['status'] === 'yes'
                                        ? 'border-green-200 bg-green-50 text-green-700'
                                        : 'border-red-200 bg-red-50 text-red-700';
                                @endphp
                                <div class="rounded-2xl border px-4 py-3 {{ $statusColors }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-semibold">{{ $step['label'] }}</div>
                                            <div class="mt-1 text-xs opacity-80">{{ $step['detail'] }}</div>
                                        </div>
                                        <span class="rounded-full bg-white/80 px-2.5 py-1 text-[11px] font-bold uppercase">{{ $step['status'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-[22px] border border-slate-200 bg-white p-4">
                            <div class="text-xs uppercase tracking-[0.16em] text-slate-500">CRM Leads</div>
                            <div class="mt-2 text-2xl font-black text-slate-900">{{ $traceReport['leads']->count() }}</div>
                        </div>
                        <div class="rounded-[22px] border border-slate-200 bg-white p-4">
                            <div class="text-xs uppercase tracking-[0.16em] text-slate-500">FB Leads</div>
                            <div class="mt-2 text-2xl font-black text-slate-900">{{ $traceReport['fb_leads']->count() }}</div>
                        </div>
                        <div class="rounded-[22px] border border-slate-200 bg-white p-4">
                            <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Webhook Events</div>
                            <div class="mt-2 text-2xl font-black text-slate-900">{{ $traceReport['fb_webhook_events']->count() }}</div>
                        </div>
                    </div>

                    @php
                        $lead = $traceReport['leads']->first();
                        $creator = $lead ? ($traceReport['users']->get($lead->created_by) ?? null) : null;
                    @endphp
                    <div class="rounded-[24px] border border-slate-200 bg-white p-5">
                        <div class="mb-4 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">CRM Lead Match</div>
                        @if($lead)
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="text-xs text-slate-500">Lead</div>
                                    <div class="mt-1 text-base font-bold text-slate-900">{{ $lead->name ?: '-' }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $lead->phone ?: '-' }}</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="text-xs text-slate-500">Source / Status</div>
                                    <div class="mt-1 text-base font-bold text-slate-900">{{ $lead->source ?: '-' }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $lead->status ?: '-' }}</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="text-xs text-slate-500">Created At</div>
                                    <div class="mt-1 text-base font-bold text-slate-900">{{ \Illuminate\Support\Carbon::parse($lead->created_at)->format('d M Y, h:i A') }}</div>
                                    <div class="mt-1 text-sm text-slate-600">Lead ID: {{ $lead->id }}</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="text-xs text-slate-500">Created By</div>
                                    <div class="mt-1 text-base font-bold text-slate-900">{{ $creator->name ?? 'Unknown' }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $creator->email ?? 'No email' }}</div>
                                </div>
                            </div>
                        @else
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-5 py-8 text-center text-sm text-slate-500">
                                No CRM lead matched this trace query.
                            </div>
                        @endif
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <div class="rounded-[24px] border border-slate-200 bg-white p-5">
                            <div class="mb-4 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Facebook Webhook Events</div>
                            @forelse($traceReport['fb_webhook_events'] as $event)
                                <div class="mb-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 last:mb-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-bold text-slate-900">{{ $event->leadgen_id ?: 'No leadgen ID' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d M Y, h:i A') }}</div>
                                        </div>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ $event->status }}</span>
                                    </div>
                                    @if($event->error)
                                        <div class="mt-2 text-xs text-red-600">{{ $event->error }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-5 py-8 text-center text-sm text-slate-500">
                                    No matching Facebook webhook event found.
                                </div>
                            @endforelse
                        </div>

                        <div class="rounded-[24px] border border-slate-200 bg-white p-5">
                            <div class="mb-4 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Assignments</div>
                            @php
                                $assignment = $traceReport['lead_assignments']->first();
                                $assignee = $assignment ? ($traceReport['users']->get($assignment->assigned_to) ?? null) : null;
                                $assigner = $assignment ? ($traceReport['users']->get($assignment->assigned_by) ?? null) : null;
                            @endphp
                            @if($assignment)
                                <div class="space-y-3">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <div class="text-xs text-slate-500">Assigned To</div>
                                        <div class="mt-1 text-sm font-bold text-slate-900">{{ $assignee->name ?? ('User #' . $assignment->assigned_to) }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <div class="text-xs text-slate-500">Assigned By</div>
                                        <div class="mt-1 text-sm font-bold text-slate-900">{{ $assigner->name ?? ('User #' . $assignment->assigned_by) }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <div class="text-xs text-slate-500">Assignment Time</div>
                                        <div class="mt-1 text-sm font-bold text-slate-900">{{ \Illuminate\Support\Carbon::parse($assignment->assigned_at)->format('d M Y, h:i A') }}</div>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-5 py-8 text-center text-sm text-slate-500">
                                    No lead assignment found for this trace.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <section class="space-y-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Portfolio Wise Control</div>
                <h3 class="mt-1 text-xl font-black text-slate-900">Connected Pages & Forms</h3>
                <p class="mt-1 text-sm text-slate-500">Portfolio, page aur form level par mapping, automation, token health aur sync status manage karo.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'forms.sync-all') }}" class="js-confirm-form" data-confirm-title="Sync all forms?" data-confirm-message="New forms pending state me add honge. Existing mapped/enabled forms change nahi honge.">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100">
                        <i class="fas fa-rotate text-xs"></i>
                        <span>Sync All Forms</span>
                    </button>
                </form>
                <button type="button" onclick="openPortfolioModal()" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <i class="fas fa-folder-plus text-xs"></i>
                    <span>Create Portfolio</span>
                </button>
                <button type="button" onclick="openAddPageModal()" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                    <i class="fas fa-plus text-xs"></i>
                    <span>Add Page</span>
                </button>
            </div>
        </div>

        @if($addedPages->isNotEmpty())
            <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
                <div class="grid gap-3 border-b border-slate-200 bg-slate-50/90 px-5 py-4 lg:sticky lg:top-0 lg:z-10 lg:grid-cols-[minmax(0,1fr)_220px_220px_180px] lg:items-center">
                    <div class="relative">
                        <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                        <input id="meta-page-search" type="search" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:ring-4 focus:ring-emerald-100" placeholder="Search portfolio, page, or form">
                    </div>
                    <select id="meta-portfolio-filter" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:ring-4 focus:ring-emerald-100">
                        <option value="">All Portfolios</option>
                        @foreach($portfolioGroups as $group)
                            <option value="{{ $group['id'] }}">{{ $group['name'] }}</option>
                        @endforeach
                    </select>
                    <select id="meta-status-filter" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:ring-4 focus:ring-emerald-100">
                        <option value="">All Status</option>
                        <option value="mapping_pending">Mapping Pending</option>
                        <option value="automation_pending">Automation Pending</option>
                        <option value="enabled">Enabled Forms</option>
                        <option value="token_issue">Token Issue</option>
                    </select>
                    <a href="{{ route($fbLeadAdsRoutePrefix . 'diagnostics') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        <i class="fas fa-heart-pulse text-xs"></i>
                        <span>Diagnostics</span>
                    </a>
                </div>

                <div class="space-y-4 p-4">
                    @foreach($portfolioGroups as $group)
                        @php
                            $groupPages = $group['pages'];
                            $groupForms = $groupPages->flatMap(fn ($page) => $formsByPage->get($page->id, collect()));
                            $groupMapped = $groupForms->filter(fn ($form) => $form->mapping)->count();
                            $groupAutomationReady = $groupForms->filter(fn ($form) => ($activeAutomationFormIds ?? collect())->has((string) $form->id))->count();
                            $groupSearch = strtolower(trim($group['name'] . ' ' . $groupPages->pluck('page_name')->implode(' ') . ' ' . $groupForms->pluck('form_name')->implode(' ')));
                        @endphp
                        <details data-portfolio-block data-portfolio-id="{{ $group['id'] }}" data-search="{{ $groupSearch }}" @if($loop->first) open @endif class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <summary class="flex cursor-pointer list-none flex-col gap-3 rounded-2xl bg-slate-50/70 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm">
                                        <i class="fas fa-chevron-down meta-accordion-chevron text-[11px]"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-base font-black text-slate-900">{{ $group['name'] }}</h4>
                                            @if($group['is_default'])
                                                <span class="rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-700">Default</span>
                                            @else
                                                <button type="button" onclick="event.stopPropagation(); event.preventDefault(); openPortfolioModal('{{ $group['id'] }}', @js($group['name']), @js($group['description'] ?? ''))" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 transition hover:bg-slate-100">
                                                    <i class="fas fa-pen mr-1"></i>Edit
                                                </button>
                                            @endif
                                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 ring-1 ring-slate-200">Click to open</span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-500">{{ $group['description'] ?: 'Portfolio ke andar pages aur forms grouped hain.' }}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-4 gap-2 text-center text-xs sm:min-w-[460px]">
                                    <div class="rounded-xl bg-white px-3 py-2 shadow-sm"><div class="font-black text-slate-900">{{ $groupPages->count() }}</div><div class="text-slate-500">Pages</div></div>
                                    <div class="rounded-xl bg-white px-3 py-2 shadow-sm"><div class="font-black text-slate-900">{{ $groupForms->count() }}</div><div class="text-slate-500">Forms</div></div>
                                    <div class="rounded-xl bg-white px-3 py-2 shadow-sm"><div class="font-black {{ $groupMapped < $groupForms->count() ? 'text-amber-700' : 'text-slate-900' }}">{{ $groupMapped }}</div><div class="text-slate-500">Mapped</div></div>
                                    <div class="rounded-xl bg-white px-3 py-2 shadow-sm"><div class="font-black {{ $groupAutomationReady < $groupForms->count() ? 'text-amber-700' : 'text-slate-900' }}">{{ $groupAutomationReady }}</div><div class="text-slate-500">Auto</div></div>
                                </div>
                            </summary>

                            <div class="grid gap-3 border-t border-slate-200 p-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                @forelse($groupPages as $page)
                                    @php
                                        $pageForms = $formsByPage->get($page->id, collect());
                                        $enabledOnPage = $pageForms->where('is_enabled', true)->count();
                                        $mappedOnPage = $pageForms->filter(fn ($form) => $form->mapping)->count();
                                        $automationOnPage = $pageForms->filter(fn ($form) => ($activeAutomationFormIds ?? collect())->has((string) $form->id))->count();
                                        $unmappedOnPage = max(0, $pageForms->count() - $mappedOnPage);
                                        $automationPendingOnPage = max(0, $pageForms->count() - $automationOnPage);
                                        $tokenCheck = $metaChecksByPage->get((string) $page->page_id);
                                        $tokenOk = (bool) ($tokenCheck['success'] ?? false);
                                        $tokenError = $tokenCheck['error'] ?? null;
                                        $pageLastLeadAt = $pageForms->map(fn ($form) => $lastLeadByForm[$form->id] ?? null)->filter()->sortDesc()->first();
                                        $pageStatuses = collect([
                                            !$tokenOk ? 'token_issue' : null,
                                            $unmappedOnPage > 0 ? 'mapping_pending' : null,
                                            $automationPendingOnPage > 0 ? 'automation_pending' : null,
                                            $enabledOnPage > 0 ? 'enabled' : null,
                                        ])->filter()->implode(' ');
                                        $pageSearchText = strtolower(trim(($page->page_name ?: $page->page_id) . ' ' . $page->page_id . ' ' . $pageForms->pluck('form_name')->implode(' ')));
                                    @endphp
                                    <article data-page-row data-portfolio-id="{{ $group['id'] }}" data-status="{{ $pageStatuses }}" data-search="{{ $pageSearchText }}" class="flex min-h-[292px] flex-col rounded-xl border border-slate-200 bg-white p-3.5 transition hover:border-emerald-200 hover:shadow-sm">
                                        <div class="flex min-w-0 items-start gap-2.5">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                                            <i class="fab fa-facebook-f text-sm"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="truncate text-sm font-bold text-slate-900" title="{{ $page->page_name ?: $page->page_id }}">{{ $page->page_name ?: $page->page_id }}</div>
                                                <div class="mt-0.5 truncate text-[10px] leading-4 text-slate-500" title="ID {{ $page->page_id }} · Last lead {{ $pageLastLeadAt ? \Illuminate\Support\Carbon::parse($pageLastLeadAt)->format('d M, h:i A') : 'Never' }}">ID {{ $page->page_id }} · Last lead {{ $pageLastLeadAt ? \Illuminate\Support\Carbon::parse($pageLastLeadAt)->format('d M, h:i A') : 'Never' }}</div>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex min-h-6 flex-wrap items-start gap-1.5">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-bold leading-none {{ $tokenOk ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}"><span class="h-1.5 w-1.5 rounded-full {{ $tokenOk ? 'bg-emerald-500' : 'bg-red-500' }}"></span>{{ $tokenOk ? 'Token OK' : 'Token Issue' }}</span>
                                            @if($unmappedOnPage > 0)
                                                <span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold leading-none text-amber-700">{{ $unmappedOnPage }} Mapping Pending</span>
                                            @endif
                                            @if($automationPendingOnPage > 0)
                                                <span class="rounded-full bg-rose-50 px-2 py-1 text-[10px] font-bold leading-none text-rose-700">{{ $automationPendingOnPage }} Automation Pending</span>
                                            @endif
                                        </div>

                                        @if(!$tokenOk && $tokenError)
                                            <div class="mt-2 truncate text-[10px] font-semibold text-red-600" title="{{ $tokenError }}">{{ $tokenError }}</div>
                                        @endif

                                        <div class="mt-3 grid grid-cols-4 divide-x divide-slate-200 rounded-lg bg-slate-50 py-2">
                                            <div class="px-1.5 text-center"><div class="text-sm font-bold leading-4 text-slate-900">{{ $pageForms->count() }}</div><div class="mt-0.5 text-[10px] leading-3 text-slate-500">Forms</div></div>
                                            <div class="px-1.5 text-center"><div class="text-sm font-bold leading-4 text-emerald-700">{{ $enabledOnPage }}</div><div class="mt-0.5 text-[10px] leading-3 text-slate-500">Enabled</div></div>
                                            <div class="px-1.5 text-center"><div class="text-sm font-bold leading-4 text-slate-900">{{ $mappedOnPage }}</div><div class="mt-0.5 text-[10px] leading-3 text-slate-500">Mapped</div></div>
                                            <div class="px-1.5 text-center"><div class="text-sm font-bold leading-4 text-slate-900">{{ $automationOnPage }}</div><div class="mt-0.5 text-[10px] leading-3 text-slate-500">Automation</div></div>
                                        </div>

                                        <div class="mt-auto grid w-full grid-cols-2 gap-2 pt-3">
                                                <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'pages.portfolio', $page) }}" class="col-span-2">
                                                    @csrf
                                                    <label class="sr-only" for="portfolio-{{ $page->id }}">Portfolio for {{ $page->page_name ?: $page->page_id }}</label>
                                                    <select id="portfolio-{{ $page->id }}" name="facebook_portfolio_id" onchange="this.form.submit()" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-semibold text-slate-700 outline-none focus:border-[#205A44] focus:ring-2 focus:ring-emerald-100">
                                                        <option value="">Unassigned Portfolio</option>
                                                        @foreach($portfolios as $portfolio)
                                                            <option value="{{ $portfolio->id }}" @selected((int) $page->facebook_portfolio_id === (int) $portfolio->id)>{{ $portfolio->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                                <a href="{{ route($fbLeadAdsRoutePrefix . 'forms', ['page_id' => $page->page_id]) }}" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-[#0b6b34] px-2 text-[11px] font-semibold text-white transition hover:bg-[#09582b]"><i class="fas fa-layer-group text-[10px]"></i> Open Forms</a>
                                                <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'pages.forms.sync', $page) }}" class="js-confirm-form" data-confirm-title="Sync page forms?" data-confirm-message="New forms pending state me add honge. Existing mapped/enabled forms change nahi honge.">
                                                    @csrf
                                                    <button type="submit" class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2 text-[11px] font-semibold text-emerald-800 transition hover:bg-emerald-100"><i class="fas fa-rotate text-[10px]"></i> Sync Forms</button>
                                                </form>
                                                <button type="button" data-page-detail-url="{{ route($fbLeadAdsRoutePrefix . 'pages.detail', $page) }}" onclick="openMetaPageDrawer(this)" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"><i class="fas fa-circle-info text-[10px]"></i> Details</button>
                                                <button type="button" onclick="removePage('{{ $page->page_id }}', this)" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-red-100 bg-red-50 px-2 text-[11px] font-semibold text-red-600 transition hover:bg-red-100"><i class="fas fa-trash-alt text-[10px]"></i> Remove</button>
                                        </div>
                                    </article>
                                @empty
                                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-5 py-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3 2xl:col-span-4">No pages assigned to this portfolio yet.</div>
                                @endforelse
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @else
            <button type="button" onclick="openAddPageModal()" class="flex w-full flex-col items-center justify-center rounded-[28px] border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm transition hover:border-[#205A44] hover:shadow-md">
                <div class="flex h-16 w-16 items-center justify-center rounded-[22px] bg-emerald-50 text-[#205A44]">
                    <i class="fab fa-facebook-f text-2xl"></i>
                </div>
                <div class="mt-5 text-2xl font-black text-slate-900">Add Your First Meta Page</div>
                <div class="mt-2 max-w-xl text-sm text-slate-500">Start by connecting a page.</div>
                <div class="mt-5 inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-3 text-sm font-semibold text-white shadow-sm">
                    <i class="fas fa-plus text-xs"></i>
                    <span>Add New Page</span>
                </div>
            </button>
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,0.95fr)]">
        <section class="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Webhook Events</h3>
                    <p class="text-sm text-slate-500">Recent webhook activity.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span class="rounded-full bg-green-50 px-2.5 py-1 text-green-700">{{ $processedCount }} processed</span>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">{{ $pendingCount }} pending</span>
                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-red-700">{{ $failedCount }} failed</span>
                    @if(($retryableFailedWebhookCount ?? 0) > 0)
                        <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'retry-failed-webhooks') }}" class="js-confirm-form" data-confirm-title="Retry failed webhooks?" data-confirm-message="Existing CRM leads will be skipped. Retry old failed Meta webhooks now?">
                            @csrf
                            <input type="hidden" name="limit" value="10">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700">
                                <i class="fas fa-rotate text-[11px]"></i>
                                <span>Retry failed</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="p-5">
                @if(isset($webhookEvents) && $webhookEvents->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($webhookEvents as $event)
                            <div class="rounded-2xl border px-4 py-3 {{ $event->status === 'failed' ? 'border-red-200 bg-red-50/60' : ($event->status === 'ignored_disabled' ? 'border-slate-200 bg-slate-100/80' : 'border-slate-200 bg-slate-50/70') }}">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-900">{{ $event->leadgen_id }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $event->created_at->format('d M Y, h:i:s A') }}</div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if($event->status === 'processed')
                                            <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">Processed</span>
                                        @elseif($event->status === 'failed')
                                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Failed</span>
                                        @elseif($event->status === 'received')
                                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Pending</span>
                                        @elseif($event->status === 'ignored_disabled')
                                            <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">Ignored: Disabled Form</span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $event->status }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if($event->error)
                                    <div class="mt-2 text-xs text-red-600">{{ $event->error }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-5 py-10 text-center text-sm text-slate-500">
                        No webhook events yet.
                    </div>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Recent Leads from Meta</h3>
                    <p class="text-sm text-slate-500">Recent Meta leads.</p>
                </div>
                @if(isset($recentLeads) && $recentLeads->isNotEmpty())
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $recentLeads->count() }} leads</span>
                @endif
            </div>
            <div class="p-5">
                @if(isset($recentLeads) && $recentLeads->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($recentLeads as $lead)
                            @php
                                $data = $lead->field_data_json ?? [];
                                $name = $data['name'] ?? $data['full_name'] ?? '-';
                                $email = $data['email'] ?? '-';
                                $phone = $data['phone'] ?? $data['phone_number'] ?? '-';
                            @endphp
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-bold text-slate-900">{{ $name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $lead->form?->form_name ?: '-' }}</div>
                                    </div>
                                    <div class="text-[11px] text-slate-500">{{ $lead->created_at->format('d M, h:i A') }}</div>
                                </div>
                                <div class="mt-3 grid gap-2 text-xs text-slate-600">
                                    <div><span class="font-semibold text-slate-700">Email:</span> {{ $email }}</div>
                                    <div><span class="font-semibold text-slate-700">Phone:</span> {{ $phone }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 px-5 py-10 text-center text-sm text-slate-500">
                        No recent Meta leads found.
                    </div>
                @endif
            </div>
        </section>
    </div>

    <div id="facebookFormDetailModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-950/60 p-3 sm:p-5">
        <div class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <div class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">Meta Form Detail</div>
                    <h3 id="facebookFormDetailTitle" class="mt-1 text-xl font-black leading-tight text-slate-900 sm:text-2xl">Loading</h3>
                    <div id="facebookFormDetailSubtitle" class="mt-1 break-words text-xs font-semibold text-slate-500"></div>
                </div>
                <button type="button" onclick="closeFacebookFormDetail()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="facebookFormDetailLoading" class="flex min-h-[260px] items-center justify-center text-sm font-semibold text-slate-500">Loading form detail...</div>
            <div id="facebookFormDetailBody" class="hidden overflow-y-auto overflow-x-hidden p-4 sm:p-6"></div>
        </div>
    </div>

    <div id="metaFormCallbackModal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-slate-950/55 p-4">
        <div class="w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="border-b border-slate-200 bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-4 text-white">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs font-black uppercase tracking-[0.16em] text-emerald-100">Old Meta Leads</div>
                        <h3 id="metaFormCallbackTitle" class="mt-1 truncate text-lg font-black">Import Old Leads</h3>
                        <p class="mt-1 text-sm text-emerald-50/90">Selected form ke purane leads Meta se pull hoke CRM me import honge. Existing leads skip rahenge.</p>
                    </div>
                    <button type="button" onclick="closeMetaFormCallback()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-white hover:bg-white/20">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <form id="metaFormCallbackForm" class="space-y-4 px-5 py-5">
                <div id="metaFormCallbackStatus" class="hidden rounded-xl border px-4 py-3 text-sm font-semibold"></div>
                <div id="metaFormCallbackPreview" class="hidden rounded-xl border border-slate-200 bg-slate-50"></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.12em] text-slate-500">From</span>
                        <input type="date" name="since" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100">
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.12em] text-slate-500">To</span>
                        <input type="date" name="until" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-black uppercase tracking-[0.12em] text-slate-500">Limit</span>
                        <select name="limit" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-[#205A44] focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                        </select>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" onclick="this.closest('form').elements.limit.value = '25'" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">25</button>
                            <button type="button" onclick="this.closest('form').elements.limit.value = '50'" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">50</button>
                            <button type="button" onclick="this.closest('form').elements.limit.value = '100'" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 hover:bg-emerald-100">100</button>
                        </div>
                    </label>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                    Pehle preview aayega. Confirm import ke baad old leads CRM me create honge, par automatic cloud calling/task trigger nahi hoga.
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeMetaFormCallback()" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button id="metaFormCallbackSubmit" type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#0b6b34] px-4 py-2 text-sm font-semibold text-white hover:bg-[#09582b] disabled:bg-slate-300">
                        <i class="fas fa-cloud-arrow-down text-xs"></i>
                        <span>Preview Leads</span>
                    </button>
                    <button id="metaFormCallbackRefresh" type="button" onclick="window.location.reload()" class="hidden items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i class="fas fa-rotate text-xs"></i>
                        <span>Refresh Page</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="metaPageDrawer" class="fixed inset-0 z-[9998] hidden bg-slate-950/50">
        <div class="absolute inset-y-0 right-0 flex w-full max-w-3xl flex-col bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
                            <i class="fab fa-facebook-f text-blue-600"></i>
                            <span>Meta Page Detail</span>
                        </div>
                        <h3 id="metaPageDrawerTitle" class="mt-1 truncate text-xl font-black text-slate-900">Loading</h3>
                        <div id="metaPageDrawerSubtitle" class="mt-1 truncate text-xs text-slate-500"></div>
                    </div>
                    <button type="button" onclick="closeMetaPageDrawer()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div id="metaPageDrawerToast" class="hidden border-b border-slate-200 px-5 py-3 text-sm font-semibold"></div>

            <div id="metaPageDrawerLoading" class="flex flex-1 items-center justify-center text-sm font-semibold text-slate-500">
                Loading page detail...
            </div>

            <div id="metaPageDrawerBody" class="hidden flex-1 overflow-y-auto">
                <div class="space-y-5 px-5 py-5">
                    <div class="grid gap-3 sm:grid-cols-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Forms</div>
                            <div id="metaPageStatForms" class="mt-1 text-xl font-black text-slate-900">0</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-emerald-600">Active</div>
                            <div id="metaPageStatActive" class="mt-1 text-xl font-black text-emerald-800">0</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Mapped</div>
                            <div id="metaPageStatMapped" class="mt-1 text-xl font-black text-slate-900">0</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Failed</div>
                            <div id="metaPageStatFailed" class="mt-1 text-xl font-black text-red-700">0</div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Webhook</div>
                                <div id="metaPageWebhookUrl" class="mt-1 truncate text-sm font-semibold text-slate-700"></div>
                                <div id="metaPageLastLead" class="mt-1 text-xs text-slate-500"></div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a id="metaPageUpdateTokenLink" href="{{ route($fbLeadAdsRoutePrefix . 'settings') }}" class="hidden items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
                                    <i class="fas fa-key text-[11px]"></i>
                                    <span>Update token</span>
                                </a>
                                <button id="metaPageCallbackBtn" type="button" onclick="callbackMetaPageLeads()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0b6b34] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#09582b] disabled:cursor-not-allowed disabled:bg-slate-300">
                                    <i class="fas fa-cloud-arrow-down text-[11px]"></i>
                                    <span>Call Back from Meta</span>
                                </button>
                            </div>
                        </div>
                        <div id="metaPageCallbackHint" class="mt-2 text-xs text-slate-500"></div>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-black text-slate-900">Forms & Mapping</h4>
                            <span id="metaPageFormSummary" class="text-xs font-semibold text-slate-500"></span>
                        </div>
                        <div id="metaPageFormsList" class="space-y-2"></div>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-black text-slate-900">Recent Meta Leads</h4>
                            <span id="metaPageLeadSummary" class="text-xs font-semibold text-slate-500"></span>
                        </div>
                        <div id="metaPageLeadsList" class="space-y-2"></div>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-black text-slate-900">Webhook Events</h4>
                            <span id="metaPageWebhookSummary" class="text-xs font-semibold text-slate-500"></span>
                        </div>
                        <div id="metaPageWebhookList" class="space-y-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="addPageModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-950/60 p-4">
        <div class="w-full max-w-lg overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-2xl">
            <div class="bg-gradient-to-r from-[#063A1C] to-[#205A44] px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-xl font-black">Add New Meta Page</h3>
                        <p class="mt-1 text-sm text-emerald-50/90">First save connection settings or open Forms to connect a page and configure its forms.</p>
                    </div>
                    <button type="button" onclick="closeAddPageModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-white transition hover:bg-white/20">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="space-y-4 px-6 py-6">
                <a href="{{ route($fbLeadAdsRoutePrefix . 'settings') }}" class="flex items-start gap-4 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4 transition hover:border-slate-300 hover:bg-slate-100">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-slate-600 shadow-sm">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-slate-900">Connection Settings</div>
                        <div class="mt-1 text-xs text-slate-500">Test token, load pages from Meta, and add the page you want to connect.</div>
                    </div>
                </a>
                <a href="{{ route($fbLeadAdsRoutePrefix . 'forms') }}" class="flex items-start gap-4 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4 transition hover:border-slate-300 hover:bg-slate-100">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-slate-600 shadow-sm">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-slate-900">Open Forms</div>
                        <div class="mt-1 text-xs text-slate-500">Go straight to page/form cards and configure or edit any connected form.</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div id="portfolioModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-950/60 p-4">
        <div class="w-full max-w-lg overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-2xl">
            <div class="bg-gradient-to-r from-[#063A1C] to-[#205A44] px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="portfolioModalTitle" class="text-xl font-black">Create Portfolio</h3>
                        <p id="portfolioModalSubtitle" class="mt-1 text-sm text-emerald-50/90">Portfolio banane ke baad pages ko dropdown se assign karo.</p>
                    </div>
                    <button type="button" onclick="closePortfolioModal()" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-white transition hover:bg-white/20">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <form id="portfolioForm" method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'portfolios.store') }}" class="space-y-4 px-6 py-6" data-store-url="{{ route($fbLeadAdsRoutePrefix . 'portfolios.store') }}" data-update-url-template="{{ route($fbLeadAdsRoutePrefix . 'portfolios.update', ['portfolio' => '__ID__']) }}">
                @csrf
                <input id="portfolioFormMethod" type="hidden" name="_method" value="POST">
                <div>
                    <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Portfolio Name</label>
                    <input id="portfolioNameInput" name="name" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-[#205A44] focus:ring-4 focus:ring-emerald-100" placeholder="Example: Prime Pocket, Base Infra, Villas">
                </div>
                <div>
                    <label class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Description</label>
                    <textarea id="portfolioDescriptionInput" name="description" rows="3" maxlength="1000" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-[#205A44] focus:ring-4 focus:ring-emerald-100" placeholder="Optional note for this portfolio"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closePortfolioModal()" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</button>
                    <button id="portfolioSubmitButton" type="submit" class="rounded-xl bg-[#0b6b34] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#09582b]">Create Portfolio</button>
                </div>
            </form>
        </div>
    </div>

    <div id="actionConfirmModal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-slate-950/50 p-4">
        <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-5 py-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <i class="fas fa-ban text-sm"></i>
                    </div>
                    <div>
                        <h3 id="actionConfirmTitle" class="text-base font-black text-slate-900">Confirm action</h3>
                        <p id="actionConfirmMessage" class="mt-1 text-sm leading-6 text-slate-600">Please confirm this action.</p>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 bg-slate-50 px-5 py-4">
                <button type="button" id="actionConfirmCancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Cancel</button>
                <button type="button" id="actionConfirmSubmit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        let pendingConfirmAction = null;
        let currentMetaPageDetailUrl = null;
        let currentMetaPageCallbackUrl = null;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[char];
            });
        }

        function closeFacebookFormDetail() {
            document.getElementById('facebookFormDetailModal')?.classList.add('hidden');
            document.getElementById('facebookFormDetailModal')?.classList.remove('flex');
        }

        let currentMetaFormCallbackUrl = null;
        let currentMetaFormCallbackPreviewSignature = null;

        function setMetaFormCallbackStatus(message, type = 'success') {
            const status = document.getElementById('metaFormCallbackStatus');
            if (!status) return;
            if (!message) {
                status.classList.add('hidden');
                status.textContent = '';
                return;
            }
            status.className = `rounded-xl border px-4 py-3 text-sm font-semibold ${type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`;
            status.textContent = message;
        }

        function metaImportConfirmation(summary) {
            const imported = Number(summary.imported || 0);
            const alreadyPresent = Number(summary.already_present || 0);
            const failed = Number(summary.failed || 0);
            const fetched = Number(summary.fetched || 0);

            if (imported > 0) {
                return `${imported} lead CRM me import ho gaye. ${alreadyPresent} lead pehle se CRM me the, ${failed} failed. Refresh Page click karke updated data dekho.`;
            }

            if (alreadyPresent > 0 && failed === 0) {
                return `New import 0 hai kyunki ${alreadyPresent} lead pehle se CRM me maujood hain. Duplicate create nahi hua.`;
            }

            if (fetched === 0) {
                return 'Selected date range me Meta se koi old lead nahi mila. Date range badha kar phir import try karo.';
            }

            return `Import complete. Imported: ${imported}, already in CRM: ${alreadyPresent}, failed: ${failed}.`;
        }

        function metaCallbackErrorMessage(data) {
            const errors = data?.errors || {};
            const firstKey = Object.keys(errors)[0];
            const firstError = firstKey && Array.isArray(errors[firstKey]) ? errors[firstKey][0] : null;
            return firstError || data?.message || 'Meta callback failed.';
        }

        function metaFormCallbackPayload(form, action) {
            return {
                since: form.elements.since.value,
                until: form.elements.until.value,
                limit: form.elements.limit.value || 50,
                action: action,
            };
        }

        function metaFormCallbackSignature(form) {
            return JSON.stringify(metaFormCallbackPayload(form, 'preview'));
        }

        function resetMetaFormCallbackPreview() {
            currentMetaFormCallbackPreviewSignature = null;
            const preview = document.getElementById('metaFormCallbackPreview');
            if (preview) {
                preview.classList.add('hidden');
                preview.innerHTML = '';
            }
            const submitText = document.querySelector('#metaFormCallbackSubmit span');
            if (submitText) submitText.textContent = 'Preview Leads';
        }

        function renderMetaFormCallbackPreview(summary, leads) {
            const preview = document.getElementById('metaFormCallbackPreview');
            if (!preview) return;

            const importable = Number(summary.importable || 0);
            const alreadyPresent = Number(summary.already_present || 0);
            const fetched = Number(summary.fetched || 0);
            const rows = (leads || []).map((lead) => `
                <div class="grid gap-2 border-t border-slate-200 px-4 py-3 text-xs sm:grid-cols-[1.2fr_1fr_1fr_0.8fr]">
                    <div class="min-w-0">
                        <div class="truncate font-black text-slate-900">${escapeHtml(lead.name || '-')}</div>
                        <div class="mt-1 break-all text-slate-500">${escapeHtml(lead.leadgen_id || '-')}</div>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-slate-700">${escapeHtml(lead.phone || '-')}</div>
                        <div class="mt-1 truncate text-slate-500">${escapeHtml(lead.email || '-')}</div>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-slate-700">${escapeHtml(lead.created_time || '-')}</div>
                        <div class="mt-1 truncate text-slate-500">${escapeHtml(lead.campaign_name || lead.ad_name || '-')}</div>
                    </div>
                    <div>
                        <span class="inline-flex rounded-full px-2.5 py-1 font-bold ${lead.importable ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'}">${lead.importable ? 'Ready' : 'Already CRM'}</span>
                    </div>
                </div>
            `).join('');

            preview.innerHTML = `
                <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-black text-slate-900">${importable} new leads import honge</div>
                        <div class="mt-1 text-xs text-slate-500">${fetched} fetched, ${alreadyPresent} already CRM me hain.</div>
                    </div>
                    <span class="inline-flex w-fit rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Confirm click ke baad import hoga</span>
                </div>
                <div class="max-h-72 overflow-y-auto">${rows || '<div class="border-t border-slate-200 px-4 py-6 text-center text-sm font-semibold text-slate-500">Is range me koi lead nahi mili.</div>'}</div>
            `;
            preview.classList.remove('hidden');

            const submitText = document.querySelector('#metaFormCallbackSubmit span');
            if (submitText) submitText.textContent = importable > 0 ? `Confirm Import (${importable})` : 'Preview Leads';
        }

        function openMetaFormCallback(url, formName) {
            currentMetaFormCallbackUrl = url;
            resetMetaFormCallbackPreview();
            const modal = document.getElementById('metaFormCallbackModal');
            const form = document.getElementById('metaFormCallbackForm');
            const today = new Date();
            const since = new Date();
            since.setDate(today.getDate() - 30);
            form?.reset();
            if (form) {
                form.elements.since.value = since.toISOString().slice(0, 10);
                form.elements.until.value = today.toISOString().slice(0, 10);
                form.elements.limit.value = 50;
            }
            document.getElementById('metaFormCallbackTitle').textContent = `Import Old Leads - ${formName || 'Meta Form'}`;
            document.getElementById('metaFormCallbackRefresh')?.classList.add('hidden');
            document.getElementById('metaFormCallbackRefresh')?.classList.remove('inline-flex');
            setMetaFormCallbackStatus('');
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
        }

        function closeMetaFormCallback() {
            currentMetaFormCallbackUrl = null;
            resetMetaFormCallbackPreview();
            document.getElementById('metaFormCallbackModal')?.classList.add('hidden');
            document.getElementById('metaFormCallbackModal')?.classList.remove('flex');
        }

        document.getElementById('metaFormCallbackForm')?.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!currentMetaFormCallbackUrl) return;

            const button = document.getElementById('metaFormCallbackSubmit');
            const oldHtml = button?.innerHTML;
            const signature = metaFormCallbackSignature(this);
            const shouldImport = currentMetaFormCallbackPreviewSignature === signature;
            const payload = metaFormCallbackPayload(this, shouldImport ? 'import' : 'preview');
            if (button) {
                button.disabled = true;
                button.innerHTML = `<i class="fas fa-spinner fa-spin text-xs"></i><span>${shouldImport ? 'Importing...' : 'Previewing...'}</span>`;
            }

            fetch(currentMetaFormCallbackUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(result => {
                    if (!result.ok || !result.data.success) {
                        throw new Error(metaCallbackErrorMessage(result.data));
                    }
                    if (result.data.mode === 'preview') {
                        currentMetaFormCallbackPreviewSignature = signature;
                        renderMetaFormCallbackPreview(result.data.summary || {}, result.data.leads || []);
                        setMetaFormCallbackStatus(result.data.message || 'Preview ready.', 'success');
                        return;
                    }
                    const s = result.data.summary || {};
                    setMetaFormCallbackStatus(metaImportConfirmation(s), 'success');
                    resetMetaFormCallbackPreview();
                    document.getElementById('metaFormCallbackRefresh')?.classList.remove('hidden');
                    document.getElementById('metaFormCallbackRefresh')?.classList.add('inline-flex');
                })
                .catch(error => {
                    setMetaFormCallbackStatus(error.message || 'Meta callback failed.', 'error');
                })
                .finally(() => {
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = oldHtml;
                        const submitText = button.querySelector('span');
                        if (submitText && currentMetaFormCallbackPreviewSignature) {
                            const importableText = document.querySelector('#metaFormCallbackPreview .text-sm')?.textContent?.match(/\d+/)?.[0];
                            submitText.textContent = importableText && Number(importableText) > 0 ? `Confirm Import (${importableText})` : 'Preview Leads';
                        }
                    }
                });
        });

        document.querySelectorAll('#metaFormCallbackForm input').forEach((input) => {
            input.addEventListener('input', resetMetaFormCallbackPreview);
        });

        function renderFacebookFormDetail(data) {
            const questions = data.questions || [];
            const mapping = data.mapping || [];
            const body = document.getElementById('facebookFormDetailBody');
            document.getElementById('facebookFormDetailTitle').textContent = data.name || data.form_id || 'Meta Form';
            document.getElementById('facebookFormDetailSubtitle').textContent = `Form ID ${data.form_id || '-'} | Page ${data.page?.page_name || '-'}`;

            const questionHtml = questions.length
                ? questions.map((question) => `
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="break-words text-sm font-black leading-5 text-slate-900">${escapeHtml(question.label)}</div>
                                <div class="mt-1 break-all text-xs leading-5 text-slate-500">Key: ${escapeHtml(question.key || '-')} | Type: ${escapeHtml(question.type || '-')}</div>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">#${question.position}</span>
                        </div>
                        ${question.options?.length ? `<div class="mt-3 flex flex-wrap gap-1.5">${question.options.map((option) => `<span class="max-w-full rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 break-words">${escapeHtml(option)}</span>`).join('')}</div>` : ''}
                    </div>
                `).join('')
                : '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Meta questions/fields available nahi hain. Token permission ya form API access check karo.</div>';

            const mappingHtml = mapping.length
                ? mapping.map((item) => `
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm">
                        <div class="break-all font-semibold leading-5 text-slate-800">${escapeHtml(item.fb_field)}</div>
                        <div class="mt-1 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">${escapeHtml(item.crm_field)}</div>
                    </div>
                `).join('')
                : '<div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">Mapping saved nahi hai.</div>';

            body.innerHTML = `
                ${data.meta_error ? `<div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">${escapeHtml(data.meta_error)}</div>` : ''}
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Meta Status</div><div class="mt-1 break-words text-sm font-black leading-5 text-slate-900">${escapeHtml(data.status || '-')}</div></div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Meta Created</div><div class="mt-1 break-words text-sm font-black leading-5 text-slate-900">${escapeHtml(data.created_time || '-')}</div></div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Meta Modified</div><div class="mt-1 break-words text-sm font-black leading-5 text-slate-900">${escapeHtml(data.updated_time || '-')}</div></div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">CRM State</div><div class="mt-1 break-words text-sm font-black leading-5 text-slate-900">${data.is_enabled ? 'Enabled' : 'Disabled'} / ${data.mapping_ready ? 'Mapped' : 'Mapping Pending'}</div></div>
                </div>
                <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_360px] xl:grid-cols-[minmax(0,1fr)_400px]">
                    <section>
                        <div class="mb-3 flex items-center justify-between gap-3"><h4 class="text-sm font-black uppercase tracking-[0.14em] text-slate-500">Form Fields</h4><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">${questions.length} fields</span></div>
                        <div class="min-w-0 space-y-3">${questionHtml}</div>
                    </section>
                    <section>
                        <div class="mb-3 flex items-center justify-between gap-3"><h4 class="text-sm font-black uppercase tracking-[0.14em] text-slate-500">CRM Mapping</h4><a href="${escapeHtml(data.mapping_url)}" class="rounded-lg bg-[#0b6b34] px-3 py-2 text-xs font-semibold text-white">Edit Mapping</a></div>
                        <div class="min-w-0 space-y-2">${mappingHtml}</div>
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">CRM Created: ${escapeHtml(data.crm_created_at || '-')}<br>CRM Updated: ${escapeHtml(data.crm_updated_at || '-')}</div>
                    </section>
                </div>
            `;
        }

        function openFacebookFormDetail(url) {
            const modal = document.getElementById('facebookFormDetailModal');
            const loading = document.getElementById('facebookFormDetailLoading');
            const body = document.getElementById('facebookFormDetailBody');
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
            loading?.classList.remove('hidden');
            body?.classList.add('hidden');
            document.getElementById('facebookFormDetailTitle').textContent = 'Loading';
            document.getElementById('facebookFormDetailSubtitle').textContent = '';

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload.success) throw new Error(payload.message || 'Could not load form detail');
                    renderFacebookFormDetail(payload.data);
                    loading?.classList.add('hidden');
                    body?.classList.remove('hidden');
                })
                .catch((error) => {
                    loading?.classList.add('hidden');
                    body.innerHTML = `<div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700">${escapeHtml(error.message || 'Could not load form detail')}</div>`;
                    body?.classList.remove('hidden');
                });
        }

        function statusClasses(status) {
            if (status === 'processed' || status === 'checked') return 'bg-emerald-50 text-emerald-700';
            if (status === 'failed') return 'bg-red-50 text-red-700';
            if (status === 'received') return 'bg-amber-50 text-amber-700';
            return 'bg-slate-100 text-slate-600';
        }

        function showMetaPageToast(message, tone) {
            const toast = document.getElementById('metaPageDrawerToast');
            if (!toast) return;
            toast.textContent = message || '';
            toast.className = 'border-b px-5 py-3 text-sm font-semibold ' + (
                tone === 'error'
                    ? 'border-red-200 bg-red-50 text-red-700'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-700'
            );
            toast.classList.toggle('hidden', !message);
        }

        function setMetaPageDrawerLoading(isLoading) {
            document.getElementById('metaPageDrawerLoading')?.classList.toggle('hidden', !isLoading);
            document.getElementById('metaPageDrawerBody')?.classList.toggle('hidden', isLoading);
        }

        function openMetaPageDrawer(trigger) {
            currentMetaPageDetailUrl = trigger?.dataset?.pageDetailUrl || null;
            currentMetaPageCallbackUrl = null;
            document.getElementById('metaPageDrawer')?.classList.remove('hidden');
            document.getElementById('metaPageDrawerTitle').textContent = 'Loading';
            document.getElementById('metaPageDrawerSubtitle').textContent = '';
            showMetaPageToast('', 'success');
            setMetaPageDrawerLoading(true);

            if (!currentMetaPageDetailUrl) {
                showMetaPageToast('Page detail URL missing.', 'error');
                setMetaPageDrawerLoading(false);
                return;
            }

            fetch(currentMetaPageDetailUrl, { headers: { 'Accept': 'application/json' } })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(result => {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.message || 'Failed to load page detail.');
                    }
                    renderMetaPageDrawer(result.data.data);
                })
                .catch(error => {
                    showMetaPageToast(error.message || 'Failed to load page detail.', 'error');
                    setMetaPageDrawerLoading(false);
                });
        }

        function closeMetaPageDrawer() {
            document.getElementById('metaPageDrawer')?.classList.add('hidden');
        }

        function renderMetaPageDrawer(data) {
            currentMetaPageCallbackUrl = data?.callback?.url || null;
            document.getElementById('metaPageDrawerTitle').textContent = data.page.page_name || data.page.page_id;
            document.getElementById('metaPageDrawerSubtitle').textContent = `ID ${data.page.page_id} | Token ${data.page.token_label}`;
            document.getElementById('metaPageStatForms').textContent = data.stats.forms;
            document.getElementById('metaPageStatActive').textContent = data.stats.active;
            document.getElementById('metaPageStatMapped').textContent = data.stats.mapped;
            document.getElementById('metaPageStatFailed').textContent = data.stats.webhook_failed;
            document.getElementById('metaPageWebhookUrl').textContent = data.webhook.url;
            document.getElementById('metaPageLastLead').textContent = `Last lead: ${data.stats.last_lead_at || 'Never'} | Webhook health: ${data.webhook.health}`;
            document.getElementById('metaPageFormSummary').textContent = `${data.stats.active} active, ${data.stats.unmapped} mapping pending`;
            document.getElementById('metaPageLeadSummary').textContent = `${data.recent_leads.length} shown`;
            document.getElementById('metaPageWebhookSummary').textContent = `${data.stats.webhook_processed} processed, ${data.stats.webhook_pending} pending`;

            const tokenLink = document.getElementById('metaPageUpdateTokenLink');
            tokenLink?.classList.toggle('hidden', !!data.page.token_ok);
            tokenLink?.classList.toggle('inline-flex', !data.page.token_ok);

            const callbackBtn = document.getElementById('metaPageCallbackBtn');
            if (callbackBtn) {
                callbackBtn.disabled = !data.callback.enabled;
            }
            document.getElementById('metaPageCallbackHint').textContent = data.callback.disabled_reason || `Pull missing leads from last ${data.callback.default_days} days. Limit ${data.callback.limit} per click.`;

            renderMetaPageForms(data.forms || []);
            renderMetaPageLeads(data.recent_leads || []);
            renderMetaPageWebhooks(data.webhook_events || []);
            setMetaPageDrawerLoading(false);
        }

        function renderMetaPageForms(forms) {
            const target = document.getElementById('metaPageFormsList');
            if (!target) return;
            if (!forms.length) {
                target.innerHTML = '<div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No forms found.</div>';
                return;
            }

            target.innerHTML = forms.map(form => {
                const mapping = (form.mapping || []).length
                    ? `<details class="mt-2"><summary class="cursor-pointer text-xs font-semibold text-slate-500">View mapping</summary><div class="mt-2 grid gap-1">${form.mapping.map(row => `<div class="flex justify-between gap-3 rounded-lg bg-slate-50 px-2 py-1 text-[11px]"><span class="text-slate-500">${escapeHtml(row.fb_field)}</span><span class="font-semibold text-slate-700">${escapeHtml(row.crm_field)}</span></div>`).join('')}</div></details>`
                    : '<div class="mt-2 text-xs text-amber-700">Mapping pending. Fallback mapping will be attempted during callback.</div>';

                return `
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold text-slate-900">${escapeHtml(form.form_name)}</div>
                                <div class="mt-1 text-xs text-slate-500">Form ID: ${escapeHtml(form.form_id)} | Last lead: ${escapeHtml(form.last_lead_at || 'Never')}</div>
                                ${mapping}
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold ${form.is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}">${form.is_enabled ? 'Enabled' : 'Disabled'}</span>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold ${form.mapping_ready ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}">${form.mapping_ready ? 'Mapped' : 'Mapping pending'}</span>
                                <a href="${escapeHtml(form.mapping_url)}" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200">Edit</a>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderMetaPageLeads(leads) {
            const target = document.getElementById('metaPageLeadsList');
            if (!target) return;
            if (!leads.length) {
                target.innerHTML = '<div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No recent Meta leads found.</div>';
                return;
            }

            target.innerHTML = leads.map(lead => `
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-slate-900">${escapeHtml(lead.name)}</div>
                            <div class="mt-1 text-xs text-slate-500">${escapeHtml(lead.phone)} | ${escapeHtml(lead.email)}</div>
                            <div class="mt-1 text-xs text-slate-500">Leadgen: ${escapeHtml(lead.leadgen_id)} | ${escapeHtml(lead.form_name)}</div>
                            <div class="mt-1 text-xs text-slate-500">${escapeHtml(lead.campaign_name || '-')} / ${escapeHtml(lead.ad_name || '-')}</div>
                        </div>
                        <div class="shrink-0 text-right text-xs text-slate-500">
                            <div>${escapeHtml(lead.created_at)}</div>
                            ${lead.crm_url ? `<a href="${escapeHtml(lead.crm_url)}" class="mt-2 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700 hover:bg-emerald-100">Open CRM</a>` : '<span class="mt-2 inline-flex rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">No CRM lead</span>'}
                        </div>
                    </div>
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-semibold text-slate-500">Raw JSON</summary>
                        <pre class="mt-2 max-h-44 overflow-auto rounded-lg bg-slate-950 px-3 py-2 text-[11px] leading-5 text-slate-100">${escapeHtml(JSON.stringify(lead.raw || {}, null, 2))}</pre>
                    </details>
                </div>
            `).join('');
        }

        function renderMetaPageWebhooks(events) {
            const target = document.getElementById('metaPageWebhookList');
            if (!target) return;
            if (!events.length) {
                target.innerHTML = '<div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No webhook events found for this page.</div>';
                return;
            }

            target.innerHTML = events.map(event => `
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-slate-900">${escapeHtml(event.leadgen_id || '-')}</div>
                            <div class="mt-1 text-xs text-slate-500">Form ID: ${escapeHtml(event.form_id || '-')} | ${escapeHtml(event.created_at)}</div>
                            ${event.error ? `<div class="mt-1 text-xs text-red-600">${escapeHtml(event.error)}</div>` : ''}
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${statusClasses(event.status)}">${escapeHtml(event.status)}</span>
                    </div>
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-semibold text-slate-500">Raw JSON</summary>
                        <pre class="mt-2 max-h-44 overflow-auto rounded-lg bg-slate-950 px-3 py-2 text-[11px] leading-5 text-slate-100">${escapeHtml(JSON.stringify(event.raw || {}, null, 2))}</pre>
                    </details>
                </div>
            `).join('');
        }

        function callbackMetaPageLeads() {
            const btn = document.getElementById('metaPageCallbackBtn');
            if (!currentMetaPageCallbackUrl || !btn) return;

            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin text-[11px]"></i><span>Calling back...</span>';
            showMetaPageToast('', 'success');

            fetch(currentMetaPageCallbackUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ limit: 100 })
            })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(result => {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.message || 'Meta callback failed.');
                    }
                    showMetaPageToast(result.data.message, 'success');
                    renderMetaPageDrawer(result.data.data);
                })
                .catch(error => {
                    showMetaPageToast(error.message || 'Meta callback failed.', 'error');
                })
                .finally(() => {
                    btn.innerHTML = oldHtml;
                    btn.disabled = false;
                });
        }

        function openActionConfirm(title, message, onConfirm) {
            pendingConfirmAction = onConfirm;
            document.getElementById('actionConfirmTitle').textContent = title || 'Confirm action';
            document.getElementById('actionConfirmMessage').textContent = message || 'Please confirm this action.';
            document.getElementById('actionConfirmModal')?.classList.remove('hidden');
            document.getElementById('actionConfirmModal')?.classList.add('flex');
        }

        function closeActionConfirm() {
            pendingConfirmAction = null;
            document.getElementById('actionConfirmModal')?.classList.add('hidden');
            document.getElementById('actionConfirmModal')?.classList.remove('flex');
        }

        document.querySelectorAll('.js-confirm-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                openActionConfirm(
                    form.dataset.confirmTitle,
                    form.dataset.confirmMessage,
                    function () {
                        form.submit();
                    }
                );
            });
        });

        document.getElementById('actionConfirmCancel')?.addEventListener('click', closeActionConfirm);
        document.getElementById('actionConfirmSubmit')?.addEventListener('click', function () {
            const action = pendingConfirmAction;
            closeActionConfirm();
            if (typeof action === 'function') {
                action();
            }
        });
        document.getElementById('actionConfirmModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeActionConfirm();
            }
        });

        document.getElementById('metaPageDrawer')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeMetaPageDrawer();
            }
        });

        document.getElementById('metaFormCallbackModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeMetaFormCallback();
            }
        });

        document.getElementById('facebookFormDetailModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeFacebookFormDetail();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMetaPageDrawer();
                closeFacebookFormDetail();
                closeMetaFormCallback();
            }
        });

        function openAddPageModal() {
            document.getElementById('addPageModal')?.classList.remove('hidden');
            document.getElementById('addPageModal')?.classList.add('flex');
        }

        function closeAddPageModal() {
            document.getElementById('addPageModal')?.classList.add('hidden');
            document.getElementById('addPageModal')?.classList.remove('flex');
        }

        function openPortfolioModal(id = null, name = '', description = '') {
            const form = document.getElementById('portfolioForm');
            const methodInput = document.getElementById('portfolioFormMethod');
            const storeUrl = form?.getAttribute('data-store-url') || '';
            const updateUrlTemplate = form?.getAttribute('data-update-url-template') || '';
            const isEdit = Boolean(id);

            if (form) {
                form.action = isEdit ? updateUrlTemplate.replace('__ID__', id) : storeUrl;
            }
            if (methodInput) {
                methodInput.value = isEdit ? 'PUT' : 'POST';
            }
            document.getElementById('portfolioModalTitle').textContent = isEdit ? 'Edit Portfolio' : 'Create Portfolio';
            document.getElementById('portfolioModalSubtitle').textContent = isEdit ? 'Portfolio details update karo.' : 'Portfolio banane ke baad pages ko dropdown se assign karo.';
            document.getElementById('portfolioNameInput').value = name || '';
            document.getElementById('portfolioDescriptionInput').value = description || '';
            document.getElementById('portfolioSubmitButton').textContent = isEdit ? 'Save Changes' : 'Create Portfolio';
            document.getElementById('portfolioModal')?.classList.remove('hidden');
            document.getElementById('portfolioModal')?.classList.add('flex');
        }

        function closePortfolioModal() {
            document.getElementById('portfolioModal')?.classList.add('hidden');
            document.getElementById('portfolioModal')?.classList.remove('flex');
        }

        document.getElementById('addPageModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeAddPageModal();
            }
        });

        document.getElementById('portfolioModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closePortfolioModal();
            }
        });

        function applyMetaPortfolioFilters() {
            const query = (document.getElementById('meta-page-search')?.value || '').trim().toLowerCase();
            const portfolioId = document.getElementById('meta-portfolio-filter')?.value || '';
            const status = document.getElementById('meta-status-filter')?.value || '';
            const hasActiveFilter = Boolean(query || portfolioId || status);

            document.querySelectorAll('[data-page-row]').forEach(function (row) {
                const text = row.getAttribute('data-search') || '';
                const rowPortfolio = row.getAttribute('data-portfolio-id') || '';
                const rowStatus = row.getAttribute('data-status') || '';
                const visible = (!query || text.includes(query))
                    && (!portfolioId || rowPortfolio === portfolioId)
                    && (!status || rowStatus.includes(status));

                row.classList.toggle('hidden', !visible);
            });

            document.querySelectorAll('[data-portfolio-block]').forEach(function (block) {
                const blockPortfolio = block.getAttribute('data-portfolio-id') || '';
                const blockText = block.getAttribute('data-search') || '';
                const portfolioMatches = !portfolioId || blockPortfolio === portfolioId;
                const searchMatches = !query || blockText.includes(query);
                const visibleRows = Array.from(block.querySelectorAll('[data-page-row]')).filter((row) => !row.classList.contains('hidden')).length;

                block.classList.toggle('hidden', !portfolioMatches || !searchMatches || visibleRows === 0);
                if (hasActiveFilter && portfolioMatches && searchMatches && visibleRows > 0) {
                    block.open = true;
                }
            });
        }

        document.getElementById('meta-page-search')?.addEventListener('input', applyMetaPortfolioFilters);
        document.getElementById('meta-portfolio-filter')?.addEventListener('change', applyMetaPortfolioFilters);
        document.getElementById('meta-status-filter')?.addEventListener('change', applyMetaPortfolioFilters);

        const portfolioBlocks = Array.from(document.querySelectorAll('[data-portfolio-block]'));
        const openPortfolioStorageKey = 'facebook-lead-ads-open-portfolios';
        try {
            const savedOpenPortfolios = JSON.parse(sessionStorage.getItem(openPortfolioStorageKey) || 'null');
            if (Array.isArray(savedOpenPortfolios)) {
                portfolioBlocks.forEach(function (block) {
                    block.open = savedOpenPortfolios.includes(block.getAttribute('data-portfolio-id') || '');
                });
            }
        } catch (error) {
            sessionStorage.removeItem(openPortfolioStorageKey);
        }

        portfolioBlocks.forEach(function (block) {
            block.addEventListener('toggle', function () {
                const openIds = portfolioBlocks
                    .filter(portfolio => portfolio.open)
                    .map(portfolio => portfolio.getAttribute('data-portfolio-id') || '');
                sessionStorage.setItem(openPortfolioStorageKey, JSON.stringify(openIds));
            });
        });

        function removePage(pageId, trigger) {
            if (!pageId) {
                return;
            }

            openActionConfirm('Remove page?', 'You can re-add this page later from Connection Settings.', function () {
                if (trigger) {
                    trigger.disabled = true;
                }

                fetch('{{ route($fbLeadAdsRoutePrefix . "remove-page") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ page_id: pageId, _token: '{{ csrf_token() }}' })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Failed to remove page');
                        }

                        window.location.reload();
                    })
                    .catch(error => {
                        alert(error.message || 'Failed to remove page');
                        if (trigger) {
                            trigger.disabled = false;
                        }
                    });
            });
        }
    </script>
</div>
@endsection
