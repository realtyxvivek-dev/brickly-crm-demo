@extends('finance-manager.layout')

@section('title', 'Expense Ledger')
@section('page_title', 'Expense Ledger')
@section('page_subtitle', 'Track company-wise expense entries with category, subcategory, vendor, and reference detail.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
<div class="expense-stack">
    @include('finance-manager.expenses._nav')

    <section class="expense-card">
        <div class="expense-header">
            <div>
                <h2>Expense Filters</h2>
                <p>Company, category, subcategory, status, aur date-wise filtered ledger with clean finance-ready totals.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="{{ route('finance-manager.expenses.entries.export', request()->query()) }}" class="expense-btn soft"><i class="fas fa-file-csv"></i> Export CSV</a>
                <a href="{{ route('finance-manager.expenses.entries.create') }}" class="expense-btn primary"><i class="fas fa-plus"></i> Add Expense</a>
            </div>
        </div>
        <form method="GET" action="{{ route('finance-manager.expenses.entries.index') }}" class="expense-form-grid" style="grid-template-columns:repeat(6,minmax(0,1fr));">
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
            <div class="expense-field">
                <label>Subcategory</label>
                <select name="expense_subcategory_id" class="expense-input">
                    <option value="">All subcategories</option>
                    @foreach($subcategories as $subcategory)
                        <option value="{{ $subcategory->id }}" @selected((string) $filters['expense_subcategory_id'] === (string) $subcategory->id)>{{ $subcategory->category?->name }} - {{ $subcategory->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field">
                <label>Status</label>
                <select name="status" class="expense-input">
                    <option value="">All status</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected((string) $filters['status'] === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field"><label>Date From</label><input class="expense-input" type="date" name="date_from" value="{{ $filters['date_from'] }}"></div>
            <div class="expense-field"><label>Date To</label><input class="expense-input" type="date" name="date_to" value="{{ $filters['date_to'] }}"></div>
            <button type="submit" class="expense-btn primary"><i class="fas fa-filter"></i> Apply</button>
            <a href="{{ route('finance-manager.expenses.entries.index') }}" class="expense-btn soft"><i class="fas fa-rotate-left"></i> Reset</a>
        </form>
    </section>

    <section class="expense-kpis">
        <div class="expense-kpi">
            <div class="expense-kpi-label">Total Amount</div>
            <div class="expense-kpi-value">Rs {{ number_format($summary['total_amount'], 2) }}</div>
            <div class="expense-kpi-note">{{ $summary['entry_count'] }} entries in current filter set</div>
        </div>
        <div class="expense-kpi">
            <div class="expense-kpi-label">Pending Approval</div>
            <div class="expense-kpi-value">{{ $summary['pending_count'] }}</div>
            <div class="expense-kpi-note">Draft entries waiting in queue</div>
            <div class="expense-kpi-note expense-kpi-note-danger">{{ $summary['rejected_count'] }} rejected need correction</div>
        </div>
        <div class="expense-kpi">
            <div class="expense-kpi-label">Top Company</div>
            @php $topCompany = $summary['company_totals']->first(); @endphp
            <div class="expense-kpi-value">{{ $topCompany?->company?->name ?? 'N/A' }}</div>
            <div class="expense-kpi-note">{{ $topCompany ? 'Rs ' . number_format((float) $topCompany->total_amount, 2) : 'No company total yet' }}</div>
        </div>
        <div class="expense-kpi">
            <div class="expense-kpi-label">Top Category</div>
            @php $topCategory = $summary['category_totals']->first(); @endphp
            <div class="expense-kpi-value">{{ $topCategory?->category?->name ?? 'N/A' }}</div>
            <div class="expense-kpi-note">{{ $topCategory ? 'Rs ' . number_format((float) $topCategory->total_amount, 2) : 'No category total yet' }}</div>
        </div>
    </section>

    <section class="expense-dual-grid">
        <div class="expense-card">
            <div class="expense-header">
                <div>
                    <h2>Company Totals</h2>
                    <p>Current filtered view me kis company par expense load zyada hai.</p>
                </div>
            </div>
            <div class="expense-mini-list">
                @forelse($summary['company_totals']->take(6) as $row)
                    <div class="expense-mini-item">
                        <div><strong>{{ $row->company?->name }}</strong><span>Expense total</span></div>
                        <div class="expense-mini-value">Rs {{ number_format((float) $row->total_amount, 2) }}</div>
                    </div>
                @empty
                    <div class="expense-mini-item"><div><strong>No company totals</strong><span>Filtered data unavailable.</span></div></div>
                @endforelse
            </div>
        </div>
        <div class="expense-card">
            <div class="expense-header">
                <div>
                    <h2>Category Totals</h2>
                    <p>Global category structure ke against expense breakup.</p>
                </div>
            </div>
            <div class="expense-mini-list">
                @forelse($summary['category_totals']->take(6) as $row)
                    <div class="expense-mini-item">
                        <div><strong>{{ $row->category?->name }}</strong><span>Expense total</span></div>
                        <div class="expense-mini-value">Rs {{ number_format((float) $row->total_amount, 2) }}</div>
                    </div>
                @empty
                    <div class="expense-mini-item"><div><strong>No category totals</strong><span>Filtered data unavailable.</span></div></div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="expense-card">
        <div class="expense-header">
            <div>
                <h2>Expense Entries</h2>
                <p>Audit-ready list with paid-to, reference number, payment mode, and creator detail.</p>
            </div>
        </div>
        <div class="expense-table-wrap">
            <table class="expense-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Company</th>
                        <th>Category</th>
                        <th>Paid To</th>
                        <th>Amount</th>
                        <th>Mode</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Attachment</th>
                        <th>Creator</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($entries as $entry)
                    @php
                        $isRejected = $entry->status === 'rejected';
                        $isRejectedOwner = $isRejected && (int) $entry->created_by === (int) auth()->id();
                    @endphp
                    <tr class="{{ $isRejected ? 'expense-row-rejected' : '' }}">
                        <td>{{ optional($entry->expense_date)->format('d M Y') }}</td>
                        <td>{{ $entry->company?->name }}</td>
                        <td><strong>{{ $entry->category?->name }}</strong><br><span style="font-size:12px;color:#697771;">{{ $entry->subcategory?->name }}</span></td>
                        <td>{{ $entry->paid_to ?: '-' }}</td>
                        <td><strong>Rs {{ number_format((float) $entry->amount, 2) }}</strong></td>
                        <td>
                            <strong>{{ $entry->paymentDisplay() }}</strong>
                            @if($entry->paymentMethod?->details)
                                <div style="font-size:12px;color:#697771;">{{ $entry->paymentMethod->details }}</div>
                            @endif
                        </td>
                        <td>{{ $entry->reference_no ?: '-' }}</td>
                        <td>
                            <span class="expense-status-badge {{ $isRejected ? 'rejected' : ($entry->status === 'approved' ? 'approved' : 'draft') }}">{{ ucfirst($entry->status) }}</span>
                            @if($entry->assignedApprover)
                                <div class="expense-status-meta">Assigned to {{ $entry->assignedApprover->name }}</div>
                            @endif
                            @if($isRejected && $entry->reject_reason)
                                <div class="expense-status-detail rejected">{{ $entry->reject_reason }}</div>
                                <div class="expense-status-meta">Rejected by {{ $entry->rejector?->name ?: 'Approver' }} on {{ optional($entry->rejected_at)->format('d M Y H:i') ?: 'N/A' }}</div>
                            @elseif($entry->status === 'approved' && $entry->approver)
                                <div class="expense-status-detail approved">By {{ $entry->approver->name }} on {{ optional($entry->approved_at)->format('d M Y H:i') }}</div>
                            @endif
                        </td>
                        <td>
                            @if($entry->attachment_path)
                                <a href="{{ route('finance-manager.expenses.entries.attachment.download', $entry) }}" class="expense-btn soft" style="padding:10px 14px;">Download</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $entry->creator?->name ?: '-' }}</td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-start;">
                                @if(!$isRejected || $isRejectedOwner)
                                    <a href="{{ route('finance-manager.expenses.entries.edit', $entry) }}" class="expense-btn {{ $isRejected ? 'danger-soft' : 'soft' }}" style="padding:10px 14px;">{{ $isRejected ? 'Edit & Resubmit' : 'Edit' }}</a>
                                @elseif($isRejected)
                                    <span class="expense-owner-note">Returned to creator for correction</span>
                                @endif
                                @if($entry->status === 'draft')
                                    <a href="{{ route('finance-manager.expenses.queue') }}" class="expense-btn soft" style="padding:10px 14px;">Review</a>
                                @endif
                                <details class="expense-delete-menu">
                                    <summary class="expense-btn danger-soft" style="padding:10px 14px;">Delete</summary>
                                    <form method="POST" action="{{ route('finance-manager.expenses.entries.destroy', $entry) }}" class="expense-delete-box">
                                        @csrf
                                        @method('DELETE')
                                        <div class="expense-delete-title">Delete Rs {{ number_format((float) $entry->amount, 2) }}</div>
                                        <input type="password" name="delete_password" placeholder="Delete password" class="expense-delete-input" required>
                                        <textarea name="delete_reason" placeholder="Reason required" class="expense-delete-input" rows="2" required></textarea>
                                        <button type="submit" class="expense-btn danger" style="padding:10px 14px;" onclick="return confirm('Is expense entry ko delete karna hai?')">Confirm Delete</button>
                                    </form>
                                </details>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11">No expenses found for the current filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px;">{{ $entries->links() }}</div>
    </section>

    <section class="expense-card">
        <div class="expense-header">
            <div>
                <h2>Deleted Expense Log</h2>
                <p>Yahan deleted entries ka audit rahega: kisne, kab, kitna amount delete kiya, aur reason.</p>
            </div>
            <div class="expense-mini-item" style="min-width:280px;">
                <div>
                    <strong>{{ $deletedSummary['entry_count'] ?? 0 }} deleted</strong>
                    <span>Current filters ke hisab se</span>
                </div>
                <div class="expense-mini-value">Rs {{ number_format((float) ($deletedSummary['total_amount'] ?? 0), 2) }}</div>
            </div>
        </div>
        <div class="expense-mini-list" style="margin-bottom:18px;grid-template-columns:repeat(3,minmax(0,1fr));display:grid;">
            @forelse($deletedUserTotals as $deletedUserTotal)
                <div class="expense-mini-item">
                    <div>
                        <strong>{{ $deletedUserTotal->deleter?->name ?: 'Unknown user' }}</strong>
                        <span>{{ (int) $deletedUserTotal->entry_count }} entries deleted</span>
                    </div>
                    <div class="expense-mini-value">Rs {{ number_format((float) $deletedUserTotal->total_amount, 2) }}</div>
                </div>
            @empty
                <div class="expense-mini-item"><div><strong>No deleted totals</strong><span>Delete hone ke baad yahan user-wise count dikhega.</span></div></div>
            @endforelse
        </div>
        <div class="expense-table-wrap">
            <table class="expense-table">
                <thead>
                    <tr>
                        <th>Deleted At</th>
                        <th>Deleted By</th>
                        <th>Company</th>
                        <th>Category</th>
                        <th>Paid To</th>
                        <th>Amount</th>
                        <th>Creator</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($deletedEntries as $deletedEntry)
                    <tr>
                        <td>{{ optional($deletedEntry->deleted_at)->format('d M Y H:i') }}</td>
                        <td>{{ $deletedEntry->deleter?->name ?: '-' }}</td>
                        <td>{{ $deletedEntry->company?->name ?: '-' }}</td>
                        <td><strong>{{ $deletedEntry->category?->name ?: '-' }}</strong><br><span style="font-size:12px;color:#697771;">{{ $deletedEntry->subcategory?->name ?: '-' }}</span></td>
                        <td>{{ $deletedEntry->paid_to ?: '-' }}</td>
                        <td><strong>Rs {{ number_format((float) $deletedEntry->amount, 2) }}</strong></td>
                        <td>{{ $deletedEntry->creator?->name ?: '-' }}</td>
                        <td>{{ $deletedEntry->delete_reason ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">Abhi koi deleted expense log nahi hai.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
