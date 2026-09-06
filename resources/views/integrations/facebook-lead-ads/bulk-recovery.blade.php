@extends('layouts.app')

@php
    $fbLeadAdsRoutePrefix = request()->routeIs('ad-manager.meta.facebook-lead-ads.*') ? 'ad-manager.meta.facebook-lead-ads.' : 'integrations.facebook-lead-ads.';
    $bulkPrefix = $fbLeadAdsRoutePrefix . 'bulk-recovery.';
    $defaultFrom = now()->subDays(30)->toDateString();
    $defaultTo = now()->toDateString();
    $running = $scan && in_array($scan->status, ['queued', 'scanning', 'importing'], true);
    $readyCount = $scan ? $scan->leads->where('status', 'ready')->count() : 0;
    $queuedCount = $scan ? $scan->leads->where('status', 'queued')->count() : 0;
    $importedCount = $scan ? $scan->leads->where('status', 'imported')->count() : 0;
@endphp

@section('title', 'Bulk Old Meta Leads Recovery')
@section('page-title', 'Bulk Old Meta Leads Recovery')
@section('page-subtitle', 'Meta ke old leads ko pehle scan karo, review karo, phir selected leads import karo.')

@section('content')
<style>
    .meta-recovery-page{display:flex;flex-direction:column;gap:18px}
    .mr-card{background:#fff;border:1px solid #dbe4ee;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
    .mr-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid #e5edf5}
    .mr-title{font-size:18px;font-weight:900;color:#0f172a;margin:0}
    .mr-sub{font-size:13px;color:#64748b;margin:4px 0 0}
    .mr-form{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;padding:18px 20px;align-items:end}
    .mr-field{display:flex;flex-direction:column;gap:6px}
    .mr-field label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:900;color:#64748b}
    .mr-field input,.mr-field select{height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;font-size:13px;background:#f8fafc;color:#0f172a}
    .mr-btn{height:42px;border:0;border-radius:10px;padding:0 14px;font-size:13px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;text-decoration:none}
    .mr-btn-primary{background:#075b2d;color:#fff}
    .mr-btn-soft{background:#f1f5f9;color:#0f172a;border:1px solid #dbe4ee}
    .mr-btn-danger{background:#fee2e2;color:#991b1b}
    .mr-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;padding:16px 20px}
    .mr-stat{border:1px solid #e5edf5;border-radius:12px;background:#f8fafc;padding:14px}
    .mr-stat strong{display:block;font-size:22px;color:#0f172a}
    .mr-stat span{font-size:12px;color:#64748b;font-weight:700}
    .mr-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 10px;font-size:11px;font-weight:900}
    .mr-badge-ready{background:#dcfce7;color:#166534}
    .mr-badge-running{background:#dbeafe;color:#1d4ed8}
    .mr-badge-failed{background:#fee2e2;color:#991b1b}
    .mr-badge-muted{background:#e2e8f0;color:#475569}
    .mr-body{padding:18px 20px}
    .mr-table{width:100%;border-collapse:collapse}
    .mr-table th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;text-align:left;background:#f8fafc;border-bottom:1px solid #e5edf5;padding:10px}
    .mr-table td{font-size:13px;color:#0f172a;border-bottom:1px solid #edf2f7;padding:10px;vertical-align:top}
    .mr-form-block{border:1px solid #e5edf5;border-radius:12px;margin-bottom:12px;overflow:hidden;background:#fff}
    .mr-form-summary{display:grid;grid-template-columns:minmax(240px,1fr) repeat(4,110px) 120px;gap:10px;align-items:center;padding:13px 14px;background:#f8fafc}
    .mr-leads{max-height:460px;overflow:auto}
    .mr-empty{padding:28px;text-align:center;color:#64748b;font-weight:700}
    .mr-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:flex-end;padding:14px 20px;border-top:1px solid #e5edf5;background:#f8fafc}
    .mr-alert{border-radius:12px;padding:12px 14px;font-size:13px;font-weight:700}
    .mr-alert-success{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
    .mr-alert-warning{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
    .mr-alert-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    @media(max-width:1100px){.mr-form{grid-template-columns:repeat(2,minmax(0,1fr))}.mr-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.mr-form-summary{grid-template-columns:1fr 1fr}.mr-table{min-width:860px}}
    @media(max-width:640px){.mr-form{grid-template-columns:1fr}.mr-stats{grid-template-columns:1fr}.mr-head{flex-direction:column}.mr-actions{justify-content:flex-start}}
</style>

@if($running)
    <script>
        setTimeout(function () { window.location.reload(); }, 5000);
    </script>
@endif

<div class="meta-recovery-page">
    @foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'error'] as $key => $tone)
        @if(session($key))
            <div class="mr-alert mr-alert-{{ $tone }}">{{ session($key) }}</div>
        @endif
    @endforeach

    <div class="mr-card">
        <div class="mr-head">
            <div>
                <h2 class="mr-title">Scan Meta Leads</h2>
                <p class="mr-sub">All connected pages/forms se old leads ko preview mode me scan karega. CRM lead import nahi hoga jab tak aap confirm nahi karte.</p>
            </div>
            <a href="{{ route($fbLeadAdsRoutePrefix . 'index') }}" class="mr-btn mr-btn-soft"><i class="fas fa-arrow-left"></i> Meta Lead Ads</a>
        </div>
        <form method="POST" action="{{ route($bulkPrefix . 'scans.store') }}" class="mr-form">
            @csrf
            <div class="mr-field">
                <label>From</label>
                <input type="date" name="date_from" value="{{ old('date_from', optional($scan?->date_from)->toDateString() ?: $defaultFrom) }}" required>
            </div>
            <div class="mr-field">
                <label>To</label>
                <input type="date" name="date_to" value="{{ old('date_to', optional($scan?->date_to)->toDateString() ?: $defaultTo) }}" required>
            </div>
            <div class="mr-field">
                <label>Scope</label>
                <select name="scope_type" id="scopeType">
                    @foreach(['all' => 'All Pages & Forms', 'portfolio' => 'Portfolio', 'page' => 'Page', 'form' => 'Form'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('scope_type', 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mr-field">
                <label>Scope Item</label>
                <select name="scope_id" id="scopeId">
                    <option value="">All</option>
                </select>
            </div>
            <div class="mr-field">
                <label>Per Form</label>
                <input type="number" name="per_form_limit" min="1" max="100" value="{{ old('per_form_limit', 50) }}" required>
            </div>
            <div class="mr-field">
                <label>Total Cap</label>
                <input type="number" name="total_limit" min="1" max="500" value="{{ old('total_limit', 500) }}" required>
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
                <button class="mr-btn mr-btn-primary" type="submit" @disabled($activeScan)>
                    <i class="fas fa-magnifying-glass"></i>
                    {{ $activeScan ? 'Scan Running' : 'Scan Meta Leads' }}
                </button>
            </div>
        </form>
    </div>

    @if($scan)
        <div class="mr-card">
            <div class="mr-head">
                <div>
                    <h2 class="mr-title">Scan #{{ $scan->id }} Report</h2>
                    <p class="mr-sub">
                        {{ optional($scan->date_from)->format('d M Y') }} to {{ optional($scan->date_to)->format('d M Y') }}
                        · Started by {{ $scan->starter?->name ?? 'System' }}
                    </p>
                </div>
                @php
                    $badgeClass = in_array($scan->status, ['queued', 'scanning', 'importing'], true) ? 'mr-badge-running' : ($scan->status === 'failed' ? 'mr-badge-failed' : 'mr-badge-ready');
                @endphp
                <span class="mr-badge {{ $badgeClass }}">{{ \Illuminate\Support\Str::headline($scan->status) }}</span>
            </div>

            <div class="mr-stats">
                <div class="mr-stat"><strong>{{ $scan->forms_scanned }}/{{ $scan->forms_total }}</strong><span>Forms scanned</span></div>
                <div class="mr-stat"><strong>{{ $readyCount }}</strong><span>New importable</span></div>
                <div class="mr-stat"><strong>{{ $scan->already_present_count }}</strong><span>Already CRM</span></div>
                <div class="mr-stat"><strong>{{ $scan->failed_count }}</strong><span>Failed</span></div>
                <div class="mr-stat"><strong>{{ $queuedCount + $importedCount }}</strong><span>Queued/imported</span></div>
            </div>

            @if($scan->error)
                <div class="mx-5 mb-4 mr-alert mr-alert-error">{{ $scan->error }}</div>
            @endif

            <form method="POST" action="{{ route($bulkPrefix . 'import', $scan) }}" id="bulkImportForm">
                @csrf
                <input type="hidden" name="import_mode" id="importMode" value="selected">
                <div class="mr-body">
                    @forelse($scan->forms as $scanForm)
                        @php
                            $page = $scanForm->page;
                            $form = $scanForm->form;
                            $portfolioName = $page?->portfolio?->name ?: 'Unassigned Portfolio';
                        @endphp
                        <div class="mr-form-block">
                            <div class="mr-form-summary">
                                <div>
                                    <div style="font-weight:900;color:#0f172a;">{{ $portfolioName }} / {{ $page?->page_name ?? 'Page missing' }}</div>
                                    <div style="font-size:12px;color:#64748b;margin-top:3px;">{{ $form?->form_name ?? 'Form missing' }} · {{ $form?->form_id }}</div>
                                </div>
                                <div><strong>{{ $scanForm->fetched_count }}</strong><br><span style="font-size:12px;color:#64748b;">Fetched</span></div>
                                <div><strong>{{ $scanForm->importable_count }}</strong><br><span style="font-size:12px;color:#64748b;">Ready</span></div>
                                <div><strong>{{ $scanForm->already_present_count }}</strong><br><span style="font-size:12px;color:#64748b;">Duplicate</span></div>
                                <div><strong>{{ $scanForm->failed_count }}</strong><br><span style="font-size:12px;color:#64748b;">Failed</span></div>
                                <div><span class="mr-badge {{ $scanForm->status === 'failed' ? 'mr-badge-failed' : ($scanForm->status === 'completed' ? 'mr-badge-ready' : 'mr-badge-running') }}">{{ \Illuminate\Support\Str::headline($scanForm->status) }}</span></div>
                            </div>
                            @if($scanForm->error)
                                <div class="mr-alert mr-alert-error" style="margin:12px;">{{ $scanForm->error }}</div>
                            @endif
                            <div class="mr-leads">
                                @if($scanForm->leads->isNotEmpty())
                                    <table class="mr-table">
                                        <thead>
                                            <tr>
                                                <th style="width:44px;"><input type="checkbox" data-select-form="{{ $scanForm->id }}"></th>
                                                <th>Lead</th>
                                                <th>Contact</th>
                                                <th>Meta Date</th>
                                                <th>Campaign / Ad</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($scanForm->leads as $lead)
                                                <tr>
                                                    <td>
                                                        @if($lead->status === 'ready')
                                                            <input type="checkbox" name="lead_ids[]" value="{{ $lead->id }}" data-form-lead="{{ $scanForm->id }}" class="js-lead-check">
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <strong>{{ $lead->name ?: '-' }}</strong>
                                                        <div style="font-size:12px;color:#64748b;word-break:break-all;">{{ $lead->leadgen_id }}</div>
                                                    </td>
                                                    <td>{{ $lead->phone ?: '-' }}<br><span style="font-size:12px;color:#64748b;">{{ $lead->email ?: '-' }}</span></td>
                                                    <td>{{ $lead->meta_created_time ? $lead->meta_created_time->format('d M Y, h:i A') : '-' }}</td>
                                                    <td>{{ $lead->campaign_name ?: '-' }}<br><span style="font-size:12px;color:#64748b;">{{ $lead->ad_name ?: '-' }}</span></td>
                                                    <td><span class="mr-badge {{ $lead->status === 'ready' ? 'mr-badge-ready' : ($lead->status === 'failed' ? 'mr-badge-failed' : 'mr-badge-muted') }}">{{ \Illuminate\Support\Str::headline($lead->status) }}</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <div class="mr-empty">Is form me selected range ke andar koi lead nahi mili.</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="mr-empty">Scan queued hai. Forms load hote hi report yaha dikhegi.</div>
                    @endforelse
                </div>

                <div class="mr-actions">
                    <button type="button" class="mr-btn mr-btn-soft" id="selectReadyBtn">Select All Ready</button>
                    <button type="button" class="mr-btn mr-btn-soft" id="clearSelectedBtn">Clear</button>
                    <button type="submit" class="mr-btn mr-btn-primary" @disabled($readyCount === 0 || $running)>
                        <i class="fas fa-cloud-arrow-down"></i>
                        Import Selected
                    </button>
                    <button type="submit" class="mr-btn mr-btn-danger" onclick="document.getElementById('importMode').value='all_ready';" @disabled($readyCount === 0 || $running)>
                        Import All Ready
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($scans->isNotEmpty())
        <div class="mr-card">
            <div class="mr-head">
                <div>
                    <h2 class="mr-title">Recent Runs</h2>
                    <p class="mr-sub">Last 10 bulk recovery scans.</p>
                </div>
            </div>
            <div class="mr-body" style="overflow:auto;">
                <table class="mr-table">
                    <thead><tr><th>ID</th><th>Status</th><th>Date Range</th><th>Ready</th><th>Already CRM</th><th>Started By</th><th></th></tr></thead>
                    <tbody>
                        @foreach($scans as $row)
                            <tr>
                                <td>#{{ $row->id }}</td>
                                <td>{{ \Illuminate\Support\Str::headline($row->status) }}</td>
                                <td>{{ optional($row->date_from)->format('d M Y') }} - {{ optional($row->date_to)->format('d M Y') }}</td>
                                <td>{{ $row->importable_count }}</td>
                                <td>{{ $row->already_present_count }}</td>
                                <td>{{ $row->starter?->name ?? 'System' }}</td>
                                <td><a class="mr-btn mr-btn-soft" href="{{ route($bulkPrefix . 'show', $row) }}">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<script>
    const scopeData = {
        portfolio: @json($portfolios->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()),
        page: @json($pages->map(fn($p) => ['id' => $p->id, 'name' => $p->page_name ?: $p->page_id])->values()),
        form: @json($forms->map(fn($f) => ['id' => $f->id, 'name' => ($f->page?->page_name ? $f->page->page_name . ' / ' : '') . ($f->form_name ?: $f->form_id)])->values()),
    };
    const oldScopeId = @json(old('scope_id'));

    function updateScopeOptions() {
        const type = document.getElementById('scopeType')?.value || 'all';
        const target = document.getElementById('scopeId');
        if (!target) return;
        const rows = scopeData[type] || [];
        target.innerHTML = '<option value="">All</option>' + rows.map(row => `<option value="${row.id}">${row.name}</option>`).join('');
        if (oldScopeId) target.value = oldScopeId;
        target.disabled = type === 'all';
    }
    document.getElementById('scopeType')?.addEventListener('change', updateScopeOptions);
    updateScopeOptions();

    document.querySelectorAll('[data-select-form]').forEach((checkbox) => {
        checkbox.addEventListener('change', function () {
            document.querySelectorAll(`[data-form-lead="${this.dataset.selectForm}"]`).forEach((lead) => {
                lead.checked = this.checked;
            });
        });
    });
    document.getElementById('selectReadyBtn')?.addEventListener('click', function () {
        document.querySelectorAll('.js-lead-check').forEach((lead) => lead.checked = true);
        document.getElementById('importMode').value = 'selected';
    });
    document.getElementById('clearSelectedBtn')?.addEventListener('click', function () {
        document.querySelectorAll('.js-lead-check,[data-select-form]').forEach((lead) => lead.checked = false);
        document.getElementById('importMode').value = 'selected';
    });
    document.getElementById('bulkImportForm')?.addEventListener('submit', function () {
        if (document.getElementById('importMode').value !== 'all_ready') {
            document.getElementById('importMode').value = 'selected';
        }
    });
</script>
@endsection
