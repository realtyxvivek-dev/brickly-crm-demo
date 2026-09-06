@extends('layouts.app')

@section('title', 'Attendance Register')
@section('page-title', 'Attendance Register')

@push('styles')
<style>
    .hr-register {
        --line: rgba(15, 23, 42, 0.08);
        --line-strong: rgba(15, 23, 42, 0.12);
        --text: #0f172a;
        --muted: #64748b;
        --green: #0f5c3f;
        --green-soft: #e7f6ee;
        --amber: #a16207;
        --amber-soft: #fff4d6;
        --red: #c2410c;
        --red-soft: #fff1eb;
        --violet: #6d49cb;
        --violet-soft: #f3eeff;
        --slate: #475569;
        --slate-soft: #f1f5f9;
        --shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        display: flex;
        flex-direction: column;
        gap: 18px;
        color: var(--text);
    }

    .hr-register * { box-sizing: border-box; }
    .hr-register-card {
        background: #fff;
        border: 1px solid var(--line);
        box-shadow: var(--shadow);
        border-radius: 24px;
    }

    .hr-register-hero {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        padding: 24px 26px;
    }

    .hr-register-kicker {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hr-register-heading {
        margin-top: 8px;
        font-size: 34px;
        line-height: 1;
        letter-spacing: -0.05em;
        font-weight: 800;
        color: var(--text);
    }

    .hr-register-subtitle {
        margin-top: 10px;
        font-size: 14px;
        font-weight: 500;
        color: var(--muted);
    }

    .hr-register-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        min-width: 420px;
    }

    .hr-register-summary-card {
        padding: 14px 16px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #f8fafc;
    }

    .hr-register-summary-label {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hr-register-summary-value {
        display: block;
        margin-top: 8px;
        font-size: 26px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: var(--text);
    }

    .hr-register-periods {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding: 0 26px 24px;
        border-top: 1px solid var(--line);
        margin-top: 2px;
        padding-top: 20px;
    }

    .hr-period-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 118px;
        padding: 11px 18px;
        border-radius: 999px;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--muted);
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        transition: all 0.18s ease;
    }

    .hr-period-link:hover {
        border-color: rgba(15, 92, 63, 0.35);
        color: var(--green);
        background: #f8fcfa;
    }

    .hr-period-link.active {
        border-color: var(--green);
        background: var(--green);
        color: #fff;
        box-shadow: 0 10px 22px rgba(15, 92, 63, 0.16);
    }

    .hr-register-table-wrap {
        overflow-x: auto;
    }

    .hr-register-table {
        width: 100%;
        min-width: 860px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .hr-register-table th,
    .hr-register-table td {
        padding: 16px 18px;
        border-bottom: 1px solid rgba(22, 47, 32, 0.08);
        text-align: left;
        white-space: nowrap;
    }

    .hr-register-table td {
        font-size: 14px;
        color: var(--text);
    }

    .hr-register-table th {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--muted);
        background: #f8fafc;
    }

    .hr-register-table tr:last-child td {
        border-bottom: none;
    }

    .hr-register-table tbody tr:hover td {
        background: #fbfdfc;
    }

    .hr-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .hr-badge-present { background: var(--green-soft); color: var(--green); }
    .hr-badge-late { background: var(--amber-soft); color: var(--amber); }
    .hr-badge-half-day { background: var(--violet-soft); color: var(--violet); }
    .hr-badge-absent,
    .hr-badge-missing-out { background: var(--red-soft); color: var(--red); }
    .hr-badge-default { background: var(--slate-soft); color: var(--slate); }

    .hr-mobile-cards {
        display: none;
        gap: 12px;
    }

    .hr-mobile-card {
        border-radius: 22px;
        padding: 16px;
        background: #fff;
        border: 1px solid var(--line);
        box-shadow: 0 16px 34px rgba(24, 49, 38, 0.06);
    }

    .hr-register-name {
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
    }

    .hr-register-role {
        margin-top: 4px;
        font-size: 12px;
        font-weight: 500;
        color: var(--muted);
    }

    .hr-register-empty {
        padding: 28px;
        text-align: center;
        font-size: 14px;
        font-weight: 600;
        color: var(--muted);
    }

    @media (max-width: 768px) {
        .hr-register {
            padding: 12px;
            border-radius: 22px;
        }

        .hr-register-hero {
            padding: 18px 18px 16px;
        }

        .hr-register-heading {
            font-size: 28px;
        }

        .hr-register-subtitle {
            font-size: 14px;
        }

        .hr-register-summary {
            width: 100%;
            min-width: 0;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .hr-register-periods {
            padding: 0 18px 18px;
            padding-top: 16px;
        }

        .hr-period-link {
            min-width: calc(33.333% - 6px);
            flex: 1 1 0;
            padding: 11px 10px;
            font-size: 12px;
        }

        .hr-register-table-wrap {
            display: none;
        }

        .hr-mobile-cards {
            display: grid;
        }
    }
</style>
@endpush

@php
    $periodLinks = [
        'today' => 'Today',
        'week' => 'This Week',
        'month' => 'This Month',
    ];

    $periodTitle = $periodLinks[$period] ?? 'Today';
@endphp

@section('content')
<div class="hr-register">
    @include('hr-manager.attendance._nav')

    <div class="hr-register-card">
        <div class="hr-register-hero">
            <div>
                <div class="hr-register-kicker">Attendance Register</div>
                <h1 class="hr-register-heading">Daily Attendance Log</h1>
                <p class="hr-register-subtitle">
                    {{ $periodTitle }}
                    @if($period === 'today')
                        for {{ $date->format('d M Y') }}
                    @else
                        from {{ $startDate->format('d M') }} to {{ $endDate->format('d M Y') }}
                    @endif
                </p>
            </div>

            <div class="hr-register-summary">
                <div class="hr-register-summary-card">
                    <div class="hr-register-summary-label">Total Records</div>
                    <strong class="hr-register-summary-value">{{ $records->count() }}</strong>
                </div>
                <div class="hr-register-summary-card">
                    <div class="hr-register-summary-label">Present / Late</div>
                    <strong class="hr-register-summary-value">{{ $records->whereIn('status', ['present', 'late'])->count() }}</strong>
                </div>
                <div class="hr-register-summary-card">
                    <div class="hr-register-summary-label">Missing Out</div>
                    <strong class="hr-register-summary-value">{{ $records->where('has_missing_punch_out', true)->count() }}</strong>
                </div>
            </div>
        </div>

        <div class="hr-register-periods">
            @foreach($periodLinks as $key => $label)
                <a href="{{ route('hr-manager.attendance.index', ['period' => $key]) }}" class="hr-period-link {{ $period === $key ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="hr-register-card" style="overflow:hidden; border-radius:28px;">
        <div class="hr-register-table-wrap">
            <table class="hr-register-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Date</th>
                        <th>Punch In</th>
                        <th>Punch Out</th>
                        <th>Status</th>
                        <th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        @php
                            $statusText = $record->has_missing_punch_out ? 'Missing Out' : str_replace('_', ' ', $record->status ?: 'absent');
                            $statusClass = match (true) {
                                $record->has_missing_punch_out => 'hr-badge-missing-out',
                                $record->status === 'present' => 'hr-badge-present',
                                $record->status === 'late' => 'hr-badge-late',
                                $record->status === 'half_day' => 'hr-badge-half-day',
                                $record->status === 'absent' => 'hr-badge-absent',
                                default => 'hr-badge-default',
                            };
                            $hours = $record->worked_minutes !== null
                                ? floor($record->worked_minutes / 60) . 'h ' . str_pad((string) ($record->worked_minutes % 60), 2, '0', STR_PAD_LEFT) . 'm'
                                : '—';
                            $outsideLabel = match($record->outside_punch_status) {
                                'approved_permission' => 'Outside · Allowed window',
                                'approved_request' => 'Outside · HR approved',
                                default => null,
                            };
                            $outsideDistance = $record->outside_punch_distance_meters === null
                                ? null
                                : ($record->outside_punch_distance_meters >= 1000
                                    ? number_format($record->outside_punch_distance_meters / 1000, 2) . ' km'
                                    : number_format($record->outside_punch_distance_meters, 0) . ' m');
                        @endphp
                        <tr>
                            <td>
                                <div class="hr-register-name">{{ $record->user?->name ?: 'Unknown User' }}</div>
                                <div class="hr-register-role">{{ $record->user?->role?->name ?: 'Employee' }}</div>
                            </td>
                            <td>{{ optional($record->attendance_date)->format('d M Y') ?: '—' }}</td>
                            <td>{{ optional($record->first_punch_in_at)->format('h:i A') ?: '—' }}</td>
                            <td>{{ optional($record->last_punch_out_at)->format('h:i A') ?: '—' }}</td>
                            <td>
                                <span class="hr-badge {{ $statusClass }}">{{ ucfirst($statusText) }}</span>
                                @if($outsideLabel)
                                    <div class="hr-register-role" style="margin-top:8px;">{{ $outsideLabel }}{{ $outsideDistance ? ' · ' . $outsideDistance : '' }}</div>
                                @endif
                            </td>
                            <td style="font-weight:700;">{{ $hours }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="hr-register-empty">No attendance records found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="hr-mobile-cards" style="padding:12px;">
            @forelse($records as $record)
                @php
                    $statusText = $record->has_missing_punch_out ? 'Missing Out' : str_replace('_', ' ', $record->status ?: 'absent');
                    $statusClass = match (true) {
                        $record->has_missing_punch_out => 'hr-badge-missing-out',
                        $record->status === 'present' => 'hr-badge-present',
                        $record->status === 'late' => 'hr-badge-late',
                        $record->status === 'half_day' => 'hr-badge-half-day',
                        $record->status === 'absent' => 'hr-badge-absent',
                        default => 'hr-badge-default',
                    };
                    $hours = $record->worked_minutes !== null
                        ? floor($record->worked_minutes / 60) . 'h ' . str_pad((string) ($record->worked_minutes % 60), 2, '0', STR_PAD_LEFT) . 'm'
                        : '—';
                    $outsideLabel = match($record->outside_punch_status) {
                        'approved_permission' => 'Outside · Allowed window',
                        'approved_request' => 'Outside · HR approved',
                        default => null,
                    };
                    $outsideDistance = $record->outside_punch_distance_meters === null
                        ? null
                        : ($record->outside_punch_distance_meters >= 1000
                            ? number_format($record->outside_punch_distance_meters / 1000, 2) . ' km'
                            : number_format($record->outside_punch_distance_meters, 0) . ' m');
                @endphp
                <div class="hr-mobile-card">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
                        <div>
                            <div class="hr-register-name">{{ $record->user?->name ?: 'Unknown User' }}</div>
                            <div class="hr-register-role">{{ optional($record->attendance_date)->format('d M Y') ?: '—' }}</div>
                        </div>
                        <span class="hr-badge {{ $statusClass }}">{{ ucfirst($statusText) }}</span>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-top:16px;">
                        <div>
                            <div class="hr-register-kicker" style="font-size:10px;">In</div>
                            <div class="hr-register-name" style="font-size:14px; margin-top:6px;">{{ optional($record->first_punch_in_at)->format('h:i A') ?: '—' }}</div>
                        </div>
                        <div>
                            <div class="hr-register-kicker" style="font-size:10px;">Out</div>
                            <div class="hr-register-name" style="font-size:14px; margin-top:6px;">{{ optional($record->last_punch_out_at)->format('h:i A') ?: '—' }}</div>
                        </div>
                        <div>
                            <div class="hr-register-kicker" style="font-size:10px;">Hours</div>
                            <div class="hr-register-name" style="font-size:14px; margin-top:6px;">{{ $hours }}</div>
                        </div>
                    </div>
                    @if($outsideLabel)
                        <div class="hr-register-role" style="margin-top:14px;">{{ $outsideLabel }}{{ $outsideDistance ? ' · ' . $outsideDistance : '' }}</div>
                    @endif
                </div>
            @empty
                <div class="hr-mobile-card hr-register-empty">No attendance records found for this period.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
