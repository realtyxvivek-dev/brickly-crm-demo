@extends('layouts.app')

@section('title', 'Expense Approval Queue')
@section('page-title', 'Expense Approval Queue')

@section('content')
<div class="w-full space-y-6">
    @include('attendance._flash')
    @include('admin.expenses._nav')

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">Pending Draft Expenses</h2>
                <p class="text-sm text-[#6B7280]">Yahan se draft expenses ko approve ya reject karo. Reject karne par reason dena mandatory hai.</p>
            </div>
            <a href="{{ route('admin.expenses.entries.create') }}" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">Add New Expense</a>
        </div>

        <form method="GET" action="{{ route('admin.expenses.queue') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4">
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
            <select name="assigned_scope" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
                <option value="all" @selected(($filters['assigned_scope'] ?? 'all') === 'all')>All Drafts</option>
                <option value="mine" @selected(($filters['assigned_scope'] ?? 'all') === 'mine')>Assigned to Me</option>
            </select>
            <select name="approval_assigned_to" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
                <option value="">All approvers</option>
                @foreach($approvers as $approver)
                    <option value="{{ $approver->id }}" @selected((string) ($filters['approval_assigned_to'] ?? '') === (string) $approver->id)>{{ $approver->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
            <div class="md:col-span-6 flex flex-wrap gap-3">
                <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">Apply</button>
                <a href="{{ route('admin.expenses.queue') }}" class="px-5 py-2 border border-[#E5DED4] rounded-lg text-brand-primary">Reset</a>
            </div>
        </form>
    </div>

    <div class="space-y-4">
        @forelse($entries as $entry)
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-2">
                        <div class="text-sm text-[#6B7280]">{{ optional($entry->expense_date)->format('d M Y') }} | {{ $entry->company?->name }}</div>
                        <div class="text-lg font-semibold text-brand-primary">{{ $entry->category?->name }} / {{ $entry->subcategory?->name }}</div>
                        <div class="text-sm text-[#6B7280]">Paid to: <span class="text-brand-primary font-medium">{{ $entry->paid_to ?: '-' }}</span></div>
                <div class="text-sm text-[#6B7280]">Reference: {{ $entry->reference_no ?: '-' }} | Mode: {{ $entry->paymentDisplay() }}</div>
                        <div class="text-sm text-[#6B7280]">Created by: {{ $entry->creator?->name ?: '-' }}</div>
                        <div class="text-sm text-[#6B7280]">
                            Assigned to:
                            <span class="font-medium text-brand-primary">{{ $entry->assignedApprover?->name ?: 'Unassigned' }}</span>
                            @if($entry->approval_assigned_at)
                                <span class="text-xs text-slate-500">on {{ optional($entry->approval_assigned_at)->format('d M Y H:i') }}</span>
                            @endif
                        </div>
                        @if($entry->remarks)
                            <div class="text-sm text-[#374151]">{{ $entry->remarks }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Amount</div>
                        <div class="text-3xl font-bold text-brand-primary mt-2">Rs {{ number_format((float) $entry->amount, 2) }}</div>
                        @if($entry->attachment_path)
                            <a href="{{ route('admin.expenses.entries.attachment.download', $entry) }}" class="inline-block mt-3 text-brand-primary font-semibold">Download Attachment</a>
                        @endif
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-3 items-start">
                    <form method="POST" action="{{ route('admin.expenses.entries.approve', $entry) }}">
                        @csrf
                        <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-lg">Approve</button>
                    </form>
                    <a href="{{ route('admin.expenses.entries.edit', $entry) }}" class="px-5 py-2 border border-[#E5DED4] rounded-lg text-brand-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.expenses.entries.reject', $entry) }}" class="flex-1 min-w-[280px]">
                        @csrf
                        <div class="flex flex-col gap-3 md:flex-row">
                            <input type="text" name="reject_reason" value="{{ old('reject_reason') }}" placeholder="Reject reason likho" class="flex-1 px-4 py-2 border border-[#E5DED4] rounded-lg">
                            <button type="submit" class="px-5 py-2 bg-red-600 text-white rounded-lg">Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 text-[#6B7280]">Approval queue is empty.</div>
        @endforelse
    </div>

    <div>{{ $entries->links() }}</div>
</div>
@endsection
