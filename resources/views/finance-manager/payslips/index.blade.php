@extends('finance-manager.layout')

@section('title', 'Payslips')
@section('page_title', 'Payslip Desk')
@section('page_subtitle', 'Handle manual adjustments, freeze-aware payslip generation, and clean payroll export downloads from one finance workspace.')

@push('styles')
<style>
    .ps-grid { display: grid; gap: 22px; }
    .ps-card {
        background: #fff;
        border: 1px solid #ddd7ca;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 10px 24px rgba(10, 25, 16, 0.05);
    }
    .ps-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 18px;
    }
    .ps-header h2, .ps-header h3 {
        margin: 0;
        font-size: 28px;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #0b2e20;
    }
    .ps-header p {
        margin: 8px 0 0;
        color: #66756f;
        font-size: 14px;
    }
    .ps-toolbar {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 180px)) auto;
        gap: 12px;
        align-items: end;
    }
    .ps-field label {
        display: block;
        margin-bottom: 8px;
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #75847d;
        font-weight: 800;
    }
    .ps-input {
        width: 100%;
        padding: 13px 14px;
        border-radius: 16px;
        border: 1px solid #d8d2c6;
        background: #f8f6f0;
        color: #0b2e20;
        font-size: 15px;
        font-family: inherit;
    }
    .ps-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .ps-btn {
        padding: 13px 18px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    .ps-btn.primary { background: linear-gradient(135deg,#083725,#0f5b42); color:#fff; }
    .ps-btn.success { background: linear-gradient(135deg,#166534,#16a34a); color:#fff; }
    .ps-btn.soft { background:#fff; color:#0f5b42; border:1px solid #d8d2c6; }
    .ps-btn.danger { background: linear-gradient(135deg,#b91c1c,#ef4444); color:#fff; }
    .ps-top-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .ps-form-grid {
        display: grid;
        gap: 12px;
    }
    .ps-freeze-note {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 800;
        background: #fff7ed;
        color: #9a3412;
    }
    .ps-freeze-note.frozen { background:#ecfdf5; color:#166534; }
    .ps-table-wrap {
        overflow-x: auto;
        border: 1px solid #e8e2d7;
        border-radius: 20px;
    }
    .ps-table {
        width: 100%;
        min-width: 860px;
        border-collapse: collapse;
    }
    .ps-table thead th {
        background: #f8f6f0;
        color: #75847d;
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        font-weight: 800;
        text-align: left;
        padding: 14px 16px;
        border-bottom: 1px solid #e8e2d7;
    }
    .ps-table tbody td {
        padding: 16px;
        border-bottom: 1px solid #f0ece3;
        font-size: 14px;
        color: #153528;
        vertical-align: top;
    }
    .ps-table tbody tr:last-child td { border-bottom: 0; }
    @media (max-width: 1100px) {
        .ps-toolbar, .ps-top-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 700px) {
        .ps-card { padding: 18px; border-radius: 20px; }
        .ps-header h2, .ps-header h3 { font-size: 24px; }
        .ps-actions { width: 100%; }
        .ps-btn { width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="ps-grid">
    <section class="ps-card">
        <div class="ps-header">
            <div>
                <h2>Payslip Desk</h2>
                <p>Pick a payroll month, export packs, record manual adjustments, and generate final payslips after freeze.</p>
            </div>
        </div>
        <form method="GET" class="ps-toolbar">
            <div class="ps-field">
                <label>Year</label>
                <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="ps-input">
            </div>
            <div class="ps-field">
                <label>Month</label>
                <input type="number" name="month" value="{{ $month }}" min="1" max="12" class="ps-input">
            </div>
            <div class="ps-actions">
                <button type="submit" class="ps-btn primary"><i class="fas fa-rotate"></i> Refresh</button>
                <a href="{{ route('finance-manager.payroll.export-rich', ['year' => $year, 'month' => $month, 'format' => 'xlsx']) }}" class="ps-btn soft"><i class="fas fa-file-excel"></i> Payroll Excel</a>
                <a href="{{ route('finance-manager.payroll.export-rich', ['year' => $year, 'month' => $month, 'format' => 'pdf']) }}" class="ps-btn success"><i class="fas fa-file-pdf"></i> Payroll PDF</a>
            </div>
        </form>
    </section>

    <section class="ps-top-grid">
        <div class="ps-card">
            <div class="ps-header">
                <div>
                    <h3>Manual Adjustment</h3>
                    <p>Add one-off earning or deduction items before final payslip generation.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('finance-manager.payslips.adjustments.store') }}" class="ps-form-grid">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <select name="user_id" class="ps-input" required>
                    <option value="">Select user</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <select name="payroll_deduction_head_id" class="ps-input">
                    <option value="">Select head</option>
                    @foreach($heads as $head)
                        <option value="{{ $head->id }}">{{ $head->name }}</option>
                    @endforeach
                </select>
                <select name="type" class="ps-input">
                    <option value="deduction">Deduction</option>
                    <option value="earning">Earning</option>
                </select>
                <input type="text" name="label" placeholder="Label" class="ps-input" required>
                <input type="number" step="0.01" name="amount" placeholder="Amount" class="ps-input" required>
                <textarea name="remarks" placeholder="Remarks" class="ps-input" style="min-height: 110px;"></textarea>
                <button type="submit" class="ps-btn primary"><i class="fas fa-floppy-disk"></i> Save Adjustment</button>
            </form>
        </div>

        <div class="ps-card">
            <div class="ps-header">
                <div>
                    <h3>Generate Payslips</h3>
                    <p>Payslips generate only after payroll freeze is active for the selected month.</p>
                </div>
            </div>
            <div class="ps-form-grid">
                <div class="ps-freeze-note {{ $freeze ? 'frozen' : '' }}">
                    <i class="fas fa-lock"></i>
                    Freeze Status: {{ $freeze ? ucfirst($freeze->status) : 'Not frozen' }}
                </div>
                <form method="POST" action="{{ route('finance-manager.payslips.generate') }}">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button type="submit" class="ps-btn success" {{ !$freeze ? 'disabled' : '' }}><i class="fas fa-file-circle-check"></i> Generate Payslips</button>
                </form>
            </div>
        </div>
    </section>

    <section class="ps-card">
        <div class="ps-header">
            <div>
                <h3>Payment Queue</h3>
                <p>Only Admin-approved payslips appear here. Mark payment without changing salary calculation.</p>
            </div>
        </div>
        <div class="ps-table-wrap">
            <table class="ps-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th>Payment Details</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($paymentQueue as $payslip)
                        <tr>
                            <td>
                                <strong>{{ $payslip->user?->name }}</strong><br>
                                <span style="color:#72807a;">{{ sprintf('%02d/%d', $payslip->month, $payslip->year) }}</span>
                            </td>
                            <td><strong>Rs {{ number_format((float) $payslip->net_pay, 2) }}</strong></td>
                            <td>{{ ucwords(str_replace('_', ' ', $payslip->status)) }}</td>
                            <td>
                                <form id="pay-form-{{ $payslip->id }}" method="POST" action="{{ route('finance-manager.payslips.mark-paid', $payslip) }}" enctype="multipart/form-data" class="ps-form-grid" style="min-width:520px;">
                                    @csrf
                                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
                                        <input class="ps-input" name="paid_amount" type="number" step="0.01" value="{{ number_format((float) $payslip->net_pay, 2, '.', '') }}" required>
                                        <input class="ps-input" name="payment_mode" placeholder="Mode" required>
                                        <input class="ps-input" name="payment_reference" placeholder="UTR / Ref" required>
                                    </div>
                                    <textarea class="ps-input" name="finance_remark" placeholder="Remark if amount differs"></textarea>
                                    <input class="ps-input" type="file" name="payment_proof">
                                </form>
                            </td>
                            <td>
                                <button form="pay-form-{{ $payslip->id }}" class="ps-btn success">Mark Paid</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No Admin-approved payslips waiting for payment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="ps-card">
        <div class="ps-header">
            <div>
                <h3>Generated Payslips</h3>
                <p>Download completed payslips for the selected payroll month.</p>
            </div>
        </div>
        <div class="ps-table-wrap">
            <table class="ps-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Payslip No</th>
                        <th>Gross</th>
                        <th>Deductions</th>
                        <th>Net</th>
                        <th>Generated</th>
                        <th>Download</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payslips as $payslip)
                        <tr>
                            <td>{{ $payslip->user?->name }}</td>
                            <td>{{ $payslip->payslip_number }}</td>
                            <td>Rs {{ number_format((float) $payslip->gross_pay, 2) }}</td>
                            <td>Rs {{ number_format((float) $payslip->total_deductions, 2) }}</td>
                            <td><strong>Rs {{ number_format((float) $payslip->net_pay, 2) }}</strong></td>
                            <td>{{ optional($payslip->generated_at)->format('d M Y h:i A') }}</td>
                            <td><a href="{{ route('finance-manager.payslips.download', $payslip) }}" class="ps-btn primary" style="padding:10px 14px;">Download</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">No payslips generated yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
