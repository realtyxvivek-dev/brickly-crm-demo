@extends('layouts.app')

@section('title', 'Proposal Analytics - ' . brand_name())
@section('page-title', 'Proposal Analytics')

@php
    $projects = collect($projects ?? []);
    $summary = $summary ?? [];
    $formatDuration = function ($milliseconds): string {
        $seconds = (int) floor(((int) $milliseconds) / 1000);
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;
        if ($minutes < 60) {
            return $minutes . 'm ' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT) . 's';
        }
        $hours = intdiv($minutes, 60);
        return $hours . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
    };
    $metricCards = [
        ['label' => 'Opens', 'value' => (string) $proposal->view_count, 'icon' => 'fa-eye'],
        ['label' => 'Visits', 'value' => (string) ($summary['total_visits'] ?? 0), 'icon' => 'fa-route'],
        ['label' => 'Total Time', 'value' => $formatDuration($summary['total_duration_ms'] ?? 0), 'icon' => 'fa-clock'],
        ['label' => 'CTA Clicks', 'value' => (string) ($summary['cta_clicks'] ?? 0), 'icon' => 'fa-hand-pointer'],
        ['label' => 'Top Project', 'value' => $summary['top_project']['name'] ?? 'Waiting', 'icon' => 'fa-building'],
        ['label' => 'Interested Unit', 'value' => $summary['top_unit'] ?? 'Waiting', 'icon' => 'fa-ruler-combined'],
    ];
    $statusClass = match($proposal->status) {
        'active' => 'bg-blue-50 text-blue-700 border-blue-100',
        'expired' => 'bg-amber-50 text-amber-700 border-amber-100',
        default => 'bg-rose-50 text-rose-700 border-rose-100',
    };
    $proposalUrlType = $proposalUrlType ?? 'proposal';
    $openButtonLabel = $proposalUrlType === 'project_share' ? 'Open Public Page' : 'Open Proposal';
@endphp

