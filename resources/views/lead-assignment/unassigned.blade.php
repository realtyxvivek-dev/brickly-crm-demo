@extends('layouts.app')

@section('title', 'Unassigned Leads - ' . brand_name())
@section('page-title', 'Unassigned Leads')
@section('page-subtitle', 'Review fresh inventory and assign leads to the right sales user')

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
                        Fresh Pool
                    </div>
                    <div class="space-y-2">
                        <h2 class="text-3xl font-semibold tracking-tight text-slate-950">Unassigned Lead Queue</h2>
                        <p class="max-w-3xl text-sm leading-7 text-slate-600">
                            Filter fresh inventory, select one or more leads, and assign them to the right sales manager or sales executive without opening each record separately.
                        </p>
                    </div>
                </div>
                <div class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">How this page works</p>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <div class="rounded-2xl border border-white bg-white px-4 py-3">
                            <p class="font-semibold text-slate-900">1. Filter queue</p>
                            <p class="mt-1">Search by customer name, phone, email, source, or status.</p>
                        </div>
                        <div class="rounded-2xl border border-white bg-white px-4 py-3">
                            <p class="font-semibold text-slate-900">2. Select leads</p>
                            <p class="mt-1">Use single select or bulk select to prepare assignment.</p>
                        </div>
                        <div class="rounded-2xl border border-white bg-white px-4 py-3">
                            <p class="font-semibold text-slate-900">3. Assign or delete</p>
                            <p class="mt-1">Pick the destination user and complete the action from one control strip.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-xl font-semibold text-slate-950">Filters</h3>
                <p class="mt-1 text-sm text-slate-500">Use filters to narrow the queue before assignment.</p>
            </div>
            <div class="px-6 py-6">
                <form method="GET" action="{{ route('lead-assignment.unassigned') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_220px_220px_160px]">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, phone, email..." class="rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <select name="status" class="rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">All Status</option>
                        <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
                        <option value="contacted" {{ request('status') === 'contacted' ? 'selected' : '' }}>Contacted</option>
                    </select>
                    <select name="source" class="rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">All Sources</option>
                        @foreach(\App\Models\Lead::sourceOptions() as $value => $label)
                            <option value="{{ $value }}" {{ request('source') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:from-[#205A44] hover:to-[#15803d]">Apply Filters</button>
                </form>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-5">
                <div>
                    <h3 class="text-xl font-semibold text-slate-950">Bulk Assignment Desk</h3>
                    <p class="mt-1 text-sm text-slate-500">Select visible leads, choose a user, then assign or delete in one step.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <button onclick="selectAll()" type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-slate-700 transition hover:bg-slate-50">Select All</button>
                    <button onclick="deselectAll()" type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-slate-700 transition hover:bg-slate-50">Clear Selection</button>
                    <span id="selected-count" class="rounded-full bg-slate-100 px-3 py-2 font-semibold text-slate-700">0 selected</span>
                </div>
            </div>
            <div class="grid gap-4 border-b border-slate-200 px-6 py-5 xl:grid-cols-[minmax(0,1fr)_220px_auto_auto] xl:items-center">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm text-slate-600">
                    Selected leads will be assigned only after you choose a user and confirm the action.
                </div>
                <select id="bulk-telecaller" class="rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Select User</option>
                    @if(isset($eligibleUsers))
                        @foreach($eligibleUsers as $roleName => $users)
                            <optgroup label="{{ $roleName }}">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    @endif
                </select>
                <button onclick="bulkAssign()" type="button" class="rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:from-[#205A44] hover:to-[#15803d]">Assign Selected</button>
                <button onclick="bulkDelete()" type="button" class="rounded-2xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700">Delete Selected</button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50/90">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Lead</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Phone</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Email</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Source</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($leads as $lead)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-6 py-4 align-top">
                                    <input type="checkbox" class="lead-checkbox rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" value="{{ $lead->id }}" onchange="updateSelectedCount()">
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="text-sm font-semibold text-slate-900">{{ $lead->name }}</div>
                                </td>
                                <td class="px-6 py-4 align-top text-sm text-slate-700">{{ $lead->phone }}</td>
                                <td class="px-6 py-4 align-top text-sm text-slate-500">{{ $lead->email ?? 'N/A' }}</td>
                                <td class="px-6 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $lead->status === 'new' ? 'bg-blue-100 text-blue-800' : ($lead->status === 'contacted' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700') }}">
                                        {{ ucfirst($lead->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 align-top text-sm text-slate-600">{{ $lead->source_label }}</td>
                                <td class="px-6 py-4 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" data-lead-id="{{ $lead->id }}" class="js-assign-lead rounded-xl border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50">Assign</button>
                                        <button type="button" data-lead-id="{{ $lead->id }}" class="js-delete-lead rounded-xl border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <p class="mt-4 text-base font-semibold text-slate-900">No unassigned leads found</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-500">The current filter set did not return any free inventory.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-6 py-4">
                {{ $leads->links() }}
            </div>
        </section>
    </div>

    <div id="assign-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/45 p-4" onclick="if (event.target === this) closeModal()">
        <div class="w-full max-w-md overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-xl font-semibold tracking-tight text-slate-950">Assign Lead</h3>
                <p class="mt-1 text-sm text-slate-500">Choose the destination user before confirming assignment.</p>
            </div>
            <div class="px-6 py-6">
                <select id="modal-telecaller" class="w-full rounded-2xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Select User</option>
                    @if(isset($eligibleUsers))
                        @foreach($eligibleUsers as $roleName => $users)
                            <optgroup label="{{ $roleName }}">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    @endif
                </select>
                <div class="mt-5 flex justify-end gap-3">
                    <button onclick="closeModal()" type="button" class="rounded-2xl border border-slate-300 px-4 py-2.5 text-slate-700 transition hover:bg-slate-50">Cancel</button>
                    <button onclick="confirmAssign()" type="button" class="rounded-2xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2.5 font-medium text-white transition hover:from-[#205A44] hover:to-[#15803d]">Assign</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let selectedLeadId = null;

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.js-assign-lead').forEach(btn => {
                btn.addEventListener('click', () => {
                    const leadId = parseInt(btn.dataset.leadId);
                    assignSingle(leadId);
                });
            });

            document.querySelectorAll('.js-delete-lead').forEach(btn => {
                btn.addEventListener('click', () => {
                    const leadId = parseInt(btn.dataset.leadId);
                    deleteSingle(leadId);
                });
            });
        });

        function selectAll() {
            document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = true);
            document.getElementById('select-all').checked = true;
            updateSelectedCount();
        }

        function deselectAll() {
            document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('select-all').checked = false;
            updateSelectedCount();
        }

        function toggleSelectAll(checkbox) {
            document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const count = document.querySelectorAll('.lead-checkbox:checked').length;
            document.getElementById('selected-count').textContent = count + ' selected';
        }

        function assignSingle(leadId) {
            selectedLeadId = leadId;
            document.getElementById('assign-modal').classList.remove('hidden');
            document.getElementById('assign-modal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('assign-modal').classList.add('hidden');
            document.getElementById('assign-modal').classList.remove('flex');
            selectedLeadId = null;
        }

        function confirmAssign() {
            const userId = document.getElementById('modal-telecaller').value;
            if (!userId) {
                alert('Please select a user');
                return;
            }

            assignLeads([selectedLeadId], userId);
        }

        function bulkAssign() {
            const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => parseInt(cb.value));
            const userId = document.getElementById('bulk-telecaller').value;

            if (selected.length === 0) {
                alert('Please select at least one lead');
                return;
            }

            if (!userId) {
                alert('Please select a user');
                return;
            }

            assignLeads(selected, userId);
        }

        function deleteSingle(leadId) {
            if (!confirm('Delete this lead?')) return;
            deleteLeads([leadId]);
        }

        function bulkDelete() {
            const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => parseInt(cb.value));
            if (selected.length === 0) {
                alert('Please select at least one lead');
                return;
            }
            if (!confirm(`Delete ${selected.length} selected lead(s)?`)) return;
            deleteLeads(selected);
        }

        function assignLeads(leadIds, telecallerId) {
            axios.post('{{ route("lead-assignment.assign") }}', {
                lead_ids: leadIds,
                telecaller_id: telecallerId
            })
            .then(response => {
                alert(response.data.message);
                window.location.reload();
            })
            .catch(error => {
                alert('Error: ' + (error.response?.data?.message || 'Failed to assign leads'));
            });
        }

        function deleteLeads(leadIds) {
            axios.post('{{ route("lead-assignment.delete") }}', {
                lead_ids: leadIds
            })
            .then(response => {
                alert(response.data.message);
                window.location.reload();
            })
            .catch(error => {
                alert('Error: ' + (error.response?.data?.message || 'Failed to delete leads'));
            });
        }
    </script>
    @endpush
@endsection
