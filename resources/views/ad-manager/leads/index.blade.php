@extends('layouts.app')

@section('title', 'Lead Monitor - ' . brand_name())
@section('page-title', 'Lead Monitor')

@section('content')
<style>
    .am-shell{display:grid;gap:16px;color:#17211d}
    .am-panel{background:#fff;border:1px solid #e3e8f0;border-radius:12px;box-shadow:0 6px 18px rgba(15,23,42,.05)}
    .am-head{padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
    .am-title{font-size:18px;font-weight:900;color:#111827}
    .am-muted{font-size:13px;color:#64748b}
    .am-filters{padding:14px;display:flex;flex-wrap:wrap;gap:10px;align-items:end}
    .am-field{display:grid;gap:5px;font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b}
    .am-input{height:40px;border:1px solid #dbe3ef;border-radius:9px;background:#fff;padding:0 11px;font-size:13px;min-width:150px}
    .am-btn{height:40px;border:0;border-radius:9px;background:#205A44;color:#fff;padding:0 14px;font-weight:800;display:inline-flex;align-items:center;gap:7px;text-decoration:none;cursor:pointer}
    .am-btn.soft{background:#eef5f2;color:#205A44}
    .am-table{width:100%;border-collapse:collapse;font-size:13px}
    .am-table th{background:#f8fafc;color:#475569;text-align:left;font-size:11px;text-transform:uppercase;padding:11px;border-bottom:1px solid #e5e7eb}
    .am-table td{padding:12px;border-bottom:1px solid #eef2f7;vertical-align:top}
    .am-badge{display:inline-flex;border-radius:999px;background:#eef5f2;color:#205A44;padding:5px 9px;font-size:11px;font-weight:800}
    @media(max-width:800px){.am-table-wrap{overflow-x:auto}.am-input{width:100%;min-width:0}.am-field{width:100%}.am-btn{width:100%;justify-content:center}}
</style>

<div class="am-shell">
    <section class="am-panel">
        <div class="am-head">
            <div>
                <div class="am-title">Source Lead Monitor</div>
                <div class="am-muted">Read-only access for assigned lead sources.</div>
            </div>
            <a href="{{ route('data-intelligence.index') }}" class="am-btn soft"><i class="fas fa-brain"></i> Data Intelligent</a>
        </div>
        <form method="GET" class="am-filters">
            <label class="am-field">Search
                <input class="am-input" type="search" name="search" value="{{ request('search') }}" placeholder="Name, phone, email">
            </label>
            <label class="am-field">Source
                <select class="am-input" name="source">
                    <option value="">All allowed</option>
                    @foreach($sourceOptions as $sourceKey => $sourceLabel)
                        <option value="{{ $sourceKey }}" @selected(request('source') === $sourceKey)>{{ $sourceLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="am-field">Status
                <select class="am-input" name="status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="am-field">User
                <select class="am-input" name="user_id">
                    <option value="">All users</option>
                    @foreach($filterUsers as $filterUser)
                        <option value="{{ $filterUser->id }}" @selected((string) request('user_id') === (string) $filterUser->id)>{{ $filterUser->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="am-field">From
                <input class="am-input" type="date" name="from" value="{{ request('from') }}">
            </label>
            <label class="am-field">To
                <input class="am-input" type="date" name="to" value="{{ request('to') }}">
            </label>
            <button class="am-btn" type="submit"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('ad-manager.leads.index') }}" class="am-btn soft">Clear</a>
        </form>
    </section>

    <section class="am-panel am-table-wrap">
        <table class="am-table">
            <thead>
                <tr>
                    <th>Lead</th>
                    <th>Phone</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>User</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                    <tr>
                        <td><b>{{ $lead->name ?: 'Unknown' }}</b><div class="am-muted">ID {{ $lead->id }}</div></td>
                        <td>{{ $lead->phone ?: '-' }}</td>
                        <td><span class="am-badge">{{ $lead->source_label }}</span></td>
                        <td>{{ str($lead->status ?: 'new')->replace('_', ' ')->title() }}</td>
                        <td>{{ $lead->activeAssignments->first()?->assignedTo?->name ?: 'Unassigned' }}</td>
                        <td>{{ optional($lead->created_at)->format('d M Y, h:i A') }}</td>
                        <td><a class="am-btn soft" href="{{ route('ad-manager.leads.show', $lead) }}"><i class="fas fa-eye"></i> View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;padding:42px;color:#64748b;">No leads found for assigned sources.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{ $leads->links() }}
</div>
@endsection
