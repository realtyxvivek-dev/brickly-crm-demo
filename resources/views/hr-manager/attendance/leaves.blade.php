@extends('layouts.app')

@section('title', 'Leave Requests')
@section('page-title', 'Leave Requests')

@push('styles')
<style>
    .hr-leave {
        --green-900: #0d4a29;
        --green-700: #166534;
        --green-50: #eef8f1;
        --ink: #0f172a;
        --muted: #64748b;
        --line: #e4ebe6;
        --amber-50: #fff7ed;
        --amber-700: #b45309;
        --red-50: #fff1f2;
        --red-700: #be123c;
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: var(--ink);
        font-family: "Instrument Sans", system-ui, sans-serif;
    }

    .hr-leave * {
        box-sizing: border-box;
    }

    .hr-leave-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 22px 24px;
        border: 1px solid var(--line);
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
    }

    .hr-leave-kicker {
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hr-leave-title {
        margin-top: 6px;
        font-size: 26px;
        line-height: 1.1;
        font-weight: 900;
        letter-spacing: -0.04em;
    }

    .hr-leave-copy {
        margin-top: 6px;
        color: var(--muted);
        font-size: 14px;
    }

    .hr-leave-count {
        min-width: 130px;
        padding: 12px 16px;
        border-radius: 18px;
        background: var(--amber-50);
        border: 1px solid #fed7aa;
        text-align: center;
    }

    .hr-leave-count strong {
        display: block;
        color: var(--amber-700);
        font-size: 26px;
        line-height: 1;
    }

    .hr-leave-count span {
        display: block;
        margin-top: 4px;
        color: var(--amber-700);
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .hr-leave-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .hr-leave-card {
        display: grid;
        grid-template-columns: minmax(220px, 1.1fr) minmax(280px, 1.6fr) minmax(170px, auto);
        gap: 18px;
        align-items: center;
        padding: 20px 22px;
        border: 1px solid var(--line);
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.045);
    }

    .hr-leave-user {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .hr-leave-avatar {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: var(--green-50);
        color: var(--green-900);
        font-weight: 900;
        flex: 0 0 auto;
    }

    .hr-leave-name {
        font-weight: 900;
        font-size: 15px;
    }

    .hr-leave-role {
        margin-top: 3px;
        color: var(--muted);
        font-size: 12px;
    }

    .hr-leave-detail {
        min-width: 0;
    }

    .hr-leave-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 10px;
    }

    .hr-leave-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .hr-leave-badge.type {
        background: var(--green-50);
        color: var(--green-900);
        border: 1px solid #cfe9d7;
    }

    .hr-leave-badge.pending {
        background: var(--amber-50);
        color: var(--amber-700);
        border: 1px solid #fed7aa;
    }

    .hr-leave-dates {
        font-size: 15px;
        font-weight: 800;
    }

    .hr-leave-reason {
        margin-top: 8px;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.45;
        max-width: 760px;
    }

    .hr-leave-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }

    .hr-leave-btn {
        min-height: 40px;
        padding: 0 16px;
        border-radius: 12px;
        border: 0;
        color: #fff;
        font: inherit;
        font-size: 13px;
        font-weight: 900;
        cursor: pointer;
    }

    .hr-leave-btn.approve {
        background: var(--green-700);
    }

    .hr-leave-btn.reject {
        background: var(--red-700);
    }

    .hr-leave-empty {
        padding: 46px 24px;
        border: 1px dashed #cbd5e1;
        border-radius: 22px;
        background: #fff;
        text-align: center;
        color: var(--muted);
        font-weight: 700;
    }

    @media (max-width: 980px) {
        .hr-leave-card {
            grid-template-columns: 1fr;
            align-items: stretch;
        }

        .hr-leave-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 640px) {
        .hr-leave-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .hr-leave-count {
            width: 100%;
            text-align: left;
        }

        .hr-leave-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .hr-leave-btn {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="hr-leave">
    @include('hr-manager.attendance._nav')

    <div class="hr-leave-head">
        <div>
            <div class="hr-leave-kicker">Approval Inbox</div>
            <div class="hr-leave-title">Leave Requests</div>
            <div class="hr-leave-copy">Review pending leave requests and approve or reject them from one clean list.</div>
        </div>
        <div class="hr-leave-count">
            <strong>{{ $pendingLeaves->count() }}</strong>
            <span>Pending</span>
        </div>
    </div>

    <div class="hr-leave-list">
        @forelse($pendingLeaves as $leave)
            <div class="hr-leave-card">
                <div class="hr-leave-user">
                    <div class="hr-leave-avatar">{{ strtoupper(mb_substr($leave->user?->name ?: 'U', 0, 1)) }}</div>
                    <div>
                        <div class="hr-leave-name">{{ $leave->user?->name ?: 'Unknown User' }}</div>
                        <div class="hr-leave-role">{{ $leave->user?->role?->name ?: 'Employee' }}</div>
                    </div>
                </div>

                <div class="hr-leave-detail">
                    <div class="hr-leave-badges">
                        <span class="hr-leave-badge type">{{ $leave->leaveType?->name ?? 'Leave' }}</span>
                        <span class="hr-leave-badge pending">{{ number_format((float) $leave->days_requested, 2) }} day(s)</span>
                        <span class="hr-leave-badge pending">Pending</span>
                    </div>
                    <div class="hr-leave-dates">
                        {{ $leave->from_date->format('d M Y') }} to {{ $leave->to_date->format('d M Y') }}
                    </div>
                    <div class="hr-leave-reason">{{ $leave->reason ?: 'No reason added.' }}</div>
                </div>

                <div class="hr-leave-actions">
                    <form method="POST" action="{{ route('hr-manager.attendance.leaves.approve', $leave) }}">
                        @csrf
                        <button type="submit" class="hr-leave-btn approve">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('hr-manager.attendance.leaves.reject', $leave) }}">
                        @csrf
                        <button type="submit" class="hr-leave-btn reject">Reject</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="hr-leave-empty">No pending leave requests right now.</div>
        @endforelse
    </div>
</div>
@endsection
