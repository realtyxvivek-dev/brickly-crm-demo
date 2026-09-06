@extends('layouts.app')

@section('title', 'Bulk Calling Tasks - ' . brand_name())
@section('page-title', 'Bulk Calling Tasks')
@section('page-subtitle', 'Create one scheduled calling task for selected or all eligible assigned leads.')

@section('header-actions')
    <a href="{{ route('lead-assignment.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors duration-200 text-sm font-medium">
        Back
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="xl:col-span-2">
                    <label for="assigned-user" class="block text-sm font-medium text-gray-700 mb-1">Assigned User</label>
                    <select id="assigned-user" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select user</option>
                        @foreach($eligibleUsers as $roleName => $users)
                            <optgroup label="{{ $roleName }}">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input id="start-date" type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label for="start-time" class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                    <input id="start-time" type="time" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label for="gap-minutes" class="block text-sm font-medium text-gray-700 mb-1">Gap (minutes)</label>
                    <input id="gap-minutes" type="number" min="0" step="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="5">
                </div>
            </div>

            <div class="mt-4">
                <label for="task-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="task-notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Optional notes for all created tasks"></textarea>
                <p class="mt-2 text-sm text-gray-500">Pehli selected lead ko chosen start date aur time milega. Uske baad har next selected lead ko selected interval ke hisab se auto time milega.</p>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-4">
                <span id="summary-text" class="text-sm text-gray-500">Pehle user select karo. Leads automatically yahan load ho jayengi.</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-4">
                    <button type="button" id="select-all-btn" class="text-sm text-indigo-600 hover:text-indigo-800">Select all on page</button>
                    <button type="button" id="clear-selection-btn" class="text-sm text-gray-600 hover:text-gray-800">Clear selection</button>
                    <span id="selected-count" class="text-sm text-gray-600">0 selected</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="create-selected-btn" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                        Create For Selected Leads
                    </button>
                </div>
            </div>

            <div id="feedback-box" class="hidden mb-4 rounded-lg border px-4 py-3 text-sm"></div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                <input type="checkbox" id="master-checkbox">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lead</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current State</th>
                        </tr>
                    </thead>
                    <tbody id="lead-table-body" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">No leads loaded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const state = {
            leads: [],
        };

        const assignedUser = document.getElementById('assigned-user');
        const startDate = document.getElementById('start-date');
        const startTime = document.getElementById('start-time');
        const gapMinutes = document.getElementById('gap-minutes');
        const notesInput = document.getElementById('task-notes');
        const tableBody = document.getElementById('lead-table-body');
        const selectedCount = document.getElementById('selected-count');
        const masterCheckbox = document.getElementById('master-checkbox');
        const summaryText = document.getElementById('summary-text');
        const feedbackBox = document.getElementById('feedback-box');

        const defaultStartDate = new Date(Date.now() + 30 * 60000);
        startDate.value = defaultStartDate.toISOString().slice(0, 10);
        startTime.value = defaultStartDate.toTimeString().slice(0, 5);

        function currentFilters() {
            return {
                assigned_user_id: assignedUser.value,
            };
        }

        function selectedLeadIds() {
            return Array.from(document.querySelectorAll('.lead-checkbox:checked')).map((checkbox) => Number(checkbox.value));
        }

        function renderFeedback(type, message) {
            feedbackBox.classList.remove('hidden', 'border-red-200', 'bg-red-50', 'text-red-700', 'border-green-200', 'bg-green-50', 'text-green-700');
            if (type === 'error') {
                feedbackBox.classList.add('border-red-200', 'bg-red-50', 'text-red-700');
            } else {
                feedbackBox.classList.add('border-green-200', 'bg-green-50', 'text-green-700');
            }
            feedbackBox.textContent = message;
        }

        function clearFeedback() {
            feedbackBox.classList.add('hidden');
            feedbackBox.textContent = '';
        }

        function updateSelectionSummary() {
            const count = selectedLeadIds().length;
            selectedCount.textContent = `${count} selected`;
            const totalCheckboxes = document.querySelectorAll('.lead-checkbox').length;
            masterCheckbox.checked = totalCheckboxes > 0 && count === totalCheckboxes;
        }

        function renderLeads() {
            if (state.leads.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No eligible leads found for this user and filter combination.</td></tr>';
                updateSelectionSummary();
                return;
            }

            tableBody.innerHTML = state.leads.map((lead) => `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4 align-top">
                        <input type="checkbox" class="lead-checkbox" value="${lead.id}">
                    </td>
                    <td class="px-4 py-4 align-top">
                        <div class="font-medium text-gray-900">${lead.name ?? 'Unknown'}</div>
                        <div class="text-xs text-gray-500 mt-1">Lead #${lead.id}</div>
                    </td>
                    <td class="px-4 py-4 align-top text-sm text-gray-700">${lead.phone ?? '-'}</td>
                    <td class="px-4 py-4 align-top text-sm text-gray-700">${lead.status ?? '-'}</td>
                    <td class="px-4 py-4 align-top text-sm text-gray-700">${lead.source_label ?? '-'}</td>
                    <td class="px-4 py-4 align-top">
                        ${lead.has_open_call_task
                            ? '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Open call task exists</span>'
                            : '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">Ready for new task</span>'
                        }
                    </td>
                </tr>
            `).join('');

            document.querySelectorAll('.lead-checkbox').forEach((checkbox) => {
                checkbox.addEventListener('change', updateSelectionSummary);
            });

            summaryText.textContent = `${state.leads.length} lead(s) loaded on this page.`;
            updateSelectionSummary();
        }

        async function loadLeads() {
            clearFeedback();

            if (!assignedUser.value) {
                renderFeedback('error', 'Please select an assigned user first.');
                return;
            }

            summaryText.textContent = 'Loading leads...';

            try {
                const response = await axios.get('{{ route('lead-assignment.calling-tasks.leads') }}', {
                    params: currentFilters(),
                });

                state.leads = response.data.data || [];
                renderLeads();
                summaryText.textContent = `${response.data.total || state.leads.length} lead(s) match the current filters.`;
            } catch (error) {
                state.leads = [];
                renderLeads();
                renderFeedback('error', error.response?.data?.message || 'Failed to load leads.');
                summaryText.textContent = 'Unable to load leads.';
            }
        }

        async function createTasks() {
            clearFeedback();

            if (!assignedUser.value) {
                renderFeedback('error', 'Please select an assigned user.');
                return;
            }

            if (!startDate.value || !startTime.value) {
                renderFeedback('error', 'Please select a start date and start time.');
                return;
            }

            if (gapMinutes.value === '' || Number(gapMinutes.value) < 0) {
                renderFeedback('error', 'Please enter a valid gap in minutes.');
                return;
            }

            const leadIds = selectedLeadIds();
            if (leadIds.length === 0) {
                renderFeedback('error', 'Please select at least one lead.');
                return;
            }

            try {
                const response = await axios.post('{{ route('lead-assignment.calling-tasks.store') }}', {
                    ...currentFilters(),
                    start_date: startDate.value,
                    start_time: startTime.value,
                    gap_minutes: Number(gapMinutes.value),
                    notes: notesInput.value,
                    lead_ids: leadIds,
                });

                const reasonCounts = response.data.reason_counts || {};
                const parts = [
                    response.data.message,
                    `Duplicate/open-task skipped: ${reasonCounts.duplicate_open_task || 0}.`,
                    `Not eligible skipped: ${reasonCounts.lead_not_eligible || 0}.`,
                ];

                if (response.data.first_scheduled_at) {
                    parts.push(`Time window: ${response.data.first_scheduled_at} to ${response.data.last_scheduled_at}.`);
                }

                renderFeedback('success', parts.join(' '));
                await loadLeads();
            } catch (error) {
                renderFeedback('error', error.response?.data?.message || 'Failed to create calling tasks.');
            }
        }

        document.getElementById('create-selected-btn').addEventListener('click', createTasks);
        document.getElementById('select-all-btn').addEventListener('click', () => {
            document.querySelectorAll('.lead-checkbox').forEach((checkbox) => checkbox.checked = true);
            updateSelectionSummary();
        });
        document.getElementById('clear-selection-btn').addEventListener('click', () => {
            document.querySelectorAll('.lead-checkbox').forEach((checkbox) => checkbox.checked = false);
            updateSelectionSummary();
        });
        masterCheckbox.addEventListener('change', () => {
            document.querySelectorAll('.lead-checkbox').forEach((checkbox) => checkbox.checked = masterCheckbox.checked);
            updateSelectionSummary();
        });
        assignedUser.addEventListener('change', loadLeads);
    </script>
@endpush
