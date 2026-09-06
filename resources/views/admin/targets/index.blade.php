@extends('layouts.app')

@section('title', 'Target Management - Admin')
@section('page-title', 'Target Management')
@section('page-subtitle')
    @if(auth()->user()->isSalesHead())
        Set targets for Sales Executives and Senior Managers. Sales Executive targets are view-only.
    @else
        Set and manage monthly targets for Sales Executives and Senior Managers
    @endif
@endsection

@php
    $targetsRouteBase = auth()->user()->isHrManager()
        ? 'hr-manager.targets'
        : (auth()->user()->isCrm() ? 'crm.targets' : 'admin.targets');
@endphp

@section('header-actions')
    <a href="{{ route($targetsRouteBase . '.create') }}" class="btn btn-brand-primary">
        + Set New Target
    </a>
@endsection

@push('styles')
<style>
    /* Scoped styles so layout navigation/header is not affected */
    .targets-page .t-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.06); }
    .targets-page .t-filter { background: white; padding: 16px; border-radius: 12px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.06); display: flex; gap: 12px; align-items: center; }
    .targets-page .t-filter input, .targets-page .t-filter select { padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; }
    .targets-page table { width: 100%; border-collapse: collapse; }
    .targets-page th, .targets-page td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
    .targets-page th { background: #f8f9fa; font-weight: 600; color: #333; }
    .targets-page .t-alert { padding: 12px; border-radius: 8px; margin-bottom: 16px; }
    .targets-page .t-alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .targets-page .t-alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .targets-page .progress-bar { width: 100%; height: 18px; background: #e0e0e0; border-radius: 10px; overflow: hidden; margin-top: 6px; }
    .targets-page .progress-fill { height: 100%; background: #205A44; transition: width 0.3s; }
    .targets-page .progress-fill.warning { background: #ffc107; }
    .targets-page .progress-fill.danger { background: #dc3545; }
    .targets-page .badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block; }
    .targets-page .badge-success { background: #d4edda; color: #155724; }
    .targets-page .badge-warning { background: #fff3cd; color: #856404; }
    .targets-page .badge-danger { background: #f8d7da; color: #721c24; }
    .targets-page .badge-info { background: #d1ecf1; color: #0c5460; }
    .targets-page .t-filter-actions { display:flex; gap:12px; align-items:center; margin-left:auto; }
    .targets-page .copy-modal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.52); display:none; align-items:center; justify-content:center; padding:20px; z-index:9999; }
    .targets-page .copy-modal-backdrop.open { display:flex; }
    .targets-page .copy-modal { width:min(520px,100%); background:#fff; border-radius:18px; box-shadow:0 20px 50px rgba(15,23,42,.22); overflow:hidden; }
    .targets-page .copy-modal-head { padding:20px 24px 12px; border-bottom:1px solid #eef2f7; }
    .targets-page .copy-modal-title { margin:0; font-size:24px; font-weight:700; color:#0f172a; }
    .targets-page .copy-modal-subtitle { margin:8px 0 0; color:#475569; font-size:14px; line-height:1.5; }
    .targets-page .copy-modal-body { padding:20px 24px; }
    .targets-page .copy-month-summary { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:18px; }
    .targets-page .copy-month-box { border:1px solid #e2e8f0; border-radius:14px; padding:14px; background:#f8fafc; }
    .targets-page .copy-month-box strong { display:block; font-size:12px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin-bottom:6px; }
    .targets-page .copy-month-box span { font-size:18px; font-weight:700; color:#0f172a; }
    .targets-page .copy-target-count { margin:0 0 18px; padding:12px 14px; border-radius:12px; background:#ecfdf5; color:#166534; font-size:14px; font-weight:600; }
    .targets-page .copy-option { display:flex; gap:12px; align-items:flex-start; border:1px solid #dbe4ee; border-radius:14px; padding:14px; cursor:pointer; margin-bottom:12px; transition:border-color .2s, box-shadow .2s, background .2s; }
    .targets-page .copy-option:hover { border-color:#205A44; box-shadow:0 10px 24px rgba(32,90,68,.08); }
    .targets-page .copy-option input { margin-top:3px; }
    .targets-page .copy-option-title { display:block; font-size:15px; font-weight:700; color:#0f172a; margin-bottom:4px; }
    .targets-page .copy-option-note { color:#475569; font-size:13px; line-height:1.45; }
    .targets-page .copy-modal-actions { display:flex; justify-content:flex-end; gap:10px; padding:16px 24px 24px; }
</style>
@endpush

@section('content')
    <div class="targets-page">

        @if(session('success'))
            <div class="t-alert t-alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="t-alert t-alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="t-filter">
            <form method="GET" action="{{ route($targetsRouteBase . '.index') }}" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <label>Month:</label>
                <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()">
                <button type="submit" class="btn btn-brand-secondary" style="padding: 10px 14px; font-size: 14px;">Filter</button>
            </form>
            <div class="t-filter-actions">
                <button type="button" class="btn btn-brand-secondary" onclick="openCopyPreviousTargetsModal()">
                    Copy Previous Month Targets
                </button>
            </div>
        </div>

        <div class="t-card">
            @if($targets->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Month</th>
                            <th>Prospects Extract</th>
                            <th>Prospects Verified</th>
                            <th>Calls</th>
                            <th>Visits</th>
                            <th>Meetings</th>
                            <th>Closers</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($targets as $target)
                            @php
                                $user = $target->user;
                            @endphp
                            @if(!$target->exists && $user)
                                <tr>
                                    <td>
                                        <strong>{{ $user->name }}</strong><br>
                                        <small style="color: #666;">{{ $user->email }}</small><br>
                                        <span class="badge badge-info" style="margin-top: 4px; display: inline-block;">{{ $user->getDisplayRoleName() }}</span>
                                    </td>
                                    <td><strong>{{ $target->target_month->format('M Y') }}</strong></td>
                                    <td colspan="6" style="text-align:center;">
                                        <span class="badge badge-warning">Target Not Set</span>
                                    </td>
                                    <td>
                                        <a href="{{ route($targetsRouteBase . '.create', ['month' => $month, 'user_id' => $user->id]) }}" class="btn btn-brand-primary" style="padding: 8px 14px; font-size: 14px;">Set Target</a>
                                    </td>
                                </tr>
                                @continue
                            @endif
                            @php
                                $progress = $target->getProgressData();
                                $isManager = $user ? $user->isSalesManager() : false;
                                $isSeniorManager = $user ? $user->isSeniorManager() : false;
                                $isAssistantSalesManager = $user ? $user->isAssistantSalesManager() : false;
                                $isExecutive = $user ? $user->isSalesExecutive() : false;
                                $isTelecaller = $user ? $user->isTelecaller() : false;
                                $isCloserEligible = $isManager || $isAssistantSalesManager || $isExecutive;
                                $usesProspectlessTargets = $isManager || $isSeniorManager || $isAssistantSalesManager;
                                $visitsBreakdown = $target->getTargetBreakdown('visits');
                                $meetingsBreakdown = $target->getTargetBreakdown('meetings');
                                $closersBreakdown = $target->getTargetBreakdown('closers');
                            @endphp
                            <tr>
                                <td>
                                    @if($user)
                                        <strong>{{ $user->name }}</strong><br>
                                        <small style="color: #666;">{{ $user->email }}</small><br>
                                        <span class="badge {{ $isExecutive ? 'badge-info' : (($isManager || $isSeniorManager || $isAssistantSalesManager) ? 'badge-warning' : 'badge-success') }}" style="margin-top: 4px; display: inline-block;">
                                            {{ $user->getDisplayRoleName() }}
                                        </span>
                                        @if(($isManager || $isSeniorManager) && $target->manager_target_calculation_logic)
                                            <br>
                                            <small style="color: #16a34a; font-weight: 600; margin-top: 4px; display: inline-block;">
                                                @if($target->manager_target_calculation_logic === 'juniors_sum')
                                                    Logic 1: Juniors Sum
                                                @else
                                                    Logic 2: Individual + Team
                                                @endif
                                                @if($target->manager_junior_scope)
                                                    ({{ $target->manager_junior_scope === 'executives_only' ? 'Executives Only' : 'Executives + Sales Executives' }})
                                                @endif
                                            </small>
                                        @endif
                                    @else
                                        <strong style="color:#dc3545;">User Deleted</strong><br>
                                        <small style="color: #666;">N/A</small><br>
                                        <span class="badge badge-danger" style="margin-top: 4px; display: inline-block;">
                                            Missing User
                                        </span>
                                    @endif
                                </td>
                                <td><strong>{{ $target->target_month->format('M Y') }}</strong></td>
                                <td>
                                    @if(!$user)
                                        <span style="color: #999;">-</span>
                                    @elseif($usesProspectlessTargets)
                                        <span style="color: #999;">N/A</span>
                                    @else
                                        <div>{{ $progress['prospects_extract']['actual'] }} / {{ $target->target_prospects_extract }}</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill {{ $progress['prospects_extract']['percentage'] >= 100 ? '' : ($progress['prospects_extract']['percentage'] >= 50 ? 'warning' : 'danger') }}" 
                                                 style="width: {{ min(100, $progress['prospects_extract']['percentage']) }}%"></div>
                                        </div>
                                        <small style="color: #666;">{{ number_format($progress['prospects_extract']['percentage'], 1) }}%</small>
                                    @endif
                                </td>
                                <td>
                                    @if(!$user)
                                        <span style="color: #999;">-</span>
                                    @elseif($usesProspectlessTargets)
                                        <span style="color: #999;">N/A</span>
                                    @else
                                        <div>{{ $progress['prospects_verified']['actual'] }} / {{ $target->target_prospects_verified }}</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill {{ $progress['prospects_verified']['percentage'] >= 100 ? '' : ($progress['prospects_verified']['percentage'] >= 50 ? 'warning' : 'danger') }}" 
                                                 style="width: {{ min(100, $progress['prospects_verified']['percentage']) }}%"></div>
                                        </div>
                                        <small style="color: #666;">{{ number_format($progress['prospects_verified']['percentage'], 1) }}%</small>
                                    @endif
                                </td>
                                <td>
                                    @if(!$user)
                                        <span style="color: #999;">-</span>
                                    @elseif($usesProspectlessTargets)
                                        <span style="color: #999;">N/A</span>
                                    @else
                                        <div>{{ $progress['calls']['actual'] }} / {{ $target->target_calls }}</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill {{ $progress['calls']['percentage'] >= 100 ? '' : ($progress['calls']['percentage'] >= 50 ? 'warning' : 'danger') }}" 
                                                 style="width: {{ min(100, $progress['calls']['percentage']) }}%"></div>
                                        </div>
                                        <small style="color: #666;">{{ number_format($progress['calls']['percentage'], 1) }}%</small>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $visitsTarget = $visitsBreakdown['final'];
                                    @endphp
                                    @if($visitsTarget > 0)
                                        <div>{{ $progress['visits']['achieved'] }} / {{ $visitsTarget }}</div>
                                        @php
                                            $visitsPercentage = $visitsTarget > 0 ? min(100, round(($progress['visits']['achieved'] / $visitsTarget) * 100, 1)) : 0;
                                        @endphp
                                        <div class="progress-bar">
                                            <div class="progress-fill {{ $visitsPercentage >= 100 ? '' : ($visitsPercentage >= 50 ? 'warning' : 'danger') }}" 
                                                 style="width: {{ $visitsPercentage }}%"></div>
                                        </div>
                                        <small style="color: #666;">{{ number_format($visitsPercentage, 1) }}%</small>
                                        @if($visitsBreakdown['uses_team_sum'])
                                            <br><small style="color: #16a34a; font-size: 10px;">Self {{ $visitsBreakdown['self'] }} + Team {{ $visitsBreakdown['team'] }}</small>
                                        @endif
                                    @else
                                        <span style="color: #999;">-</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $meetingsTarget = $meetingsBreakdown['final'];
                                    @endphp
                                    @if($meetingsTarget > 0)
                                        <div>{{ $progress['meetings']['achieved'] }} / {{ $meetingsTarget }}</div>
                                        @php
                                            $meetingsPercentage = $meetingsTarget > 0 ? min(100, round(($progress['meetings']['achieved'] / $meetingsTarget) * 100, 1)) : 0;
                                        @endphp
                                        <div class="progress-bar">
                                            <div class="progress-fill {{ $meetingsPercentage >= 100 ? '' : ($meetingsPercentage >= 50 ? 'warning' : 'danger') }}" 
                                                 style="width: {{ $meetingsPercentage }}%"></div>
                                        </div>
                                        <small style="color: #666;">{{ number_format($meetingsPercentage, 1) }}%</small>
                                        @if($meetingsBreakdown['uses_team_sum'])
                                            <br><small style="color: #16a34a; font-size: 10px;">Self {{ $meetingsBreakdown['self'] }} + Team {{ $meetingsBreakdown['team'] }}</small>
                                        @endif
                                    @else
                                        <span style="color: #999;">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$user)
                                        <span style="color: #999;">-</span>
                                    @elseif($isManager || $isSeniorManager || $isCloserEligible)
                                        @if($closersBreakdown['final'] > 0)
                                            <div>{{ $progress['closers']['achieved'] }} / {{ $closersBreakdown['final'] }}</div>
                                            <div class="progress-bar">
                                                <div class="progress-fill {{ $progress['closers']['percentage'] >= 100 ? '' : ($progress['closers']['percentage'] >= 50 ? 'warning' : 'danger') }}" 
                                                     style="width: {{ min(100, $progress['closers']['percentage']) }}%"></div>
                                            </div>
                                            <small style="color: #666;">{{ number_format($progress['closers']['percentage'], 1) }}%</small>
                                            @if($closersBreakdown['uses_team_sum'])
                                                <br><small style="color: #16a34a; font-size: 10px;">Self {{ $closersBreakdown['self'] }} + Team {{ $closersBreakdown['team'] }}</small>
                                            @endif
                                        @else
                                            <div>{{ $progress['closers']['achieved'] }} / 0</div>
                                            <small style="color: #666;">Target not set</small>
                                        @endif
                                    @else
                                        <span style="color: #999;">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$user)
                                        <form method="POST" action="{{ route($targetsRouteBase . '.destroy', $target->id) }}" style="display: inline;" onsubmit="return confirm('User missing. Delete this target record?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding: 8px 14px; font-size: 14px;">Delete</button>
                                        </form>
                                    @elseif(auth()->user()->isSalesHead() && $isTelecaller)
                                        <span style="color: #6b7280; font-size: 14px;">
                                            <i class="fas fa-eye mr-2"></i>View Only
                                        </span>
                                    @else
                                        <a href="{{ route($targetsRouteBase . '.edit', $target->id) }}" class="btn btn-brand-secondary" style="padding: 8px 14px; font-size: 14px;">Edit</a>
                                        <form method="POST" action="{{ route($targetsRouteBase . '.destroy', $target->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this target?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding: 8px 14px; font-size: 14px;">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p style="text-align: center; color: #666; padding: 40px;">No eligible sales users found.</p>
            @endif
        </div>

        <div id="copyPreviousTargetsModal" class="copy-modal-backdrop" aria-hidden="true">
            <div class="copy-modal" role="dialog" aria-modal="true" aria-labelledby="copyPreviousTargetsTitle">
                <div class="copy-modal-head">
                    <h2 id="copyPreviousTargetsTitle" class="copy-modal-title">Copy Previous Month Targets</h2>
                    <p class="copy-modal-subtitle">Previous month ke targets current selected month me copy ho jayenge. Mode har baar choose karna hoga.</p>
                </div>
                <form method="POST" action="{{ route($targetsRouteBase . '.copy-previous') }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <div class="copy-modal-body">
                        <div class="copy-month-summary">
                            <div class="copy-month-box">
                                <strong>Source Month</strong>
                                <span>{{ $previousMonth->format('M Y') }}</span>
                            </div>
                            <div class="copy-month-box">
                                <strong>Destination Month</strong>
                                <span>{{ \Carbon\Carbon::parse($month . '-01')->format('M Y') }}</span>
                            </div>
                        </div>
                        <p class="copy-target-count">
                            Previous month targets found: {{ number_format($previousMonthTargetsCount) }} users
                        </p>

                        <label class="copy-option">
                            <input type="radio" name="mode" value="skip_existing" checked>
                            <span>
                                <span class="copy-option-title">Copy Only Missing Targets</span>
                                <span class="copy-option-note">Current month me jo users already present hain unhe untouched rakhega. Sirf missing users copy honge.</span>
                            </span>
                        </label>

                        <label class="copy-option">
                            <input type="radio" name="mode" value="overwrite_existing">
                            <span>
                                <span class="copy-option-title">Overwrite Existing Targets</span>
                                <span class="copy-option-note">Current month ke existing target rows bhi previous month values se replace ho jayenge.</span>
                            </span>
                        </label>
                    </div>
                    <div class="copy-modal-actions">
                        <button type="button" class="btn btn-brand-secondary" onclick="closeCopyPreviousTargetsModal()">Cancel</button>
                        <button type="submit" class="btn btn-brand-primary">Copy Targets</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function openCopyPreviousTargetsModal() {
        const modal = document.getElementById('copyPreviousTargetsModal');
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeCopyPreviousTargetsModal() {
        const modal = document.getElementById('copyPreviousTargetsModal');
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', function (event) {
        const modal = document.getElementById('copyPreviousTargetsModal');
        if (modal && event.target === modal) {
            closeCopyPreviousTargetsModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeCopyPreviousTargetsModal();
        }
    });
</script>
@endpush
