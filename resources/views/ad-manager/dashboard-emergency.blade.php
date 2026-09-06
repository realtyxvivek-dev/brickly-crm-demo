@extends('layouts.app')

@section('title', 'Ad Manager Dashboard - ' . brand_name())
@section('page-title', 'Ad Manager Dashboard')
@section('page-subtitle', 'Meta dashboard is loading in safe mode.')

@section('content')
<div class="p-6">
    <div class="rounded-lg border border-emerald-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-xs font-black uppercase tracking-widest text-emerald-700">Ad Manager</div>
                <h1 class="mt-2 text-2xl font-black text-slate-900">Dashboard Safe Mode</h1>
                <p class="mt-2 text-sm text-slate-600">Server resource issue ke karan full dashboard temporarily safe mode me hai. Lead quality, Meta health aur attendance data controller se load ho raha hai.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('ad-manager.meta.index') }}" class="rounded bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Meta Ops</a>
                <a href="{{ route('ad-manager.meta.facebook-lead-ads.diagnostics') }}" class="rounded border border-emerald-200 px-4 py-2 text-sm font-bold text-emerald-800">Diagnostics</a>
                <a href="{{ route('data-intelligence.lead-quality', ['source' => $filters['source'] ?? 'meta']) }}" class="rounded border border-slate-200 px-4 py-2 text-sm font-bold text-slate-800">Full Report</a>
            </div>
        </div>
        <div class="mt-6 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div class="rounded border p-4"><div class="text-xs font-bold uppercase text-slate-500">Total Leads</div><div class="mt-1 text-2xl font-black">{{ number_format($summary['total'] ?? 0) }}</div></div>
            <div class="rounded border p-4"><div class="text-xs font-bold uppercase text-slate-500">Interested</div><div class="mt-1 text-2xl font-black text-emerald-700">{{ number_format($summary['interested'] ?? 0) }}</div></div>
            <div class="rounded border p-4"><div class="text-xs font-bold uppercase text-slate-500">Not Interested</div><div class="mt-1 text-2xl font-black text-red-700">{{ number_format($summary['not_interested'] ?? 0) }}</div></div>
            <div class="rounded border p-4"><div class="text-xs font-bold uppercase text-slate-500">Junk</div><div class="mt-1 text-2xl font-black text-red-700">{{ number_format($summary['junk'] ?? 0) }}</div></div>
            <div class="rounded border p-4"><div class="text-xs font-bold uppercase text-slate-500">CNP</div><div class="mt-1 text-2xl font-black text-amber-700">{{ number_format($summary['cnp'] ?? 0) }}</div></div>
            <div class="rounded border p-4"><div class="text-xs font-bold uppercase text-slate-500">Quality Score</div><div class="mt-1 text-2xl font-black text-emerald-800">{{ $summary['quality_score'] ?? 0 }}%</div></div>
        </div>
        <div class="mt-6 rounded border bg-slate-50 p-4 text-sm text-slate-700">
            <b>Meta Health:</b>
            Pages {{ number_format($healthSnapshot['connected_pages'] ?? 0) }},
            Forms {{ number_format($healthSnapshot['enabled_forms'] ?? 0) }},
            Failed webhooks {{ number_format($healthSnapshot['failed_count'] ?? 0) }}.
        </div>
    </div>
</div>
@endsection
