@php
    $attendanceUsesSalesManagerLayout = auth()->check()
        && (auth()->user()->isSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isAssistantSalesManager());

    $latestPayslip = $payslips->first();
    $yearlyNetTotal = $payslips->sum(fn ($payslip) => (float) $payslip->net_pay);
    $availablePdfCount = $payslips->filter(fn ($payslip) => $payslip->canDownloadFinal() && str_ends_with((string) $payslip->pdf_path, '.pdf'))->count();
@endphp

@extends($attendanceUsesSalesManagerLayout ? 'sales-manager.layout' : 'layouts.app')
@section('title', 'My Payslips')
@section('page-title', 'My Payslips')

@section('content')
<style>
    .payslip-shell {
        max-width: 1220px;
        margin: 0 auto;
        display: grid;
        gap: 24px;
    }

    body.asm-shell .payslip-shell {
        width: 100%;
        max-width: none;
        margin: 0;
        gap: 16px;
    }

    body.asm-shell .payslip-hero,
    body.asm-shell .payslip-card {
        border-color: #dfe8e2;
        box-shadow: 0 12px 30px rgba(15, 45, 31, 0.05);
    }

    .payslip-hero {
        position: relative;
        overflow: hidden;
        border: 1px solid #d9e6df;
        border-radius: 28px;
        padding: 32px;
        background:
            radial-gradient(circle at top left, rgba(28, 99, 74, 0.16), transparent 40%),
            linear-gradient(135deg, #fbfdfc 0%, #f4faf7 48%, #eef6f1 100%);
        box-shadow: 0 18px 45px rgba(14, 62, 42, 0.08);
    }

    .payslip-hero::after {
        content: '';
        position: absolute;
        inset: auto -80px -90px auto;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: rgba(33, 92, 68, 0.08);
    }

    .payslip-hero-grid {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.9fr);
        gap: 24px;
        align-items: start;
    }

    .payslip-kicker {
        margin: 0 0 10px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.22em;
        text-transform: uppercase;
        color: #5f7f71;
    }

    .payslip-title {
        margin: 0;
        font-size: clamp(32px, 4.6vw, 52px);
        line-height: 0.95;
        font-weight: 800;
        color: #0e2f22;
        letter-spacing: -0.04em;
    }

    .payslip-copy {
        margin: 14px 0 0;
        max-width: 720px;
        font-size: 15px;
        line-height: 1.7;
        color: #5d6f66;
    }

    .payslip-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 20px;
    }

    .payslip-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 46px;
        padding: 0 16px;
        border-radius: 999px;
        border: 1px solid #dbe8e1;
        background: rgba(255, 255, 255, 0.92);
        color: #214b3a;
        font-size: 14px;
        font-weight: 700;
    }

    .payslip-chip span {
        color: #6f8378;
        font-weight: 600;
    }

    .payslip-summary-grid {
        display: grid;
        gap: 14px;
    }

    .payslip-summary-card {
        border-radius: 24px;
        padding: 20px 22px;
        border: 1px solid #dce8e2;
        background: rgba(255, 255, 255, 0.88);
        box-shadow: 0 16px 35px rgba(17, 63, 44, 0.06);
    }

    .payslip-summary-label {
        margin: 0 0 8px;
        font-size: 12px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #71877b;
        font-weight: 700;
    }

    .payslip-summary-value {
        margin: 0;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #102f23;
    }

    .payslip-summary-note {
        margin-top: 8px;
        font-size: 13px;
        color: #6d8176;
    }

    .payslip-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .payslip-metric {
        border-radius: 24px;
        border: 1px solid #dde7e2;
        background: #fff;
        padding: 20px 22px;
        box-shadow: 0 14px 32px rgba(18, 63, 45, 0.05);
    }

    .payslip-metric-label {
        margin: 0;
        font-size: 12px;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #6d8176;
        font-weight: 700;
    }

    .payslip-metric-value {
        margin: 10px 0 0;
        font-size: 34px;
        line-height: 1;
        font-weight: 800;
        color: #0f2f22;
        letter-spacing: -0.04em;
    }

    .payslip-metric-copy {
        margin-top: 8px;
        color: #73857b;
        font-size: 13px;
    }

    .payslip-list-card {
        border-radius: 28px;
        border: 1px solid #dde8e2;
        background: #fff;
        box-shadow: 0 18px 38px rgba(15, 58, 41, 0.06);
        overflow: hidden;
    }

    .payslip-list-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 24px 24px 18px;
        border-bottom: 1px solid #edf2ef;
    }

    .payslip-list-title {
        margin: 0;
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #123326;
    }

    .payslip-list-copy {
        margin: 8px 0 0;
        font-size: 14px;
        color: #6d8176;
    }

    .payslip-list-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        border-radius: 999px;
        background: #f4faf7;
        border: 1px solid #d9e6df;
        color: #205a44;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
    }

    .payslip-table-wrap {
        overflow-x: auto;
    }

    .payslip-table {
        width: 100%;
        min-width: 860px;
        border-collapse: collapse;
    }

    .payslip-table thead th {
        padding: 16px 24px;
        text-align: left;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #6b8176;
        border-bottom: 1px solid #edf1ef;
        background: #fbfdfc;
    }

    .payslip-table tbody td {
        padding: 18px 24px;
        border-bottom: 1px solid #eef3f0;
        font-size: 15px;
        color: #173427;
        vertical-align: middle;
    }

    .payslip-table tbody tr:last-child td {
        border-bottom: none;
    }

    .payslip-number {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .payslip-number strong {
        font-size: 15px;
        color: #153527;
    }

    .payslip-number span,
    .payslip-subtle {
        color: #74867d;
        font-size: 13px;
    }

    .payslip-money {
        font-weight: 800;
        white-space: nowrap;
    }

    .payslip-money.-net {
        color: #16573f;
    }

    .payslip-render-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 92px;
        padding: 10px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .payslip-render-badge.-pdf {
        background: #ecf8f1;
        color: #176345;
        border: 1px solid #cde7d8;
    }

    .payslip-render-badge.-html {
        background: #fff5eb;
        color: #b6631b;
        border: 1px solid #f4d9b6;
    }

    .payslip-download {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 46px;
        padding: 0 18px;
        border-radius: 14px;
        background: linear-gradient(135deg, #184f39 0%, #246a4c 100%);
        color: #fff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.04em;
        box-shadow: 0 12px 26px rgba(24, 79, 57, 0.22);
    }
    .payslip-download.-disabled {
        background: #e5e7eb;
        color: #6b7280;
        box-shadow: none;
        pointer-events: none;
    }
    .payslip-watermark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 9px 12px;
        border-radius: 999px;
        background: #fff7ed;
        color: #b45309;
        border: 1px dashed #fdba74;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .payslip-status {
        display: inline-flex;
        padding: 9px 12px;
        border-radius: 999px;
        background: #eef8f1;
        color: #176345;
        border: 1px solid #cde7d8;
        font-size: 12px;
        font-weight: 800;
    }
    .payslip-correction-form {
        display: grid;
        gap: 8px;
        min-width: 220px;
    }
    .payslip-correction-form textarea {
        min-height: 72px;
        border: 1px solid #dbe8e1;
        border-radius: 14px;
        padding: 10px 12px;
        font: inherit;
    }
    .payslip-correction-form button {
        border: none;
        border-radius: 12px;
        padding: 10px 12px;
        background: #b45309;
        color: #fff;
        font-weight: 800;
        cursor: pointer;
    }

    .payslip-empty {
        padding: 56px 24px;
        text-align: center;
        color: #71847a;
        font-size: 15px;
    }

    @media (max-width: 980px) {
        .payslip-hero,
        .payslip-list-card {
            border-radius: 24px;
        }

        .payslip-hero-grid,
        .payslip-metrics {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .payslip-shell {
            gap: 18px;
        }

        .payslip-hero {
            padding: 24px 18px;
        }

        .payslip-title {
            font-size: 36px;
        }

        .payslip-list-head {
            padding: 20px 18px 16px;
            flex-direction: column;
            align-items: flex-start;
        }

        .payslip-table thead th,
        .payslip-table tbody td {
            padding-left: 18px;
            padding-right: 18px;
        }
    }
</style>

<div class="payslip-shell">
    @include('attendance._flash')
    @include('attendance._nav')

    <section class="payslip-hero">
        <div class="payslip-hero-grid">
            <div>
                <p class="payslip-kicker">Payroll Center</p>
                <h1 class="payslip-title">Premium salary slips, ready to download.</h1>
                <p class="payslip-copy">
                    Check monthly gross pay, deductions, net salary, and download your latest payslip in one clean place.
                </p>
                <div class="payslip-chips">
                    <div class="payslip-chip"><span>Latest Slip</span>{{ $latestPayslip ? $latestPayslip->payslip_number : 'Not generated' }}</div>
                    <div class="payslip-chip"><span>Latest Month</span>{{ $latestPayslip ? sprintf('%02d/%d', $latestPayslip->month, $latestPayslip->year) : '--' }}</div>
                </div>
            </div>

            <div class="payslip-summary-grid">
                <div class="payslip-summary-card">
                    <p class="payslip-summary-label">Available payslips</p>
                    <p class="payslip-summary-value">{{ $payslips->count() }}</p>
                    <div class="payslip-summary-note">{{ $availablePdfCount }} ready as PDF download</div>
                </div>
                <div class="payslip-summary-card">
                    <p class="payslip-summary-label">Net salary tracked</p>
                    <p class="payslip-summary-value">Rs {{ number_format($yearlyNetTotal, 2) }}</p>
                    <div class="payslip-summary-note">Combined net value from the listed slips</div>
                </div>
            </div>
        </div>
    </section>

    <section class="payslip-metrics">
        <div class="payslip-metric">
            <p class="payslip-metric-label">Latest Gross</p>
            <p class="payslip-metric-value">{{ $latestPayslip ? 'Rs ' . number_format((float) $latestPayslip->gross_pay, 2) : '--' }}</p>
            <div class="payslip-metric-copy">Salary before deductions</div>
        </div>
        <div class="payslip-metric">
            <p class="payslip-metric-label">Latest Deductions</p>
            <p class="payslip-metric-value">{{ $latestPayslip ? 'Rs ' . number_format((float) $latestPayslip->total_deductions, 2) : '--' }}</p>
            <div class="payslip-metric-copy">Tax and policy deductions</div>
        </div>
        <div class="payslip-metric">
            <p class="payslip-metric-label">Latest Net</p>
            <p class="payslip-metric-value">{{ $latestPayslip ? 'Rs ' . number_format((float) $latestPayslip->net_pay, 2) : '--' }}</p>
            <div class="payslip-metric-copy">Take-home amount for the latest month</div>
        </div>
    </section>

    <section class="payslip-list-card">
        <div class="payslip-list-head">
            <div>
                <h2 class="payslip-list-title">All Payslips</h2>
                <p class="payslip-list-copy">Every generated salary slip is listed here with current download status.</p>
            </div>
                            <div class="payslip-list-badge">{{ $payslips->count() }} records</div>
        </div>

        <div class="payslip-table-wrap">
            <table class="payslip-table">
                <thead>
                    <tr>
                        <th>Payslip</th>
                        <th>Month</th>
                        <th>Gross</th>
                        <th>Deductions</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th>Preview</th>
                        <th>Download</th>
                        <th>Correction</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payslips as $payslip)
                        @php
                            $isPdf = str_ends_with((string) $payslip->pdf_path, '.pdf');
                        @endphp
                        <tr>
                            <td>
                                <div class="payslip-number">
                                    <strong>{{ $payslip->payslip_number }}</strong>
                                    <span>{{ $payslip->created_at?->format('d M Y') ?? 'Generated slip' }}</span>
                                </div>
                            </td>
                            <td>
                                <div>{{ sprintf('%02d', $payslip->month) }}/{{ $payslip->year }}</div>
                                <div class="payslip-subtle">Salary month</div>
                            </td>
                            <td class="payslip-money">Rs {{ number_format((float) $payslip->gross_pay, 2) }}</td>
                            <td class="payslip-money">Rs {{ number_format((float) $payslip->total_deductions, 2) }}</td>
                            <td class="payslip-money -net">Rs {{ number_format((float) $payslip->net_pay, 2) }}</td>
                            <td>
                                @if(!$payslip->canDownloadFinal())
                                    <span class="payslip-watermark">This is not final payslip</span>
                                @elseif($payslip->status === 'paid')
                                    <span class="payslip-status">Paid</span>
                                @else
                                    <span class="payslip-status">Approved, payment pending</span>
                                @endif
                            </td>
                            <td>
                                @if($payslip->verification_token)
                                    <a href="{{ route('payslips.verify', $payslip->verification_token) }}" target="_blank" class="payslip-download">
                                        View Preview
                                    </a>
                                @else
                                    <span class="payslip-subtle">Not ready</span>
                                @endif
                            </td>
                            <td>
                                @if($payslip->canDownloadFinal())
                                    <a href="{{ route('attendance.payslips.download', $payslip) }}" class="payslip-download">
                                        {{ $isPdf ? 'Download Final' : 'Open Slip' }}
                                    </a>
                                @else
                                    <span class="payslip-download -disabled">Preview Only</span>
                                @endif
                            </td>
                            <td>
                                @if($payslip->canEmployeeRequestCorrection())
                                    <form method="POST" action="{{ route('attendance.payslips.correction', $payslip) }}" class="payslip-correction-form">
                                        @csrf
                                        <textarea name="employee_correction_note" placeholder="What needs correction?" required></textarea>
                                        <button type="submit">Request Correction</button>
                                    </form>
                                @elseif($payslip->status === 'correction_requested')
                                    <span class="payslip-status" style="background:#fff7ed;color:#b45309;border-color:#fdba74;">Correction sent</span>
                                @else
                                    <span class="payslip-subtle">Closed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="payslip-empty">No payslips have been generated yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
