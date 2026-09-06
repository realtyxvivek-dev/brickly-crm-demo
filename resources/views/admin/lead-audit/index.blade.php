@extends('layouts.app')

@section('title', 'Lead Detail Checker')
@section('page-title', 'Lead Detail Checker')
@section('page-subtitle', 'Trace lead identity, source path, import trail, and action history from one clean support workspace')

@section('content')
<div class="space-y-6">
    <div class="rounded-[28px] border border-emerald-100 bg-white shadow-[0_18px_50px_rgba(15,23,42,0.06)] overflow-hidden">
        <div class="border-b border-slate-100 bg-[radial-gradient(circle_at_top_right,_rgba(16,185,129,0.10),_transparent_36%),linear-gradient(135deg,_#ffffff_0%,_#f7fbf8_100%)] px-6 py-6 lg:px-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                        <i class="fas fa-shield-halved"></i>
                        Technical Support Tool
                    </div>
                    <h2 class="mt-4 text-3xl font-bold tracking-tight text-slate-950">Lead Detail Checker</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">
                        Find one lead and inspect the full journey in one place: source entry, import references, assignment flow,
                        task outcome, sync status, duplicate signal, and final diagnosis.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                            <i class="fas fa-phone text-emerald-600"></i> Phone
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                            <i class="fab fa-facebook text-blue-600"></i> Meta leadgen ID
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                            <i class="fas fa-id-badge text-violet-600"></i> CRM lead ID
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                            <i class="fas fa-phone-volume text-amber-600"></i> IVR / MCube call ID
                        </span>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-3 xl:min-w-[420px]">
                    <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-4 shadow-sm">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Use Case</div>
                        <div class="mt-2 text-sm font-semibold text-slate-900">Lead trace</div>
                        <div class="mt-1 text-xs leading-5 text-slate-500">Check one lead end to end.</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-4 shadow-sm">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Best For</div>
                        <div class="mt-2 text-sm font-semibold text-slate-900">Support debug</div>
                        <div class="mt-1 text-xs leading-5 text-slate-500">Find exact break point fast.</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-4 shadow-sm">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Output</div>
                        <div class="mt-2 text-sm font-semibold text-slate-900">Readable report</div>
                        <div class="mt-1 text-xs leading-5 text-slate-500">Timeline, flags, diagnosis, export.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 lg:px-8">
            <form method="GET" action="{{ route('admin.lead-audit.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
                <div>
                    <label for="leadAuditSearch" class="mb-2 block text-sm font-semibold text-slate-700">Search lead reference</label>
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 focus-within:border-emerald-500 focus-within:bg-white focus-within:ring-4 focus-within:ring-emerald-50">
                        <i class="fas fa-magnifying-glass text-slate-400"></i>
                        <input
                            id="leadAuditSearch"
                            name="search"
                            type="text"
                            value="{{ $search }}"
                            placeholder="Phone, CRM lead ID, Meta leadgen ID, MCube call ID"
                            class="w-full border-0 bg-transparent px-0 py-0 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                        >
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Single search se linked lead, import trail, assignment path, and sync issues ek hi page par milenge.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    @if($audit)
                    <a
                        href="{{ route('admin.lead-audit.export', ['context_type' => $audit['context']['contextType'], 'context_id' => $audit['context']['contextId']]) }}"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100"
                    >
                        <i class="fas fa-file-pdf"></i>
                        Export Report
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($search !== '' && !$audit && $candidates->isEmpty())
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-950">
        No lead trace found for this search.
    </div>
    @endif

    @if($search === '' && !$audit)
    <div class="rounded-[24px] border border-slate-200 bg-white px-6 py-6 shadow-sm lg:px-8">
        <div class="max-w-3xl">
            <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                <i class="fas fa-radar"></i>
                Support-ready debug view
            </div>
            <h3 class="mt-4 text-2xl font-bold text-slate-900">Trace any lead without opening backend logs</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                Use one search to inspect lead identity, source trail, raw inbound references, assignment/task outcomes,
                duplicate checks, Meta sync state, and failure diagnosis.
            </p>
        </div>
    </div>
    @endif

    @if($candidates->count() > 1)
    <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Multiple matches found</h2>
                <p class="mt-1 text-sm text-slate-500">Pick the exact record you want to inspect.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $candidates->count() }} results</span>
        </div>
        <div class="grid gap-3">
            @foreach($candidates as $candidate)
            <a
                href="{{ route('admin.lead-audit.index', ['search' => $search, 'context_type' => $candidate['type'], 'context_id' => $candidate['id']]) }}"
                class="rounded-2xl border border-slate-200 px-4 py-4 transition hover:border-emerald-300 hover:bg-emerald-50/40"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-base font-semibold text-slate-900">{{ $candidate['title'] }}</div>
                        <div class="mt-1 text-sm text-slate-500">{{ $candidate['subtitle'] }}</div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        {{ $candidate['badge'] }}
                    </span>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    @if($audit)
    @php
        $summary = $audit['summary'];
        $diagnosis = $audit['diagnosis'];
        $flags = $diagnosis['flags'];
    @endphp

    <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Lead Summary</p>
                <h2 class="text-2xl font-bold text-slate-900">{{ $summary['lead_name'] }}</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $summary['source'] }}</span>
                    <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700">{{ $summary['status'] }}</span>
                    <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">Owner: {{ $summary['owner'] }}</span>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[360px]">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $summary['phone'] }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created At</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $summary['created_at'] }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lead ID</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ $summary['lead_id'] ?: 'Not Created' }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">External IDs</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900">
                        @if(!empty($summary['external_ids']))
                            {{ collect($summary['external_ids'])->map(fn ($value, $label) => $label . ': ' . $value)->implode(' | ') }}
                        @else
                            N/A
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-7">
        @foreach([
            'lead_created' => 'Lead Created',
            'assignment_created' => 'Assignment',
            'task_created' => 'Task',
            'automation_matched' => 'Automation',
            'import_found' => 'Import',
            'meta_sync_found' => 'Meta Sync',
            'duplicate_detected' => 'Duplicate/Skip',
        ] as $key => $label)
        <div class="rounded-2xl border {{ $flags[$key] ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }} px-4 py-4">
            <div class="text-xs font-semibold uppercase tracking-wide {{ $flags[$key] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $label }}</div>
            <div class="mt-2 text-lg font-bold {{ $flags[$key] ? 'text-emerald-900' : 'text-rose-900' }}">{{ $flags[$key] ? 'Yes' : 'No' }}</div>
        </div>
        @endforeach
    </div>

    <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-3 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">System Diagnosis</h3>
                <p class="text-sm text-slate-500">Plain-language explanation of the most likely flow result</p>
            </div>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 font-medium text-amber-950">
            {{ $diagnosis['summary'] }}
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Latest Import Status</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $diagnosis['details']['latest_import_status'] ?: 'N/A' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Latest Meta Sync Status</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $diagnosis['details']['latest_meta_sync_status'] ?: 'N/A' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Latest Meta Sync Reason</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $diagnosis['details']['latest_meta_sync_reason'] ?: 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Audit Timeline</h3>
                    <p class="text-sm text-slate-500">Unified event stream across source, assignment, task, import, and sync</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $audit['timeline']->count() }} events</span>
            </div>
            <div class="space-y-4">
                @forelse($audit['timeline'] as $event)
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="text-base font-semibold text-slate-900">{{ $event['title'] }}</div>
                            <div class="mt-1 text-sm text-slate-600">{{ $event['description'] }}</div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">
                                    Actor: {{ $event['actor'] ?? 'System' }}
                                </span>
                                @if(!empty($event['entity']))
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-700">
                                    {{ $event['entity'] }}
                                </span>
                                @endif
                                @if(!empty($event['reference']))
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700">
                                    {{ $event['reference'] }}
                                </span>
                                @endif
                                @if(!empty($event['error']))
                                <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 font-semibold text-rose-700">
                                    Error: {{ \Illuminate\Support\Str::limit((string) $event['error'], 140) }}
                                </span>
                                @endif
                                @if(!empty($event['reason']))
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 font-semibold text-amber-800">
                                    Cancel Reason: {{ \Illuminate\Support\Str::limit((string) $event['reason'], 180) }}
                                </span>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $event['source'] }}</div>
                            <div class="mt-1 text-sm text-slate-700">{{ optional($event['timestamp'])->format('d M Y, h:i A') }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $event['status'] ?: 'logged' }}</div>
                        </div>
                    </div>
                    @if(!empty($event['payload']))
                    <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Payload Snapshot</summary>
                        <pre class="mt-3 overflow-x-auto whitespace-pre-wrap break-words rounded-xl bg-slate-900/95 p-4 text-xs leading-6 text-slate-100">{{ json_encode($event['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                    @endif
                </div>
                @empty
                <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
                    No timeline events logged for this lead.
                </div>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                        <i class="fas fa-route"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Audit Context</h3>
                        <p class="text-sm text-slate-500">Current record path and linked object details</p>
                    </div>
                </div>
                <dl class="space-y-4 text-sm">
                    @foreach($audit['context'] as $label => $value)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ \Illuminate\Support\Str::headline($label) }}</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ is_scalar($value) || $value === null ? ($value ?: 'N/A') : json_encode($value) }}</dd>
                    </div>
                    @endforeach
                </dl>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                        <i class="fas fa-link"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Raw Evidence Panels</h3>
                        <p class="text-sm text-slate-500">Imported rows, sync logs, webhook payloads, and linked raw references</p>
                    </div>
                </div>
                <div class="space-y-4">
                    @forelse($audit['rawPanels'] as $panel)
                    <div>
                        <div class="mb-1 text-sm font-semibold text-slate-900">{{ $panel['title'] ?? 'Panel' }}</div>
                        <div class="mb-2 text-xs text-slate-500">{{ $panel['subtitle'] ?? 'Raw supporting data' }}</div>
                        <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700">Open data</summary>
                            <pre class="mt-3 overflow-x-auto whitespace-pre-wrap break-words rounded-xl bg-slate-900/95 p-4 text-xs leading-6 text-slate-100">{{ json_encode($panel['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    </div>
                    @empty
                    <div class="rounded-xl border border-dashed border-slate-200 px-4 py-4 text-sm text-slate-500">No raw evidence found.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
