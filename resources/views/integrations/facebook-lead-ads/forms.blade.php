@extends('layouts.app')

@php
    $fbLeadAdsRoutePrefix = request()->routeIs('ad-manager.meta.facebook-lead-ads.*') ? 'ad-manager.meta.facebook-lead-ads.' : 'integrations.facebook-lead-ads.';
    $sourceAutomationRoutePrefix = request()->routeIs('ad-manager.*') ? 'ad-manager.automation.' : 'admin.automation.';
    $metaOpsBackRoute = request()->routeIs('ad-manager.*') ? route('ad-manager.meta.index') : route('integrations.index');
@endphp

@section('title', 'Select Form - Facebook Lead Ads - ' . brand_name())
@section('page-title', 'Facebook Lead Ads Forms')

@section('header-actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
            <i class="fas fa-arrow-left text-xs"></i>
            <span>{{ isset($page) && $page ? 'Dashboard' : 'Back' }}</span>
        </a>
        @if(isset($page) && $page)
            <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'pages.forms.sync', $page) }}" class="js-confirm-form" data-confirm-title="Sync forms?" data-confirm-message="Meta se latest forms fetch honge. New forms pending/disabled state me add honge.">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm transition hover:bg-emerald-100">
                    <i class="fas fa-rotate text-xs"></i>
                    <span>Sync Forms</span>
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
<div class="w-full space-y-6">
    @foreach(['warning' => 'amber', 'error' => 'red', 'success' => 'green'] as $type => $color)
        @if(session($type))
            <div class="rounded-2xl border border-{{ $color }}-200 bg-{{ $color }}-50 px-5 py-4 text-sm text-{{ $color }}-800 shadow-sm">
                {{ session($type) }}
            </div>
        @endif
    @endforeach
    @if(!empty($syncWarning))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 shadow-sm">
            Meta se latest forms fetch nahi hue. Saved forms neeche available hain. {{ $syncWarning }}
        </div>
    @endif

    @if(isset($pages) && $pages->isNotEmpty())
        <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 bg-gradient-to-r from-[#063A1C] to-[#205A44] px-6 py-6 text-white lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-100">Facebook Pages</div>
                    <h2 class="mt-1 text-2xl font-black">Choose a Page</h2>
                    <p class="mt-2 text-sm text-emerald-50/90">Page open karke uske forms, mapping aur automation manage karo.</p>
                </div>
                <a href="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/20 transition hover:bg-white/15">
                    <i class="fas fa-grid-2 text-xs"></i>
                    <span>Open Dashboard</span>
                </a>
            </div>

            <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach($pages as $p)
                    <a href="{{ route($fbLeadAdsRoutePrefix . 'forms', ['page_id' => $p->page_id]) }}" class="group rounded-2xl border border-slate-200 bg-slate-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-white hover:shadow-md">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                                    <i class="fab fa-facebook-f text-lg"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-base font-black text-slate-900">{{ $p->page_name ?: $p->page_id }}</div>
                                    <div class="mt-1 truncate text-xs text-slate-500">ID {{ $p->page_id }}</div>
                                </div>
                            </div>
                            <i class="fas fa-arrow-right text-slate-400 transition group-hover:text-emerald-700"></i>
                        </div>
                        <div class="mt-5 rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Forms</div>
                            <div class="mt-1 text-sm font-semibold text-slate-800">Open form control</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @else
        @php
            $automationRules = $automationRules ?? collect();
            $formRows = collect($forms ?? [])->map(function ($form) use ($existingFormStates, $page, $automationRules, $fbLeadAdsRoutePrefix, $sourceAutomationRoutePrefix) {
                $metaId = (string) ($form['id'] ?? '');
                $name = $form['name'] ?? ('Form ' . $metaId);
                $localForm = isset($existingFormStates) ? $existingFormStates->get($metaId) : null;
                $automationRule = $localForm ? $automationRules->first(function ($rule) use ($localForm) {
                    return (int) $rule->fb_form_id === (int) $localForm->id
                        || ($rule->source_type === 'facebook_lead_ads' && (int) $rule->source_id === (int) $localForm->id)
                        || ($rule->relationLoaded('fbForms') && $rule->fbForms->contains('id', $localForm->id));
                }) : null;

                return [
                    'meta_id' => $metaId,
                    'name' => $name,
                    'created' => $form['created_time'] ?? '',
                    'local_form' => $localForm,
                    'exists' => (bool) $localForm,
                    'is_enabled' => (bool) ($localForm?->is_enabled ?? false),
                    'needs_mapping' => $localForm && !$localForm->mapping,
                    'mapping_ready' => $localForm && $localForm->mapping,
                    'automation_rule' => $automationRule,
                    'mapping_url' => route($fbLeadAdsRoutePrefix . 'mapping', ['formId' => $metaId, 'form_name' => $name, 'page_id' => $page->page_id]),
                    'automation_url' => $automationRule
                        ? route($sourceAutomationRoutePrefix . 'edit', $automationRule)
                        : route($sourceAutomationRoutePrefix . 'create', [
                            'source_type' => 'facebook_lead_ads',
                            'source_id' => $localForm?->id ?? $metaId,
                            'source_label' => $name,
                            'return_to' => request()->fullUrl(),
                        ]),
                ];
            });
            $enabledCount = $formRows->where('is_enabled', true)->count();
            $mappedCount = $formRows->where('mapping_ready', true)->count();
            $automationCount = $formRows->filter(fn ($row) => $row['automation_rule'])->count();
            $pendingCount = max(0, $formRows->count() - $mappedCount);
        @endphp

        <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 bg-gradient-to-r from-[#063A1C] to-[#205A44] px-6 py-6 text-white xl:flex-row xl:items-end xl:justify-between">
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-100">Lead Form Control</div>
                    <h2 class="mt-1 truncate text-2xl font-black">{{ $page->page_name ?: $page->page_id }}</h2>
                    <p class="mt-2 text-sm text-emerald-50/90">Forms ko sync, mapping, enable/disable aur automation ke saath manage karo.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'pages.forms.sync', $page) }}" class="js-confirm-form" data-confirm-title="Sync forms?" data-confirm-message="Meta se latest forms fetch honge. New forms pending/disabled state me add honge.">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-[#0b6b34] shadow-sm transition hover:bg-emerald-50">
                            <i class="fas fa-rotate text-xs"></i>
                            <span>Sync Forms</span>
                        </button>
                    </form>
                    <a href="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/20 transition hover:bg-white/15">
                        <i class="fas fa-grid-2 text-xs"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>

            <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Forms</div>
                        <div class="mt-1 text-2xl font-black text-slate-900">{{ $formRows->count() }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Enabled</div>
                        <div class="mt-1 text-2xl font-black text-emerald-800">{{ $enabledCount }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Mapped</div>
                        <div class="mt-1 text-2xl font-black {{ $pendingCount > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $mappedCount }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Automation</div>
                        <div class="mt-1 text-2xl font-black {{ $automationCount < $formRows->count() ? 'text-rose-700' : 'text-slate-900' }}">{{ $automationCount }}</div>
                    </div>
                </div>
            </div>

            <div class="p-5">
                @if($formRows->isEmpty())
                    <div class="rounded-[24px] border border-dashed border-slate-300 bg-slate-50/70 px-6 py-12 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm">
                            <i class="fas fa-folder-open text-xl"></i>
                        </div>
                        <div class="mt-4 text-lg font-bold text-slate-900">No forms found for this page</div>
                        <div class="mt-2 text-sm text-slate-500">Meta me form create karne ke baad Sync Forms click karo.</div>
                        <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'pages.forms.sync', $page) }}" class="js-confirm-form mt-5 inline-flex" data-confirm-title="Sync forms?" data-confirm-message="Meta se latest forms fetch honge.">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#0b6b34] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#09582b]">
                                <i class="fas fa-rotate text-xs"></i>
                                <span>Sync Forms</span>
                            </button>
                        </form>
                    </div>
                @else
                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <div class="hidden grid-cols-[minmax(0,1fr)_130px_140px_220px_300px] gap-3 bg-slate-50 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-slate-500 lg:grid">
                            <div>Form</div>
                            <div>Lead Intake</div>
                            <div>Mapping</div>
                            <div>Automation</div>
                            <div>Actions</div>
                        </div>
                        @foreach($formRows as $row)
                            <article class="grid gap-3 border-t border-slate-200 bg-white px-4 py-4 text-sm transition hover:bg-slate-50/70 lg:grid-cols-[minmax(0,1fr)_130px_140px_220px_300px] lg:items-center">
                                <div class="min-w-0">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                            <i class="fas fa-wpforms text-sm"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="truncate text-base font-black text-slate-900" title="{{ $row['name'] }}">{{ $row['name'] }}</div>
                                            <div class="mt-1 text-xs text-slate-500">Form ID {{ $row['meta_id'] }} - Created {{ $row['created'] ?: 'Not available' }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $row['is_enabled'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $row['is_enabled'] ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $row['mapping_ready'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $row['mapping_ready'] ? 'Mapped' : 'Mapping Pending' }}
                                    </span>
                                </div>
                                <div>
                                    @if($row['local_form'] && $automationRules->isNotEmpty())
                                        <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'forms.automation', $row['local_form']) }}">
                                            @csrf
                                            <label class="sr-only" for="automation-rule-{{ $row['local_form']->id }}">Automation</label>
                                            <select id="automation-rule-{{ $row['local_form']->id }}" name="automation_rule_id" onchange="if (this.value) this.form.submit()" class="w-full rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                                <option value="">Select automation</option>
                                                @foreach($automationRules as $automation)
                                                    <option value="{{ $automation->id }}" @selected($row['automation_rule']?->id === $automation->id)>{{ $automation->name }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $row['automation_rule'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                            {{ $row['automation_rule'] ? 'Automation Active' : 'Automation Pending' }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if($row['local_form'])
                                        <button type="button" onclick="openFacebookFormDetail('{{ route($fbLeadAdsRoutePrefix . 'forms.detail', $row['local_form']) }}')" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            <i class="fas fa-eye text-[11px]"></i>
                                            <span>View</span>
                                        </button>
                                        <button type="button" onclick="openMetaFormCallback('{{ route($fbLeadAdsRoutePrefix . 'forms.callback', $row['local_form']) }}', @js($row['name']))" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100">
                                            <i class="fas fa-cloud-arrow-down text-[11px]"></i>
                                            <span>Import Old</span>
                                        </button>
                                        <button type="button" onclick="openMetaFormCallback('{{ route($fbLeadAdsRoutePrefix . 'forms.callback', $row['local_form']) }}', @js($row['name']))" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-800 transition hover:bg-blue-100">
                                            <i class="fas fa-rotate text-[11px]"></i>
                                            <span>Sync</span>
                                        </button>
                                    @endif
                                    <a href="{{ $row['mapping_url'] }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                        <i class="fas fa-pen text-[11px]"></i>
                                        <span>{{ $row['exists'] ? 'Mapping' : 'Configure' }}</span>
                                    </a>
                                    @if($automationRules->isEmpty())
                                        <a href="{{ $row['automation_url'] }}" class="inline-flex items-center gap-1.5 rounded-lg bg-[#0b6b34] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#09582b]">
                                            <i class="fas fa-bolt text-[11px]"></i>
                                            <span>Create Automation</span>
                                        </a>
                                    @endif
                                    @if($row['local_form'])
                                        <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'forms.toggle', $row['local_form']) }}" class="js-confirm-form" data-confirm-title="{{ $row['is_enabled'] ? 'Disable form?' : 'Enable form?' }}" data-confirm-message="{{ $row['is_enabled'] ? 'New leads from this form will not be created in CRM.' : 'Enable only after mapping is reviewed.' }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold transition {{ $row['is_enabled'] ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                <i class="fas {{ $row['is_enabled'] ? 'fa-ban' : 'fa-check' }} text-[11px]"></i>
                                                <span>{{ $row['is_enabled'] ? 'Disable' : 'Enable' }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

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
            <div id="facebookFormDetailLoading" class="flex min-h-[260px] items-center justify-center text-sm font-semibold text-slate-500">
                Loading form detail...
            </div>
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
                        <p class="mt-1 text-sm text-emerald-50/90">Meta se selected form ke old leads pull hoke CRM me import honge. Existing leads skip rahenge.</p>
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
        function escapeFacebookFormHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }[char];
            });
        }

        function closeFacebookFormDetail() {
            document.getElementById('facebookFormDetailModal')?.classList.add('hidden');
            document.getElementById('facebookFormDetailModal')?.classList.remove('flex');
        }

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
                                <div class="break-words text-sm font-black leading-5 text-slate-900">${escapeFacebookFormHtml(question.label)}</div>
                                <div class="mt-1 break-all text-xs leading-5 text-slate-500">Key: ${escapeFacebookFormHtml(question.key || '-')} | Type: ${escapeFacebookFormHtml(question.type || '-')}</div>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">#${question.position}</span>
                        </div>
                        ${question.options?.length ? `<div class="mt-3 flex flex-wrap gap-1.5">${question.options.map((option) => `<span class="max-w-full rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 break-words">${escapeFacebookFormHtml(option)}</span>`).join('')}</div>` : ''}
                    </div>
                `).join('')
                : '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Meta questions/fields available nahi hain. Token permission ya form API access check karo.</div>';

            const mappingHtml = mapping.length
                ? mapping.map((item) => `
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm">
                        <div class="break-all font-semibold leading-5 text-slate-800">${escapeFacebookFormHtml(item.fb_field)}</div>
                        <div class="mt-1 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">${escapeFacebookFormHtml(item.crm_field)}</div>
                    </div>
                `).join('')
                : '<div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">Mapping saved nahi hai.</div>';

            body.innerHTML = `
                ${data.meta_error ? `<div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">${escapeFacebookFormHtml(data.meta_error)}</div>` : ''}
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Meta Status</div>
                        <div class="mt-1 break-words text-sm font-black leading-5 text-slate-900">${escapeFacebookFormHtml(data.status || '-')}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Meta Created</div>
                        <div class="mt-1 break-words text-sm font-black leading-5 text-slate-900">${escapeFacebookFormHtml(data.created_time || '-')}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Meta Modified</div>
                        <div class="mt-1 break-words text-sm font-black leading-5 text-slate-900">${escapeFacebookFormHtml(data.updated_time || '-')}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">CRM State</div>
                        <div class="mt-1 break-words text-sm font-black leading-5 text-slate-900">${data.is_enabled ? 'Enabled' : 'Disabled'} / ${data.mapping_ready ? 'Mapped' : 'Mapping Pending'}</div>
                    </div>
                </div>
                <div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_360px] xl:grid-cols-[minmax(0,1fr)_400px]">
                    <section>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h4 class="text-sm font-black uppercase tracking-[0.14em] text-slate-500">Form Fields</h4>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">${questions.length} fields</span>
                        </div>
                        <div class="min-w-0 space-y-3">${questionHtml}</div>
                    </section>
                    <section>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h4 class="text-sm font-black uppercase tracking-[0.14em] text-slate-500">CRM Mapping</h4>
                            <a href="${escapeFacebookFormHtml(data.mapping_url)}" class="rounded-lg bg-[#0b6b34] px-3 py-2 text-xs font-semibold text-white">Edit Mapping</a>
                        </div>
                        <div class="min-w-0 space-y-2">${mappingHtml}</div>
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">
                            CRM Created: ${escapeFacebookFormHtml(data.crm_created_at || '-')}<br>
                            CRM Updated: ${escapeFacebookFormHtml(data.crm_updated_at || '-')}
                        </div>
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
                    if (!payload.success) {
                        throw new Error(payload.message || 'Could not load form detail');
                    }
                    renderFacebookFormDetail(payload.data);
                    loading?.classList.add('hidden');
                    body?.classList.remove('hidden');
                })
                .catch((error) => {
                    loading?.classList.add('hidden');
                    body.innerHTML = `<div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700">${escapeFacebookFormHtml(error.message || 'Could not load form detail')}</div>`;
                    body?.classList.remove('hidden');
                });
        }

        document.getElementById('facebookFormDetailModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeFacebookFormDetail();
            }
        });

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
                        <div class="truncate font-black text-slate-900">${escapeFacebookFormHtml(lead.name || '-')}</div>
                        <div class="mt-1 break-all text-slate-500">${escapeFacebookFormHtml(lead.leadgen_id || '-')}</div>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-slate-700">${escapeFacebookFormHtml(lead.phone || '-')}</div>
                        <div class="mt-1 truncate text-slate-500">${escapeFacebookFormHtml(lead.email || '-')}</div>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-slate-700">${escapeFacebookFormHtml(lead.created_time || '-')}</div>
                        <div class="mt-1 truncate text-slate-500">${escapeFacebookFormHtml(lead.campaign_name || lead.ad_name || '-')}</div>
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
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then((result) => {
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
                .catch((error) => {
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

        document.getElementById('metaFormCallbackModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeMetaFormCallback();
            }
        });

        (function () {
            let pendingForm = null;
            const modal = document.getElementById('actionConfirmModal');
            const title = document.getElementById('actionConfirmTitle');
            const message = document.getElementById('actionConfirmMessage');
            const cancel = document.getElementById('actionConfirmCancel');
            const submit = document.getElementById('actionConfirmSubmit');

            function closeModal() {
                pendingForm = null;
                modal?.classList.add('hidden');
                modal?.classList.remove('flex');
            }

            document.querySelectorAll('.js-confirm-form').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    pendingForm = form;
                    if (title) {
                        title.textContent = form.dataset.confirmTitle || 'Confirm action';
                    }
                    if (message) {
                        message.textContent = form.dataset.confirmMessage || 'Please confirm this action.';
                    }
                    modal?.classList.remove('hidden');
                    modal?.classList.add('flex');
                });
            });

            cancel?.addEventListener('click', closeModal);
            submit?.addEventListener('click', function () {
                const form = pendingForm;
                closeModal();
                form?.submit();
            });
            modal?.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        })();
    </script>
</div>
@endsection
