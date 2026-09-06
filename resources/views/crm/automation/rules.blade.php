@extends('layouts.app')

@section('title', 'Assignment Rules - ' . brand_name())
@section('page-title', 'Assignment Rules')
@section('page-subtitle', 'Manage how imported CRM leads are distributed across users.')

@push('styles')
<style>
    .crm-user-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 120px auto;
        gap: 12px;
        align-items: center;
        margin-bottom: 12px;
    }
    .crm-total-pill {
        margin-top: 10px;
        padding: 12px 16px;
        border-radius: 16px;
        background: #f4f7f4;
        font-weight: 700;
    }
    .crm-total-pill.valid {
        background: #ecfdf3;
        color: #027a48;
    }
    .crm-total-pill.invalid {
        background: #fff6f6;
        color: #b42318;
    }
    @media (max-width: 768px) {
        .crm-user-row {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="page-shell">
    <section class="crm-hero">
        <div class="crm-hero-grid">
            <div>
                <span class="crm-kicker">
                    <i class="fas fa-diagram-project"></i>
                    Rule Manager
                </span>
                <h2 class="crm-hero-title">Build assignment rules in the <strong>same CRM workspace style</strong>.</h2>
                <p class="crm-hero-copy">Specific user aur percentage-based distribution ka backend same rahega. Is page par sirf layout aur usability improve hui hai.</p>
            </div>
            <div class="crm-note">
                <strong>Rule types:</strong> one user assignment ya multi-user percentage distribution. Percentage rules ko exactly 100% sum karna hoga.
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="crm-note">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="crm-note crm-note-warning">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="crm-surface">
        <div class="crm-surface-header">
            <div>
                <div class="crm-pill">Create Rule</div>
                <h3 class="crm-section-title">New Assignment Rule</h3>
            </div>
        </div>

        <form method="POST" action="{{ route('crm.automation.rules.store') }}" id="ruleForm">
            @csrf
            <div class="crm-form-grid">
                <div class="crm-field">
                    <label for="ruleName">Rule Name</label>
                    <input type="text" id="ruleName" name="name" required placeholder="e.g., Team Distribution" class="crm-form-control w-100">
                </div>
                <div class="crm-field">
                    <label for="ruleType">Rule Type</label>
                    <select name="type" id="ruleType" required onchange="toggleRuleType()" class="crm-form-control w-100">
                        <option value="specific_user">Assign to Specific User</option>
                        <option value="percentage">Percentage-Based Distribution</option>
                    </select>
                </div>
            </div>

            <div class="crm-field mt-4" id="specificUserSection">
                <label for="specificUserId">Select User</label>
                <select name="specific_user_id" id="specificUserId" class="crm-form-control w-100">
                    <option value="">Select user</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->name }})</option>
                    @endforeach
                </select>
            </div>

            <div class="crm-field mt-4" id="percentageSection" style="display:none;">
                <label>User Distribution</label>
                <div id="usersContainer">
                    <div class="crm-user-row">
                        <select name="users[0][user_id]" required class="crm-form-control w-100">
                            <option value="">Select user</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->name }})</option>
                            @endforeach
                        </select>
                        <input type="number" name="users[0][percentage]" class="crm-form-control percentage-input w-100" min="0" max="100" step="0.01" required placeholder="%" onchange="updateTotal()">
                        <button type="button" onclick="removeUserRow(this)" class="btn btn-danger">Remove</button>
                    </div>
                </div>
                <div class="crm-inline-stack mt-3">
                    <button type="button" onclick="addUserRow()" class="btn btn-brand-secondary">Add User</button>
                </div>
                <div class="crm-total-pill invalid" id="totalPercentage">Total: 0%</div>
            </div>

            <div class="crm-field mt-4">
                <label for="ruleDescription">Description</label>
                <textarea name="description" id="ruleDescription" rows="4" placeholder="Describe this rule..." class="crm-form-control w-100" style="padding-top:12px;"></textarea>
            </div>

            <div class="crm-inline-stack mt-4">
                <button type="submit" class="btn btn-brand-primary">Create Rule</button>
                <a href="{{ route('crm.automation.index') }}" class="btn btn-light">Back</a>
            </div>
        </form>
    </section>

    <section class="crm-surface">
        <div class="crm-surface-header">
            <div>
                <div class="crm-pill">Existing Rules</div>
                <h3 class="crm-section-title">Current Assignment Rules</h3>
            </div>
        </div>

        <div class="crm-table-shell">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $rule)
                            <tr>
                                <td><strong>{{ $rule->name }}</strong></td>
                                <td>{{ $rule->type === 'specific_user' ? 'Specific User' : 'Percentage' }}</td>
                                <td>
                                    @if($rule->type === 'specific_user')
                                        {{ $rule->specificUser->name ?? 'N/A' }}
                                    @else
                                        @foreach($rule->ruleUsers as $ruleUser)
                                            {{ $ruleUser->user->name }} ({{ $ruleUser->percentage }}%)@if(!$loop->last), @endif
                                        @endforeach
                                    @endif
                                    @if($rule->googleSheetsConfigs->count() > 0)
                                        <div class="mt-1 text-muted small">Used by {{ $rule->googleSheetsConfigs->count() }} Google Sheet(s)</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="crm-pill" style="{{ $rule->is_active ? 'background:#ecfdf3;color:#027a48;' : 'background:#fff1f2;color:#b42318;' }}">
                                        {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $rule->created_at->format('M d, Y') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('crm.automation.rules.destroy', $rule) }}" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="crm-empty">
                                        <i class="fas fa-diagram-project"></i>
                                        <p>No rules created yet.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    let userRowIndex = 1;

    function toggleRuleType() {
        const type = document.getElementById('ruleType').value;
        const specificSection = document.getElementById('specificUserSection');
        const percentageSection = document.getElementById('percentageSection');
        const specificSelect = document.getElementById('specificUserId');

        if (type === 'specific_user') {
            specificSection.style.display = 'block';
            percentageSection.style.display = 'none';
            specificSelect.required = true;
        } else {
            specificSection.style.display = 'none';
            percentageSection.style.display = 'block';
            specificSelect.required = false;
        }
    }

    function addUserRow() {
        const container = document.getElementById('usersContainer');
        const row = document.createElement('div');
        row.className = 'crm-user-row';
        row.innerHTML = `
            <select name="users[${userRowIndex}][user_id]" required class="crm-form-control w-100">
                <option value="">Select user</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->name }})</option>
                @endforeach
            </select>
            <input type="number" name="users[${userRowIndex}][percentage]" class="crm-form-control percentage-input w-100" min="0" max="100" step="0.01" required placeholder="%" onchange="updateTotal()">
            <button type="button" onclick="removeUserRow(this)" class="btn btn-danger">Remove</button>
        `;
        container.appendChild(row);
        userRowIndex++;
    }

    function removeUserRow(btn) {
        btn.closest('.crm-user-row').remove();
        updateTotal();
    }

    function updateTotal() {
        const inputs = document.querySelectorAll('.percentage-input');
        let total = 0;
        inputs.forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        const totalDiv = document.getElementById('totalPercentage');
        totalDiv.textContent = `Total: ${total.toFixed(2)}%`;
        totalDiv.className = Math.abs(total - 100) < 0.01 ? 'crm-total-pill valid' : 'crm-total-pill invalid';
    }

    document.getElementById('ruleForm').addEventListener('submit', function(e) {
        const type = document.getElementById('ruleType').value;
        if (type === 'percentage') {
            const totalDiv = document.getElementById('totalPercentage');
            const total = parseFloat(totalDiv.textContent.replace('Total: ', '').replace('%', ''));
            if (Math.abs(total - 100) >= 0.01) {
                e.preventDefault();
                alert('Percentages must sum to exactly 100%');
                return false;
            }
        }
    });
</script>
@endpush
