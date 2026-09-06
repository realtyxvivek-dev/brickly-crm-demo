@extends('layouts.app')

@section('title', '99acres Lead Distribution')
@section('page-title', '99acres Lead Distribution')

@php
    $selectedMethod = old('assignment_method', $rule->assignment_method ?? 'round_robin');
    $existingUsers = old('users', isset($rule) ? $rule->users->map(fn($ruleUser) => [
        'user_id' => $ruleUser->user_id,
        'percentage' => $ruleUser->percentage,
        'daily_limit' => $ruleUser->daily_limit,
    ])->toArray() : [['user_id' => '', 'percentage' => '', 'daily_limit' => '']]);
    $selectedUserIds = collect($existingUsers)->pluck('user_id')->filter()->map(fn($id) => (int) $id)->all();
    $methods = [
        'single_user' => ['title' => 'Single User', 'copy' => 'All 99acres leads go to one selected user.', 'icon' => 'fas fa-user'],
        'round_robin' => ['title' => 'Round Robin', 'copy' => 'Rotate leads across selected users.', 'icon' => 'fas fa-sync-alt'],
        'first_available' => ['title' => 'First Available', 'copy' => 'Send to the available user with lowest load.', 'icon' => 'fas fa-user-check'],
        'percentage' => ['title' => 'Percentage Split', 'copy' => 'Split leads by fixed user percentages.', 'icon' => 'fas fa-percent'],
    ];
@endphp

