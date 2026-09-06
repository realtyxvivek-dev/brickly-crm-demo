@extends('layouts.app')

@section('title', 'Set Target - Admin')
@section('page-title', 'Set Monthly Target')
@section('page-subtitle', 'Set targets for Sales Executive or Senior Manager for a specific month')

@php
    $targetsRouteBase = auth()->user()->isHrManager()
        ? 'hr-manager.targets'
        : (auth()->user()->isCrm() ? 'crm.targets' : 'admin.targets');
@endphp

@section('header-actions')
    <a href="{{ route($targetsRouteBase . '.index', ['month' => $month]) }}" class="btn btn-brand-secondary">← Back</a>
@endsection

@push('styles')
<style>
    .targets-page .t-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.06); max-width: 900px; }
    .targets-page .t-alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
    .targets-page .t-info { background: #e3f2fd; padding: 14px; border-radius: 10px; margin-bottom: 16px; border-left: 4px solid #2196f3; }
    .targets-page .form-group { margin-bottom: 16px; }
    .targets-page .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; }
    .targets-page .form-group label .required { color: #dc3545; }
    .targets-page .form-group input, .targets-page .form-group select { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white; }
    .targets-page .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .targets-page .section-title { font-size: 16px; font-weight: 700; color: #111; margin: 22px 0 12px 0; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
    .targets-page .target-preview-card { background:#f8fafc; border:1px solid #dbe4ee; border-radius:14px; padding:16px; margin-bottom:18px; display:none; }
    .targets-page .target-preview-title { font-size:14px; font-weight:700; color:#0f172a; margin-bottom:10px; }
    .targets-page .target-preview-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; }
    .targets-page .target-preview-metric { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px; }
    .targets-page .target-preview-metric strong { display:block; font-size:12px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin-bottom:8px; }
    .targets-page .target-preview-metric span { display:block; font-size:14px; color:#334155; line-height:1.5; }
</style>
@endpush

@section('content')
    <div class="targets-page">

        @if($errors->any())
            <div class="t-alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if($existingTarget)
            <div class="t-info">
                <strong>Note:</strong> A target already exists for this user and month. Updating will replace the existing target.
            </div>
        @endif

        <form method="POST" action="{{ route($targetsRouteBase . '.store') }}" class="t-card">
            @csrf

            <div class="form-group">
                <label>Select User <span class="required">*</span></label>
                <select name="user_id" id="user_id" required onchange="toggleClosersField()">
                    <option value="">-- Select User --</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" 
                                data-role="{{ $user->role->slug }}"
                                data-preview='@json($user->target_preview ?? [])'
                                {{ (old('user_id') ?? $userId) == $user->id ? 'selected' : '' }}>
                            {{ $user->name }} ({{ $user->email }}) - {{ $user->getDisplayRoleName() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Target Month <span class="required">*</span></label>
                <input type="month" name="month" value="{{ old('month', $month) }}" required>
            </div>

            <h2 class="section-title">User Targets</h2>

            <!-- Prospect Targets (Hidden for Senior Managers) -->
            <div id="prospect-targets-section">
                <div class="form-row">
                    <div class="form-group">
                        <label>Prospects to Extract</label>
                        <input type="number" name="target_prospects_extract" id="target_prospects_extract" value="{{ old('target_prospects_extract', $existingTarget->target_prospects_extract ?? 0) }}" min="0" placeholder="0">
                        <small style="color: #666;">Number of prospects the user should extract/create</small>
                    </div>

                    <div class="form-group">
                        <label>Prospects to Verify</label>
                        <input type="number" name="target_prospects_verified" id="target_prospects_verified" value="{{ old('target_prospects_verified', $existingTarget->target_prospects_verified ?? 0) }}" min="0" placeholder="0">
                        <small style="color: #666;">Number of prospects that should be verified/approved</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Calls to Make</label>
                    <input type="number" name="target_calls" id="target_calls" value="{{ old('target_calls', $existingTarget->target_calls ?? 0) }}" min="0" placeholder="0">
                    <small style="color: #666;">Number of phone calls the user should complete</small>
                </div>
            </div>

            <h2 class="section-title">Additional Targets (Optional)</h2>

            <div id="leader-target-preview" class="target-preview-card">
                <div class="target-preview-title">Final target = self target + live sum of team targets for this month.</div>
                <div class="target-preview-grid">
                    <div class="target-preview-metric">
                        <strong>Visits</strong>
                        <span id="preview-visits-self">Self: 0</span>
                        <span id="preview-visits-team">Team: 0</span>
                        <span id="preview-visits-final">Final: 0</span>
                    </div>
                    <div class="target-preview-metric">
                        <strong>Meetings</strong>
                        <span id="preview-meetings-self">Self: 0</span>
                        <span id="preview-meetings-team">Team: 0</span>
                        <span id="preview-meetings-final">Final: 0</span>
                    </div>
                    <div class="target-preview-metric">
                        <strong>Closers</strong>
                        <span id="preview-closers-self">Self: 0</span>
                        <span id="preview-closers-team">Team: 0</span>
                        <span id="preview-closers-final">Final: 0</span>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Site Visits</label>
                    <input type="number" name="target_visits" value="{{ old('target_visits', $existingTarget->target_visits ?? 0) }}" min="0" placeholder="0">
                </div>

                <div class="form-group">
                    <label>Meetings</label>
                    <input type="number" name="target_meetings" value="{{ old('target_meetings', $existingTarget->target_meetings ?? 0) }}" min="0" placeholder="0">
                </div>
            </div>

            <div class="form-group" id="closers-field" style="display: none;">
                <label>Closers</label>
                <input type="number" name="target_closers" value="{{ old('target_closers', $existingTarget->target_closers ?? 0) }}" min="0" placeholder="0">
                <small style="color: #666;">For Senior Managers, Assistant Sales Managers, and Sales Executives</small>
            </div>

            <!-- Incentive Rates Section -->
            <h2 class="section-title">Incentive Rates (Optional)</h2>
            
            <div class="form-group" id="incentive-per-closer-field" style="display: none;">
                <label>Incentive per Closer (₹)</label>
                <input type="number" name="incentive_per_closer" id="incentive_per_closer" step="0.01" min="0" value="{{ old('incentive_per_closer', $existingTarget->incentive_per_closer ?? '') }}" placeholder="0.00">
                <small style="color: #666;">Incentive amount per closer for Managers and Sales Executives</small>
            </div>

            <div class="form-group" id="incentive-per-visit-field" style="display: none;">
                <label>Incentive per Visit (₹)</label>
                <input type="number" name="incentive_per_visit" id="incentive_per_visit" step="0.01" min="0" value="{{ old('incentive_per_visit', $existingTarget->incentive_per_visit ?? '') }}" placeholder="0.00">
                <small style="color: #666;">Incentive amount per site visit for Sales Executives</small>
            </div>

            <!-- Manager Target Calculation Logic (Only for Senior Managers) -->
            <div id="manager-logic-section" style="display: none;">
                <h2 class="section-title">Manager Target Calculation Logic</h2>
                
                <div class="form-group">
                    <label>Calculation Logic <span class="required">*</span></label>
                    <select name="manager_target_calculation_logic" id="manager_target_calculation_logic" required>
                        <option value="">-- Select Logic --</option>
                        <option value="juniors_sum" {{ old('manager_target_calculation_logic', $existingTarget->manager_target_calculation_logic ?? '') == 'juniors_sum' ? 'selected' : '' }}>
                            Sum of Juniors' Targets (Logic 1)
                        </option>
                        <option value="individual_plus_team" {{ old('manager_target_calculation_logic', $existingTarget->manager_target_calculation_logic ?? '') == 'individual_plus_team' ? 'selected' : '' }}>
                            Individual Target + Team Consolidated (Logic 2)
                        </option>
                    </select>
                    <small style="color: #666;">
                        <strong>Logic 1:</strong> Manager's target = Sum of all juniors' targets<br>
                        <strong>Logic 2:</strong> Manager's target = Individual target + Sum of juniors' targets
                    </small>
                </div>

                <div class="form-group">
                    <label>Junior Scope <span class="required">*</span></label>
                    <select name="manager_junior_scope" id="manager_junior_scope" required>
                        <option value="">-- Select Scope --</option>
                        <option value="executives_only" {{ old('manager_junior_scope', $existingTarget->manager_junior_scope ?? '') == 'executives_only' ? 'selected' : '' }}>
                            Assistant Sales Managers Only
                        </option>
                        <option value="executives_and_telecallers" {{ old('manager_junior_scope', $existingTarget->manager_junior_scope ?? '') == 'executives_and_telecallers' ? 'selected' : '' }}>
                            Assistant Sales Managers + Sales Executives
                        </option>
                    </select>
                    <small style="color: #666;">Select which junior roles should be included in the live team sum.</small>
                </div>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-brand-primary">Set Target</button>
                <a href="{{ route($targetsRouteBase . '.index', ['month' => $month]) }}" class="btn btn-brand-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
        function toggleClosersField() {
            const userSelect = document.getElementById('user_id');
            const closersField = document.getElementById('closers-field');
            const managerLogicSection = document.getElementById('manager-logic-section');
            const prospectTargetsSection = document.getElementById('prospect-targets-section');
            const incentivePerCloserField = document.getElementById('incentive-per-closer-field');
            const incentivePerVisitField = document.getElementById('incentive-per-visit-field');
            const selectedOption = userSelect.options[userSelect.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const role = selectedOption.getAttribute('data-role');
                
                const previewCard = document.getElementById('leader-target-preview');
                const previewData = selectedOption.getAttribute('data-preview');
                const parsedPreview = previewData ? JSON.parse(previewData) : {};
                const isLeaderRole = role === 'sales_manager' || role === 'senior_manager';
                
                // Hide prospect targets for Senior Managers and Assistant Sales Managers
                if (role === 'sales_manager' || role === 'senior_manager' || role === 'assistant_sales_manager') {
                    prospectTargetsSection.style.display = 'none';
                    // Set prospect fields to 0 for managers
                    document.getElementById('target_prospects_extract').value = 0;
                    document.getElementById('target_prospects_verified').value = 0;
                    document.getElementById('target_calls').value = 0;
                } else {
                    prospectTargetsSection.style.display = 'block';
                }
                
                // Show closers field for Senior Managers, Assistant Sales Managers, and Sales Executives
                if (role === 'sales_manager' || role === 'senior_manager' || role === 'sales_executive' || role === 'assistant_sales_manager') {
                    closersField.style.display = 'block';
                } else {
                    closersField.style.display = 'none';
                }
                
                // Show incentive per closer for Senior Managers, Assistant Sales Managers, and Sales Executives
                if (role === 'sales_manager' || role === 'senior_manager' || role === 'sales_executive' || role === 'assistant_sales_manager') {
                    incentivePerCloserField.style.display = 'block';
                } else {
                    incentivePerCloserField.style.display = 'none';
                }
                
                // Show incentive per visit for Telecallers
                if (role === 'telecaller') {
                    incentivePerVisitField.style.display = 'block';
                } else {
                    incentivePerVisitField.style.display = 'none';
                }
                
                // Show manager logic section only for Senior Managers
                if (isLeaderRole) {
                    managerLogicSection.style.display = 'block';
                    previewCard.style.display = 'block';
                    // Make fields required
                    document.getElementById('manager_target_calculation_logic').required = true;
                    document.getElementById('manager_junior_scope').required = true;
                    renderLeaderPreview(parsedPreview);
                } else {
                    managerLogicSection.style.display = 'none';
                    previewCard.style.display = 'none';
                    // Make fields not required
                    document.getElementById('manager_target_calculation_logic').required = false;
                    document.getElementById('manager_junior_scope').required = false;
                }
            } else {
                prospectTargetsSection.style.display = 'block';
                closersField.style.display = 'none';
                managerLogicSection.style.display = 'none';
                document.getElementById('leader-target-preview').style.display = 'none';
                incentivePerCloserField.style.display = 'none';
                incentivePerVisitField.style.display = 'none';
                document.getElementById('manager_target_calculation_logic').required = false;
                document.getElementById('manager_junior_scope').required = false;
            }
        }

        function renderLeaderPreview(preview) {
            const sections = ['visits', 'meetings', 'closers'];
            sections.forEach(function(section) {
                const values = preview[section] || { self: 0, team: 0, final: 0 };
                document.getElementById(`preview-${section}-self`).textContent = `Self: ${values.self || 0}`;
                document.getElementById(`preview-${section}-team`).textContent = `Team: ${values.team || 0}`;
                document.getElementById(`preview-${section}-final`).textContent = `Final: ${values.final || 0}`;
            });
        }
        
        // Call on page load if user is already selected
        document.addEventListener('DOMContentLoaded', function() {
            toggleClosersField();
        });
    </script>
@endpush
