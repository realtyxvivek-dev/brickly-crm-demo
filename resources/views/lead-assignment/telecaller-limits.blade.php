@extends('layouts.app')

@section('title', 'Sales Executive Daily Limits - ' . brand_name())
@section('page-title', 'Sales Executive Daily Limits')
@section('page-subtitle', 'Control intake capacity and pending thresholds for each sales user')

@section('header-actions')
    <a href="{{ route('lead-assignment.index') }}" class="inline-flex items-center gap-2 rounded-2xl bg-slate-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        <section class="rounded-[28px] border border-emerald-100 bg-white shadow-sm">
            <div class="grid gap-6 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                        Capacity Rules
                    </div>
                    <div class="space-y-2">
                        <h2 class="text-3xl font-semibold tracking-tight text-slate-950">Daily Limit Control</h2>
                        <p class="max-w-3xl text-sm leading-7 text-slate-600">
                            Define how many fresh leads each sales user can receive in a day and how many pending leads they can hold before new assignment stops.
                        </p>
                    </div>
                </div>
                <div class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">How to use</p>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <div class="rounded-2xl border border-white bg-white px-4 py-3">
                            <p class="font-semibold text-slate-900">Overall Daily Limit</p>
                            <p class="mt-1">Maximum fresh assignments allowed for that user in one day.</p>
                        </div>
                        <div class="rounded-2xl border border-white bg-white px-4 py-3">
                            <p class="font-semibold text-slate-900">Assigned Today</p>
                            <p class="mt-1">Live count already assigned today.</p>
                        </div>
                        <div class="rounded-2xl border border-white bg-white px-4 py-3">
                            <p class="font-semibold text-slate-900">Max Pending Leads</p>
                            <p class="mt-1">Pending threshold after which fresh assignment should slow or stop.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-5">
                <div>
                    <h3 class="text-xl font-semibold text-slate-950">User Limit Table</h3>
                    <p class="mt-1 text-sm text-slate-500">Edit intake limits user by user without touching assignment logic.</p>
                </div>
                <div class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">
                    {{ count($telecallers) }} users
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50/90">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sales Executive</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Overall Daily Limit</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Assigned Today</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Available</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Max Pending</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($telecallers as $telecaller)
                            @php
                                $availableSlots = $telecaller['overall_daily_limit'] - $telecaller['assigned_count_today'];
                            @endphp
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-6 py-4 align-top">
                                    <div class="text-sm font-semibold text-slate-900">{{ $telecaller['name'] }}</div>
                                    <div class="mt-1 text-sm text-slate-500">{{ $telecaller['email'] }}</div>
                                </td>
                                <td class="px-6 py-4 align-top text-sm font-medium text-slate-900">{{ $telecaller['overall_daily_limit'] }}</td>
                                <td class="px-6 py-4 align-top text-sm font-medium text-slate-900">{{ $telecaller['assigned_count_today'] }}</td>
                                <td class="px-6 py-4 align-top text-sm">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $availableSlots > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $availableSlots }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 align-top text-sm font-medium text-slate-900">{{ $telecaller['max_pending_leads'] }}</td>
                                <td class="px-6 py-4 align-top">
                                    <button onclick="editLimit({{ $telecaller['id'] }}, {{ $telecaller['overall_daily_limit'] }}, {{ $telecaller['max_pending_leads'] }})" class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L12 15l-4 1 1-4 8.586-8.586z" />
                                        </svg>
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/45 p-4" onclick="if (event.target === this) closeEditModal()">
        <div class="w-full max-w-md overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-xl font-semibold tracking-tight text-slate-950">Edit Daily Limit</h3>
                <p class="mt-1 text-sm text-slate-500">Update intake capacity and pending threshold for the selected user.</p>
            </div>
            <form id="edit-form" class="px-6 py-6">
                <input type="hidden" id="edit-user-id">
                <div class="mb-4">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Overall Daily Limit</label>
                    <input type="number" id="edit-daily-limit" min="0" class="w-full rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                </div>
                <div class="mb-5">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Max Pending Leads</label>
                    <input type="number" id="edit-max-pending" min="0" class="w-full rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeEditModal()" class="rounded-2xl border border-slate-300 px-4 py-2.5 text-slate-700 transition hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2.5 font-medium text-white transition hover:from-[#205A44] hover:to-[#15803d]">Save</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function editLimit(userId, dailyLimit, maxPending) {
            document.getElementById('edit-user-id').value = userId;
            document.getElementById('edit-daily-limit').value = dailyLimit;
            document.getElementById('edit-max-pending').value = maxPending;
            document.getElementById('edit-modal').classList.remove('hidden');
            document.getElementById('edit-modal').classList.add('flex');
        }

        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
            document.getElementById('edit-modal').classList.remove('flex');
        }

        document.getElementById('edit-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('edit-user-id').value;
            const dailyLimit = document.getElementById('edit-daily-limit').value;
            const maxPending = document.getElementById('edit-max-pending').value;

            axios.post('{{ route("lead-assignment.telecaller-limits.save") }}', {
                user_id: userId,
                overall_daily_limit: dailyLimit,
                max_pending_leads: maxPending
            })
            .then(response => {
                alert(response.data.message);
                window.location.reload();
            })
            .catch(error => {
                alert('Error: ' + (error.response?.data?.message || 'Failed to update limit'));
            });
        });
    </script>
    @endpush
@endsection
