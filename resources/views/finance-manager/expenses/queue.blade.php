@extends('finance-manager.layout')

@section('title', 'Expense Approval Queue')
@section('page_title', 'Expense Approval Queue')
@section('page_subtitle', 'Draft entries ko yahan review karo, approve karo, ya reject reason ke saath return karo.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
<div class="expense-stack">
    @include('finance-manager.expenses._nav')

    <section class="expense-card">
        <div class="expense-header">
            <div>
                <h2>Draft Approval Queue</h2>
                <p>Sirf draft entries yahan dikhengi. Approve karne par ledger final ho jayega, reject karne par reason save hoga.</p>
            </div>
            <a href="{{ route('finance-manager.expenses.entries.create') }}" class="expense-btn primary"><i class="fas fa-plus"></i> Add Expense</a>
        </div>

        <form method="GET" action="{{ route('finance-manager.expenses.queue') }}" class="expense-form-grid" style="grid-template-columns:repeat(5,minmax(0,1fr));">
            <div class="expense-field">
                <label>Company</label>
                <select name="company_id" class="expense-input">
                    <option value="">All companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) $filters['company_id'] === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field">
                <label>Category</label>
                <select name="expense_category_id" class="expense-input">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['expense_category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field"><label>Date From</label><input class="expense-input" type="date" name="date_from" value="{{ $filters['date_from'] }}"></div>
            <div class="expense-field"><label>Date To</label><input class="expense-input" type="date" name="date_to" value="{{ $filters['date_to'] }}"></div>
            <div class="expense-field" style="justify-content:flex-end;display:flex;align-items:end;gap:12px;">
                <button type="submit" class="expense-btn primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="{{ route('finance-manager.expenses.queue') }}" class="expense-btn soft">Reset</a>
            </div>
        </form>
    </section>

    @forelse($entries as $entry)
        <section class="expense-card">
            <div class="expense-header">
                <div>
                    <h2>{{ $entry->company?->name }} / {{ $entry->category?->name }} / {{ $entry->subcategory?->name }}</h2>
                    <p>{{ optional($entry->expense_date)->format('d M Y') }} | Paid to {{ $entry->paid_to ?: 'N/A' }} | {{ $entry->paymentDisplay() }} | Ref {{ $entry->reference_no ?: '-' }}</p>
                </div>
                <div style="text-align:right;">
                    <div class="expense-kpi-label">Amount</div>
                    <div class="expense-kpi-value">Rs {{ number_format((float) $entry->amount, 2) }}</div>
                </div>
            </div>

                <div class="expense-dual-grid" style="grid-template-columns:1.15fr .85fr;">
                <div class="expense-card" style="padding:20px;background:#f8faf8;">
                    <div class="expense-mini-list">
                        <div class="expense-mini-item">
                            <div><strong>Created By</strong><span>{{ $entry->creator?->name ?: '-' }}</span></div>
                            <div class="expense-mini-value">{{ ucfirst($entry->status) }}</div>
                        </div>
                        <div class="expense-mini-item">
                            <div>
                                <strong>Assigned Approver</strong>
                                <span>{{ $entry->assignedApprover?->name ?: 'Unassigned' }}</span>
                            </div>
                            <div class="expense-mini-value">{{ $entry->approval_assigned_at ? optional($entry->approval_assigned_at)->format('d M Y') : 'Pending' }}</div>
                        </div>
                        <div class="expense-mini-item">
                            <div><strong>Remarks</strong><span>{{ $entry->remarks ?: 'No remarks provided' }}</span></div>
                            @if($entry->attachment_path)
                                <div><a href="{{ route('finance-manager.expenses.entries.attachment.download', $entry) }}" class="expense-btn soft" style="padding:10px 14px;">Attachment</a></div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="expense-card" style="padding:20px;background:#fff9eb;border-color:#f2d79d;">
                    <div class="expense-header" style="margin-bottom:14px;">
                        <div>
                            <h2 style="font-size:20px;">Approval Action</h2>
                            <p>Reject karte waqt reason required hai.</p>
                        </div>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                        <form method="POST" action="{{ route('finance-manager.expenses.entries.approve', $entry) }}">
                            @csrf
                            <button type="submit" class="expense-btn primary"><i class="fas fa-check"></i> Approve</button>
                        </form>
                        <a href="{{ route('finance-manager.expenses.entries.edit', $entry) }}" class="expense-btn soft">Edit Entry</a>
                    </div>
                    <form method="POST" action="{{ route('finance-manager.expenses.entries.reject', $entry) }}">
                        @csrf
                        <div class="expense-field">
                            <label>Reject Reason</label>
                            <textarea name="reject_reason" rows="3" class="expense-input" placeholder="Why is this expense being rejected?">{{ old('reject_reason') }}</textarea>
                        </div>
                        <button type="submit" class="expense-btn soft" style="background:#b42318;color:#fff;border-color:#b42318;"><i class="fas fa-xmark"></i> Reject</button>
                    </form>
                </div>
            </div>
        </section>
    @empty
        <section class="expense-card">
            <div class="expense-header">
                <div>
                    <h2>Queue Empty</h2>
                    <p>Abhi koi draft expense approval ke liye pending nahi hai.</p>
                </div>
            </div>
        </section>
    @endforelse

    <div>{{ $entries->links() }}</div>
</div>
@endsection
