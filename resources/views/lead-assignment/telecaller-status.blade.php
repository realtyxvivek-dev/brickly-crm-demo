@php
    $isCrmView = auth()->user()?->isCrm();
    $isAuditorView = auth()->user()?->isLeadQualityAuditor();
    $leadOffIndexRoute = $isAuditorView ? route('lead-quality-auditor.lead-off.index') : route('lead-assignment.telecaller-status');
    $leadOffUpdateRoute = $isAuditorView ? route('lead-quality-auditor.lead-off.update') : route('lead-assignment.telecaller-status.update');
@endphp
@extends('layouts.app')

@section('title', 'Lead Off - ' . brand_name())
@section('page-title', 'Lead Off')
@section('page-subtitle', $isCrmView ? 'Control which sales, telecaller and HR users should stop receiving new auto-assigned leads' : 'Manage lead intake availability')

@section('header-actions')
    @unless($isAuditorView)
    <a href="{{ route('lead-assignment.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back
    </a>
    @endunless
@endsection

@push('styles')
<style>
    .lead-off-actions-menu summary::-webkit-details-marker { display: none; }

    .auditor-lead-off { padding: 14px; background: #f4faf6; }
    .auditor-lead-off > section { border-color: #b9d5c4 !important; border-radius: 4px !important; box-shadow: none !important; }
    .auditor-lead-off > section:first-child { background: #eaf6ee; }
    .auditor-lead-off .rounded-2xl,
    .auditor-lead-off .rounded-xl,
    .auditor-lead-off .rounded-full,
    .auditor-lead-off .rounded-\[28px\] { border-radius: 4px !important; }
    .auditor-lead-off .lead-off-table table { border-collapse: collapse; }
    .auditor-lead-off .lead-off-table thead { position: static !important; background: #0b573d !important; }
    .auditor-lead-off .lead-off-table th { color: #fff !important; letter-spacing: 0 !important; border: 1px solid #2b745b; }
    .auditor-lead-off .lead-off-table td { border: 1px solid #d8e2dc; }
    .auditor-lead-off .lead-off-table tbody tr:hover { background: #f0f8f3 !important; }
    .auditor-lead-off input,
    .auditor-lead-off select,
    .auditor-lead-off textarea { border-radius: 4px !important; }

    @media (max-width: 860px) {
        .lead-off-table table,
        .lead-off-table thead,
        .lead-off-table tbody,
        .lead-off-table tr,
        .lead-off-table td {
            display: block;
            width: 100%;
        }

        .lead-off-table thead { display: none; }

        .lead-off-table tbody {
            display: grid;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
        }

        .lead-off-table tr {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .lead-off-table td {
            padding: 10px 14px;
            border: 0;
        }

        .lead-off-table td[data-label] {
            display: grid;
            grid-template-columns: 112px minmax(0, 1fr);
            gap: 10px;
            align-items: start;
        }

        .lead-off-table td[data-label]::before {
            content: attr(data-label);
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .lead-off-table td:first-child {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
    }
</style>
@endpush

@section('content')
    <div class="space-y-5 {{ $isAuditorView ? 'auditor-lead-off' : '' }}">
        <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-700">
                        Lead Intake Control
                    </div>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Lead Off Control Desk</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">
                        Fresh lead allocation ko user-wise pause, resume ya schedule karo.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700">
                        <span class="text-slate-500">Users</span> {{ $summary['total_users'] }}
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-rose-100 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700">
                        <span class="text-rose-500">Off</span> {{ $summary['lead_off_users'] }}
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
                        <span class="text-emerald-600">On</span> {{ $summary['lead_on_users'] }}
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-amber-100 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700">
                        <span class="text-amber-600">Returning</span> {{ $summary['returning_today'] }}
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700">
                        <span class="text-indigo-600">Scheduled</span> {{ $summary['scheduled_windows'] ?? 0 }}
                    </span>
                </div>
            </div>
        </section>

        <section class="sticky top-0 z-20 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
            <form method="GET" class="grid gap-4 xl:grid-cols-[minmax(240px,1fr)_220px_220px_auto] xl:items-end">
                <div>
                    <label for="user-search" class="mb-2 block text-sm font-medium text-slate-700">Search user</label>
                    <input id="user-search" name="search" value="{{ $search ?? '' }}" class="w-full rounded-xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Name or email">
                </div>
                <div>
                    <label for="role-filter" class="mb-2 block text-sm font-medium text-slate-700">Role</label>
                    <select id="role-filter" name="role" class="w-full rounded-xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="all" {{ ($roleFilter ?? 'all') === 'all' ? 'selected' : '' }}>All lead users</option>
                        @foreach($roleOptions as $role)
                            <option value="{{ $role->slug }}" {{ ($roleFilter ?? 'all') === $role->slug ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status-filter" class="mb-2 block text-sm font-medium text-slate-700">Status view</label>
                    <select id="status-filter" name="status" class="w-full rounded-xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Users</option>
                        <option value="off" {{ $statusFilter === 'off' ? 'selected' : '' }}>Lead Off</option>
                        <option value="on" {{ $statusFilter === 'on' ? 'selected' : '' }}>Lead On</option>
                        <option value="scheduled" {{ $statusFilter === 'scheduled' ? 'selected' : '' }}>Scheduled Window</option>
                        <option value="returning_today" {{ $statusFilter === 'returning_today' ? 'selected' : '' }}>Returning Today</option>
                    </select>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-xl bg-[#064e3b] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0f6b50]">Apply</button>
                    @if($statusFilter !== 'all' || ($roleFilter ?? 'all') !== 'all' || ($search ?? '') !== '')
                        <a href="{{ $leadOffIndexRoute }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Clear</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="overflow-visible rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="text-xl font-semibold text-slate-950">User Availability</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $isAuditorView ? 'All active non-admin users.' : 'All active sales, telecaller and HR users.' }}</p>
                </div>
                <div class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                    {{ $summary['total_users'] }} users
                </div>
            </div>

            <div class="lead-off-table overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="sticky top-[88px] z-10 bg-slate-50/95 backdrop-blur">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">User</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Role</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Load</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Lead Off Window</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Set By</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($users as $user)
                            <tr class="align-top transition hover:bg-slate-50/70">
                                <td class="px-5 py-4" data-label="User">
                                    <div class="text-sm font-semibold text-slate-900">{{ $user['name'] }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $user['email'] }}</div>
                                </td>
                                <td class="px-5 py-4" data-label="Role">
                                    <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                                        {{ $user['role'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-4" data-label="Status">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $user['is_absent'] ? 'bg-rose-100 text-rose-800' : ($user['has_scheduled_lead_off'] ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                        {{ $user['is_absent'] ? 'Lead Off' : ($user['has_scheduled_lead_off'] ? 'Scheduled' : 'Lead On') }}
                                    </span>
                                    <span class="ml-2 inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $user['can_receive'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $user['can_receive'] ? 'Can receive' : 'Blocked' }}
                                    </span>
                                    @if($user['absent_reason'])
                                        <div class="mt-2 max-w-[220px] text-xs leading-5 text-slate-500">{{ $user['absent_reason'] }}</div>
                                    @endif
                                    @if($user['lead_off_fallback_user_name'])
                                        <div class="mt-1 text-xs font-medium text-indigo-600">New leads: {{ $user['lead_off_fallback_user_name'] }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-900" data-label="Load">
                                    <div class="font-semibold">{{ $user['active_assigned_count'] }} active</div>
                                    @if($user['uses_pending_capacity'])
                                        <div class="mt-1 text-xs text-slate-500">{{ $user['pending_count'] }} pending</div>
                                        @if($user['max_pending_leads'] !== null)
                                            <div class="mt-1 text-xs text-slate-400">Max pending: {{ $user['max_pending_leads'] }}</div>
                                        @endif
                                    @else
                                        <div class="mt-1 text-xs text-slate-400">Pending N/A</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700" data-label="Window">
                                    <div>
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">From</span>
                                        <span class="ml-2">
                                            @if($user['lead_off_start_at'])
                                                {{ \Illuminate\Support\Carbon::parse($user['lead_off_start_at'])->format('d M Y h:i A') }}
                                            @elseif($user['is_absent'])
                                                Immediate
                                            @else
                                                -
                                            @endif
                                        </span>
                                    </div>
                                    <div class="mt-1">
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Until</span>
                                        <span class="ml-2">
                                            @if($user['lead_off_end_at'])
                                                {{ \Illuminate\Support\Carbon::parse($user['lead_off_end_at'])->format('d M Y h:i A') }}
                                            @else
                                                Until turned on
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700" data-label="Set By">
                                    @if($user['lead_off_source'] === 'self')
                                        <span class="inline-flex rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800">Self</span>
                                    @elseif($user['lead_off_source'] === 'lead_quality_auditor')
                                        <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Lead Auditor</span>
                                    @elseif($user['lead_off_source'])
                                        <span class="inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800">CRM</span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right" data-label="Actions">
                                    @if($user['is_absent'] || $user['has_scheduled_lead_off'])
                                        <button type="button" onclick="openStatusModal({{ $user['id'] }}, 'on')" class="rounded-xl border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50">
                                            Turn On
                                        </button>
                                    @else
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <button type="button" onclick="openStatusModal({{ $user['id'] }}, 'now')" class="rounded-xl border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50">
                                                Lead Off
                                            </button>
                                            <details class="lead-off-actions-menu">
                                                <summary class="cursor-pointer rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">More</summary>
                                                <div class="mt-2 grid gap-2 rounded-xl border border-slate-200 bg-white p-2 text-left shadow-sm">
                                                    <button type="button" onclick="openStatusModal({{ $user['id'] }}, 'now')" class="rounded-lg px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-50">
                                                        Turn Off Now
                                                    </button>
                                                    <button type="button" onclick="openStatusModal({{ $user['id'] }}, 'schedule')" class="rounded-lg px-3 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-50">
                                                        Schedule Window
                                                    </button>
                                                </div>
                                            </details>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <p class="mt-4 text-base font-semibold text-slate-900">No users matched the selected filter</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-500">Try another status view to inspect more eligible sales or HR users.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="status-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/45 p-4" onclick="if (event.target === this) closeStatusModal()">
        <div class="w-full max-w-md overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-xl font-semibold tracking-tight text-slate-950">Update Lead Off Status</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $isCrmView ? 'Change fresh lead availability for this user.' : 'Change intake availability for this user.' }}</p>
            </div>
            <form id="status-form" class="px-6 py-6">
                <input type="hidden" id="status-user-id">
                <input type="hidden" id="status-is-absent">
                <input type="hidden" id="status-mode">

                <div class="mb-4">
                    <label class="mb-2 block text-sm font-medium text-slate-700">{{ $isCrmView ? 'Lead Off Reason' : 'Reason' }} <span id="status-reason-required">*</span></label>
                    <textarea id="status-reason" rows="3" required class="w-full rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Why should this user's new leads be redirected?"></textarea>
                </div>

                <div class="mb-4" id="status-fallback-wrap">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Send new leads to <span class="text-rose-600">*</span></label>
                    <select id="status-fallback-user" required class="w-full rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">Select fallback user</option>
                        @foreach($fallbackUsers as $fallbackUser)
                            <option value="{{ $fallbackUser->id }}">{{ $fallbackUser->name }}</option>
                        @endforeach
                    </select>
                    <div class="mt-2 text-xs text-slate-500">New automatic leads for this user will be assigned to the selected fallback user.</div>
                </div>

                <div class="mb-4 hidden" id="status-start-wrap">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Lead Off From</label>
                    <input type="datetime-local" id="status-start" class="w-full rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>

                <div class="mb-5" id="status-end-wrap">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Lead Off Until (Optional)</label>
                    <input type="datetime-local" id="status-until" class="w-full rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <div class="mt-2 text-xs text-slate-500">Blank end time keeps lead intake off until it is turned on manually.</div>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeStatusModal()" class="rounded-2xl border border-slate-300 px-4 py-2.5 text-slate-700 transition hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2.5 font-medium text-white transition hover:from-[#205A44] hover:to-[#15803d]">Save</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function formatDateTimeLocal(date) {
            const pad = (value) => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        }

        function openStatusModal(userId, mode) {
            document.getElementById('status-user-id').value = userId;
            document.getElementById('status-is-absent').value = mode === 'on' ? 'false' : 'true';
            document.getElementById('status-mode').value = mode;
            document.getElementById('status-reason').value = '';
            document.getElementById('status-fallback-user').value = '';
            document.getElementById('status-start').value = '';
            document.getElementById('status-until').value = '';

            const startWrap = document.getElementById('status-start-wrap');
            const endWrap = document.getElementById('status-end-wrap');
            const fallbackWrap = document.getElementById('status-fallback-wrap');
            const reason = document.getElementById('status-reason');
            const fallback = document.getElementById('status-fallback-user');
            const reasonRequired = document.getElementById('status-reason-required');

            [...fallback.options].forEach((option) => {
                option.hidden = Number(option.value) === Number(userId);
            });

            const isLeadOff = mode !== 'on';
            fallbackWrap.classList.toggle('hidden', !isLeadOff);
            reason.parentElement.classList.toggle('hidden', !isLeadOff);
            reason.required = isLeadOff;
            fallback.required = isLeadOff;
            reasonRequired.classList.toggle('hidden', !isLeadOff);

            if (mode === 'schedule') {
                startWrap.classList.remove('hidden');
                endWrap.classList.remove('hidden');
                document.getElementById('status-start').value = formatDateTimeLocal(new Date());
            } else if (mode === 'now') {
                startWrap.classList.add('hidden');
                endWrap.classList.remove('hidden');
            } else {
                startWrap.classList.add('hidden');
                endWrap.classList.add('hidden');
            }

            document.getElementById('status-modal').classList.remove('hidden');
            document.getElementById('status-modal').classList.add('flex');
        }

        function closeStatusModal() {
            document.getElementById('status-modal').classList.add('hidden');
            document.getElementById('status-modal').classList.remove('flex');
        }

        function extractErrorMessage(error) {
            const responseData = error?.response?.data || {};
            if (responseData.message) {
                return responseData.message;
            }

            const errors = responseData.errors || {};
            const firstField = Object.keys(errors)[0];
            if (firstField && Array.isArray(errors[firstField]) && errors[firstField].length) {
                return errors[firstField][0];
            }

            return 'Failed to update status';
        }

        document.getElementById('status-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('status-user-id').value;
            const isAbsent = document.getElementById('status-is-absent').value === 'true';
            const mode = document.getElementById('status-mode').value;
            const reason = document.getElementById('status-reason').value;
            const start = document.getElementById('status-start').value;
            const until = document.getElementById('status-until').value;
            const fallbackUserId = document.getElementById('status-fallback-user').value;

            axios.post(@json($leadOffUpdateRoute), {
                user_id: userId,
                is_absent: isAbsent,
                mode: mode,
                absent_reason: reason || null,
                fallback_user_id: isAbsent ? (fallbackUserId || null) : null,
                lead_off_start_at: isAbsent && mode === 'schedule' ? (start || null) : null,
                lead_off_end_at: isAbsent && mode === 'schedule' ? (until || null) : null,
                absent_until: isAbsent && mode !== 'schedule' ? (until || null) : null
            })
            .then(response => {
                alert(response.data.message);
                window.location.reload();
            })
            .catch(error => {
                alert('Error: ' + extractErrorMessage(error));
            });
        });
    </script>
    @endpush
@endsection
