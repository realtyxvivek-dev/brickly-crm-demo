@extends('layouts.app')

@section('title', 'Desktop Issue Report #' . $report->id)
@section('page-title', 'Desktop Issue Report')
@section('page-subtitle', $report->title)

@section('content')
<div style="padding:24px;">
    @if(session('success'))
        <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:18px;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <a href="{{ route('admin.desktop-issue-reports.index') }}" style="color:#0f766e;text-decoration:none;font-weight:700;">
            <i class="fas fa-arrow-left"></i> Back to reports
        </a>
        <form method="POST" action="{{ route('admin.desktop-issue-reports.status', $report) }}" style="display:flex;gap:8px;align-items:center;">
            @csrf
            <select name="status" style="border:1.5px solid #dbe3ea;border-radius:8px;padding:9px 12px;background:#fff;">
                <option value="open" {{ $report->status==='open' ? 'selected' : '' }}>Open</option>
                <option value="in_progress" {{ $report->status==='in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="resolved" {{ $report->status==='resolved' ? 'selected' : '' }}>Resolved</option>
            </select>
            <button style="background:#063A1C;color:white;border:none;border-radius:8px;padding:9px 16px;font-weight:700;">Update Status</button>
        </form>
    </div>

    <div style="display:grid;grid-template-columns:1.1fr 0.9fr;gap:20px;">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:22px;">
            <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:18px;">
                <div style="width:46px;height:46px;border-radius:12px;background:#dcfce7;color:#166534;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-desktop"></i>
                </div>
                <div>
                    <div style="font-size:12px;color:#0f766e;font-weight:800;text-transform:uppercase;">{{ $report->issue_type_label }}</div>
                    <h2 style="margin:3px 0 4px;color:#0f172a;">{{ $report->title }}</h2>
                    <span style="background:{{ $report->status_bg }};color:{{ $report->status_color }};padding:5px 10px;border-radius:999px;font-size:12px;font-weight:800;">{{ $report->status_label }}</span>
                </div>
            </div>

            <h3 style="font-size:14px;color:#334155;text-transform:uppercase;letter-spacing:.08em;">Description</h3>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;white-space:pre-wrap;color:#1f2937;">{{ $report->description ?: 'No description provided.' }}</div>

            @if($report->last_error)
                <h3 style="font-size:14px;color:#334155;text-transform:uppercase;letter-spacing:.08em;margin-top:18px;">Last Error</h3>
                <pre style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:14px;white-space:pre-wrap;color:#9a3412;">{{ $report->last_error }}</pre>
            @endif

            <h3 style="font-size:14px;color:#334155;text-transform:uppercase;letter-spacing:.08em;margin-top:18px;">Activity Timeline</h3>
            @php $logs = collect($report->activity_logs ?? [])->take(120); @endphp
            @if($logs->isEmpty())
                <div style="border:1px dashed #cbd5e1;border-radius:10px;padding:18px;color:#64748b;text-align:center;">No activity logs attached.</div>
            @else
                <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                    @foreach($logs as $log)
                        <div style="padding:11px 14px;border-bottom:1px solid #f1f5f9;display:grid;grid-template-columns:155px 130px 1fr;gap:12px;align-items:start;">
                            <div style="font-size:12px;color:#64748b;">{{ data_get($log, 'at') }}</div>
                            <div style="font-size:12px;color:#0f766e;font-weight:800;">{{ data_get($log, 'type', '-') }}</div>
                            <pre style="margin:0;white-space:pre-wrap;font-size:12px;color:#334155;font-family:Consolas,monospace;">{{ json_encode(collect($log)->except(['at','type'])->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;">
                <h3 style="margin:0 0 14px;color:#0f172a;">Reporter</h3>
                <div style="font-weight:800;color:#0f172a;">{{ $report->reporter->name ?? 'Unknown' }}</div>
                <div style="color:#64748b;font-size:13px;">{{ $report->reporter->email ?? '' }}</div>
                <div style="margin-top:8px;color:#64748b;font-size:13px;">Role: {{ $report->reporter->role->name ?? 'N/A' }}</div>
                <hr style="border:none;border-top:1px solid #e2e8f0;margin:14px 0;">
                <div style="font-size:13px;color:#64748b;">Assigned to</div>
                <div style="font-weight:800;color:#0f172a;">{{ $report->assignee->name ?? 'Not assigned' }}</div>
                <div style="color:#64748b;font-size:13px;">{{ $report->assignee->email ?? '' }}</div>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;">
                <h3 style="margin:0 0 14px;color:#0f172a;">App & Device</h3>
                <div style="display:grid;gap:10px;">
                    <div><strong>Version:</strong> v{{ $report->app_version_name }}+{{ $report->app_version_code }}</div>
                    <div><strong>Device:</strong> {{ $report->device_name ?: 'Unknown' }}</div>
                    <div><strong>OS:</strong> {{ $report->os_version ?: 'Unknown' }}</div>
                    <div><strong>Reported:</strong> {{ optional($report->reported_at ?? $report->created_at)->format('d M Y, h:i A') }}</div>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;">
                <h3 style="margin:0 0 14px;color:#0f172a;">Page Context</h3>
                <div style="font-size:13px;color:#64748b;margin-bottom:6px;">Page title</div>
                <div style="font-weight:700;color:#0f172a;margin-bottom:12px;">{{ $report->page_title ?: '-' }}</div>
                <div style="font-size:13px;color:#64748b;margin-bottom:6px;">Current URL</div>
                <a href="{{ $report->current_url }}" target="_blank" style="word-break:break-all;color:#2563eb;">{{ $report->current_url ?: '-' }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
