@extends('layouts.app')

@section('title', 'Meta Ops - ' . brand_name())
@section('page-title', 'Meta Ops')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Ad Manager Workspace</div>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">Meta Ops</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Manage Meta Lead Ads forms, mappings, callbacks, WhatsApp/WABA settings, Instagram automation, and Meta-only source automation.</p>
            </div>
            <a href="{{ route('ad-manager.automation.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                <i class="fas fa-bolt"></i>
                Source Automation
            </a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('ad-manager.meta.facebook-lead-ads.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-300 hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="grid h-10 w-10 place-items-center rounded-lg bg-blue-50 text-blue-700"><i class="fab fa-facebook-f"></i></div>
                <div>
                    <div class="font-bold text-slate-900">Lead Ads</div>
                    <div class="text-xs text-slate-500">Pages, forms, mapping, sync</div>
                </div>
            </div>
        </a>
        <a href="{{ route('ad-manager.meta-waba.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-300 hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="grid h-10 w-10 place-items-center rounded-lg bg-emerald-50 text-emerald-700"><i class="fab fa-whatsapp"></i></div>
                <div>
                    <div class="font-bold text-slate-900">Meta WABA</div>
                    <div class="text-xs text-slate-500">Accounts, templates, campaigns</div>
                </div>
            </div>
        </a>
        <a href="{{ route('ad-manager.meta.whatsapp') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-300 hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="grid h-10 w-10 place-items-center rounded-lg bg-teal-50 text-teal-700"><i class="fas fa-message"></i></div>
                <div>
                    <div class="font-bold text-slate-900">WhatsApp</div>
                    <div class="text-xs text-slate-500">API settings and tests</div>
                </div>
            </div>
        </a>
        <a href="{{ route('ad-manager.meta.instagram.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-300 hover:shadow-md">
            <div class="flex items-center gap-3">
                <div class="grid h-10 w-10 place-items-center rounded-lg bg-pink-50 text-pink-700"><i class="fab fa-instagram"></i></div>
                <div>
                    <div class="font-bold text-slate-900">Instagram</div>
                    <div class="text-xs text-slate-500">Rules, flows, conversations</div>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