@push('styles')
<style>
.nna-shell { max-width:1180px; margin:0 auto 40px; }
.nna-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,.05); margin-bottom:18px; overflow:hidden; }
.nna-head { padding:18px 22px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; gap:16px; align-items:flex-start; }
.nna-title { margin:0; font-size:18px; font-weight:700; color:#111827; }
.nna-sub { margin:4px 0 0; font-size:13px; color:#6b7280; }
.nna-body { padding:22px; }
.nna-status { display:inline-flex; align-items:center; gap:7px; padding:7px 12px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid #d1fae5; color:#047857; background:#ecfdf5; }
.nna-status.off { color:#92400e; background:#fffbeb; border-color:#fde68a; }
.nna-grid-4 { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
.nna-grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
.nna-method { position:relative; cursor:pointer; }
.nna-method input { position:absolute; opacity:0; }
.nna-method-card { height:100%; border:1.5px solid #e5e7eb; border-radius:10px; padding:14px; background:#fff; transition:.15s; }
.nna-method input:checked + .nna-method-card { border-color:#047857; background:#f0fdf4; box-shadow:0 0 0 3px rgba(4,120,87,.08); }
.nna-method-icon { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:#f3f4f6; color:#374151; margin-bottom:9px; }
.nna-method input:checked + .nna-method-card .nna-method-icon { background:#047857; color:#fff; }
.nna-method-title { margin:0 0 4px; font-size:13px; font-weight:700; color:#111827; }
.nna-method-copy { margin:0; font-size:12px; color:#6b7280; line-height:1.35; }
.nna-field { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
.nna-label { display:block; margin-bottom:7px; font-size:11px; font-weight:700; color:#6b7280; letter-spacing:.05em; text-transform:uppercase; }
.nna-input, .nna-select { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:9px 11px; font-size:13px; background:#fff; color:#111827; }
.nna-input:focus, .nna-select:focus { outline:none; border-color:#047857; box-shadow:0 0 0 3px rgba(4,120,87,.1); }
.nna-table-wrap { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; background:#fff; }
.nna-table { width:100%; border-collapse:collapse; }
.nna-table th { background:#f9fafb; border-bottom:1px solid #e5e7eb; padding:10px 12px; text-align:left; font-size:11px; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; }
.nna-table td { border-bottom:1px solid #f3f4f6; padding:10px 12px; vertical-align:middle; }
.nna-table tr:last-child td { border-bottom:none; }
.nna-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.nna-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:none; border-radius:8px; padding:9px 16px; font-size:13px; font-weight:700; text-decoration:none; cursor:pointer; }
.nna-btn.primary { background:#047857; color:#fff; }
.nna-btn.secondary { background:#fff; color:#374151; border:1px solid #d1d5db; }
.nna-btn.danger { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:8px 10px; }
.nna-check { display:flex; gap:10px; align-items:flex-start; font-size:13px; font-weight:600; color:#374151; }
.nna-note { border:1px solid #dbeafe; background:#eff6ff; color:#1e3a8a; border-radius:10px; padding:12px 14px; font-size:13px; }
.nna-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:10px; padding:12px 14px; margin-bottom:16px; font-size:13px; }
.nna-history { display:grid; gap:10px; }
.nna-history-row { display:flex; justify-content:space-between; gap:16px; padding:10px 0; border-bottom:1px solid #f3f4f6; font-size:13px; }
.nna-history-row:last-child { border-bottom:none; }
@media(max-width:900px){ .nna-grid-4,.nna-grid-3{ grid-template-columns:1fr 1fr; } }
@media(max-width:640px){ .nna-head{ flex-direction:column; } .nna-grid-4,.nna-grid-3{ grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="nna-shell">
    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="nna-error">
            <strong>Please fix:</strong>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="nna-card">
        <div class="nna-head">
            <div>
                <h2 class="nna-title">99acres Distribution Rule</h2>
                <p class="nna-sub">Webhook leads with source 99acres will use this rule before falling back to the integration queue.</p>
            </div>
            <span class="nna-status {{ ($rule?->is_active ?? false) ? '' : 'off' }}">
                <i class="fas fa-circle" style="font-size:8px;"></i>
                {{ ($rule?->is_active ?? false) ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.automation.99acres.update') }}">
            @csrf
            <div class="nna-body">
                <div class="nna-grid-4">
                    @foreach($methods as $value => $method)
                        <label class="nna-method">
                            <input type="radio" name="assignment_method" value="{{ $value }}" class="nna-method-radio" {{ $selectedMethod === $value ? 'checked' : '' }}>
                            <div class="nna-method-card">
                                <div class="nna-method-icon"><i class="{{ $method['icon'] }}"></i></div>
                                <p class="nna-method-title">{{ $method['title'] }}</p>
                                <p class="nna-method-copy">{{ $method['copy'] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div id="nnaSinglePanel" class="nna-field mt-4" style="{{ $selectedMethod === 'single_user' ? '' : 'display:none;' }}">
                    <label class="nna-label">Single user</label>
                    <select name="single_user_id" class="nna-select">
                        <option value="">Select user</option>
                        @foreach($assignableUsers as $user)
                            <option value="{{ $user->id }}" {{ (int) old('single_user_id', $rule->single_user_id ?? 0) === $user->id ? 'selected' : '' }}>
                                {{ $user->name }} - {{ $user->role?->name ?? 'User' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="nnaMultiPanel" class="mt-4" style="{{ $selectedMethod === 'single_user' ? 'display:none;' : '' }}">
                    <div class="nna-table-wrap">
                        <table class="nna-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th id="nnaPctHead" style="{{ $selectedMethod === 'percentage' ? '' : 'display:none;' }}">Percentage</th>
                                    <th>Daily Limit</th>
                                    <th style="width:52px;"></th>
                                </tr>
                            </thead>
                            <tbody id="nnaUsersBody">
                                @foreach($existingUsers as $index => $existingUser)
                                    <tr>
                                        <td>
                                            <select name="users[{{ $index }}][user_id]" class="nna-select nna-user-select">
                                                <option value="">Select user</option>
                                                @foreach($assignableUsers as $user)
                                                    <option value="{{ $user->id }}" {{ (int) ($existingUser['user_id'] ?? 0) === $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }} - {{ $user->role?->name ?? 'User' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="nna-pct-col" style="{{ $selectedMethod === 'percentage' ? '' : 'display:none;' }}">
                                            <input type="number" step="0.1" min="0" max="100" name="users[{{ $index }}][percentage]" class="nna-input nna-pct-input" value="{{ $existingUser['percentage'] ?? '' }}" placeholder="0">
                                        </td>
                                        <td>
                                            <input type="number" min="1" name="users[{{ $index }}][daily_limit]" class="nna-input" value="{{ $existingUser['daily_limit'] ?? '' }}" placeholder="Unlimited">
                                        </td>
                                        <td>
                                            <button type="button" class="nna-btn danger nna-remove-row"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="nna-actions mt-3">
                        <button type="button" class="nna-btn secondary" id="nnaAddUser"><i class="fas fa-plus"></i>Add User</button>
                        <span id="nnaPctSummary" class="text-sm font-semibold text-gray-600" style="{{ $selectedMethod === 'percentage' ? '' : 'display:none;' }}">Total: 0%</span>
                    </div>
                </div>

                <div class="nna-grid-3 mt-4">
                    <div class="nna-field">
                        <label class="nna-label">Rule daily limit</label>
                        <input type="number" min="1" name="daily_limit" class="nna-input" value="{{ old('daily_limit', $rule->daily_limit ?? '') }}" placeholder="Unlimited">
                    </div>
                    <div class="nna-field">
                        <label class="nna-label">Fallback user</label>
                        <select name="fallback_user_id" class="nna-select">
                            <option value="">No fallback</option>
                            @foreach($assignableUsers as $user)
                                <option value="{{ $user->id }}" {{ (int) old('fallback_user_id', $rule->fallback_user_id ?? 0) === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="nna-field">
                        <label class="nna-label">Source</label>
                        <input class="nna-input" value="99acres" readonly>
                    </div>
                </div>

                <div class="nna-grid-3 mt-4">
                    <label class="nna-field nna-check">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $rule->is_active ?? true) ? 'checked' : '' }}>
                        <span>Enable 99acres auto distribution</span>
                    </label>
                    <label class="nna-field nna-check">
                        <input type="checkbox" name="auto_create_task" value="1" {{ old('auto_create_task', $rule->auto_create_task ?? true) ? 'checked' : '' }}>
                        <span>Create calling task after assignment</span>
                    </label>
                    <div class="nna-note">
                        If selected users are absent, inactive, or over limit, fallback user is used. If no fallback is set, lead stays unassigned.
                    </div>
                </div>

                <div class="nna-actions mt-5">
                    <button type="submit" class="nna-btn primary"><i class="fas fa-save"></i>Save 99acres Distribution</button>
                    <a href="{{ route('admin.automation.index') }}" class="nna-btn secondary"><i class="fas fa-arrow-left"></i>Back</a>
                    <a href="{{ route('integrations.99acres.index') }}" class="nna-btn secondary"><i class="fas fa-link"></i>Webhook Settings</a>
                </div>
            </div>
        </form>
    </div>

    <div class="nna-card">
        <div class="nna-head">
            <div>
                <h3 class="nna-title">Recent 99acres Assignments</h3>
                <p class="nna-sub">Last assigned leads from this source.</p>
            </div>
        </div>
        <div class="nna-body">
            <div class="nna-history">
                @forelse($recentAssignments as $assignment)
                    <div class="nna-history-row">
                        <div>
                            <strong>{{ $assignment->lead?->name ?? 'Lead' }}</strong>
                            <span class="text-gray-500">({{ $assignment->lead?->phone ?? '-' }})</span>
                        </div>
                        <div>
                            {{ $assignment->assignedTo?->name ?? 'Unknown user' }}
                            <span class="text-gray-400">{{ $assignment->assigned_at?->format('d M, h:i A') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500">No 99acres assignment history yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const nnaUsers = @json($assignableUsers->map(fn($user) => ['id' => $user->id, 'label' => $user->name . ' - ' . ($user->role?->name ?? 'User')])->values());
let nnaRowIndex = {{ count($existingUsers) }};
const nnaUsersBody = document.getElementById('nnaUsersBody');
const nnaPctHead = document.getElementById('nnaPctHead');
const nnaPctSummary = document.getElementById('nnaPctSummary');

function nnaOptions() {
    return '<option value="">Select user</option>' + nnaUsers.map((user) => `<option value="${user.id}">${user.label}</option>`).join('');
}

function nnaIsPercentage() {
    return document.querySelector('.nna-method-radio:checked')?.value === 'percentage';
}

function nnaSyncMethod() {
    const isSingle = document.querySelector('.nna-method-radio:checked')?.value === 'single_user';
    const isPct = nnaIsPercentage();
    document.getElementById('nnaSinglePanel').style.display = isSingle ? '' : 'none';
    document.getElementById('nnaMultiPanel').style.display = isSingle ? 'none' : '';
    nnaPctHead.style.display = isPct ? '' : 'none';
    nnaPctSummary.style.display = isPct ? '' : 'none';
    document.querySelectorAll('.nna-pct-col').forEach((cell) => cell.style.display = isPct ? '' : 'none');
    nnaUpdatePct();
}

function nnaUpdatePct() {
    let total = 0;
    document.querySelectorAll('.nna-pct-input').forEach((input) => total += parseFloat(input.value) || 0);
    total = Math.round(total * 10) / 10;
    nnaPctSummary.textContent = `Total: ${total}%`;
    nnaPctSummary.style.color = Math.abs(total - 100) < 0.1 ? '#047857' : '#b45309';
}

function nnaBindRow(row) {
    row.querySelector('.nna-remove-row').addEventListener('click', () => {
        row.remove();
        nnaUpdatePct();
    });
}

document.querySelectorAll('.nna-method-radio').forEach((radio) => radio.addEventListener('change', nnaSyncMethod));
document.querySelectorAll('#nnaUsersBody tr').forEach(nnaBindRow);
nnaUsersBody.addEventListener('input', (event) => {
    if (event.target.matches('.nna-pct-input')) {
        nnaUpdatePct();
    }
});
document.getElementById('nnaAddUser').addEventListener('click', () => {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><select name="users[${nnaRowIndex}][user_id]" class="nna-select nna-user-select">${nnaOptions()}</select></td>
        <td class="nna-pct-col" style="${nnaIsPercentage() ? '' : 'display:none;'}"><input type="number" step="0.1" min="0" max="100" name="users[${nnaRowIndex}][percentage]" class="nna-input nna-pct-input" placeholder="0"></td>
        <td><input type="number" min="1" name="users[${nnaRowIndex}][daily_limit]" class="nna-input" placeholder="Unlimited"></td>
        <td><button type="button" class="nna-btn danger nna-remove-row"><i class="fas fa-trash"></i></button></td>
    `;
    nnaUsersBody.appendChild(row);
    nnaBindRow(row);
    nnaRowIndex++;
});
nnaSyncMethod();
</script>
@endpush
