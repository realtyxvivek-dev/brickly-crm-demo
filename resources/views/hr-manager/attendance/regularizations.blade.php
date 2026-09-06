@extends('layouts.app')

@section('title', 'Regularization Requests')
@section('page-title', 'Regularization Requests')

@push('styles')
<style>
    .hr-regularization-page {
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: #10231a;
    }

    .hr-regularization-hero,
    .hr-regularization-group {
        background: #fff;
        border: 1px solid #e4ebe5;
        border-radius: 18px;
        box-shadow: 0 12px 32px rgba(16, 35, 26, .06);
    }

    .hr-regularization-hero {
        padding: 22px 26px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
    }

    .hr-regularization-eyebrow {
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: #64786d;
        margin-bottom: 6px;
    }

    .hr-regularization-title {
        margin: 0;
        font-size: 28px;
        line-height: 1.1;
        font-weight: 900;
        color: #031b10;
    }

    .hr-regularization-subtitle {
        margin-top: 6px;
        color: #52677a;
        font-size: 13px;
    }

    .hr-regularization-stats {
        display: flex;
        align-items: stretch;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .hr-regularization-stat {
        min-width: 118px;
        padding: 13px 16px;
        border-radius: 15px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        text-align: center;
        color: #334155;
    }

    .hr-regularization-stat.pending {
        background: #fff7ed;
        border-color: #fed7aa;
        color: #9a4b00;
    }

    .hr-regularization-stat.hidden {
        background: #f1f5f9;
        border-color: #dbe4ee;
    }

    .hr-regularization-stat strong {
        display: block;
        font-size: 24px;
        line-height: 1;
        color: #10231a;
    }

    .hr-regularization-stat.pending strong {
        color: #b45309;
    }

    .hr-regularization-stat span {
        display: block;
        margin-top: 6px;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .hr-regularization-note {
        margin-top: -4px;
        padding: 12px 16px;
        border: 1px solid #dbeafe;
        border-radius: 14px;
        background: #eff6ff;
        color: #31537a;
        font-size: 13px;
    }

    .hr-regularization-groups {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .hr-regularization-group {
        overflow: hidden;
    }

    .hr-regularization-group-head {
        padding: 16px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        background: #fbfdfc;
        border-bottom: 1px solid #edf2ef;
    }

    .hr-regularization-user {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .hr-regularization-avatar {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        background: #e9f7ef;
        color: #075332;
        font-weight: 900;
    }

    .hr-regularization-name {
        font-weight: 900;
        color: #061c12;
    }

    .hr-regularization-role {
        margin-top: 2px;
        color: #607386;
        font-size: 12px;
    }

    .hr-regularization-user-count {
        padding: 8px 12px;
        border-radius: 999px;
        background: #ecfdf5;
        border: 1px solid #bbf7d0;
        color: #047857;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .hr-regularization-row {
        display: grid;
        grid-template-columns: 130px 120px minmax(210px, .8fr) minmax(240px, 1.3fr) auto;
        align-items: center;
        gap: 14px;
        padding: 13px 18px;
        border-bottom: 1px solid #edf2ef;
    }

    .hr-regularization-row:last-child {
        border-bottom: 0;
    }

    .hr-regularization-row:hover {
        background: #fcfffd;
    }

    .hr-regularization-cell-label {
        display: block;
        margin-bottom: 3px;
        color: #718096;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .hr-regularization-date,
    .hr-regularization-submitted,
    .hr-regularization-type,
    .hr-regularization-time,
    .hr-regularization-reason {
        font-size: 13px;
        color: #0f172a;
    }

    .hr-regularization-date,
    .hr-regularization-submitted,
    .hr-regularization-time {
        font-weight: 850;
    }

    .hr-regularization-submitted {
        display: block;
        margin-top: 2px;
        color: #334155;
        font-size: 12px;
    }

    .hr-regularization-submission-label {
        margin-top: 10px;
    }

    .hr-regularization-context {
        display: inline-flex;
        margin-top: 6px;
        padding: 4px 8px;
        border-radius: 999px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 10px;
        font-weight: 900;
        white-space: nowrap;
    }

    .hr-regularization-context.backdated {
        border-color: #fed7aa;
        background: #fff7ed;
        color: #9a4b00;
    }

    .hr-regularization-type {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: #ecfdf5;
        border: 1px solid #bbf7d0;
        color: #047857;
        font-weight: 900;
    }

    .hr-regularization-score {
        display: inline-flex;
        margin-left: 6px;
        padding: 5px 9px;
        border-radius: 999px;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #b45309;
        font-size: 11px;
        font-weight: 900;
    }

    .hr-regularization-reason {
        color: #52677a;
        line-height: 1.35;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .hr-regularization-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }

    .hr-regularization-button {
        border: 0;
        border-radius: 10px;
        padding: 9px 13px;
        font: inherit;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        white-space: nowrap;
    }

    .hr-regularization-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, .12);
    }

    .hr-regularization-button.approve {
        background: #06633b;
        color: #fff;
    }

    .hr-regularization-button.reject {
        background: #e11d48;
        color: #fff;
    }

    .hr-regularization-empty {
        padding: 56px 24px;
        background: #fff;
        border: 1px solid #e4ebe5;
        border-radius: 18px;
        box-shadow: 0 12px 32px rgba(16, 35, 26, .06);
        text-align: center;
        color: #64748b;
    }

    .hr-regularization-empty strong {
        display: block;
        margin-bottom: 6px;
        color: #0f172a;
        font-size: 18px;
    }

    @media (max-width: 1100px) {
        .hr-regularization-row {
            grid-template-columns: 110px 110px minmax(190px, .8fr) minmax(220px, 1fr);
        }

        .hr-regularization-actions {
            grid-column: 1 / -1;
            justify-content: flex-start;
        }
    }

    @media (max-width: 760px) {
        .hr-regularization-hero,
        .hr-regularization-group-head {
            flex-direction: column;
            align-items: flex-start;
        }

        .hr-regularization-stats {
            justify-content: flex-start;
            width: 100%;
        }

        .hr-regularization-stat {
            flex: 1 1 120px;
        }

        .hr-regularization-row {
            grid-template-columns: 1fr;
            gap: 9px;
        }

        .hr-regularization-reason {
            -webkit-line-clamp: 3;
        }
    }
</style>
@endpush

@section('content')
@php
    $regularizationGroups = $pendingRegularizations
        ->groupBy(fn ($request) => $request->user_id ?: 'unknown')
        ->sortByDesc(fn ($items) => $items->count());
@endphp

<div class="hr-regularization-page">
    <section class="hr-regularization-hero">
        <div>
            <div class="hr-regularization-eyebrow">Approval Inbox</div>
            <h1 class="hr-regularization-title">Regularization Requests</h1>
            <div class="hr-regularization-subtitle">Approve punch corrections from one compact user-wise list.</div>
        </div>
        <div class="hr-regularization-stats">
            <div class="hr-regularization-stat pending">
                <strong>{{ $pendingRegularizations->count() }}</strong>
                <span>Tracked Pending</span>
            </div>
            <div class="hr-regularization-stat">
                <strong>{{ $totalPendingRegularizations ?? $pendingRegularizations->count() }}</strong>
                <span>Total Pending</span>
            </div>
            @if(($hiddenPendingRegularizations ?? 0) > 0)
                <div class="hr-regularization-stat hidden">
                    <strong>{{ $hiddenPendingRegularizations }}</strong>
                    <span>Hidden</span>
                </div>
            @endif
        </div>
    </section>

    @if(($hiddenPendingRegularizations ?? 0) > 0)
        <div class="hr-regularization-note">
            {{ $hiddenPendingRegularizations }} pending request(s) are hidden because their users are not in the active attendance tracking list for this period.
        </div>
    @endif

    @if($regularizationGroups->isNotEmpty())
        <section class="hr-regularization-groups">
            @foreach($regularizationGroups as $group)
                @php
                    $firstRequest = $group->first();
                    $user = $firstRequest?->user;
                    $initial = strtoupper(substr((string) $user?->name, 0, 1)) ?: 'U';
                @endphp
                <div class="hr-regularization-group">
                    <div class="hr-regularization-group-head">
                        <div class="hr-regularization-user">
                            <div class="hr-regularization-avatar">{{ $initial }}</div>
                            <div>
                                <div class="hr-regularization-name">{{ $user?->name ?: 'Unknown User' }}</div>
                                <div class="hr-regularization-role">{{ $user?->role?->name ?: 'No role assigned' }}</div>
                            </div>
                        </div>
                        <div class="hr-regularization-user-count">{{ $group->count() }} pending</div>
                    </div>

                    @foreach($group->sortByDesc('created_at') as $request)
                        @php
                            $requestType = ucwords(str_replace('_', ' ', (string) $request->request_type)) ?: 'Regularization';
                            $requestedStatus = $request->requested_status ?: 'Update';
                            $requestedIn = optional($request->requested_in_time)->format('h:i A') ?: '--';
                            $requestedOut = optional($request->requested_out_time)->format('h:i A') ?: '--';
                            $score = $request->abuse_score_snapshot;
                            $submittedAt = $request->created_at?->copy()->timezone(config('app.timezone'));
                            $attendanceDay = $request->attendance_date?->copy()->startOfDay();
                            $submittedDay = $submittedAt?->copy()->startOfDay();
                            $dayDifference = ($attendanceDay && $submittedDay) ? (int) $attendanceDay->diffInDays($submittedDay) : 0;
                            $submissionContext = $attendanceDay && $submittedDay && $submittedDay->gt($attendanceDay)
                                ? 'Backdated by ' . $dayDifference . ' day' . ($dayDifference === 1 ? '' : 's')
                                : ($attendanceDay && $submittedDay && $submittedDay->lt($attendanceDay) ? 'Submitted in advance' : 'Same Day');
                        @endphp
                        <article class="hr-regularization-row">
                            <div>
                                <span class="hr-regularization-cell-label">Attendance For</span>
                                <span class="hr-regularization-date">{{ optional($request->attendance_date)->format('d M Y') ?: 'No date' }}</span>
                                <span class="hr-regularization-cell-label hr-regularization-submission-label">Submitted At</span>
                                <span class="hr-regularization-submitted">{{ $submittedAt?->format('d M Y, h:i A') ?: '--' }}</span>
                                <span class="hr-regularization-context {{ str_starts_with($submissionContext, 'Backdated') ? 'backdated' : '' }}">{{ $submissionContext }}</span>
                            </div>
                            <div>
                                <span class="hr-regularization-cell-label">Type</span>
                                <span class="hr-regularization-type">{{ $requestType }}</span>
                            </div>
                            <div>
                                <span class="hr-regularization-cell-label">Requested</span>
                                <span class="hr-regularization-time">{{ $requestedStatus }} | In {{ $requestedIn }} | Out {{ $requestedOut }}</span>
                                @if(! is_null($score))
                                    <span class="hr-regularization-score">Score {{ $score }}</span>
                                @endif
                            </div>
                            <div>
                                <span class="hr-regularization-cell-label">Reason</span>
                                <div class="hr-regularization-reason" title="{{ $request->reason ?: 'No reason added.' }}">{{ $request->reason ?: 'No reason added.' }}</div>
                            </div>
                            <div class="hr-regularization-actions">
                                <form method="POST" action="{{ route('hr-manager.attendance.regularizations.approve', $request) }}">
                                    @csrf
                                    <button type="submit" class="hr-regularization-button approve">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('hr-manager.attendance.regularizations.reject', $request) }}">
                                    @csrf
                                    <button type="submit" class="hr-regularization-button reject">Reject</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endforeach
        </section>
    @else
        <div class="hr-regularization-empty">
            <strong>No pending regularization requests</strong>
            All tracked attendance regularization requests are clear.
        </div>
    @endif
</div>
@endsection
