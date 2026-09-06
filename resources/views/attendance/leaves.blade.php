@php
    $attendanceUsesSalesManagerLayout = auth()->check()
        && (auth()->user()->isSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isAssistantSalesManager());
@endphp

@extends($attendanceUsesSalesManagerLayout ? 'sales-manager.layout' : 'layouts.app')

@section('title', 'My Leave Requests')
@section('page-title', 'My Leave Requests')

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
        grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.68fr);
        gap: 1.5rem;
        align-items: start;
    }

    .att-request-card {
        background: #fff;
        border: 1px solid #dfe8e2;
        border-radius: 1.35rem;
        box-shadow: 0 10px 24px rgba(15, 45, 31, 0.05);
    }

    .att-request-main {
        padding: 1.4rem 1.5rem;
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
        font-size: 1.8rem;
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

    .att-request-side {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .att-balance-grid {
        display: grid;
        gap: 0.9rem;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }

    .att-balance-card {
        padding: 1rem;
        border-radius: 1rem;
        border: 1px solid #dfe8e2;
        background: linear-gradient(135deg,#fbfffc,#f4faf6);
    }

    .att-balance-name {
        font-size: 0.8rem;
        color: #6B7280;
        font-weight: 700;
    }

    .att-balance-value {
        margin-top: 0.4rem;
        font-size: 1.6rem;
        font-weight: 800;
        color: #163020;
    }

    .att-balance-meta {
        margin-top: 0.3rem;
        font-size: 0.75rem;
        color: #6B7280;
    }

    .att-history-card {
        padding: 1.25rem 1.5rem;
        overflow-x: auto;
    }

    .att-history-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: #163020;
        margin-bottom: 1rem;
    }

    .att-history-table {
        width: 100%;
        min-width: 720px;
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

    @media (max-width: 980px) {
        .att-request-shell {
            grid-template-columns: 1fr;
        }
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
            <h2 class="att-request-title">Apply Leave</h2>
            <div class="att-request-copy">Fill the leave details once. Your request history and current leave balance stay visible separately, so the form remains clean and easy to use.</div>

            <form method="POST" action="{{ route('attendance.leaves.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="att-request-grid">
                    <div class="att-request-field full">
                        <label class="att-request-label" for="leaveType">Leave Type</label>
                        <select id="leaveType" name="leave_type_id" class="att-request-input" required>
                            <option value="">Select leave type</option>
                            @foreach($leaveTypes as $leaveType)
                                <option value="{{ $leaveType->id }}" @selected(old('leave_type_id') == $leaveType->id)>{{ $leaveType->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="att-request-field">
                        <label class="att-request-label" for="leaveFromDate">From Date</label>
                        <input id="leaveFromDate" type="date" name="from_date" value="{{ old('from_date') }}" class="att-request-input" required>
                    </div>

                    <div class="att-request-field">
                        <label class="att-request-label" for="leaveToDate">To Date</label>
                        <input id="leaveToDate" type="date" name="to_date" value="{{ old('to_date') }}" class="att-request-input" required>
                    </div>

                    <div class="att-request-field full">
                        <label class="att-request-label" for="leaveDuration">Duration</label>
                        <select id="leaveDuration" name="duration_mode" class="att-request-input" required>
                            <option value="full_day" @selected(old('duration_mode', 'full_day') === 'full_day')>Full Day</option>
                            <option value="half_day_am" @selected(old('duration_mode') === 'half_day_am')>Half Day AM</option>
                            <option value="half_day_pm" @selected(old('duration_mode') === 'half_day_pm')>Half Day PM</option>
                        </select>
                    </div>

                    <div class="att-request-field full">
                        <label class="att-request-label" for="leaveReason">Reason</label>
                        <textarea id="leaveReason" name="reason" rows="4" class="att-request-input" placeholder="Write the reason for this leave">{{ old('reason') }}</textarea>
                    </div>

                    <div class="att-request-field full">
                        <label class="att-request-label" for="leaveAttachment">Attachment</label>
                        <input id="leaveAttachment" type="file" name="attachment" class="att-request-input">
                    </div>
                </div>

                <button type="submit" class="att-request-submit">Submit Leave Request</button>
            </form>
        </div>

        <div class="att-request-side">
            <div class="att-request-card att-history-card">
                <div class="att-history-title">Leave Balances</div>
                <div class="att-balance-grid">
                    @forelse($balances as $balance)
                        <div class="att-balance-card">
                            <div class="att-balance-name">{{ $balance->leaveType?->name }}</div>
                            <div class="att-balance-value">{{ number_format((float) $balance->remaining, 2) }}</div>
                            <div class="att-balance-meta">Remaining of {{ number_format((float) $balance->opening_balance + (float) $balance->credited, 2) }}</div>
                        </div>
                    @empty
                        <div class="att-empty">No leave balances are available yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="att-request-card att-history-card">
        <div class="att-history-title">My Leave Requests</div>
        <table class="att-history-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $request)
                    <tr>
                        <td>{{ $request->leaveType?->name }}</td>
                        <td>{{ $request->from_date->format('d M Y') }} to {{ $request->to_date->format('d M Y') }}</td>
                        <td>{{ number_format((float) $request->days_requested, 2) }}</td>
                        <td>
                            <span class="att-status {{ $request->status }}">
                                {{ ucfirst($request->status) }}
                            </span>
                        </td>
                        <td>{{ $request->reason ?: '--' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="att-empty">No leave requests yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
