@extends('layouts.app')

@section('title', 'Meta Lead Check')
@section('page-title', 'Meta Lead Check')

@php
    $tab = $activeTab ?? 'upload';
    $statusLabels = [
        'in_crm' => 'In CRM',
        'imported' => 'Imported',
        'missing' => 'Missing',
        'not_enough_data' => 'Need Detail',
        'possible_duplicate' => 'Possible Duplicate',
        'name_only_review' => 'Name Review',
        'webhook_received' => 'Webhook Received',
        'webhook_failed' => 'Webhook Failed',
        'webhook_missing' => 'Webhook Missing',
        'invalid' => 'Invalid',
    ];
    $statusClasses = [
        'in_crm' => 'good',
        'imported' => 'good',
        'missing' => 'warn',
        'not_enough_data' => 'bad',
        'possible_duplicate' => 'info',
        'name_only_review' => 'info',
        'webhook_received' => 'info',
        'webhook_failed' => 'bad',
        'webhook_missing' => 'warn',
        'invalid' => 'bad',
    ];
    $recoveryStats = $recoveryReport['stats'] ?? null;
    $recoveryRows = collect($recoveryReport['rows'] ?? []);
    $recoveryImportableRows = $recoveryRows->where('importable', true)->values();
    $recoveryIssueRows = $recoveryRows->filter(fn ($row) => in_array($row['status'] ?? '', ['webhook_missing', 'webhook_failed', 'missing', 'not_enough_data'], true))->values();
    $recoveryImportableCount = $recoveryStats['importable'] ?? $recoveryImportableRows->count();
    $recoveryMissingCount = ($recoveryStats['webhook_missing'] ?? 0) + ($recoveryStats['webhook_failed'] ?? 0) + ($recoveryStats['missing'] ?? 0);
    $recoverySampleRows = ($recoveryImportableRows->isNotEmpty() ? $recoveryImportableRows : $recoveryIssueRows)->take(3);
@endphp

