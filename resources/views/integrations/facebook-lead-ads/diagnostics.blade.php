@extends('layouts.app')

@php
    $fbLeadAdsRoutePrefix = request()->routeIs('ad-manager.meta.facebook-lead-ads.*') ? 'ad-manager.meta.facebook-lead-ads.' : 'integrations.facebook-lead-ads.';
    $sourceAutomationRoutePrefix = request()->routeIs('ad-manager.*') ? 'ad-manager.automation.' : 'admin.automation.';
    $metaOpsBackRoute = request()->routeIs('ad-manager.*') ? route('ad-manager.meta.index') : route('integrations.index');
@endphp

@section('title', 'Facebook Lead Ads Diagnostics - ' . brand_name())
@section('page-title', 'Facebook Lead Ads Diagnostics')

@section('header-actions')
    <a href="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
        <i class="fas fa-arrow-left text-xs"></i>
        <span>Back to Meta Integration</span>
    </a>
@endsection

@section('content')
@php
    $toneClasses = [
        'green' => 'border-green-200 bg-green-50 text-green-800',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
        'red' => 'border-red-200 bg-red-50 text-red-800',
    ];
    $healthClass = $toneClasses[$healthSnapshot['tone'] ?? 'green'] ?? $toneClasses['green'];
@endphp

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
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-sm font-black">Facebook Lead Ads needs attention: {{ $healthSnapshot['label'] }}</div>
                    <div class="mt-2 space-y-1 text-sm">
                        @foreach(($healthSnapshot['reasons'] ?? []) as $reason)
                            <div>{{ $reason }}</div>
                        @endforeach
                    </div>
                </div>
                @if(!empty($healthSnapshot['actions']))
                    <div class="rounded-2xl bg-white/70 px-4 py-3 text-sm">
                        <div class="font-black">Next action</div>
                        <div class="mt-1">{{ $healthSnapshot['actions'][0] }}</div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-[#0b3b1d] via-[#205A44] to-[#2e6f56] px-6 py-6 text-white sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <div class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white/12 shadow-inner">
                        <i class="fas fa-heart-pulse text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-black tracking-tight sm:text-3xl">Lead Ads Health Monitor</h2>
                    <p class="mt-2 max-w-2xl text-sm text-emerald-50/90 sm:text-base">Meta webhook se CRM lead creation tak ka live diagnostic view.</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route($fbLeadAdsRoutePrefix . 'settings') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-[#0b3b1d] shadow-sm transition hover:bg-emerald-50">
                            <i class="fas fa-sliders text-xs"></i>
                            <span>Settings</span>
                        </a>
                        <a href="{{ route($fbLeadAdsRoutePrefix . 'forms') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/15">
                            <i class="fas fa-layer-group text-xs"></i>
                            <span>Forms</span>
                        </a>
                        <a href="{{ route($fbLeadAdsRoutePrefix . 'missing-checker') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/15">
                            <i class="fas fa-file-csv text-xs"></i>
                            <span>Missing Checker</span>
                        </a>
                    </div>
                </div>

                <div class="w-full rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm lg:max-w-xl">
                    <div class="text-xs uppercase tracking-[0.18em] text-emerald-50/70">Overall Status</div>
                    <div class="mt-2 flex items-center gap-3">
                        <span class="inline-flex rounded-full border px-3 py-1 text-sm font-black {{ $healthClass }}">{{ $healthSnapshot['label'] }}</span>
                        <span class="text-sm text-emerald-50/90">{{ $range['from']->format('d M Y') }} - {{ $range['to']->format('d M Y') }}</span>
                    </div>
                    <div class="mt-4 space-y-2 text-sm text-emerald-50/90">
                        @foreach($healthSnapshot['reasons'] as $reason)
                            <div class="flex gap-2">
                                <i class="fas fa-circle-info mt-1 text-[11px]"></i>
                                <span>{{ $reason }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200 bg-slate-50/70 px-6 py-5 sm:px-8">
            <form method="GET" action="{{ route($fbLeadAdsRoutePrefix . 'diagnostics') }}" class="grid gap-3 lg:grid-cols-[180px_180px_180px_1fr] lg:items-end">
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Range</label>
                    <select name="range" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:ring-4 focus:ring-emerald-100">
                        <option value="today" @selected($range['preset'] === 'today')>Today</option>
                        <option value="7d" @selected($range['preset'] === '7d')>7 Days</option>
                        <option value="30d" @selected($range['preset'] === '30d')>30 Days</option>
                        <option value="custom" @selected($range['preset'] === 'custom')>Custom</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">From</label>
                    <input type="date" name="date_from" value="{{ $range['date_from'] }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:ring-4 focus:ring-emerald-100">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">To</label>
                    <input type="date" name="date_to" value="{{ $range['date_to'] }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-[#205A44] focus:ring-4 focus:ring-emerald-100">
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                        <i class="fas fa-filter text-xs"></i>
                        <span>Apply</span>
                    </button>
                    <a href="{{ route($fbLeadAdsRoutePrefix . 'diagnostics') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        <i class="fas fa-rotate-left text-xs"></i>
                        <span>Reset</span>
                    </a>
                </div>
            </form>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'Webhooks Received', 'value' => $summary['webhooks_received'], 'icon' => 'fa-satellite-dish'],
            ['label' => 'FB Leads Stored', 'value' => $summary['fb_leads_stored'], 'icon' => 'fa-database'],
            ['label' => 'CRM Leads Created', 'value' => $summary['crm_leads_created'], 'icon' => 'fa-user-plus'],
            ['label' => 'Unlinked FB Leads', 'value' => $summary['unlinked_fb_leads'], 'icon' => 'fa-link-slash'],
            ['label' => 'Unassigned Meta', 'value' => $summary['unassigned_meta_leads_7d'], 'icon' => 'fa-user-xmark'],
            ['label' => 'Failed Meta Jobs', 'value' => $summary['failed_meta_jobs'], 'icon' => 'fa-circle-exclamation'],
            ['label' => 'Failed Webhooks', 'value' => $summary['failed'], 'icon' => 'fa-triangle-exclamation'],
            ['label' => 'Suspected Missing', 'value' => $summary['suspected_missing'], 'icon' => 'fa-magnifying-glass-chart'],
            ['label' => 'Connected Pages', 'value' => $summary['connected_pages'], 'icon' => 'fa-link'],
            ['label' => 'Enabled Forms', 'value' => $summary['enabled_forms'], 'icon' => 'fa-list-check'],
            ['label' => 'Mapped Forms', 'value' => $summary['mapped_forms'], 'icon' => 'fa-diagram-project'],
        ] as $card)
            <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">{{ $card['label'] }}</div>
                        <div class="mt-3 text-3xl font-black text-slate-950">{{ $card['value'] }}</div>
                    </div>
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-[#205A44]">
                        <i class="fas {{ $card['icon'] }}"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-xl font-black text-slate-900">Recent Meta Leads</h3>
                <p class="mt-1 text-sm text-slate-500">Latest Facebook lead fetches with CRM handoff status.</p>
            </div>
            <a href="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                <i class="fas fa-magnifying-glass text-xs"></i>
                <span>Trace Lead</span>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-5 py-4 text-left">Lead</th>
                        <th class="px-5 py-4 text-left">Form</th>
                        <th class="px-5 py-4 text-left">Page</th>
                        <th class="px-5 py-4 text-left">CRM Status</th>
                        <th class="px-5 py-4 text-left">Source</th>
                        <th class="px-5 py-4 text-left">Received</th>
                        <th class="px-5 py-4 text-left">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($recentMetaLeads as $fbLead)
                        @php
                            $fieldData = collect($fbLead->field_data_json ?? []);
                            $leadName = $fbLead->crmLead?->name
                                ?: ($fieldData->get('full_name') ?: $fieldData->get('name') ?: $fieldData->get('first_name') ?: 'Meta Lead');
                            $leadPhone = $fbLead->crmLead?->phone
                                ?: ($fieldData->get('phone_number') ?: $fieldData->get('phone') ?: null);
                            $isCsvRecovery = data_get($fbLead->raw_response_json, 'csv_fallback_imported') || data_get($fbLead->raw_response_json, 'source') === 'manual_missing_checker_import';
                            $isFallback = data_get($fbLead->raw_response_json, '_crm_import_mode') === 'fallback_mapping';
                        @endphp
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-black text-slate-900">{{ $leadName }}</div>
                                <div class="mt-1 text-xs text-slate-500">Leadgen: {{ $fbLead->leadgen_id }}</div>
                                @if($leadPhone)
                                    <div class="mt-1 text-xs text-slate-500">{{ $leadPhone }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ $fbLead->form?->form_name ?: 'Unknown form' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ $fbLead->form?->page?->page_name ?: 'N/A' }}</td>
                            <td class="px-5 py-4">
                                @if($fbLead->crmLead)
                                    <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-black text-green-700">CRM #{{ $fbLead->crmLead->id }}</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700">No CRM link</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($isCsvRecovery)
                                    <span class="rounded-full bg-orange-50 px-2.5 py-1 text-xs font-black text-orange-700">CSV Recovery</span>
                                @elseif($isFallback)
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700">Fallback Mapping</span>
                                @else
                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-black text-blue-700">Real Webhook</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-500">{{ $fbLead->created_at?->format('d M, h:i A') }}</td>
                            <td class="px-5 py-4">
                                @if($fbLead->crmLead)
                                    <a href="{{ route('leads.show', $fbLead->crmLead) }}" class="rounded-xl bg-[#0b3b1d] px-3 py-2 text-xs font-semibold text-white">Open Lead</a>
                                @else
                                    <a href="{{ route($fbLeadAdsRoutePrefix . 'index', ['trace' => $fbLead->leadgen_id]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Trace</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-slate-500">No Meta lead has been stored yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-xl font-black text-slate-900">Connection Checks</h3>
                    <p class="mt-1 text-sm text-slate-500">Token, page, webhook, and Meta form fetch status.</p>
                </div>
                <span class="rounded-full border px-3 py-1 text-xs font-black {{ $healthClass }}">{{ $healthSnapshot['label'] }}</span>
            </div>
            <div class="mt-5 space-y-3 text-sm">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Webhook URL</div>
                    <div class="mt-2 flex gap-2">
                        <code class="min-w-0 flex-1 overflow-hidden text-ellipsis whitespace-nowrap rounded-xl bg-white px-3 py-2 text-slate-700">{{ $webhookUrl }}</code>
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $webhookUrl }}')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700">Copy</button>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Graph Version</div>
                        <div class="mt-2 font-black text-slate-900">{{ $settings->graph_version ?: 'Not set' }}</div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Signature Check</div>
                        <div class="mt-2 font-black text-slate-900">{{ $settings->signature_verification_enabled ? 'Enabled' : 'Off' }}</div>
                    </div>
                </div>
                @forelse($metaChecks as $check)
                    <div class="rounded-2xl border {{ $check['success'] ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }} p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-black text-slate-900">{{ $check['page_name'] ?: $check['page_id'] }}</div>
                                <div class="mt-1 text-xs text-slate-600">Page ID: {{ $check['page_id'] }}</div>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $check['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $check['success'] ? $check['forms_count'] . ' forms' : 'Failed' }}
                            </span>
                        </div>
                        @if(!$check['success'])
                            <div class="mt-3 text-sm text-red-700">{{ $check['error'] }}</div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">No connected page token found.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-xl font-black text-slate-900">Error Monitor</h3>
                    <p class="mt-1 text-sm text-slate-500">Latest failed webhook events and recovery shortcuts.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if(($summary['retryable_failed'] ?? 0) > 0)
                        <form method="POST" action="{{ route($fbLeadAdsRoutePrefix . 'retry-failed-webhooks') }}" onsubmit="return confirm('Retry failed Meta webhooks now? Existing CRM leads will be skipped.');">
                            @csrf
                            <input type="hidden" name="limit" value="10">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                                <i class="fas fa-rotate text-xs"></i>
                                <span>Retry {{ $summary['retryable_failed'] }}</span>
                            </button>
                        </form>
                    @endif
                    <a href="{{ route($fbLeadAdsRoutePrefix . 'missing-checker') }}" class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">
                        <i class="fas fa-file-csv text-xs"></i>
                        <span>Missing Checker</span>
                    </a>
                </div>
            </div>
            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Leadgen</th>
                            <th class="px-4 py-3 text-left">Form</th>
                            <th class="px-4 py-3 text-left">Page</th>
                            <th class="px-4 py-3 text-left">Error</th>
                            <th class="px-4 py-3 text-left">Retry</th>
                            <th class="px-4 py-3 text-left">Time</th>
                            <th class="px-4 py-3 text-left">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($latestFailedEvents as $row)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $row['event']->leadgen_id ?: 'N/A' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['form_id'] ?: 'N/A' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['page_id'] ?: 'N/A' }}</td>
                                <td class="max-w-md px-4 py-3 text-red-700">{{ Str::limit($row['event']->error ?: 'No error message', 120) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $row['retryable'] ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $row['retryable'] ? 'Retryable' : 'Manual check' }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $row['event']->created_at?->format('d M, h:i A') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route($fbLeadAdsRoutePrefix . 'index', ['trace' => $row['event']->leadgen_id]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Trace</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">No failed webhook in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h3 class="text-xl font-black text-slate-900">Form Wise Health</h3>
            <p class="mt-1 text-sm text-slate-500">Har form ka mapping, webhook, CRM creation, aur latest error status.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                    <tr>
                        <th class="px-5 py-4 text-left">Form</th>
                        <th class="px-5 py-4 text-left">Page</th>
                        <th class="px-5 py-4 text-left">Status</th>
                        <th class="px-5 py-4 text-left">Mapping</th>
                        <th class="px-5 py-4 text-right">Webhooks</th>
                        <th class="px-5 py-4 text-right">CRM Leads</th>
                        <th class="px-5 py-4 text-right">Failed</th>
                        <th class="px-5 py-4 text-right">Fallback</th>
                        <th class="px-5 py-4 text-left">Last Received</th>
                        <th class="px-5 py-4 text-left">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($formRows as $row)
                        @php($form = $row['form'])
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-black text-slate-900">{{ $form->form_name ?: 'Unnamed form' }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $form->form_id }}</div>
                                @if($row['latest_error'])
                                    <div class="mt-2 text-xs text-red-600">{{ Str::limit($row['latest_error'], 90) }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ $form->page?->page_name ?: 'N/A' }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $form->is_enabled ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $form->is_enabled ? 'Enabled' : 'Off' }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $row['mapping_ready'] ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $row['mapping_ready'] ? 'Mapped' : 'Needs mapping' }}</span>
                                @if(($row['fallback_leads'] ?? 0) > 0)
                                    <div class="mt-2 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700">Fallback used</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ $row['webhooks_received'] }}</td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-900">{{ $row['crm_leads_created'] }}</td>
                            <td class="px-5 py-4 text-right font-semibold {{ $row['failed'] > 0 ? 'text-red-700' : 'text-slate-900' }}">{{ $row['failed'] }}</td>
                            <td class="px-5 py-4 text-right font-semibold {{ ($row['fallback_leads'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $row['fallback_leads'] ?? 0 }}</td>
                            <td class="px-5 py-4 text-slate-500">{{ $row['last_received_at'] ? $row['last_received_at']->format('d M, h:i A') : '-' }}</td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route($fbLeadAdsRoutePrefix . 'mapping', ['formId' => $form->form_id, 'form_name' => $form->form_name, 'page_id' => $form->page?->page_id]) }}" class="rounded-xl bg-[#0b3b1d] px-3 py-2 text-xs font-semibold text-white">Mapping</a>
                                    <a href="{{ route($fbLeadAdsRoutePrefix . 'index', ['trace' => $form->form_id]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Trace</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-10 text-center text-slate-500">No forms configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-xl font-black text-slate-900">Webhook Without Stored Lead</h3>
            <p class="mt-1 text-sm text-slate-500">Webhook mila, but matching `fb_leads` row nahi mila.</p>
            <div class="mt-5 space-y-3">
                @forelse($missingWebhookRows as $row)
                    @php($event = $row['event'])
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-black text-slate-900">{{ $event->leadgen_id }}</div>
                                <div class="mt-1 text-xs text-slate-600">Form: {{ $row['form_id'] ?: 'N/A' }}</div>
                            </div>
                            <div class="text-xs text-slate-500">{{ $event->created_at?->format('d M, h:i A') }}</div>
                        </div>
                        @if($event->error)
                            <div class="mt-2 text-sm text-amber-800">{{ Str::limit($event->error, 140) }}</div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">No suspected missing webhook in this range.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-xl font-black text-slate-900">Stored Meta Lead Without CRM Lead</h3>
            <p class="mt-1 text-sm text-slate-500">Meta lead fetch hua, but CRM lead link missing hai.</p>
            <div class="mt-5 space-y-3">
                @forelse($fbLeadsWithoutCrm as $fbLead)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-black text-slate-900">{{ $fbLead->leadgen_id }}</div>
                                <div class="mt-1 text-xs text-slate-600">{{ $fbLead->form?->form_name ?: 'Unknown form' }}</div>
                            </div>
                            <div class="text-xs text-slate-500">{{ $fbLead->created_at?->format('d M, h:i A') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">No stored Meta lead without CRM link in this range.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
