@php
    $attendanceUsesSalesManagerLayout = auth()->check()
        && (auth()->user()->isSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isAssistantSalesManager());

    $regularizationTypeLabels = [
        'missed_punch_in' => 'Missed Punch In',
        'missed_punch_out' => 'Missed Punch Out',
        'wrong_status' => 'Wrong Status',
        'manual_present' => 'Mark Present',
        'manual_half_day' => 'Mark Half Day',
    ];

    $statusLabels = [
        'present' => 'Present',
        'late' => 'Late',
        'half_day' => 'Half Day',
        'absent' => 'Absent',
    ];
@endphp

@extends($attendanceUsesSalesManagerLayout ? 'sales-manager.layout' : 'layouts.app')

@section('title', 'My Regularizations')
@section('page-title', 'My Regularizations')

@push('styles')
<style>
    .att-request-page {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        width: 100%;
        max-width: none;
        margin: 0;
    }

    .att-request-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1.5rem;
    }

    .att-request-card {
        background: #fff;
        border: 1px solid #dfe8e2;
        border-radius: 1.35rem;
        box-shadow: 0 12px 30px rgba(15, 45, 31, 0.05);
    }

    .att-request-main,
    .att-history-card {
        padding: 1.5rem;
    }

    .att-request-kicker {
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #6B7280;
    }

    .att-request-title {
        margin-top: 0.5rem;
        font-size: 2rem;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #163020;
    }

    .att-request-copy {
        margin-top: 0.75rem;
        font-size: 0.95rem;
        line-height: 1.6;
        color: #5E6B61;
    }

    .att-request-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .att-request-field {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .att-request-field.full {
        grid-column: 1 / -1;
    }

    .att-request-label {
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #64748B;
    }

    .att-request-input {
        width: 100%;
        min-height: 3rem;
        padding: 0.85rem 1rem;
        border: 1px solid #dfe8e2;
        border-radius: 0.9rem;
        background: #fbfdfb;
        color: #163020;
        font-size: 0.95rem;
        font-weight: 600;
        outline: none;
    }

    .att-request-input:focus {
        border-color: rgba(32, 90, 68, 0.45);
        box-shadow: 0 0 0 3px rgba(32, 90, 68, 0.08);
        background: #fff;
    }

    textarea.att-request-input {
        min-height: 7rem;
        resize: vertical;
    }

    .att-request-submit {
        margin-top: 1.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 3rem;
        padding: 0.85rem 1.3rem;
        border: none;
        border-radius: 999px;
        background: #205A44;
        color: #fff;
        font-size: 0.95rem;
        font-weight: 800;
        cursor: pointer;
    }

    .att-history-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: #163020;
        margin-bottom: 1rem;
    }

    .att-history-table {
        width: 100%;
        min-width: 860px;
        border-collapse: collapse;
    }

    .att-history-table th,
    .att-history-table td {
        padding: 0.95rem 0.8rem;
        border-bottom: 1px solid #EFE7DC;
        text-align: left;
        vertical-align: top;
    }

    .att-history-table th {
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #64748B;
    }

    .att-history-table td {
        font-size: 0.92rem;
        color: #1F2937;
    }

    .att-status {
        display: inline-flex;
        align-items: center;
        padding: 0.4rem 0.7rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .att-status.approved { background: #DCFCE7; color: #166534; }
    .att-status.rejected { background: #FEE2E2; color: #B91C1C; }
    .att-status.pending { background: #FEF3C7; color: #B45309; }

    .att-empty {
        padding: 1.25rem 0;
        color: #6B7280;
        font-size: 0.92rem;
    }

    @media (max-width: 640px) {
        .att-request-main,
        .att-history-card {
            padding: 1rem;
        }

        .att-request-title {
            font-size: 1.6rem;
        }

        .att-request-grid {
            grid-template-columns: 1fr;
        }

        .att-request-submit {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="att-request-page">
    @include('attendance._flash')
    @include('attendance._nav')

    <div class="att-request-shell">
        <div class="att-request-card att-request-main">
            <div class="att-request-kicker">Attendance Request</div>
            <h2 class="att-request-title">Raise Regularization</h2>
            <div class="att-request-copy">Use this form when punch in, punch out, status, or attendance marking needs correction. Fill the exact date, select the issue, and add supporting notes if needed.</div>

            <form method="POST" action="{{ route('attendance.regularizations.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="att-request-grid">
                    <div class="att-request-field">
                        <label class="att-request-label" for="regularizationDate">Attendance Date</label>
                        <input id="regularizationDate" type="date" name="attendance_date" value="{{ old('attendance_date') }}" class="att-request-input" required>
                    </div>

                    <div class="att-request-field">
                        <label class="att-request-label" for="regularizationType">Request Type</label>
                        <select id="regularizationType" name="request_type" class="att-request-input" required>
                            <option value="">Select request type</option>
                            @foreach($regularizationTypeLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('request_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="att-request-field">
                        <label class="att-request-label" for="requestedStatus">Requested Status</label>
                        <select id="requestedStatus" name="requested_status" class="att-request-input">
                            <option value="">Keep current status</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('requested_status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="att-request-field">
                        <label class="att-request-label" for="requestedInTime">Requested In Time</label>
                        <input id="requestedInTime" type="time" name="requested_in_time" value="{{ old('requested_in_time') }}" class="att-request-input">
                    </div>

                    <div class="att-request-field">
                        <label class="att-request-label" for="requestedOutTime">Requested Out Time</label>
                        <input id="requestedOutTime" type="time" name="requested_out_time" value="{{ old('requested_out_time') }}" class="att-request-input">
                    </div>

                    <div class="att-request-field full">
                        <label class="att-request-label" for="regularizationReason">Reason</label>
                        <textarea id="regularizationReason" name="reason" rows="4" class="att-request-input" placeholder="Explain what needs to be corrected">{{ old('reason') }}</textarea>
                    </div>

                    <div class="att-request-field full">
                        <label class="att-request-label" for="regularizationProof">Proof</label>
                        <input id="regularizationProof" type="file" name="proof" class="att-request-input">
                    </div>
                </div>

                <button type="submit" class="att-request-submit">Submit Regularization Request</button>
            </form>
        </div>
    </div>

    <div class="att-request-card att-history-card overflow-x-auto">
        <div class="att-history-title">My Regularization Requests</div>
        <table class="att-history-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Request Type</th>
                    <th>Requested Status</th>
                    <th>Requested Time</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $request)
                    <tr>
                        <td>{{ $request->attendance_date->format('d M Y') }}</td>
                        <td>{{ $regularizationTypeLabels[$request->request_type] ?? ucwords(str_replace('_', ' ', $request->request_type)) }}</td>
                        <td>{{ $request->requested_status ? ($statusLabels[$request->requested_status] ?? ucwords(str_replace('_', ' ', $request->requested_status))) : '--' }}</td>
                        <td>
                            In {{ $request->requested_in_time ?: '--' }}<br>
                            Out {{ $request->requested_out_time ?: '--' }}
                        </td>
                        <td>{{ $request->abuse_score_snapshot }}</td>
                        <td>
                            <span class="att-status {{ $request->status }}">
                                {{ ucfirst($request->status) }}
                            </span>
                        </td>
                        <td>{{ $request->reason ?: '--' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="att-empty">No regularization requests yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
