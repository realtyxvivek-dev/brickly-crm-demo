@extends('layouts.app')

@section('title', 'Mail Center - ' . brand_name())

@section('content')
<style>
    .mail-page { padding: 22px; background: #f5f7f4; min-height: calc(100vh - 80px); }
    .mail-hero, .mail-card { background: #fff; border: 1px solid #dbe6df; border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, .06); }
    .mail-hero { padding: 26px; display: flex; justify-content: space-between; gap: 18px; align-items: center; }
    .mail-eyebrow { font-size: 12px; letter-spacing: 2px; color: #5b6f66; text-transform: uppercase; font-weight: 800; }
    .mail-title { margin: 6px 0 4px; font-size: 34px; line-height: 1.1; color: #052e1b; font-weight: 900; }
    .mail-sub { color: #53675f; font-size: 15px; }
    .mail-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid #cfe0d6; border-radius: 12px; padding: 11px 15px; font-weight: 800; text-decoration: none; color: #052e1b; background: #fff; }
    .mail-btn.primary { background: #0b6b3f; color: #fff; border-color: #0b6b3f; }
    .mail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-top: 16px; }
    .mail-kpi { background: #fff; border: 1px solid #dbe6df; border-radius: 16px; padding: 18px; }
    .mail-kpi span { display: block; color: #5b6f66; font-weight: 800; font-size: 12px; letter-spacing: 1px; text-transform: uppercase; }
    .mail-kpi strong { display: block; margin-top: 8px; color: #052e1b; font-size: 28px; }
    .mail-card { margin-top: 16px; overflow: hidden; }
    .mail-card-head { padding: 18px 20px; border-bottom: 1px solid #e5eee8; display:flex; justify-content:space-between; gap:12px; align-items:center; }
    .mail-filters { padding: 16px 20px; display:grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; border-bottom: 1px solid #e5eee8; }
    .mail-input { width: 100%; border: 1px solid #d6e1db; border-radius: 11px; padding: 11px 12px; background: #fbfdfb; font-weight: 700; }
    .mail-send-form { padding: 18px 20px; display:grid; grid-template-columns: minmax(150px, .7fr) minmax(160px, .8fr) minmax(260px, 1.4fr) auto auto auto; gap: 12px; align-items:end; }
    .mail-field label { display:block; margin: 0 0 7px; color:#5b6f66; font-size:12px; letter-spacing:1px; text-transform:uppercase; font-weight:900; }
    .mail-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .mail-table th { text-align: left; color: #5b6f66; font-size: 12px; letter-spacing: 1px; text-transform: uppercase; background: #f6faf7; padding: 13px 14px; }
    .mail-table td { padding: 14px; border-top: 1px solid #edf3ef; vertical-align: top; }
    .mail-status { display:inline-flex; border-radius:999px; padding:5px 10px; font-weight:900; font-size:12px; }
    .mail-status.sent { color:#065f46; background:#dff8eb; }
    .mail-status.failed { color:#991b1b; background:#fee2e2; }
    .mail-status.queued { color:#92400e; background:#fef3c7; }
    .mail-status.skipped { color:#334155; background:#e2e8f0; }
    .mail-error { max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #991b1b; }
    .mail-recipient-panel { padding:18px 20px 20px; }
    .mail-recipient-toolbar { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:14px; }
    .mail-recipient-summary { display:flex; flex-wrap:wrap; gap:8px; align-items:center; color:#52685e; font-size:13px; font-weight:800; }
    .mail-pill { display:inline-flex; align-items:center; border-radius:999px; background:#e8f5ee; color:#075f39; padding:6px 10px; font-size:12px; font-weight:900; }
    .mail-recipient-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; max-height:310px; overflow:auto; padding:2px 4px 2px 0; }
    .mail-recipient-option { display:flex; gap:12px; align-items:flex-start; border:1px solid #dbe8e1; border-radius:14px; padding:13px 14px; background:#fbfdfb; cursor:pointer; transition:border-color .18s, background .18s, box-shadow .18s; }
    .mail-recipient-option:hover { border-color:#9fc7b2; background:#f3fbf6; }
    .mail-recipient-option:has(input:checked) { border-color:#0b6b3f; background:#eefaf3; box-shadow:0 0 0 2px rgba(11,107,63,.08); }
    .mail-recipient-check { width:18px; height:18px; margin-top:2px; accent-color:#0b6b3f; flex:0 0 auto; }
    .mail-recipient-name { color:#052e1b; font-size:14px; font-weight:900; line-height:1.25; }
    .mail-recipient-meta { color:#64746d; font-size:12px; font-weight:700; margin-top:4px; overflow-wrap:anywhere; }
    .mail-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; margin-top:16px; }
    .mail-help { margin-top:10px; color:#64746d; font-size:12px; font-weight:700; }
    @media (max-width: 1100px) { .mail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .mail-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } .mail-send-form { grid-template-columns: repeat(2, minmax(0, 1fr)); } .mail-recipient-list { grid-template-columns:1fr; } }
    @media (max-width: 700px) { .mail-page { padding: 12px; } .mail-hero { display:block; } .mail-grid, .mail-filters, .mail-send-form { grid-template-columns: 1fr; } .mail-card-head, .mail-recipient-toolbar { align-items:flex-start; flex-direction:column; } .mail-table { min-width: 980px; } .mail-table-wrap { overflow-x:auto; } .mail-actions { justify-content:flex-start; } }
</style>

<div class="mail-page">
    <div class="mail-hero">
        <div>
            <div class="mail-eyebrow">Admin Mail Audit</div>
            <h1 class="mail-title">Mail Center</h1>
            <div class="mail-sub">Kaunsa mail gaya, kisko gaya, success/fail reason aur resend audit yahin track hoga.</div>
        </div>
        <a class="mail-btn primary" href="{{ route('admin.mail-center.daily-report-preview') }}" target="_blank">
            <i class="fas fa-eye"></i> Daily Report Preview
        </a>
    </div>

    @if(session('success'))
        <div class="mail-card" style="padding:14px 18px;color:#065f46;background:#ecfdf5;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mail-card" style="padding:14px 18px;color:#991b1b;background:#fef2f2;">{{ session('error') }}</div>
    @endif

    <div class="mail-grid">
        <div class="mail-kpi"><span>Sent Today</span><strong>{{ $summary['sent_today'] }}</strong></div>
        <div class="mail-kpi"><span>Failed Today</span><strong>{{ $summary['failed_today'] }}</strong></div>
        <div class="mail-kpi"><span>Pending / Queued</span><strong>{{ $summary['queued'] }}</strong></div>
        <div class="mail-kpi"><span>This Month</span><strong>{{ $summary['month_total'] }}</strong></div>
    </div>

    <div class="mail-card">
        <div class="mail-card-head">
            <div>
                <div class="mail-eyebrow">2 Cr+ Alerts</div>
                <h2 style="margin:4px 0 0;font-size:22px;color:#052e1b;">High Budget Alert Recipients</h2>
            </div>
            <form method="POST" action="{{ route('admin.mail-center.high-budget-test-send') }}" onsubmit="return confirm('Latest real 2 Cr+ lead ka test mail selected recipients ko bhejna hai?');">
                @csrf
                <button class="mail-btn" type="submit"><i class="fas fa-paper-plane"></i> Send Test</button>
            </form>
        </div>
        <form class="mail-recipient-panel" method="POST" action="{{ route('admin.mail-center.high-budget-recipients.update') }}" data-high-budget-recipient-form>
            @csrf
            <div class="mail-recipient-toolbar">
                <div class="mail-recipient-summary">
                    <span class="mail-pill"><span data-recipient-count>{{ count($selectedHighBudgetRecipientIds) }}</span> selected</span>
                    <span>Future 2 Cr+ alerts selected users ko jayenge.</span>
                </div>
                <div class="mail-actions" style="margin-top:0;">
                    <button class="mail-btn primary" type="submit"><i class="fas fa-save"></i> Save Recipients</button>
                </div>
            </div>
            <div class="mail-field">
                <label>Recipients</label>
                <div class="mail-recipient-list">
                    @foreach($highBudgetRecipients as $user)
                        <label class="mail-recipient-option">
                            <input class="mail-recipient-check" type="checkbox" name="recipient_user_ids[]" value="{{ $user->id }}" @checked(in_array((int) $user->id, $selectedHighBudgetRecipientIds, true)) data-recipient-checkbox>
                            <span>
                                <span class="mail-recipient-name">{{ $user->name }}</span>
                                <span class="mail-recipient-meta">{{ $user->email }}</span>
                                <span class="mail-recipient-meta">{{ $user->role?->name ?? str($user->role?->slug)->replace('_', ' ')->title() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="mail-help">Unchecked save karne par active admins fallback rahenge. Search/filter nahi chahiye, yeh list sirf eligible admin/manager users ki hai.</div>
                @error('recipient_user_ids')<div style="color:#991b1b;margin-top:6px;font-weight:800;">{{ $message }}</div>@enderror
                @error('recipient_user_ids.*')<div style="color:#991b1b;margin-top:6px;font-weight:800;">{{ $message }}</div>@enderror
            </div>
        </form>
    </div>

    <div class="mail-card">
        <div class="mail-card-head">
            <div>
                <div class="mail-eyebrow">Manual Daily Report</div>
                <h2 style="margin:4px 0 0;font-size:22px;color:#052e1b;">Send Report To Admin</h2>
            </div>
        </div>
        <form class="mail-send-form" method="POST" action="{{ route('admin.mail-center.daily-report-send') }}" data-daily-report-form>
            @csrf
            <div class="mail-field">
                <label>Date Range</label>
                <select class="mail-input" name="report_range" data-report-range required>
                    <option value="today" @selected(old('report_range', 'today') === 'today')>Today</option>
                    <option value="previous_day" @selected(old('report_range') === 'previous_day')>Previous Day</option>
                    <option value="this_week" @selected(old('report_range') === 'this_week')>This Week</option>
                    <option value="previous_week" @selected(old('report_range') === 'previous_week')>Previous Week</option>
                    <option value="this_month" @selected(old('report_range') === 'this_month')>This Month</option>
                    <option value="previous_month" @selected(old('report_range') === 'previous_month')>Previous Month</option>
                    <option value="all_time" @selected(old('report_range') === 'all_time')>All Time</option>
                </select>
                @error('report_range')<div style="color:#991b1b;margin-top:6px;font-weight:800;">{{ $message }}</div>@enderror
            </div>
            <div class="mail-field">
                <label>Base Date</label>
                <input class="mail-input" type="date" name="report_date" value="{{ old('report_date', today()->toDateString()) }}" data-report-date required>
                @error('report_date')<div style="color:#991b1b;margin-top:6px;font-weight:800;">{{ $message }}</div>@enderror
            </div>
            <div class="mail-field">
                <label>Admin Recipient</label>
                <select class="mail-input" name="recipient_user_id" required>
                    <option value="">Select admin</option>
                    @foreach($adminRecipients as $admin)
                        <option value="{{ $admin->id }}" @selected((string) old('recipient_user_id') === (string) $admin->id)>
                            {{ $admin->name }} - {{ $admin->email }}
                        </option>
                    @endforeach
                </select>
                @error('recipient_user_id')<div style="color:#991b1b;margin-top:6px;font-weight:800;">{{ $message }}</div>@enderror
            </div>
            <a class="mail-btn" href="{{ route('admin.mail-center.daily-report-preview', ['date' => today()->toDateString(), 'range' => 'today']) }}" target="_blank" data-preview-link>
                <i class="fas fa-eye"></i> Preview
            </a>
            <a class="mail-btn" href="{{ route('admin.mail-center.daily-report-pdf', ['date' => today()->toDateString(), 'range' => 'today']) }}" target="_blank" data-pdf-link>
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <button class="mail-btn primary" type="submit">
                <i class="fas fa-paper-plane"></i> Send Report
            </button>
        </form>
    </div>

    <div class="mail-card">
        <div class="mail-card-head">
            <div>
                <div class="mail-eyebrow">Delivery Logs</div>
                <h2 style="margin:4px 0 0;font-size:22px;color:#052e1b;">All Mails</h2>
            </div>
            <a class="mail-btn" href="{{ route('admin.mail-center.index') }}"><i class="fas fa-rotate-left"></i> Clear</a>
        </div>

        <form class="mail-filters" method="GET" action="{{ route('admin.mail-center.index') }}">
            <input class="mail-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            <input class="mail-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            <select class="mail-input" name="mail_type">
                <option value="">All mail types</option>
                @foreach($typeLabels as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['mail_type'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="mail-input" name="status">
                <option value="">All statuses</option>
                @foreach($statusLabels as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input class="mail-input" name="recipient" value="{{ $filters['recipient'] ?? '' }}" placeholder="Recipient email">
            <button class="mail-btn primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
            <input class="mail-input" style="grid-column: span 5;" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search subject, email, error">
        </form>

        <div class="mail-table-wrap">
            <table class="mail-table">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>Mail Type</th>
                        <th>Subject</th>
                        <th>Recipient</th>
                        <th>Status</th>
                        <th>Error</th>
                        <th>Sent By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ optional($log->created_at)->format('d M Y h:i A') }}</td>
                            <td><strong>{{ $log->typeLabel() }}</strong></td>
                            <td>{{ $log->subject ?: '-' }}</td>
                            <td>{{ $log->recipient_email }}</td>
                            <td><span class="mail-status {{ $log->status }}">{{ $log->statusLabel() }}</span></td>
                            <td><div class="mail-error" title="{{ $log->error_message }}">{{ $log->error_message ?: '-' }}</div></td>
                            <td>{{ $log->creator?->name ?? 'System' }}</td>
                            <td><a class="mail-btn" href="{{ route('admin.mail-center.show', $log) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center;color:#64748b;padding:24px;">No mail logs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="padding:16px 20px;">
            {{ $logs->links() }}
        </div>
    </div>
</div>
<script>
    (function () {
        const form = document.querySelector('[data-high-budget-recipient-form]');
        if (!form) return;

        const count = form.querySelector('[data-recipient-count]');
        const boxes = Array.from(form.querySelectorAll('[data-recipient-checkbox]'));

        function syncCount() {
            count.textContent = boxes.filter((box) => box.checked).length;
        }

        boxes.forEach((box) => box.addEventListener('change', syncCount));
        syncCount();
    })();

    (function () {
        const form = document.querySelector('[data-daily-report-form]');
        if (!form) return;

        const dateInput = form.querySelector('[data-report-date]');
        const rangeInput = form.querySelector('[data-report-range]');
        const previewLink = form.querySelector('[data-preview-link]');
        const pdfLink = form.querySelector('[data-pdf-link]');
        const previewBase = @json(route('admin.mail-center.daily-report-preview'));
        const pdfBase = @json(route('admin.mail-center.daily-report-pdf'));

        function syncLinks() {
            const params = new URLSearchParams({
                date: dateInput.value || @json(today()->toDateString()),
                range: rangeInput.value || 'today'
            });
            previewLink.href = previewBase + '?' + params.toString();
            pdfLink.href = pdfBase + '?' + params.toString();
        }

        dateInput.addEventListener('change', syncLinks);
        rangeInput.addEventListener('change', syncLinks);
        syncLinks();
    })();
</script>
@endsection
