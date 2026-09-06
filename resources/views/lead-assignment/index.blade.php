@extends('layouts.app')

@section('title', 'Lead Assignment - ' . brand_name())
@section('page-title', 'Lead Assignment')
@section('page-subtitle', 'Manage lead distribution, sales executive limits, and blacklist controls')

@section('header-actions')
    <div class="flex items-center gap-3">
        <button
            type="button"
            onclick="document.getElementById('lead-assignment-help-modal').classList.remove('hidden')"
            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-200 hover:text-emerald-700"
            title="Page use"
            aria-label="Page use"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9a3.5 3.5 0 116.544 1.74c0 1.38-.84 2.056-1.716 2.76-.8.644-1.628 1.31-1.628 2.5v.25m.072 3.25h.01" />
            </svg>
        </button>
        <a href="{{ route('lead-assignment.unassigned') }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:from-[#205A44] hover:to-[#15803d]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            View Unassigned Leads
        </a>
    </div>
@endsection

@section('content')
    <div class="space-y-8">
        <section class="overflow-hidden rounded-[28px] border border-emerald-100 bg-white shadow-sm">
            <div class="grid gap-6 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                        Lead Ops
                    </div>
                    <div class="space-y-2">
                        <h2 class="text-3xl font-semibold tracking-tight text-slate-950">Sales Executive Control Desk</h2>
                        <p class="max-w-3xl text-sm leading-7 text-slate-600">
                            Assign fresh inventory, control daily intake, manage sheet-based routing, and block unwanted numbers from entering future workflows.
                        </p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Primary Use</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">Lead allocation control</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Best For</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">CRM and assignment admins</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Safety</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">Blacklist prevents future imports</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[26px] border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-sky-50 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">What this page does</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">Assignment overview</h3>
                        </div>
                        <button
                            type="button"
                            onclick="document.getElementById('lead-assignment-help-modal').classList.remove('hidden')"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-200 bg-white text-emerald-700 shadow-sm transition hover:bg-emerald-50"
                            title="Page use"
                            aria-label="Page use"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16h.01M12 8a2.5 2.5 0 012.5 2.5c0 1.063-.601 1.643-1.237 2.256-.53.51-1.082 1.04-1.082 1.994V15" />
                                <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-5 space-y-3">
                        <div class="flex items-start gap-3 rounded-2xl border border-white/80 bg-white/80 px-4 py-3">
                            <span class="mt-0.5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-700">1</span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Review inventory</p>
                                <p class="mt-1 text-xs leading-5 text-slate-600">Check total unassigned pool and today assignment movement.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 rounded-2xl border border-white/80 bg-white/80 px-4 py-3">
                            <span class="mt-0.5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-sky-100 text-xs font-semibold text-sky-700">2</span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Set control rules</p>
                                <p class="mt-1 text-xs leading-5 text-slate-600">Daily limits help balance intake so no user gets overloaded.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 rounded-2xl border border-white/80 bg-white/80 px-4 py-3">
                            <span class="mt-0.5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-100 text-xs font-semibold text-rose-700">3</span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Protect imports</p>
                                <p class="mt-1 text-xs leading-5 text-slate-600">Blacklist numbers that should never re-enter the lead flow.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-4 md:grid-cols-2">
            <article class="group rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Total Sales Executives</p>
                        <p class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ $stats['total_telecallers'] }}</p>
                        <p class="mt-2 text-sm text-slate-500">Configured for assignment intake</p>
                    </div>
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </article>

            <article class="group rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Active Sales Executives</p>
                        <p class="mt-3 text-4xl font-semibold tracking-tight text-emerald-600">{{ $stats['active_telecallers'] }}</p>
                        <p class="mt-2 text-sm text-slate-500">Ready to receive new leads</p>
                    </div>
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </article>

            <article class="group rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Unassigned Leads</p>
                        <p class="mt-3 text-4xl font-semibold tracking-tight text-amber-600">{{ $stats['unassigned_leads'] }}</p>
                        <p class="mt-2 text-sm text-slate-500">Waiting for owner mapping</p>
                    </div>
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </article>

            <article class="group rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Assigned Today</p>
                        <p class="mt-3 text-4xl font-semibold tracking-tight text-indigo-600">{{ $stats['assigned_today'] }}</p>
                        <p class="mt-2 text-sm text-slate-500">Today's completed allocations</p>
                    </div>
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <a href="{{ route('lead-assignment.unassigned') }}" class="group rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-slate-900">Unassigned Leads</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-600">Open fresh pool, review pending inventory, and assign leads to sales executives.</p>
                        </div>
                    </div>
                    <svg class="mt-1 h-5 w-5 shrink-0 text-slate-300 transition group-hover:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </a>

            <a href="{{ route('lead-assignment.telecaller-limits') }}" class="group rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-slate-900">Daily Limits</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-600">Control how many leads a sales executive can receive in a working day.</p>
                        </div>
                    </div>
                    <svg class="mt-1 h-5 w-5 shrink-0 text-slate-300 transition group-hover:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </a>

        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-slate-950">Quick Links</h3>
                    <p class="mt-1 text-sm text-slate-500">Jump to supporting tools without opening multiple admin sections.</p>
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('lead-assignment-help-modal').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600 transition hover:border-emerald-200 hover:text-emerald-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16h.01M12 8a2.5 2.5 0 012.5 2.5c0 1.063-.601 1.643-1.237 2.256-.53.51-1.082 1.04-1.082 1.994V15" />
                        <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
                    </svg>
                    Its Use
                </button>
            </div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <a href="{{ route('lead-assignment.telecaller-status') }}" class="group flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/60 px-5 py-4 transition hover:border-emerald-200 hover:bg-emerald-50/50">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-600 shadow-sm">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ auth()->user()->isCrm() ? 'Lead Off Users' : 'Sales Executive Status Management' }}</p>
                            <p class="mt-1 text-xs text-slate-500">Review who is open, paused, or blocked from fresh assignment.</p>
                        </div>
                    </div>
                    <svg class="h-5 w-5 text-slate-300 transition group-hover:text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-6 py-6">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Blacklisted Numbers</h2>
                        <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">{{ $blacklistedNumbers->count() }} blocked</span>
                    </div>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Numbers added here stay excluded from future imports and assignment flow.</p>
                </div>
                <button onclick="document.getElementById('add-blacklist-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-2xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add to Blacklist
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50/90">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Phone</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Reason</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Blacklisted By</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white" id="blacklist-table-body">
                        @forelse($blacklistedNumbers as $blacklisted)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900">{{ $blacklisted->phone }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $blacklisted->reason }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ $blacklisted->blacklistedBy->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ $blacklisted->blacklisted_at->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="removeFromBlacklist({{ $blacklisted->id }})" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-3 py-2 text-rose-700 transition hover:bg-rose-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <p class="mt-4 text-base font-semibold text-slate-900">No blacklisted numbers found</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-500">Your import protection list is currently empty. Add a number when you want to permanently block future lead intake.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="lead-assignment-help-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/45 p-4" onclick="if (event.target === this) this.classList.add('hidden')">
        <div class="w-full max-w-2xl overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Its Use</p>
                    <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">How to use Lead Assignment</h3>
                </div>
                <button type="button" onclick="document.getElementById('lead-assignment-help-modal').classList.add('hidden')" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="space-y-4 px-6 py-6">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-sm font-semibold text-slate-900">1. Unassigned Leads</p>
                    <p class="mt-1 text-sm leading-6 text-slate-600">Use this when fresh leads have no owner and you want to assign them manually to sales executives.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-sm font-semibold text-slate-900">2. Daily Limits</p>
                    <p class="mt-1 text-sm leading-6 text-slate-600">Use this to control how many new leads a user can receive in one day so workload stays balanced.</p>
                </div>
                <div class="rounded-2xl border border-rose-100 bg-rose-50/60 p-4">
                    <p class="text-sm font-semibold text-rose-900">3. Blacklisted Numbers</p>
                    <p class="mt-1 text-sm leading-6 text-rose-800">Add numbers here when they should be blocked from future imports, duplicate intake, or unwanted reassignment.</p>
                </div>
            </div>
            <div class="flex justify-end border-t border-slate-200 px-6 py-4">
                <button type="button" onclick="document.getElementById('lead-assignment-help-modal').classList.add('hidden')" class="rounded-2xl bg-[#063A1C] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#0a4a25]">
                    Close
                </button>
            </div>
        </div>
    </div>

    <div id="add-blacklist-modal" class="fixed inset-0 bg-slate-950/45 hidden z-50 flex items-center justify-center p-4" onclick="if (event.target === this) this.classList.add('hidden')">
        <div class="w-full max-w-md overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-xl font-semibold tracking-tight text-slate-950">Add to Blacklist</h3>
                <p class="mt-1 text-sm text-slate-500">Blocked numbers stay excluded from upcoming imports and assignment flow.</p>
            </div>
            <form id="add-blacklist-form" class="px-6 py-6">
                @csrf
                <div class="mb-4">
                    <label for="blacklist-phone" class="mb-2 block text-sm font-medium text-slate-700">Phone Number <span class="text-rose-500">*</span></label>
                    <input type="text" id="blacklist-phone" name="phone" required class="block w-full rounded-2xl border-slate-300 bg-white text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div class="mb-5">
                    <label for="blacklist-reason" class="mb-2 block text-sm font-medium text-slate-700">Reason <span class="text-rose-500">*</span></label>
                    <textarea id="blacklist-reason" name="reason" required rows="3" class="block w-full rounded-2xl border-slate-300 bg-white text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('add-blacklist-modal').classList.add('hidden')" class="rounded-2xl border border-slate-300 px-4 py-2.5 text-slate-700 transition hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-2xl bg-rose-600 px-4 py-2.5 font-medium text-white transition hover:bg-rose-700">Add to Blacklist</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('add-blacklist-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {
                phone: formData.get('phone'),
                reason: formData.get('reason')
            };

            try {
                const response = await fetch('/api/crm/blacklist', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok) {
                    alert('Number added to blacklist successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to add to blacklist'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error adding to blacklist');
            }
        });

        async function removeFromBlacklist(id) {
            if (!confirm('Are you sure you want to remove this number from the blacklist?')) {
                return;
            }

            try {
                const response = await fetch(`/api/crm/blacklist/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (response.ok) {
                    alert('Number removed from blacklist successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to remove from blacklist'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error removing from blacklist');
            }
        }
    </script>
    @endpush
@endsection
