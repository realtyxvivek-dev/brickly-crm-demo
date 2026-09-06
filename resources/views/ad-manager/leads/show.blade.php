@extends('layouts.app')

@section('title', 'Lead Detail - ' . brand_name())
@section('page-title', 'Lead Detail')

@section('content')
<style>
    .am-detail{display:grid;gap:16px;color:#17211d}
    .am-panel{background:#fff;border:1px solid #e3e8f0;border-radius:12px;box-shadow:0 6px 18px rgba(15,23,42,.05);padding:18px}
    .am-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
    .am-title{font-size:22px;font-weight:900;color:#111827;margin:0}
    .am-muted{font-size:13px;color:#64748b}
    .am-btn{height:40px;border-radius:9px;background:#eef5f2;color:#205A44;padding:0 14px;font-weight:800;display:inline-flex;align-items:center;gap:7px;text-decoration:none}
    .am-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .am-item{border:1px solid #eef2f7;border-radius:10px;padding:12px;background:#fbfdff}
    .am-label{font-size:11px;font-weight:900;text-transform:uppercase;color:#64748b;margin-bottom:5px}
    .am-value{font-size:14px;color:#111827;font-weight:700;overflow-wrap:anywhere}
    .am-table{width:100%;border-collapse:collapse;font-size:13px}
    .am-table th{background:#f8fafc;color:#475569;text-align:left;font-size:11px;text-transform:uppercase;padding:10px;border-bottom:1px solid #e5e7eb}
    .am-table td{padding:11px;border-bottom:1px solid #eef2f7;vertical-align:top}
    @media(max-width:800px){.am-grid{grid-template-columns:1fr}.am-table-wrap{overflow-x:auto}.am-head{display:grid}}
</style>

<div class="am-detail">
    <section class="am-panel">
        <div class="am-head">
            <div>
                <h1 class="am-title">{{ $lead->name ?: 'Unknown Lead' }}</h1>
                <div class="am-muted">Read-only lead detail. Source: {{ $lead->source_label }}</div>
            </div>
            <a class="am-btn" href="{{ route('ad-manager.leads.index', request()->query()) }}"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </section>

    <section class="am-panel">
        <div class="am-grid">
            @foreach([
                'Phone' => $lead->phone,
                'Email' => $lead->email,
                'Status' => str($lead->status ?: 'new')->replace('_', ' ')->title(),
                'Assigned To' => $lead->activeAssignments->first()?->assignedTo?->name ?: 'Unassigned',
                'City' => $lead->city,
                'Budget' => $lead->budget,
                'Preferred Location' => $lead->preferred_location,
                'Created By' => $lead->creator?->name,
                'Created At' => optional($lead->created_at)->format('d M Y, h:i A'),
            ] as $label => $value)
                <div class="am-item">
                    <div class="am-label">{{ $label }}</div>
                    <div class="am-value">{{ filled($value) ? $value : '-' }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="am-panel am-table-wrap">
        <div class="am-label" style="margin-bottom:10px;">Captured Fields</div>
        <table class="am-table">
            <tbody>
                @forelse($lead->formFieldValues as $field)
                    <tr>
                        <th>{{ str($field->field_key)->replace('_', ' ')->title() }}</th>
                        <td>{{ $field->field_value ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td>No additional fields captured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="am-panel am-table-wrap">
        <div class="am-label" style="margin-bottom:10px;">Recent Outcomes</div>
        <table class="am-table">
            <thead><tr><th>Type</th><th>Status</th><th>Outcome</th><th>Notes</th><th>Date</th></tr></thead>
            <tbody>
                @php($recentTasks = $lead->tasks->concat($lead->managerTasks)->sortByDesc('created_at')->take(10))
                @forelse($recentTasks as $task)
                    <tr>
                        <td>{{ class_basename($task) }}</td>
                        <td>{{ str($task->status ?: '-')->replace('_', ' ')->title() }}</td>
                        <td>{{ str($task->outcome ?: '-')->replace('_', ' ')->title() }}</td>
                        <td>{{ $task->outcome_remark ?? $task->notes ?? '-' }}</td>
                        <td>{{ optional($task->created_at)->format('d M Y, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:#64748b;">No recent task outcomes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