@push('styles')
<style>
    .meta-check-shell{display:flex;flex-direction:column;gap:14px}
    .meta-check-hero,.meta-check-card{background:#fff;border:1px solid #dce7e0;border-radius:16px;box-shadow:0 12px 30px rgba(15,23,42,.045)}
    .meta-check-hero{padding:22px 24px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:22px;align-items:center}
    .meta-check-kicker{font-size:11px;font-weight:850;letter-spacing:.14em;text-transform:uppercase;color:#64748b}
    .meta-check-title{font-size:30px;font-weight:850;color:#062a18;margin:6px 0 4px;letter-spacing:-.02em}
    .meta-check-copy{color:#536275;margin:0;line-height:1.45;max-width:780px}
    .meta-check-tabs{display:inline-flex;gap:4px;flex-wrap:nowrap;padding:5px;border:1px solid #dbe7df;border-radius:14px;background:#f7faf8;white-space:nowrap}
    .meta-check-tab{border:0;border-radius:10px;padding:10px 14px;text-decoration:none;color:#102a43;font-weight:800;background:transparent;line-height:1}
    .meta-check-tab.active{background:#075c35;color:#fff;box-shadow:0 8px 18px rgba(7,92,53,.18)}
    .meta-check-grid{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:10px}
    .meta-check-stat{padding:14px 16px;border:1px solid #dfe8e2;border-radius:14px;background:#fbfdfc;display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:78px}
    .meta-check-stat b{display:block;font-size:24px;color:#061826;line-height:1}
    .meta-check-stat span{font-size:12px;color:#64748b;font-weight:800}
    .meta-check-stat i{width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:#edf8f1;color:#075c35}
    .meta-check-card{overflow:hidden}
    .meta-check-card-head{padding:15px 18px;border-bottom:1px solid #e8efe9;display:flex;justify-content:space-between;gap:12px;align-items:center;background:#fcfefd}
    .meta-check-card-body{padding:16px 18px}
    .meta-check-form{display:grid;grid-template-columns:minmax(300px,1.8fr) minmax(220px,1.15fr) minmax(150px,.75fr) minmax(150px,.75fr) auto;gap:12px;align-items:end}
    .meta-check-field label{display:block;font-size:11px;font-weight:850;letter-spacing:.11em;text-transform:uppercase;color:#61708a;margin-bottom:7px}
    .meta-check-input{width:100%;height:44px;border:1px solid #d6e0d9;border-radius:12px;padding:0 12px;background:#fff;color:#0f172a;outline:none}
    .meta-check-input:focus,.meta-check-file:focus-within{border-color:#0b6b42;box-shadow:0 0 0 3px rgba(7,92,53,.09)}
    .meta-check-file{height:44px;border:1px solid #d6e0d9;border-radius:12px;background:#fff;display:flex;align-items:center;gap:10px;padding:0 12px;overflow:hidden}
    .meta-check-file input{position:absolute;opacity:0;pointer-events:none}
    .meta-check-file-action{flex:0 0 auto;border-radius:9px;background:#eef7f2;color:#075c35;font-weight:850;padding:7px 10px;font-size:12px;letter-spacing:.04em;text-transform:uppercase}
    .meta-check-file-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;font-weight:750;font-size:12px;letter-spacing:.04em;text-transform:uppercase}
    .meta-check-btn{height:44px;border:0;border-radius:12px;padding:0 18px;font-weight:850;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;white-space:nowrap}
    .meta-check-btn.primary{background:#075c35;color:#fff;box-shadow:0 10px 20px rgba(7,92,53,.15)}
    .meta-check-btn.secondary{background:#f8fafc;color:#075c35;border:1px solid #d6e0d9}
    .meta-check-btn.blue{background:#2563eb;color:#fff}
    .meta-check-table{width:100%;border-collapse:collapse}
    .meta-check-table th,.meta-check-table td{padding:13px 14px;border-bottom:1px solid #edf2ee;text-align:left;vertical-align:top;font-size:14px}
    .meta-check-table th{background:#f5f8f6;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.08em}
    .meta-check-badge{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#eef2f7;color:#334155}
    .meta-check-badge.good{background:#dcfce7;color:#166534}.meta-check-badge.warn{background:#fef3c7;color:#92400e}.meta-check-badge.bad{background:#fee2e2;color:#991b1b}.meta-check-badge.info{background:#dbeafe;color:#1d4ed8}
    .meta-check-sub{font-size:12px;color:#64748b;margin-top:3px;line-height:1.45}
    .meta-check-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .meta-check-token{display:none;margin-top:14px;padding:14px;border-radius:12px;background:#f0fdf4;border:1px solid #bbf7d0;word-break:break-all;font-family:monospace;font-size:12px}
    .crm-missing-action{border:1px solid #cfe7d9;background:linear-gradient(135deg,#f7fffb,#eef9f2);border-radius:16px;padding:18px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center}
    .crm-missing-action.ok{border-color:#bbf7d0;background:#f0fdf4}.crm-missing-action.warn{border-color:#fde68a;background:#fffbeb}
    .crm-action-kicker{font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
    .crm-action-title{font-size:24px;font-weight:900;color:#062a18;margin:4px 0}
    .crm-action-copy{color:#536275;font-size:13px;line-height:1.45}
    .crm-action-samples{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
    .crm-action-chip{border:1px solid #d7e5dc;background:#fff;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:800;color:#25483a}
    .crm-action-buttons{display:flex;flex-wrap:wrap;gap:9px;justify-content:flex-end}
    .crm-details-panel{display:none;margin-top:14px;border-top:1px solid #e8efe9;padding-top:14px}
    .crm-details-panel.open{display:block}
    @media(max-width:1100px){.meta-check-hero{grid-template-columns:1fr}.meta-check-tabs{width:max-content}.meta-check-grid{grid-template-columns:repeat(2,1fr)}.meta-check-form{grid-template-columns:1fr 1fr}.meta-check-table{min-width:980px}.meta-check-scroll{overflow:auto}}
    @media(max-width:720px){.meta-check-grid,.meta-check-form,.crm-missing-action{grid-template-columns:1fr}.crm-action-buttons{justify-content:stretch}.crm-action-buttons .meta-check-btn{width:100%}.meta-check-tabs{width:100%;overflow:auto}.meta-check-tab{flex:1;text-align:center}.meta-check-title{font-size:26px}}
</style>
@endpush

@section('content')
<div class="meta-check-shell">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <section class="meta-check-hero">
        <div>
            <div class="meta-check-kicker">CRM Meta Workspace</div>
            <h1 class="meta-check-title">Meta Lead Check</h1>
            <p class="meta-check-copy">Meta Excel/CSV upload karo, preview dekho, phir selected missing leads ko CRM me import karo.</p>
        </div>
        <div class="meta-check-tabs">
            <a class="meta-check-tab {{ $tab === 'upload' ? 'active' : '' }}" href="{{ route('crm.meta-lead-check.index', ['tab' => 'upload']) }}">Upload Check</a>
            <a class="meta-check-tab {{ $tab === 'missing' ? 'active' : '' }}" href="{{ route('crm.meta-lead-check.index', ['tab' => 'missing']) }}">Missing Leads</a>
            <a class="meta-check-tab {{ $tab === 'extension' ? 'active' : '' }}" href="{{ route('crm.meta-lead-check.index', ['tab' => 'extension']) }}">Extension</a>
        </div>
    </section>

    <div class="meta-check-grid">
        <div class="meta-check-stat"><div><b>{{ $stats['total'] }}</b><span>Total Rows</span></div><i class="fas fa-table-list"></i></div>
        <div class="meta-check-stat"><div><b>{{ $stats['in_crm'] }}</b><span>In CRM</span></div><i class="fas fa-circle-check"></i></div>
        <div class="meta-check-stat"><div><b>{{ $stats['missing'] }}</b><span>Missing</span></div><i class="fas fa-triangle-exclamation"></i></div>
        <div class="meta-check-stat"><div><b>{{ $stats['duplicate'] }}</b><span>Duplicate Review</span></div><i class="fas fa-code-compare"></i></div>
        <div class="meta-check-stat"><div><b>{{ $stats['importable'] }}</b><span>Importable</span></div><i class="fas fa-cloud-arrow-up"></i></div>
    </div>

    @if($tab === 'upload')
        <section class="meta-check-card">
            <div class="meta-check-card-head">
                <div>
                    <h2 style="margin:0;font-size:20px;">Upload Meta File</h2>
                </div>
            </div>
            <div class="meta-check-card-body">
                <form class="meta-check-form" action="{{ route('crm.meta-lead-check.preview') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="meta-check-field">
                        <label>Excel / CSV File</label>
                        <label class="meta-check-file">
                            <input id="metaLeadFileInput" type="file" name="lead_file" accept=".xlsx,.xls,.csv,.txt" required>
                            <span class="meta-check-file-action"><i class="fas fa-file-arrow-up"></i> Choose file</span>
                            <span id="metaLeadFileName" class="meta-check-file-name">No file selected</span>
                        </label>
                        <div id="metaAutoDetectNote" class="meta-check-sub" style="display:none;"></div>
                    </div>
                    <div class="meta-check-field">
                        <label>Meta Form</label>
                        <select class="meta-check-input" name="fb_form_id" id="metaFormSelect" required>
                            <option value="">Select Meta form</option>
                            @foreach($metaForms as $form)
                                <option value="{{ $form->id }}" data-form-name="{{ $form->form_name ?: '' }}" data-form-id="{{ $form->form_id ?: '' }}" @selected((string) old('fb_form_id') === (string) $form->id)>
                                    {{ $form->form_name ?: $form->form_id }}{{ $form->page?->page_name ? ' - ' . $form->page->page_name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="meta-check-field">
                        <label>Date From</label>
                        <input class="meta-check-input" type="date" name="date_from" id="metaDateFrom" value="{{ old('date_from') }}">
                    </div>
                    <div class="meta-check-field">
                        <label>Date To</label>
                        <input class="meta-check-input" type="date" name="date_to" id="metaDateTo" value="{{ old('date_to') }}">
                    </div>
                    <button class="meta-check-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> Check File</button>
                </form>
                        <div class="meta-check-sub" style="margin-top:10px;">Selected form file rows ko correct Meta form aur page ke saath compare karega.</div>
            </div>
        </section>
        @if($recoveryReport && $recoveryStats)
            <section class="meta-check-card">
                <div class="meta-check-card-head">
                    <div>
                        <h2 style="margin:0;font-size:20px;">Latest Recovery Actions</h2>
                        <div class="meta-check-sub">Last checked file ke importable missing leads yahan se recover kar sakte ho.</div>
                    </div>
                    <a class="meta-check-btn secondary" href="{{ route('crm.meta-lead-check.index', ['tab' => 'missing']) }}">View Missing Leads</a>
                </div>
                <div class="meta-check-card-body">
                    @if($recoveryImportableCount > 0)
                        <div class="crm-missing-action">
                            <div>
                                <div class="crm-action-kicker">Missing leads ready</div>
                                <div class="crm-action-title">{{ $recoveryImportableCount }} lead{{ $recoveryImportableCount === 1 ? '' : 's' }} import ke liye ready hain</div>
                                <div class="crm-action-copy">Missing Meta leads ko abhi CRM me import kar sakte ho. Import + Assign existing Meta rule use karega.</div>
                                <div class="crm-action-samples">
                                    @foreach($recoverySampleRows as $row)
                                        <span class="crm-action-chip">{{ $row['name'] ?: 'No name' }}{{ !empty($row['phone']) ? ' · ' . $row['phone'] : '' }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="crm-action-buttons">
                                <form action="{{ route('crm.meta-lead-check.import') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="fallback_form_id" value="{{ $recoveryReport['fallback_form_id'] ?? '' }}">
                                    <input type="hidden" name="batch_limit" value="20">
                                    <button class="meta-check-btn secondary" type="submit" name="import_action" value="import"><i class="fas fa-cloud-arrow-down"></i> Import Missing</button>
                                </form>
                                <form action="{{ route('crm.meta-lead-check.import') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="fallback_form_id" value="{{ $recoveryReport['fallback_form_id'] ?? '' }}">
                                    <input type="hidden" name="batch_limit" value="20">
                                    <button class="meta-check-btn primary" type="submit" name="import_action" value="import_assign"><i class="fas fa-user-check"></i> Import + Assign</button>
                                </form>
                            </div>
                        </div>
                    @elseif($recoveryMissingCount > 0)
                        <div class="crm-missing-action warn">
                            <div>
                                <div class="crm-action-kicker">Review needed</div>
                                <div class="crm-action-title">{{ $recoveryMissingCount }} missing lead found, import data incomplete hai</div>
                                <div class="crm-action-copy">Form mapping ya contact detail issue ho sakta hai. Details me reason check karo.</div>
                            </div>
                            <div class="crm-action-buttons">
                                <a class="meta-check-btn primary" href="{{ route('crm.meta-lead-check.index', ['tab' => 'missing']) }}">Show Details</a>
                            </div>
                        </div>
                    @else
                        <div class="crm-missing-action ok">
                            <div>
                                <div class="crm-action-kicker">All clear</div>
                                <div class="crm-action-title">No missing leads</div>
                                <div class="crm-action-copy">Latest file ki Meta leads already CRM me mil gayi hain.</div>
                            </div>
                            <a class="meta-check-btn secondary" href="{{ route('crm.meta-lead-check.index', ['tab' => 'missing']) }}">View Details</a>
                        </div>
                    @endif
                </div>
            </section>
        @endif
    @elseif($tab === 'extension')
        <section class="meta-check-card">
            <div class="meta-check-card-head">
                <div>
                    <h2 style="margin:0;font-size:20px;">Facebook Lead Center Extension</h2>
                    <div class="meta-check-sub">Extension install karke Facebook Lead Center page scan/re-check kar sakte ho.</div>
                </div>
                <a class="meta-check-btn secondary" href="{{ route('crm.meta-lead-check.extension.download') }}"><i class="fas fa-download"></i> Download Extension</a>
            </div>
            <div class="meta-check-card-body">
                <div class="meta-check-actions">
                    <button id="metaGenerateToken" class="meta-check-btn primary" type="button"><i class="fas fa-key"></i> Generate Token</button>
                    <span class="meta-check-sub">Token extension settings me paste karo. CRM base URL: {{ url('/') }}</span>
                </div>
                <div id="metaTokenBox" class="meta-check-token"></div>
                <div style="margin-top:18px;" class="meta-check-sub">
                    Step: extension download karo, Chrome me load unpacked karo, token generate karke paste karo, Facebook Lead Center page par scan/re-check dabao.
                </div>
            </div>
        </section>
    @else
        <section class="meta-check-card">
            <div class="meta-check-card-head">
                <div>
                    <h2 style="margin:0;font-size:20px;">Missing Leads</h2>
                    <div class="meta-check-sub">
                        @if($latestAudit)
                            Latest check: {{ $latestAudit->created_at?->format('d M Y, h:i A') }} by {{ $latestAudit->user?->name ?? 'User' }}
                            @if(data_get($latestAudit->browser_meta, 'selected_form_name'))
                                | Form: {{ data_get($latestAudit->browser_meta, 'selected_form_name') }}
                            @endif
                        @else
                            Abhi koi check nahi hua.
                        @endif
                    </div>
                </div>
                <a class="meta-check-btn secondary" href="{{ route('crm.meta-lead-check.index', ['tab' => 'upload']) }}">New Upload</a>
            </div>
            <div class="meta-check-card-body" style="padding:0;">
                @if($recoveryReport && $recoveryStats)
                    <div class="meta-check-card-body" style="border-bottom:1px solid #e8efe9;">
                        @if($recoveryImportableCount > 0)
                            <div class="crm-missing-action">
                                <div>
                                    <div class="crm-action-kicker">Action required</div>
                                    <div class="crm-action-title">{{ $recoveryImportableCount }} missing lead{{ $recoveryImportableCount === 1 ? '' : 's' }} import kar sakte ho</div>
                                    <div class="crm-action-copy">Direct import karo ya existing Meta rule ke saath assign karo. Technical details hidden hain.</div>
                                    <div class="crm-action-samples">
                                        @foreach($recoverySampleRows as $row)
                                            <span class="crm-action-chip">{{ $row['name'] ?: 'No name' }}{{ !empty($row['phone']) ? ' · ' . $row['phone'] : '' }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="crm-action-buttons">
                                    <form action="{{ route('crm.meta-lead-check.import') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="fallback_form_id" value="{{ $recoveryReport['fallback_form_id'] ?? '' }}">
                                        <input type="hidden" name="batch_limit" value="20">
                                        <button class="meta-check-btn secondary" type="submit" name="import_action" value="import"><i class="fas fa-cloud-arrow-down"></i> Import Missing</button>
                                    </form>
                                    <form action="{{ route('crm.meta-lead-check.import') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="fallback_form_id" value="{{ $recoveryReport['fallback_form_id'] ?? '' }}">
                                        <input type="hidden" name="batch_limit" value="20">
                                        <button class="meta-check-btn primary" type="submit" name="import_action" value="import_assign"><i class="fas fa-user-check"></i> Import + Assign</button>
                                    </form>
                                    <button class="meta-check-btn secondary" type="button" data-toggle-crm-details><i class="fas fa-list"></i> Show Details</button>
                                </div>
                            </div>
                        @elseif($recoveryMissingCount > 0)
                            <div class="crm-missing-action warn">
                                <div>
                                    <div class="crm-action-kicker">Review needed</div>
                                    <div class="crm-action-title">{{ $recoveryMissingCount }} missing lead found</div>
                                    <div class="crm-action-copy">Import ke liye enough data/form mapping nahi mila. Details open karke reason check karo.</div>
                                </div>
                                <div class="crm-action-buttons">
                                    <button class="meta-check-btn primary" type="button" data-toggle-crm-details><i class="fas fa-list"></i> Show Details</button>
                                </div>
                            </div>
                        @else
                            <div class="crm-missing-action ok">
                                <div>
                                    <div class="crm-action-kicker">All clear</div>
                                    <div class="crm-action-title">No missing leads</div>
                                    <div class="crm-action-copy">Latest file ki Meta leads already CRM me mil gayi hain.</div>
                                </div>
                                <button class="meta-check-btn secondary" type="button" data-toggle-crm-details><i class="fas fa-list"></i> Show Details</button>
                            </div>
                        @endif

                        <div id="crmRecoveryDetails" class="crm-details-panel">
                        <div class="meta-check-grid" style="grid-template-columns:repeat(6,minmax(110px,1fr));margin-bottom:14px;">
                            <div class="meta-check-stat"><div><b>{{ $recoveryStats['total'] ?? 0 }}</b><span>Total</span></div><i class="fas fa-table-list"></i></div>
                            <div class="meta-check-stat"><div><b>{{ $recoveryStats['in_crm'] ?? 0 }}</b><span>In CRM</span></div><i class="fas fa-circle-check"></i></div>
                            <div class="meta-check-stat"><div><b>{{ $recoveryStats['webhook_received'] ?? 0 }}</b><span>Received</span></div><i class="fas fa-inbox"></i></div>
                            <div class="meta-check-stat"><div><b>{{ $recoveryStats['webhook_failed'] ?? 0 }}</b><span>Failed</span></div><i class="fas fa-triangle-exclamation"></i></div>
                            <div class="meta-check-stat"><div><b>{{ $recoveryStats['webhook_missing'] ?? 0 }}</b><span>Missing</span></div><i class="fas fa-cloud-question"></i></div>
                            <div class="meta-check-stat"><div><b>{{ $recoveryStats['importable'] ?? 0 }}</b><span>Importable</span></div><i class="fas fa-cloud-arrow-up"></i></div>
                        </div>

                        @if(($recoveryStats['importable'] ?? 0) > 0)
                            <form action="{{ route('crm.meta-lead-check.import') }}" method="POST" class="meta-check-card-body" style="padding:0;">
                                @csrf
                                <div class="meta-check-form" style="grid-template-columns:minmax(220px,1fr) 140px 170px 190px;">
                                    <div class="meta-check-field">
                                        <label>Fallback Form</label>
                                        <select class="meta-check-input" name="fallback_form_id">
                                            <option value="">Use row form ID</option>
                                            @foreach($metaForms as $form)
                                                <option value="{{ $form->id }}" @selected((string)($recoveryReport['fallback_form_id'] ?? '') === (string)$form->id)>
                                                    {{ $form->form_name ?: $form->form_id }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="meta-check-field">
                                        <label>Batch Limit</label>
                                        <select class="meta-check-input" name="batch_limit">
                                            <option value="10">10</option>
                                            <option value="20" selected>20</option>
                                            <option value="30">30</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                    <button class="meta-check-btn secondary" type="submit" name="import_action" value="import">
                                        <i class="fas fa-cloud-arrow-down"></i> Import Only
                                    </button>
                                    <button class="meta-check-btn primary" type="submit" name="import_action" value="import_assign">
                                        <i class="fas fa-user-check"></i> Import + Assign
                                    </button>
                                </div>

                                <div style="margin-top:12px;padding:12px;border:1px solid #dbeafe;border-radius:12px;background:#eff6ff;color:#1e3a8a;font-size:12px;font-weight:800;">
                                    CRM default safe mode: Import + Assign sirf existing Meta rule use karega. Existing leads reassign nahi honge.
                                </div>

                                @if($canUseAdvancedRecovery)
                                    <div style="margin-top:12px;padding:14px;border:1px solid #dce7e0;border-radius:14px;background:#fbfdfc;">
                                        <div class="meta-check-sub" style="margin-bottom:10px;font-weight:850;color:#075c35;">Advanced assignment controls</div>
                                        <div class="meta-check-form" style="grid-template-columns:repeat(3,minmax(180px,1fr));">
                                            <div class="meta-check-field">
                                                <label>Assignment Method</label>
                                                <select class="meta-check-input" name="assignment_method">
                                                    <option value="existing_rule">Existing Meta Rule</option>
                                                    <option value="single_user">Single User</option>
                                                    <option value="round_robin">Round Robin</option>
                                                    <option value="first_available">First Available</option>
                                                    <option value="percentage">Percentage</option>
                                                </select>
                                            </div>
                                            <div class="meta-check-field">
                                                <label>Single User</label>
                                                <select class="meta-check-input" name="assignee_user_id">
                                                    <option value="">Select user</option>
                                                    @foreach($assignableUsers as $userItem)
                                                        <option value="{{ $userItem->id }}">{{ $userItem->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="meta-check-field">
                                                <label>User Pool</label>
                                                <select class="meta-check-input" name="assignment_user_ids[]" multiple style="height:90px;">
                                                    @foreach($assignableUsers as $userItem)
                                                        <option value="{{ $userItem->id }}">{{ $userItem->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="meta-check-sub" style="margin-top:12px;margin-bottom:8px;font-weight:850;color:#075c35;">Percentage weights</div>
                                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;">
                                            @foreach($assignableUsers as $userItem)
                                                <label style="display:grid;gap:5px;border:1px solid #dce7e0;border-radius:10px;padding:9px;background:#fff;">
                                                    <span class="meta-check-sub" style="margin:0;font-weight:800;color:#334155;">{{ $userItem->name }}</span>
                                                    <input class="meta-check-input" style="height:36px;" type="number" min="0" max="100" step="1" name="user_percentages[{{ $userItem->id }}]" placeholder="0%">
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="meta-check-actions" style="margin-top:12px;">
                                            <label class="meta-check-sub" style="display:flex;align-items:center;gap:8px;margin:0;font-weight:800;color:#0f5132;">
                                                <input type="checkbox" name="create_calling_task" value="1" checked>
                                                Create calling task
                                            </label>
                                            <label class="meta-check-sub" style="display:flex;align-items:center;gap:8px;margin:0;font-weight:800;color:#92400e;">
                                                <input type="checkbox" name="reassign_existing" value="1">
                                                Reassign existing CRM leads
                                            </label>
                                        </div>
                                    </div>
                                @endif
                            </form>
                        @endif

                        <div class="meta-check-scroll" style="margin-top:14px;">
                            <table class="meta-check-table">
                                <thead>
                                    <tr>
                                        <th>Lead</th>
                                        <th>Phone</th>
                                        <th>Meta IDs</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(collect($recoveryReport['rows'] ?? [])->take(50) as $row)
                                        @php $class = $statusClasses[$row['status'] ?? ''] ?? ''; @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $row['name'] ?: 'No name' }}</strong>
                                                <div class="meta-check-sub">{{ $row['email'] ?: '-' }}</div>
                                            </td>
                                            <td>{{ $row['phone'] ?: '-' }}</td>
                                            <td>
                                                <div>Lead: {{ $row['leadgen_id'] ?: '-' }}</div>
                                                <div class="meta-check-sub">Form: {{ $row['form_id'] ?: '-' }}</div>
                                            </td>
                                            <td><span class="meta-check-badge {{ $class }}">{{ $row['status_label'] ?? ($statusLabels[$row['status'] ?? ''] ?? ucfirst(str_replace('_', ' ', $row['status'] ?? ''))) }}</span></td>
                                            <td>{{ $row['reason'] ?? '-' }}</td>
                                            <td>
                                                @if(!empty($row['crm_lead_id']))
                                                    <a class="meta-check-btn secondary" style="height:34px;padding:0 10px;" href="{{ route('leads.show', $row['crm_lead_id']) }}">Open</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                @endif

                @if($rows && $rows->count())
                    <form action="{{ route('crm.meta-lead-check.import') }}" method="POST">
                        @csrf
                        <div class="meta-check-scroll">
                            <table class="meta-check-table">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" onclick="document.querySelectorAll('.meta-row-check').forEach(cb => cb.checked = this.checked)"></th>
                                        <th>Lead</th>
                                        <th>Phone</th>
                                        <th>Meta IDs</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                        @php
                                            $importable = in_array($row->status, ['missing', 'not_enough_data'], true) && ($row->phone || $row->email);
                                            $class = $statusClasses[$row->status] ?? '';
                                        @endphp
                                        <tr>
                                            <td>
                                                @if($importable)
                                                    <input class="meta-row-check" type="checkbox" name="row_ids[]" value="{{ $row->id }}">
                                                @endif
                                            </td>
                                            <td>
                                                <strong>{{ $row->lead_name ?: 'No name' }}</strong>
                                                <div class="meta-check-sub">{{ $row->email ?: '-' }}</div>
                                            </td>
                                            <td>
                                                {{ $row->phone ?: '-' }}
                                            </td>
                                            <td>
                                                <div>Lead: {{ $row->leadgen_id ?: '-' }}</div>
                                                <div class="meta-check-sub">Form: {{ $row->form_id ?: '-' }} | Page: {{ $row->page_id ?: '-' }}</div>
                                            </td>
                                            <td>{{ $row->submitted_at_text ?: '-' }}</td>
                                            <td><span class="meta-check-badge {{ $class }}">{{ $statusLabels[$row->status] ?? ucfirst(str_replace('_', ' ', $row->status)) }}</span></td>
                                            <td>{{ $row->match_reason ?: '-' }}</td>
                                            <td>
                                                @if($row->crm_lead_id)
                                                    <a class="meta-check-btn secondary" style="height:34px;padding:0 10px;" href="{{ route('leads.show', $row->crm_lead_id) }}">Open</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="meta-check-card-body" style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
                            <div>{{ $rows->links() }}</div>
                            <div class="meta-check-actions">
                                <label class="meta-check-sub" style="display:flex;align-items:center;gap:8px;margin:0;font-weight:800;color:#0f5132;">
                                    <input type="checkbox" name="assign_existing_rule" value="1">
                                    Import & assign by existing Meta rule
                                </label>
                                <button class="meta-check-btn primary" type="submit"><i class="fas fa-cloud-arrow-up"></i> Import Selected</button>
                            </div>
                        </div>
                    </form>
                @else
                    <div class="meta-check-card-body">Missing leads dekhne ke liye pehle Upload Check karo.</div>
                @endif
            </div>
        </section>
    @endif

    @if($importResults)
        <section class="meta-check-card">
            <div class="meta-check-card-head"><h2 style="margin:0;font-size:20px;">Last Import Result</h2></div>
            <div class="meta-check-card-body">
                Imported: {{ $importResults['imported_count'] ?? 0 }}
                | Skipped: {{ $importResults['skipped_count'] ?? 0 }}
                | Failed: {{ $importResults['failed_count'] ?? 0 }}
                | Assignment rule: {{ !empty($importResults['assignment_rule_checked']) ? 'Checked' : 'Not checked' }}

                @php
                    $resultRows = collect($importResults['rows'] ?? [])
                        ->merge($importResults['skipped'] ?? [])
                        ->merge($importResults['failed'] ?? [])
                        ->take(20);
                @endphp
                @if($resultRows->isNotEmpty())
                    <div class="meta-check-scroll" style="margin-top:14px;">
                        <table class="meta-check-table">
                            <thead>
                                <tr>
                                    <th>Lead</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($resultRows as $resultRow)
                                    <tr>
                                        <td>{{ $resultRow['lead_name'] ?? $resultRow['name'] ?? '-' }}</td>
                                        <td>{{ $resultRow['phone'] ?? '-' }}</td>
                                        <td><span class="meta-check-badge {{ ($resultRow['status'] ?? '') === 'failed' ? 'bad' : ((($resultRow['status'] ?? '') === 'imported') ? 'good' : 'info') }}">{{ ucfirst(str_replace('_', ' ', $resultRow['status'] ?? 'skipped')) }}</span></td>
                                        <td>{{ $resultRow['message'] ?? $resultRow['match_reason'] ?? '-' }}</td>
                                        <td>
                                            @if(!empty($resultRow['lead_url']))
                                                <a class="meta-check-btn secondary" style="height:34px;padding:0 10px;" href="{{ $resultRow['lead_url'] }}">Open</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if(($recentAudits ?? collect())->count())
        <section class="meta-check-card">
            <div class="meta-check-card-head"><h2 style="margin:0;font-size:20px;">Recent Checks</h2></div>
            <div class="meta-check-card-body">
                <div class="meta-check-scroll">
                    <table class="meta-check-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>File</th>
                                <th>Total</th>
                                <th>In CRM</th>
                                <th>Missing</th>
                                <th>Duplicate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentAudits as $audit)
                                <tr>
                                    <td>{{ $audit->created_at?->format('d M Y, h:i A') }}</td>
                                    <td>{{ $audit->source_url ?: '-' }}</td>
                                    <td>{{ $audit->total_rows }}</td>
                                    <td>{{ $audit->matched_rows }}</td>
                                    <td>{{ $audit->missing_rows }}</td>
                                    <td>{{ $audit->possible_duplicate_rows }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.getElementById('metaLeadFileInput')?.addEventListener('change', (event) => {
    const nameBox = document.getElementById('metaLeadFileName');
    const file = event.target.files?.[0];
    if (nameBox) {
        nameBox.textContent = file ? file.name : 'No file selected';
    }

    autoDetectMetaFileFields(file?.name || '');
});

function normalizeMetaFormName(value) {
    return (value || '')
        .toString()
        .toLowerCase()
        .replace(/\.[a-z0-9]+$/i, '')
        .replace(/\bleads?\b/g, ' ')
        .replace(/&/g, ' and ')
        .replace(/[_|/\\-]+/g, ' ')
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function parseMetaLeadFilename(filename) {
    const baseName = (filename || '').replace(/\.[^.]+$/, '');
    const match = baseName.match(/^(.+?)_Leads_(\d{4}-\d{2}-\d{2})_(\d{4}-\d{2}-\d{2})$/i);
    if (!match) {
        return null;
    }

    return {
        formName: match[1].replace(/[_]+/g, ' ').replace(/\s+/g, ' ').trim(),
        dateFrom: match[2],
        dateTo: match[3],
    };
}

function bestMetaFormOption(parsedName) {
    const select = document.getElementById('metaFormSelect');
    if (!select || !parsedName) {
        return null;
    }

    const target = normalizeMetaFormName(parsedName);
    if (!target) {
        return null;
    }

    const candidates = Array.from(select.options)
        .filter((option) => option.value)
        .map((option) => {
            const label = option.dataset.formName || option.textContent || '';
            const normalized = normalizeMetaFormName(label);
            let score = 0;
            if (normalized === target) {
                score = 100;
            } else if (normalized.includes(target) || target.includes(normalized)) {
                score = 80;
            } else {
                const targetTokens = target.split(' ').filter(Boolean);
                const optionTokens = normalized.split(' ').filter(Boolean);
                const matches = targetTokens.filter((token) => optionTokens.includes(token)).length;
                score = targetTokens.length ? Math.round((matches / targetTokens.length) * 70) : 0;
            }

            return { option, score, normalized };
        })
        .filter((candidate) => candidate.score >= 60)
        .sort((a, b) => b.score - a.score);

    if (!candidates.length) {
        return null;
    }

    if (candidates.length > 1 && candidates[0].score === candidates[1].score && candidates[0].normalized !== candidates[1].normalized) {
        return null;
    }

    return candidates[0].option;
}

function autoDetectMetaFileFields(filename) {
    const note = document.getElementById('metaAutoDetectNote');
    const formSelect = document.getElementById('metaFormSelect');
    const dateFrom = document.getElementById('metaDateFrom');
    const dateTo = document.getElementById('metaDateTo');
    const parsed = parseMetaLeadFilename(filename);

    if (note) {
        note.style.display = 'none';
        note.textContent = '';
        note.style.color = '#64748b';
    }

    if (!parsed) {
        if (note && filename) {
            note.style.display = 'block';
            note.style.color = '#92400e';
            note.textContent = 'Filename se form/date auto-detect nahi hua. Format: FORM_Leads_YYYY-MM-DD_YYYY-MM-DD';
        }
        return;
    }

    if (dateFrom) {
        dateFrom.value = parsed.dateFrom;
    }
    if (dateTo) {
        dateTo.value = parsed.dateTo;
    }

    const matchedOption = bestMetaFormOption(parsed.formName);
    if (formSelect && matchedOption) {
        formSelect.value = matchedOption.value;
    }

    if (note) {
        note.style.display = 'block';
        note.style.color = matchedOption ? '#0f5132' : '#92400e';
        note.textContent = matchedOption
            ? `Auto detected: ${matchedOption.textContent.trim()}, ${parsed.dateFrom} to ${parsed.dateTo}`
            : `Date auto detected: ${parsed.dateFrom} to ${parsed.dateTo}. Form "${parsed.formName}" manually select karo.`;
    }
}

document.querySelectorAll('[data-toggle-crm-details]').forEach((button) => {
    button.addEventListener('click', () => {
        const panel = document.getElementById('crmRecoveryDetails');
        if (!panel) {
            return;
        }

        const isOpen = panel.classList.toggle('open');
        button.innerHTML = isOpen
            ? '<i class="fas fa-eye-slash"></i> Hide Details'
            : '<i class="fas fa-list"></i> Show Details';
    });
});

document.getElementById('metaGenerateToken')?.addEventListener('click', async () => {
    const box = document.getElementById('metaTokenBox');
    box.style.display = 'block';
    box.textContent = 'Generating...';
    try {
        const response = await fetch(@json(route('crm.meta-lead-check.extension.generate-token')), {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json'}
        });
        const data = await response.json();
        box.textContent = data.success ? `Base URL: ${data.crm_base_url}\nToken: ${data.token}` : (data.message || 'Token generate nahi hua.');
    } catch (error) {
        box.textContent = 'Token generate nahi hua. Page refresh karke try karo.';
    }
});
</script>
@endpush
