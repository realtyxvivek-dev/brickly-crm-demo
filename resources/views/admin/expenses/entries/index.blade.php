@extends('layouts.app')

@section('title', 'Expense Entries')
@section('page-title', 'Expense Entries')

@push('styles')
<style>
    .expense-ledger-page {
        --expense-green: #063A1C;
        --expense-green-2: #205A44;
        --expense-border: #E5DED4;
        --expense-muted: #64748B;
        --expense-soft: #F8FAF7;
    }

    .expense-ledger-table-card {
        border-radius: 18px;
        border: 1px solid var(--expense-border);
        background: #fff;
        box-shadow: 0 14px 35px rgba(15, 23, 42, 0.05);
        overflow: hidden;
    }

    .expense-ledger-scroll {
        overflow-x: auto;
    }

    .expense-ledger-table {
        width: 100%;
        min-width: 1320px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
    }

    .expense-ledger-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #FAFBFA;
        border-bottom: 1px solid var(--expense-border);
        color: #52605B;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.06em;
        padding: 14px 12px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .expense-ledger-table tbody td {
        border-bottom: 1px solid #EDF0EE;
        color: #0F172A;
        padding: 13px 12px;
        vertical-align: top;
    }

    .expense-ledger-table tbody tr:hover {
        background: var(--expense-soft);
    }

    .expense-ledger-primary {
        color: var(--expense-green);
        font-weight: 800;
        white-space: nowrap;
    }

    .expense-ledger-subtext {
        color: var(--expense-muted);
        font-size: 12px;
        margin-top: 2px;
    }

    .expense-ledger-status {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        font-size: 11px;
        font-weight: 800;
        gap: 6px;
        letter-spacing: 0.04em;
        padding: 5px 10px;
        text-transform: uppercase;
    }

    .expense-ledger-status.approved {
        background: #E7F7EF;
        color: #047857;
    }

    .expense-ledger-status.draft {
        background: #FEF3C7;
        color: #92400E;
    }

    .expense-ledger-status.rejected {
        background: #FEE2E2;
        color: #B91C1C;
    }

    .expense-ledger-action-menu {
        min-width: 128px;
        position: relative;
    }

    .expense-ledger-action-menu summary {
        list-style: none;
    }

    .expense-ledger-action-menu summary::-webkit-details-marker {
        display: none;
    }

    .expense-ledger-action-panel {
        background: #fff;
        border: 1px solid var(--expense-border);
        border-radius: 12px;
        box-shadow: 0 18px 42px rgba(15, 23, 42, 0.14);
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 8px;
        padding: 10px;
        position: absolute;
        right: 0;
        top: 100%;
        width: 176px;
        z-index: 20;
    }

    .expense-ledger-btn {
        align-items: center;
        border-radius: 9px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        gap: 7px;
        justify-content: center;
        line-height: 1;
        min-height: 34px;
        padding: 9px 12px;
        text-decoration: none;
        transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        white-space: nowrap;
    }

    .expense-ledger-btn:hover {
        transform: translateY(-1px);
    }

    .expense-ledger-btn.primary {
        background: var(--expense-green-2);
        color: #fff;
    }

    .expense-ledger-btn.soft {
        background: #EEF7F2;
        border: 1px solid #CFE3D8;
        color: var(--expense-green);
    }

    .expense-ledger-btn.warning {
        background: #FFF7ED;
        border: 1px solid #FED7AA;
        color: #9A3412;
    }

    .expense-ledger-btn.danger {
        background: #FFF1F2;
        border: 1px solid #FECDD3;
        color: #B91C1C;
        cursor: pointer;
    }

    .expense-ledger-delete summary {
        list-style: none;
    }

    .expense-ledger-delete summary::-webkit-details-marker {
        display: none;
    }

    .expense-ledger-delete-box {
        background: #FFF7F7;
        border: 1px solid #FECACA;
        border-radius: 12px;
        margin-top: 8px;
        padding: 12px;
        width: 260px;
    }

    .expense-ledger-delete-title {
        color: #991B1B;
        font-size: 12px;
        font-weight: 900;
        margin-bottom: 8px;
    }

    .expense-ledger-input {
        border: 1px solid #FCA5A5;
        border-radius: 9px;
        font-size: 12px;
        outline: none;
        padding: 9px 10px;
        width: 100%;
    }

    .expense-ledger-input:focus {
        border-color: #DC2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
    }

    .expense-ledger-footer {
        align-items: center;
        background: #FAFBFA;
        border-top: 1px solid var(--expense-border);
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        justify-content: space-between;
        padding: 14px 16px;
    }

    .expense-ledger-page-size {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .expense-ledger-page-size-label {
        color: var(--expense-muted);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .expense-ledger-page-size-btn {
        border: 1px solid #D7E4DC;
        border-radius: 999px;
        color: var(--expense-green);
        font-size: 12px;
        font-weight: 800;
        min-height: 34px;
        padding: 8px 13px;
    }

    .expense-ledger-page-size-btn.active {
        background: var(--expense-green-2);
        border-color: var(--expense-green-2);
        color: #fff;
    }

    @media (max-width: 768px) {
        .expense-ledger-table-card {
            border-radius: 14px;
        }

        .expense-ledger-table {
            min-width: 1180px;
        }
    }
</style>
@endpush

@section('content')
<div class="expense-ledger-page w-full space-y-6">
    @include('attendance._flash')
    @include('admin.expenses._nav')

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">Expense Ledger</h2>
                <p class="text-sm text-[#6B7280]">Company, category, subcategory aur vendor/person wise clean expense record.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.expenses.entries.export', request()->query()) }}" class="px-5 py-2 border border-[#E5DED4] rounded-lg text-brand-primary">Export CSV</a>
                <a href="{{ route('admin.expenses.entries.create') }}" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">Add Expense</a>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.expenses.entries.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="md:col-span-2 relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-[#6B7280] text-sm"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search entry ID, paid to, ref, amount" class="w-full pl-10 pr-4 py-2 border border-[#E5DED4] rounded-lg">
            </div>
            <select name="company_id" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
                <option value="">All companies</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected((string) $filters['company_id'] === (string) $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <select name="expense_category_id" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $filters['expense_category_id'] === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="expense_subcategory_id" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
                <option value="">All subcategories</option>
                @foreach($subcategories as $subcategory)
                    <option value="{{ $subcategory->id }}" @selected((string) $filters['expense_subcategory_id'] === (string) $subcategory->id)>{{ $subcategory->category?->name }} - {{ $subcategory->name }}</option>
                @endforeach
            </select>
            <select name="status" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
                <option value="">All status</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected((string) $filters['status'] === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
            <div class="md:col-span-6 flex flex-wrap gap-3">
                <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">Apply Filters</button>
                <a href="{{ route('admin.expenses.entries.index') }}" class="px-5 py-2 border border-[#E5DED4] rounded-lg text-brand-primary">Reset</a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5">
            <div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Total Amount</div>
            <div class="text-3xl font-bold text-brand-primary mt-2">Rs {{ number_format($summary['total_amount'], 2) }}</div>
            <div class="text-sm text-[#6B7280] mt-2">{{ $summary['entry_count'] }} entries in current view</div>
            <div class="text-sm text-amber-700 mt-1">{{ $summary['pending_count'] }} pending in approval queue</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5">
            <div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Top Companies</div>
            <div class="space-y-2 mt-3 text-sm">
                @forelse($summary['company_totals']->take(3) as $row)
                    <div class="flex items-center justify-between gap-3"><span>{{ $row->company?->name }}</span><strong>Rs {{ number_format((float) $row->total_amount, 2) }}</strong></div>
                @empty
                    <div class="text-[#6B7280]">No data</div>
                @endforelse
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5">
            <div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Top Categories</div>
            <div class="space-y-2 mt-3 text-sm">
                @forelse($summary['category_totals']->take(3) as $row)
                    <div class="flex items-center justify-between gap-3"><span>{{ $row->category?->name }}</span><strong>Rs {{ number_format((float) $row->total_amount, 2) }}</strong></div>
                @empty
                    <div class="text-[#6B7280]">No data</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="expense-ledger-table-card">
        <div class="expense-ledger-scroll">
        <table class="expense-ledger-table">
            <thead>
                <tr class="text-left">
                    <th>Date</th>
                    <th>Entry ID</th>
                    <th>Company</th>
                    <th>Category</th>
                    <th>Paid To</th>
                    <th>Amount</th>
                    <th>Mode</th>
                    <th>Ref</th>
                    <th>Status</th>
                    <th>Attachment</th>
                    <th>Created By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td class="expense-ledger-primary">{{ optional($entry->expense_date)->format('d M Y') }}</td>
                    <td><span class="expense-ledger-status draft">EXP-{{ str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT) }}</span></td>
                    <td>{{ $entry->company?->name }}</td>
                    <td><strong>{{ $entry->category?->name }}</strong><div class="expense-ledger-subtext">{{ $entry->subcategory?->name }}</div></td>
                    <td>{{ $entry->paid_to ?: '-' }}</td>
                    <td class="expense-ledger-primary">Rs {{ number_format((float) $entry->amount, 2) }}</td>
                    <td>{{ $entry->paymentDisplay() }}</td>
                    <td>{{ $entry->reference_no ?: '-' }}</td>
                    <td>
                        <span class="expense-ledger-status {{ $entry->status }}">{{ ucfirst($entry->status) }}</span>
                        @if($entry->assignedApprover)
                            <div class="expense-ledger-subtext">Assigned to {{ $entry->assignedApprover->name }}</div>
                        @endif
                        @if($entry->status === 'rejected' && $entry->reject_reason)
                            <div class="text-xs text-red-600 mt-2">{{ $entry->reject_reason }}</div>
                            <div class="expense-ledger-subtext text-red-500">Rejected by {{ $entry->rejector?->name ?: 'Approver' }} on {{ optional($entry->rejected_at)->format('d M Y H:i') ?: 'N/A' }}</div>
                        @elseif($entry->status === 'approved' && $entry->approver)
                            <div class="expense-ledger-subtext text-emerald-700">By {{ $entry->approver->name }} on {{ optional($entry->approved_at)->format('d M Y H:i') }}</div>
                        @endif
                    </td>
                    <td>
                        @if($entry->attachment_path)
                            <a href="{{ route('admin.expenses.entries.attachment.download', $entry) }}" class="expense-ledger-btn soft"><i class="fas fa-download"></i> Download</a>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $entry->creator?->name ?: '-' }}</td>
                    <td>
                        <details class="expense-ledger-action-menu">
                            <summary class="expense-ledger-btn primary"><i class="fas fa-ellipsis-v"></i> Action</summary>
                            <div class="expense-ledger-action-panel">
                                <a href="{{ route('admin.expenses.entries.edit', $entry) }}" class="expense-ledger-btn soft"><i class="fas fa-pen"></i> {{ $entry->status === 'rejected' ? 'Resubmit' : 'Edit' }}</a>
                                @if($entry->status === 'draft')
                                    <a href="{{ route('admin.expenses.queue') }}" class="expense-ledger-btn warning"><i class="fas fa-clipboard-check"></i> Review</a>
                                @endif
                                <details class="expense-ledger-delete">
                                    <summary class="expense-ledger-btn danger"><i class="fas fa-trash"></i> Delete</summary>
                                    <form method="POST" action="{{ route('admin.expenses.entries.destroy', $entry) }}" class="expense-ledger-delete-box space-y-2">
                                        @csrf
                                        @method('DELETE')
                                        <div class="expense-ledger-delete-title">Delete Rs {{ number_format((float) $entry->amount, 2) }}</div>
                                        <input type="password" name="delete_password" placeholder="Delete password" class="expense-ledger-input" required>
                                        <textarea name="delete_reason" placeholder="Reason required" class="expense-ledger-input" rows="2" required></textarea>
                                        <button type="submit" class="expense-ledger-btn danger w-full bg-red-700 text-white" onclick="return confirm('Is expense entry ko delete karna hai?')"><i class="fas fa-check"></i> Confirm Delete</button>
                                    </form>
                                </details>
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="py-4 text-[#6B7280]">No expenses found.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div class="expense-ledger-footer">
            <div class="expense-ledger-page-size">
                <span class="expense-ledger-page-size-label">Rows per page</span>
                @foreach($perPageOptions as $value => $label)
                    <form method="GET" action="{{ route('admin.expenses.entries.index') }}">
                        @foreach(request()->except(['page', 'per_page']) as $key => $valueFromRequest)
                            @if(is_array($valueFromRequest))
                                @foreach($valueFromRequest as $nestedValue)
                                    <input type="hidden" name="{{ $key }}[]" value="{{ $nestedValue }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $valueFromRequest }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="per_page" value="{{ $value }}">
                        <button type="submit" class="expense-ledger-page-size-btn {{ (string) $perPage === (string) $value ? 'active' : '' }}">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
            <div>{{ $entries->links() }}</div>
        </div>
    </div>
</div>
@endsection
