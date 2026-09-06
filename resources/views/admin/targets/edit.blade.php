@extends('layouts.app')

@section('title', 'Edit Target - Admin')
@section('page-title', 'Edit Target')
@section('page-subtitle', 'Edit target for ' . ($target->user->name ?? 'User') . ' - ' . $target->target_month->format('M Y'))

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
    .targets-page .t-info { background: #fff3cd; padding: 14px; border-radius: 10px; margin-bottom: 16px; border-left: 4px solid #ffc107; }
    .targets-page .form-group { margin-bottom: 16px; }
    .targets-page .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; }
    .targets-page .form-group input, .targets-page .form-group select { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px; background: white; }
    .targets-page .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .targets-page .section-title { font-size: 16px; font-weight: 700; color: #111; margin: 22px 0 12px 0; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
    .targets-page .target-preview-card { background:#f8fafc; border:1px solid #dbe4ee; border-radius:14px; padding:16px; margin-bottom:18px; }
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

        @php
            $progress = $target->getProgressData();
            $targetPreview = [
                'visits' => $target->getTargetBreakdown('visits'),
                'meetings' => $target->getTargetBreakdown('meetings'),
                'closers' => $target->getTargetBreakdown('closers'),
            ];
            $isLeaderRole = $target->user->isSalesManager() || $target->user->isSeniorManager();
            $usesProspectlessTargets = $target->user->isSalesManager() || $target->user->isSeniorManager() || $target->user->isAssistantSalesManager();
        @endphp

        <div class="t-info">
            <strong>Current Progress:</strong><br>
            Prospects Extract: {{ $progress['prospects_extract']['actual'] }} / {{ $target->target_prospects_extract }} ({{ number_format($progress['prospects_extract']['percentage'], 1) }}%)<br>
            Prospects Verified: {{ $progress['prospects_verified']['actual'] }} / {{ $target->target_prospects_verified }} ({{ number_format($progress['prospects_verified']['percentage'], 1) }}%)<br>
            Calls: {{ $progress['calls']['actual'] }} / {{ $target->target_calls }} ({{ number_format($progress['calls']['percentage'], 1) }}%)
        </div>

        <form method="POST" action="{{ route($targetsRouteBase . '.update', $target->id) }}" class="t-card">
            @csrf
            @method('PUT')

            <h2 class="section-title">User Targets</h2>

            <!-- Prospect Targets (Hidden for Senior Managers) -->
            <div id="prospect-targets-section" style="display: {{ $usesProspectlessTargets ? 'none' : 'block' }};">
                <div class="form-row">
                    <div class="form-group">
                        <label>Prospects to Extract</label>
                        <input type="number" name="target_prospects_extract" value="{{ old('target_prospects_extract', $target->target_prospects_extract) }}" min="0" placeholder="0">
                        <small style="color: #666;">Number of prospects the user should extract/create</small>
                    </div>

                    <div class="form-group">
                        <label>Prospects to Verify</label>
                        <input type="number" name="target_prospects_verified" value="{{ old('target_prospects_verified', $target->target_prospects_verified) }}" min="0" placeholder="0">
                        <small style="color: #666;">Number of prospects that should be verified/approved</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Calls to Make</label>
                    <input type="number" name="target_calls" value="{{ old('target_calls', $target->target_calls) }}" min="0" placeholder="0">
                    <small style="color: #666;">Number of phone calls the user should complete</small>
                </div>
            </div>

            <h2 class="section-title">Additional Targets (Optional)</h2>

            @if($isLeaderRole)
                <div class="target-preview-card">
                    <div class="target-preview-title">Final target = self target + live sum of team targets for this month.</div>
                    <div class="target-preview-grid">
                        <div class="target-preview-metric">
                            <strong>Visits</strong>
                            <span>Self: {{ $targetPreview['visits']['self'] }}</span>
                            <span>Team: {{ $targetPreview['visits']['team'] }}</span>
                            <span>Final: {{ $targetPreview['visits']['final'] }}</span>
                        </div>
                        <div class="target-preview-metric">
                            <strong>Meetings</strong>
                            <span>Self: {{ $targetPreview['meetings']['self'] }}</span>
                            <span>Team: {{ $targetPreview['meetings']['team'] }}</span>
                            <span>Final: {{ $targetPreview['meetings']['final'] }}</span>
                        </div>
                        <div class="target-preview-metric">
                            <strong>Closers</strong>
                            <span>Self: {{ $targetPreview['closers']['self'] }}</span>
                            <span>Team: {{ $targetPreview['closers']['team'] }}</span>
                            <span>Final: {{ $targetPreview['closers']['final'] }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="form-row">
                <div class="form-group">
                    <label>Site Visits</label>
                    <input type="number" name="target_visits" value="{{ old('target_visits', $target->target_visits) }}" min="0" placeholder="0">
                </div>

                <div class="form-group">
                    <label>Meetings</label>
                    <input type="number" name="target_meetings" value="{{ old('target_meetings', $target->target_meetings) }}" min="0" placeholder="0">
                </div>
            </div>

            <div class="form-group" id="closers-field" style="display: {{ ($target->user->isSalesManager() || $target->user->isSeniorManager() || $target->user->isSalesExecutive() || $target->user->isAssistantSalesManager()) ? 'block' : 'none' }};">
                <label>Closers</label>
                <input type="number" name="target_closers" value="{{ old('target_closers', $target->target_closers) }}" min="0" placeholder="0">
                <small style="color: #666;">For Senior Managers, Assistant Sales Managers, and Sales Executives</small>
            </div>

            <!-- Incentive Rates Section -->
            <h2 class="section-title">Incentive Rates (Optional)</h2>
            
            <div class="form-group" id="incentive-per-closer-field" style="display: {{ ($target->user->isSalesManager() || $target->user->isSeniorManager() || $target->user->isSalesExecutive() || $target->user->isAssistantSalesManager()) ? 'block' : 'none' }};">
                <label>Incentive per Closer (₹)</label>
                <input type="number" name="incentive_per_closer" id="incentive_per_closer" step="0.01" min="0" value="{{ old('incentive_per_closer', $target->incentive_per_closer ?? '') }}" placeholder="0.00">
                <small style="color: #666;">Incentive amount per closer for Managers and Sales Executives</small>
            </div>

            <div class="form-group" id="incentive-per-visit-field" style="display: {{ $target->user->isTelecaller() ? 'block' : 'none' }};">
                <label>Incentive per Visit (₹)</label>
                <input type="number" name="incentive_per_visit" id="incentive_per_visit" step="0.01" min="0" value="{{ old('incentive_per_visit', $target->incentive_per_visit ?? '') }}" placeholder="0.00">
                <small style="color: #666;">Incentive amount per site visit for Sales Executives</small>
            </div>

            <!-- Manager Target Calculation Logic (Only for Senior Managers) -->
            <div id="manager-logic-section" style="display: {{ $isLeaderRole ? 'block' : 'none' }};">
                <h2 class="section-title">Manager Target Calculation Logic</h2>
                
                <div class="form-group">
                    <label>Calculation Logic <span class="required">*</span></label>
                    <select name="manager_target_calculation_logic" id="manager_target_calculation_logic" {{ $isLeaderRole ? 'required' : '' }}>
                        <option value="">-- Select Logic --</option>
                        <option value="juniors_sum" {{ old('manager_target_calculation_logic', $target->manager_target_calculation_logic ?? '') == 'juniors_sum' ? 'selected' : '' }}>
                            Sum of Juniors' Targets (Logic 1)
                        </option>
                        <option value="individual_plus_team" {{ old('manager_target_calculation_logic', $target->manager_target_calculation_logic ?? '') == 'individual_plus_team' ? 'selected' : '' }}>
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
                    <select name="manager_junior_scope" id="manager_junior_scope" {{ $isLeaderRole ? 'required' : '' }}>
                        <option value="">-- Select Scope --</option>
                        <option value="executives_only" {{ old('manager_junior_scope', $target->manager_junior_scope ?? '') == 'executives_only' ? 'selected' : '' }}>
                            Assistant Sales Managers Only
                        </option>
                        <option value="executives_and_telecallers" {{ old('manager_junior_scope', $target->manager_junior_scope ?? '') == 'executives_and_telecallers' ? 'selected' : '' }}>
                            Assistant Sales Managers + Sales Executives
                        </option>
                    </select>
                    <small style="color: #666;">Select which junior roles should be included in the live team sum.</small>
                </div>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-brand-primary">Update Target</button>
                <a href="{{ route($targetsRouteBase . '.index', ['month' => $month]) }}" class="btn btn-brand-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
