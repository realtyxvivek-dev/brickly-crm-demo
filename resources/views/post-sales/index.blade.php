@extends(auth()->user()?->isFinanceManager() && !auth()->user()?->isAdmin() ? 'finance-manager.layout' : 'layouts.app')

@section('title', 'Post Sales & Builder Desk')
@section('page-title', 'Post Sales')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/post-sales.css') }}?v={{ file_exists(public_path('css/post-sales.css')) ? filemtime(public_path('css/post-sales.css')) : time() }}">
@endpush

@section('content')
@php
    $money = fn($value) => 'Rs '.number_format((float) $value, 2);
    $tabs = [
        'master' => ['Customer Master', 'fa-table'],
        'demands' => ['Demand Schedule', 'fa-calendar-days'],
        'documents' => ['Document Tracker', 'fa-folder-open'],
        'receivables' => ['Builder Receivables', 'fa-building-columns'],
        'builder-desk' => ['Builder Desk', 'fa-file-invoice-dollar'],
        'reports' => ['Reports', 'fa-chart-column'],
    ];
    $pager = $cases ?: ($demands ?: $documents);
@endphp
<div class="ps-shell" data-bulk-url="{{ route('post-sales.bulk-update') }}">
    <header class="ps-topbar">
        <div class="ps-title">Post Sales & Builder Desk</div>
        <div class="ps-kpis">
            <div class="ps-kpi"><span>Total</span><strong>{{ number_format($summary['total_cases']) }}</strong></div>
            <div class="ps-kpi"><span>Active</span><strong>{{ number_format($summary['active_cases']) }}</strong></div>
            <div class="ps-kpi"><span>Handover</span><strong>{{ number_format($summary['handover_pending']) }}</strong></div>
            <div class="ps-kpi"><span>Overdue</span><strong>{{ number_format($summary['overdue_demands']) }}</strong></div>
            <div class="ps-kpi"><span>Builder Due</span><strong>{{ $money($summary['builder_outstanding']) }}</strong></div>
        </div>
        <a class="ps-btn" href="{{ route('post-sales.export') }}"><i class="fas fa-download"></i> Export Full</a>
    </header>

    <div class="ps-mail-lock">
        <i class="fas fa-shield-halved"></i>
        <strong>Customer email delivery OFF.</strong> Test email sirf {{ $summary['test_recipient'] }} par jayegi.
    </div>

    <nav class="ps-tabs" aria-label="Post Sales sheets">
        @foreach($tabs as $key => [$label, $icon])
            <a class="ps-tab {{ $tab === $key ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => $key, 'page' => 1]) }}"><i class="fas {{ $icon }}"></i>{{ $label }}</a>
        @endforeach
    </nav>

    <form class="ps-toolbar" method="GET" action="{{ route('post-sales.index') }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="ps-field grow"><label>Search</label><input class="ps-input" name="search" value="{{ $search }}" placeholder="Customer, phone, case or project"></div>
        <div class="ps-field"><label>Status</label><select class="ps-select" name="status"><option value="">All status</option>@foreach($caseStatuses as $item)<option value="{{ $item }}" @selected($status === $item)>{{ str($item)->headline() }}</option>@endforeach</select></div>
        <div class="ps-field"><label>Project</label><select class="ps-select" name="project_id"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((int)$projectId === $project->id)>{{ $project->name }}</option>@endforeach</select></div>
        <div class="ps-field"><label>Rows</label><select class="ps-select" name="per_page">@foreach([100,250,500] as $size)<option value="{{ $size }}" @selected($perPage===$size)>{{ $size }}</option>@endforeach</select></div>
        <button class="ps-btn primary" type="submit"><i class="fas fa-filter"></i> Apply</button>
        <a class="ps-btn" href="{{ route('post-sales.index', ['tab' => $tab]) }}"><i class="fas fa-xmark"></i> Clear</a>
        <span class="ps-unsaved" id="psUnsaved">0 unsaved changes</span>
        <button class="ps-btn blue" type="button" id="psSaveAll" disabled><i class="fas fa-floppy-disk"></i> Save All</button>
        <button class="ps-btn" type="button" id="psReset" disabled><i class="fas fa-rotate-left"></i> Reset</button>
    </form>

    @if($tab === 'master')
        <section class="ps-sheet">
            <div class="ps-sheet-head"><strong>Customer Master</strong><span>Safe cells inline editable; financial values protected.</span><span class="meta">{{ number_format($cases->total()) }} cases</span></div>
            <div class="ps-table-wrap"><table class="ps-table"><thead><tr>
                <th class="freeze-1">#</th><th class="freeze-2">Customer / Case</th><th>Email</th><th>Project Mapping</th><th>Unit</th><th>Booking</th><th>Agreement</th><th>Collected</th><th>Collection %</th><th>KYC</th><th>Documents</th><th>Brokerage</th><th>Case Status</th><th>Next Due</th><th>Owner</th><th>Remark</th><th>Actions</th>
            </tr></thead><tbody>
            @forelse($cases as $case)
                @php $m=$case->finance_metrics; $nextDue=$case->demands()->whereIn('status',['upcoming','due','part_paid','overdue'])->orderBy('due_date')->first(); @endphp
                <tr>
                    <td data-label="#" class="freeze-1">{{ $case->id }}</td>
                    <td data-label="Customer / Case" class="freeze-2"><strong>{{ $case->customer_name }}</strong><br><small>{{ $case->case_number }} · {{ $case->customer_phone ?: '-' }}</small></td>
                    <td data-label="Email"><input class="ps-cell-input" value="{{ $case->customer_email }}" data-ps-edit data-type="case" data-id="{{ $case->id }}" data-field="customer_email" placeholder="Email missing"></td>
                    <td data-label="Project"><select class="ps-cell-select" data-ps-edit data-type="case" data-id="{{ $case->id }}" data-field="project_id"><option value="">Needs Mapping</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($case->project_id===$project->id)>{{ $project->name }}</option>@endforeach</select></td>
                    <td data-label="Unit">{{ $case->unit_label ?: '-' }}</td><td data-label="Booking">{{ optional($case->booking_date)->format('d M Y') ?: '-' }}</td>
                    <td data-label="Agreement" class="ps-money"><span class="ps-cell-readonly">{{ $money($case->agreement_value) }}</span></td>
                    <td data-label="Collected" class="ps-money">{{ $money($m['net_collected']) }}</td><td data-label="Collection %"><strong>{{ number_format($m['collection_percent'],2) }}%</strong></td>
                    <td data-label="KYC"><span class="ps-badge {{ $case->kyc_complete?'completed':'handover_pending' }}">{{ $case->kyc_complete?'Complete':'Pending' }}</span></td>
                    <td data-label="Documents">{{ $case->documents()->whereIn('status',['signed','completed'])->count() }}/{{ $case->documents()->count() }}</td>
                    <td data-label="Brokerage" class="ps-money">{{ $money($m['eligible_brokerage']) }}</td>
                    <td data-label="Status"><select class="ps-cell-select" data-ps-edit data-type="case" data-id="{{ $case->id }}" data-field="status">@foreach($caseStatuses as $item)@if($item !== 'cancelled' || $case->status === 'cancelled')<option value="{{ $item }}" @selected($case->status===$item) @disabled($item === 'cancelled')>{{ str($item)->headline() }}</option>@endif @endforeach</select></td>
                    <td data-label="Next Due">{{ optional($nextDue?->due_date)->format('d M Y') ?: '-' }}</td><td data-label="Owner">{{ $case->owner?->name ?: '-' }}</td>
                    <td data-label="Remark"><input class="ps-cell-input" value="{{ $case->internal_remark }}" data-ps-edit data-type="case" data-id="{{ $case->id }}" data-field="internal_remark" placeholder="Add remark"></td>
                    <td data-label="Actions"><button class="ps-btn" type="button" onclick="psOpenCase({{ $case->id }}, @js($case->customer_name), {{ $m['fresh_claimable'] }})"><i class="fas fa-ellipsis"></i></button></td>
                </tr>
            @empty <tr><td colspan="17"><div class="ps-empty">No Post Sales cases found.</div></td></tr>@endforelse
            </tbody></table></div>
            @include('post-sales.partials.pagination', ['pager' => $cases])
        </section>
    @elseif($tab === 'demands')
        <section class="ps-sheet"><div class="ps-sheet-head"><strong>Demand Schedule</strong><span>Customer emails disabled; test delivery fixed.</span><span class="meta">{{ number_format($demands->total()) }} demands</span></div>
            <div class="ps-table-wrap"><table class="ps-table"><thead><tr><th class="freeze-1">#</th><th class="freeze-2">Customer</th><th>Project</th><th>Demand</th><th>Type</th><th>Demand %</th><th>Amount</th><th>Due Date</th><th>Received</th><th>Balance</th><th>Status</th><th>Proof / Verify</th><th>Reminder</th><th>Action</th></tr></thead><tbody>
            @forelse($demands as $demand)
                @php
                    $verified = (float) $demand->transactions->where('status', 'verified')->where('type', 'payment')->sum('amount')
                        - (float) $demand->transactions->where('status', 'verified')->whereIn('type', ['refund', 'reversal'])->sum('amount');
                    $pending = $demand->transactions->where('status', 'pending');
                    $pendingPayload = $pending->values()->map(function ($transaction) {
                        return [
                            'id' => $transaction->id,
                            'type' => $transaction->type,
                            'amount' => $transaction->amount,
                            'date' => optional($transaction->transaction_date)->format('d M Y'),
                            'remark' => $transaction->remark,
                        ];
                    })->all();
                @endphp
                <tr><td data-label="#" class="freeze-1">{{ $demand->id }}</td><td data-label="Customer" class="freeze-2"><strong>{{ $demand->postSaleCase->customer_name }}</strong><br><small>{{ $demand->postSaleCase->case_number }}</small></td><td data-label="Project">{{ $demand->postSaleCase->project_name }}</td>
                    <td data-label="Demand"><input class="ps-cell-input" value="{{ $demand->title }}" data-ps-edit data-type="demand" data-id="{{ $demand->id }}" data-field="title"></td><td data-label="Type">{{ str($demand->amount_type)->headline() }}</td><td data-label="Demand %">{{ $demand->percentage ? number_format((float)$demand->percentage,2).'%' : '-' }}</td><td data-label="Amount" class="ps-money">{{ $money($demand->amount) }}</td>
                    <td data-label="Due Date"><input class="ps-cell-input" type="date" value="{{ optional($demand->due_date)->format('Y-m-d') }}" data-ps-edit data-type="demand" data-id="{{ $demand->id }}" data-field="due_date"></td><td data-label="Received" class="ps-money">{{ $money($verified) }}</td><td data-label="Balance" class="ps-money">{{ $money(max(0,(float)$demand->amount-$verified)) }}</td><td data-label="Status"><span class="ps-badge {{ $demand->status }}">{{ str($demand->status)->headline() }}</span></td>
                    <td data-label="Proof / Verify">@if($pending->count())<button class="ps-btn warn" type="button" onclick='psVerify(@js($pendingPayload))'>{{ $pending->count() }} pending</button>@else - @endif</td>
                    <td data-label="Reminder"><form method="POST" action="{{ route('post-sales.demands.send-test',$demand) }}">@csrf<button class="ps-btn" type="submit"><i class="fas fa-envelope"></i> Test only</button></form></td>
                    <td data-label="Action"><button class="ps-btn primary" type="button" onclick="psOpenTransaction({{ $demand->post_sale_case_id }}, {{ $demand->id }}, @js($demand->postSaleCase->customer_name))"><i class="fas fa-indian-rupee-sign"></i> Payment</button></td></tr>
            @empty<tr><td colspan="14"><div class="ps-empty">No demands found.</div></td></tr>@endforelse
            </tbody></table></div>@include('post-sales.partials.pagination',['pager'=>$demands])</section>
    @elseif($tab === 'documents')
        <section class="ps-sheet"><div class="ps-sheet-head"><strong>Document Tracker</strong><span class="meta">{{ number_format($documents->total()) }} rows</span></div><div class="ps-table-wrap"><table class="ps-table"><thead><tr><th class="freeze-1">#</th><th class="freeze-2">Customer</th><th>Project</th><th>Document</th><th>Status</th><th>Uploaded</th><th>Sent</th><th>Signed</th><th>File</th><th>Update</th></tr></thead><tbody>
        @forelse($documents as $document)<tr><td data-label="#" class="freeze-1">{{ $document->id }}</td><td data-label="Customer" class="freeze-2">{{ $document->postSaleCase->customer_name }}</td><td data-label="Project">{{ $document->postSaleCase->project_name }}</td><td data-label="Document"><strong>{{ $document->title }}</strong></td><td data-label="Status"><select class="ps-cell-select" data-ps-edit data-type="document" data-id="{{ $document->id }}" data-field="status">@foreach($documentStatuses as $item)<option value="{{ $item }}" @selected($document->status===$item)>{{ str($item)->headline() }}</option>@endforeach</select></td><td data-label="Uploaded">{{ optional($document->uploaded_at)->format('d M Y') ?: '-' }}</td><td data-label="Sent">{{ optional($document->sent_at)->format('d M Y') ?: '-' }}</td><td data-label="Signed">{{ optional($document->signed_at)->format('d M Y') ?: '-' }}</td><td data-label="File">@if($document->file_path)<a class="ps-btn" href="{{ route('post-sales.files.download',['type'=>'document','id'=>$document->id]) }}"><i class="fas fa-download"></i></a>@else - @endif</td><td data-label="Update"><button class="ps-btn" type="button" onclick="psOpenDocument({{ $document->id }}, @js($document->title), @js($document->status))"><i class="fas fa-upload"></i> Upload</button></td></tr>
        @empty<tr><td colspan="10"><div class="ps-empty">No document rows found.</div></td></tr>@endforelse</tbody></table></div>@include('post-sales.partials.pagination',['pager'=>$documents])</section>
    @elseif($tab === 'receivables')
        <section class="ps-sheet"><div class="ps-sheet-head"><strong>Builder Eligibility</strong><span>Computed from verified customer collection and case slab snapshot.</span></div><div class="ps-table-wrap"><table class="ps-table"><thead><tr><th class="freeze-1">#</th><th class="freeze-2">Customer</th><th>Builder</th><th>Project</th><th>Collection %</th><th>Slab %</th><th>Revenue Value</th><th>Eligible</th><th>Claimed</th><th>Received</th><th>Outstanding</th><th>Warning</th><th>Action</th></tr></thead><tbody>
        @forelse($cases as $case)@php $m=$case->finance_metrics; @endphp<tr><td data-label="#" class="freeze-1">{{ $case->id }}</td><td data-label="Customer" class="freeze-2">{{ $case->customer_name }}</td><td data-label="Builder">{{ $case->builder?->name ?: 'Needs Mapping' }}</td><td data-label="Project">{{ $case->project_name }}</td><td data-label="Collection %">{{ number_format($m['collection_percent'],2) }}%</td><td data-label="Slab %">{{ number_format($m['release_percent'],2) }}%</td><td data-label="Revenue" class="ps-money">{{ $money($case->revenue_value) }}</td><td data-label="Eligible" class="ps-money">{{ $money($m['eligible_brokerage']) }}</td><td data-label="Claimed" class="ps-money">{{ $money($m['claimed']) }}</td><td data-label="Received" class="ps-money">{{ $money($m['received']) }}</td><td data-label="Outstanding" class="ps-money">{{ $money($m['outstanding']) }}</td><td data-label="Warning">@if($m['over_claimed'])<span class="ps-badge over_claimed">Over-claimed review</span>@else - @endif</td><td data-label="Action"><button class="ps-btn primary" @disabled($m['fresh_claimable']<=0) type="button" onclick="psOpenClaim({{ $case->id }}, @js($case->customer_name), {{ $m['fresh_claimable'] }})">Raise Claim</button></td></tr>@empty<tr><td colspan="13"><div class="ps-empty">No eligible cases.</div></td></tr>@endforelse</tbody></table></div>@include('post-sales.partials.pagination',['pager'=>$cases])</section>
        <form class="ps-sheet" method="POST" action="{{ route('post-sales.invoices.store') }}" style="margin-top:10px">@csrf<div class="ps-sheet-head"><strong>Claims & Receipts</strong><span>Select same builder/project claims for one-click invoice draft.</span><button class="ps-btn blue" type="submit" style="margin-left:auto"><i class="fas fa-file-invoice"></i> Create Invoice Draft</button></div><div class="ps-table-wrap"><table class="ps-table"><thead><tr><th class="freeze-1"><input type="checkbox" data-check-all></th><th class="freeze-2">Customer</th><th>Builder / Project</th><th>Claim Date</th><th>Claim</th><th>GST</th><th>TDS</th><th>Received</th><th>Expected</th><th>Status</th><th>Invoice</th><th>Action</th></tr></thead><tbody>
        @forelse($claims as $claim)@php $received=(float)$claim->receipts->sum('amount');$invoice=$claim->invoiceItems->first()?->invoice; @endphp<tr><td data-label="Select" class="freeze-1"><input type="checkbox" name="claim_ids[]" value="{{ $claim->id }}" @disabled($invoice && $invoice->status!=='void')></td><td data-label="Customer" class="freeze-2">{{ $claim->postSaleCase->customer_name }}</td><td data-label="Builder / Project">{{ $claim->postSaleCase->builder?->name }} / {{ $claim->postSaleCase->project_name }}</td><td data-label="Claim Date">{{ optional($claim->claim_date)->format('d M Y') }}</td><td data-label="Claim" class="ps-money">{{ $money($claim->claim_amount) }}</td><td data-label="GST" class="ps-money">{{ $money($claim->gst_amount) }}</td><td data-label="TDS" class="ps-money">{{ $money($claim->tds_amount) }}</td><td data-label="Received" class="ps-money">{{ $money($received) }}</td><td data-label="Expected"><input class="ps-cell-input" type="date" value="{{ optional($claim->expected_date)->format('Y-m-d') }}" data-ps-edit data-type="claim" data-id="{{ $claim->id }}" data-field="expected_date"></td><td data-label="Status"><span class="ps-badge {{ $claim->status }}">{{ str($claim->status)->headline() }}</span></td><td data-label="Invoice">@if($invoice)<a class="ps-btn" href="{{ route('post-sales.invoices.edit',$invoice) }}">{{ $invoice->invoice_number }}</a>@else - @endif</td><td data-label="Action"><button class="ps-btn" type="button" onclick="psOpenReceipt({{ $claim->id }}, @js($claim->postSaleCase->customer_name), {{ max(0,(float)$claim->claim_amount-$received) }})">Receipt</button></td></tr>@empty<tr><td colspan="12"><div class="ps-empty">No builder claims raised.</div></td></tr>@endforelse</tbody></table></div></form>
    @elseif($tab === 'builder-desk')
        <div class="ps-panel-grid">
            <section class="ps-panel"><h3><i class="fas fa-calendar-check"></i> Project Payment Plan <button class="ps-btn" type="button" onclick="psOpenQuickProject()"><i class="fas fa-plus"></i> Add Project</button></h3><form method="POST" action="{{ route('post-sales.templates.store') }}" data-loading>@csrf<div class="ps-form-grid"><div class="ps-field"><label>Project</label><select class="ps-select" name="project_id" required><option value="">Select</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((int) request('selected_project') === $project->id)>{{ $project->name }}</option>@endforeach</select></div><div class="ps-field"><label>Plan Name</label><input class="ps-input" name="name" required placeholder="Construction Linked Plan"></div></div><div id="psPlanRows" style="margin-top:10px"><div class="ps-plan-row"><input class="ps-input" name="items[0][title]" placeholder="Demand title" required><select class="ps-select" name="items[0][amount_type]"><option value="percentage">%</option><option value="fixed">Fixed</option></select><input class="ps-input" name="items[0][percentage]" type="number" step="0.001" placeholder="% / amount"><input type="hidden" name="items[0][fixed_amount]"><select class="ps-select" name="items[0][due_rule]"><option value="relative_days">Days after booking</option><option value="fixed_date">Fixed date</option><option value="milestone">Milestone</option></select><input class="ps-input" name="items[0][relative_days]" type="number" placeholder="Days"></div></div><div style="display:flex;gap:8px;margin-top:9px"><button type="button" class="ps-btn" data-add-plan-row><i class="fas fa-plus"></i> Row</button><button class="ps-btn primary" type="submit">Save New Version</button></div></form></section>
            <section class="ps-panel"><h3><i class="fas fa-clock-rotate-left"></i> Active Plan Versions</h3>@forelse($templates as $template)<div class="ps-invoice-row"><div class="main"><strong>{{ $template->project?->name ?: $template->name }} · v{{ $template->version }}</strong><small>{{ $template->items->count() }} demands · {{ $template->is_active?'Active':'Archived' }}</small></div></div>@empty<div class="ps-empty">No project plan saved yet.</div>@endforelse</section>
        </div>
        <section class="ps-sheet" style="margin-top:10px"><div class="ps-sheet-head"><strong>Builder Brokerage Release Plans</strong><span>Changes apply only to future case snapshots.</span></div><div class="ps-panel-grid" style="padding:10px">@foreach($schemes as $scheme)<form class="ps-panel" method="POST" action="{{ route('post-sales.schemes.update',$scheme) }}" data-loading>@csrf @method('PUT')<h3>{{ $scheme->builder_name }} {{ $scheme->project_name }} <span class="ps-badge {{ $scheme->setup_status }}">{{ str($scheme->setup_status)->headline() }}</span></h3><div class="ps-form-grid"><div class="ps-field"><label>Builder</label><select class="ps-select" name="builder_id"><option value="">Needs mapping</option>@foreach($builders as $builder)<option value="{{ $builder->id }}" @selected($scheme->builder_id===$builder->id)>{{ $builder->name }}</option>@endforeach</select></div><div class="ps-field"><label>Project override</label><select class="ps-select" name="project_id"><option value="">Builder default</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($scheme->project_id===$project->id)>{{ $project->name }}</option>@endforeach</select></div></div><input type="hidden" name="is_active" value="0"><label style="display:flex;gap:8px;align-items:center;margin:9px 0"><input type="checkbox" name="is_active" value="1" @checked($scheme->is_active)> Active</label>@foreach($scheme->slabs as $i=>$slab)<div class="ps-slab-row"><div class="ps-field"><label>Customer %</label><input class="ps-input" name="slabs[{{ $i }}][collection]" value="{{ $slab->customer_collection_percent }}" required></div><div class="ps-field"><label>Release %</label><input class="ps-input" name="slabs[{{ $i }}][release]" value="{{ $slab->brokerage_release_percent }}" required></div></div>@endforeach @if($scheme->slabs->isEmpty())<div class="ps-slab-row"><input class="ps-input" name="slabs[0][collection]" placeholder="Customer %" required><input class="ps-input" name="slabs[0][release]" placeholder="Release %" required></div>@endif<button class="ps-btn primary" type="submit">Save Plan</button></form>@endforeach</div></section>
        <section class="ps-sheet" style="margin-top:10px"><div class="ps-sheet-head"><strong>Builder Invoices</strong><span>Draft editable; issued changes create a revision.</span></div><div style="padding:0 12px">@forelse($invoices as $invoice)<div class="ps-invoice-row"><div class="main"><strong>{{ $invoice->invoice_number }} · {{ $invoice->buyer_name }}</strong><small>{{ optional($invoice->invoice_date)->format('d M Y') }} · {{ $invoice->items->count() }} claim(s) · Revision {{ $invoice->revision_no }}</small></div><span class="ps-badge {{ $invoice->status }}">{{ str($invoice->status)->headline() }}</span><strong class="ps-money">{{ $money($invoice->net_receivable) }}</strong><a class="ps-btn" href="{{ route('post-sales.invoices.edit',$invoice) }}"><i class="fas fa-pen"></i> Open</a>@if($invoice->status==='issued')<a class="ps-btn" href="{{ route('post-sales.invoices.download',$invoice) }}"><i class="fas fa-download"></i></a>@endif</div>@empty<div class="ps-empty">Claims select karke invoice draft create karein.</div>@endforelse</div></section>
    @else
        <div class="ps-panel-grid"><section class="ps-panel"><h3>Collection Overview</h3><div class="ps-kpi"><span>Cases</span><strong>{{ number_format($summary['total_cases']) }}</strong></div><div class="ps-kpi"><span>Active</span><strong>{{ number_format($summary['active_cases']) }}</strong></div><div class="ps-kpi"><span>Overdue demands</span><strong>{{ number_format($summary['overdue_demands']) }}</strong></div></section><section class="ps-panel"><h3>Builder Overview</h3><div class="ps-kpi"><span>Outstanding</span><strong>{{ $money($summary['builder_outstanding']) }}</strong></div><p>Detailed filtered export ke liye Export Full use karein.</p></section><section class="ps-panel"><h3>Mail Safety</h3><span class="ps-badge handover_pending">Customer delivery OFF</span><p>Test messages only: {{ $summary['test_recipient'] }}</p></section></div>
    @endif
</div>

@include('post-sales.partials.dialogs')
<div class="ps-loading" id="psLoading"><div class="ps-loading-box"><span class="ps-spinner"></span> Saving finance data...</div></div>
@endsection

@push('scripts')
<script src="{{ asset('js/post-sales.js') }}?v={{ file_exists(public_path('js/post-sales.js')) ? filemtime(public_path('js/post-sales.js')) : time() }}"></script>
@endpush