@push('styles')
<style>
    .proposal-analytics {
        font-family: Arial, Helvetica, sans-serif;
    }
    .pa-card {
        border: 1px solid #dbe6f3;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
    }
    .pa-primary {
        background: linear-gradient(135deg, var(--primary-color, #007aff), var(--secondary-color, #0057b8));
    }
</style>
@endpush

@section('content')
<div class="proposal-analytics space-y-6">
    <section class="pa-primary rounded-3xl p-5 text-white shadow-lg sm:p-7">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] {{ $statusClass }}">{{ $proposal->status }}</span>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold">{{ $projects->count() }} project{{ $projects->count() === 1 ? '' : 's' }}</span>
                </div>
                <h1 class="mt-4 text-2xl font-bold sm:text-3xl">{{ $lead->name ?: 'Lead' }} Proposal Analytics</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-white/80">
                    Full customer engagement for the private proposal link. Lead page stays clean; this page keeps visit-wise and project-wise detail.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($projects as $project)
                        <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold">{{ $project->name }}</span>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('leads.show', $lead) }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/25">
                    <i class="fas fa-arrow-left"></i> Back to Lead
                </a>
                <a href="{{ $proposalUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-50">
                    <i class="fas fa-external-link-alt"></i> {{ $openButtonLabel }}
                </a>
                <button type="button" id="copyProposalLink" data-url="{{ $proposalUrl }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-50">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
            </div>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($metricCards as $card)
            <div class="pa-card p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-xl font-bold text-slate-950">{{ $card['value'] }}</p>
                    </div>
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-blue-50 text-blue-700">
                        <i class="fas {{ $card['icon'] }}"></i>
                    </span>
                </div>
            </div>
        @endforeach
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(360px,.8fr)]">
        <div class="space-y-6">
            <div class="pa-card p-5 sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-950">Project Engagement</h2>
                        <p class="text-sm text-slate-500">Ranking by active time and views.</p>
                    </div>
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ collect($summary['project_rows'] ?? [])->count() }} projects</span>
                </div>
                <div class="space-y-3">
                    @forelse(collect($summary['project_rows'] ?? []) as $row)
                        @php
                            $maxDuration = max(1, (int) collect($summary['project_rows'] ?? [])->max('duration_ms'));
                            $percent = min(100, round(((int) ($row['duration_ms'] ?? 0) / $maxDuration) * 100));
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-bold text-slate-950">{{ $row['name'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $row['views'] ?? 0 }} views</p>
                                </div>
                                <span class="text-sm font-bold text-blue-700">{{ $formatDuration($row['duration_ms'] ?? 0) }}</span>
                            </div>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-white">
                                <div class="h-full rounded-full bg-blue-600" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">Waiting for project engagement.</p>
                    @endforelse
                </div>
            </div>

            <div class="pa-card p-5 sm:p-6">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-slate-950">Visit Timeline</h2>
                    <p class="text-sm text-slate-500">Each proposal open is tracked as a separate visit.</p>
                </div>
                <div class="space-y-3">
                    @forelse(collect($summary['visit_rows'] ?? []) as $visit)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-bold text-slate-950">
                                        {{ $visit['label'] ?? 'Visit' }}
                                        <span class="font-semibold text-slate-500">
                                            - {{ !empty($visit['started_at']) ? \Illuminate\Support\Carbon::parse($visit['started_at'])->format('d M, h:i A') : '-' }}
                                        </span>
                                    </p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $visit['device_context'] ?? 'Waiting for device data' }}</p>
                                </div>
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ $formatDuration($visit['duration_ms'] ?? 0) }}</span>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl bg-white p-3">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-slate-500">Top Project</p>
                                    <p class="mt-1 text-sm font-bold text-slate-900">{{ $visit['top_project']['name'] ?? 'Waiting' }}</p>
                                </div>
                                <div class="rounded-xl bg-white p-3">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-slate-500">Interested Unit</p>
                                    <p class="mt-1 text-sm font-bold text-slate-900">{{ $visit['top_unit'] ?? 'Waiting' }}</p>
                                </div>
                                <div class="rounded-xl bg-white p-3">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-slate-500">Events</p>
                                    <p class="mt-1 text-sm font-bold text-slate-900">{{ $visit['event_count'] ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">No visit data yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="pa-card p-5 sm:p-6">
                <h2 class="text-lg font-bold text-slate-950">Device & Location</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $summary['last_visit_context'] ?? 'Waiting for visitor data' }}</p>
                <div class="mt-4 grid gap-3">
                    @foreach([
                        'Device' => $summary['visitor_device'] ?? 'Waiting',
                        'Browser' => $summary['visitor_browser'] ?? 'Waiting',
                        'OS' => $summary['visitor_os'] ?? 'Waiting',
                        'Screen' => $summary['visitor_screen'] ?? 'Waiting',
                        'Approx Location' => collect([$summary['visitor_city'] ?? null, $summary['visitor_region'] ?? null, $summary['visitor_country'] ?? null])->filter()->implode(', ') ?: 'Unavailable',
                    ] as $label => $value)
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-slate-500">{{ $label }}</p>
                            <p class="mt-1 text-sm font-bold text-slate-950">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pa-card p-5 sm:p-6">
                <h2 class="text-lg font-bold text-slate-950">Floor / Unit Interest</h2>
                <div class="mt-4 space-y-2">
                    @forelse(collect($summary['floor_plan_rows'] ?? []) as $row)
                        <div class="flex items-center justify-between rounded-xl bg-blue-50 px-3 py-2 text-sm">
                            <span class="font-bold text-blue-950">{{ $row['label'] }}</span>
                            <span class="text-xs font-bold text-blue-700">{{ $row['views'] }} views · {{ $formatDuration($row['duration_ms'] ?? 0) }}</span>
                        </div>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm font-semibold text-slate-500">Waiting for floor plan activity.</p>
                    @endforelse
                </div>
            </div>

            <div class="pa-card p-5 sm:p-6">
                <h2 class="text-lg font-bold text-slate-950">CTA & Downloads</h2>
                <div class="mt-4 space-y-2">
                    @forelse(collect($summary['cta_breakdown'] ?? []) as $label => $count)
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                            <span class="font-bold capitalize text-slate-900">{{ str_replace('_', ' ', $label) }}</span>
                            <span class="font-bold text-blue-700">{{ $count }}</span>
                        </div>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-4 text-sm font-semibold text-slate-500">No CTA clicks yet.</p>
                    @endforelse
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                        <span class="font-bold text-slate-900">Downloads</span>
                        <span class="font-bold text-blue-700">{{ $summary['downloads'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Suggestion</p>
                <p class="mt-2 text-sm font-semibold leading-6 text-blue-950">{{ $summary['suggestion'] ?? 'Proposal sent. Follow up after the customer opens the link.' }}</p>
            </div>
        </aside>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const copyButton = document.getElementById('copyProposalLink');
    copyButton?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(copyButton.dataset.url || '');
            copyButton.innerHTML = '<i class="fas fa-check"></i> Copied';
            setTimeout(() => copyButton.innerHTML = '<i class="fas fa-copy"></i> Copy Link', 1500);
        } catch (error) {
            alert('Copy failed. Please copy manually.');
        }
    });
});
</script>
@endpush
