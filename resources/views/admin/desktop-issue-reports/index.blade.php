@extends('layouts.app')

@section('title', 'Desktop Issue Reports')
@section('page-title', 'Desktop Issue Reports')
@section('page-subtitle', 'Reports sent from the {{ brand_name() }} Windows desktop app')

@section('content')
<div style="padding:24px;">
    @if(session('success'))
        <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px;">
        @foreach([
            ['label'=>'Total','value'=>$stats['total'],'color'=>'#1d4ed8','bg'=>'#eff6ff','icon'=>'fa-desktop'],
            ['label'=>'Open','value'=>$stats['open'],'color'=>'#0f766e','bg'=>'#ccfbf1','icon'=>'fa-folder-open'],
            ['label'=>'In Progress','value'=>$stats['in_progress'],'color'=>'#b45309','bg'=>'#fef3c7','icon'=>'fa-spinner'],
            ['label'=>'Resolved','value'=>$stats['resolved'],'color'=>'#15803d','bg'=>'#dcfce7','icon'=>'fa-circle-check'],
        ] as $stat)
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;display:flex;align-items:center;gap:14px;">
                <div style="width:42px;height:42px;border-radius:10px;background:{{ $stat['bg'] }};color:{{ $stat['color'] }};display:flex;align-items:center;justify-content:center;">
                    <i class="fas {{ $stat['icon'] }}"></i>
                </div>
                <div>
                    <div style="font-size:24px;font-weight:800;color:#0f172a;">{{ $stat['value'] }}</div>
                    <div style="font-size:12px;color:#64748b;font-weight:600;">{{ $stat['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.desktop-issue-reports.index') }}" style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:18px;display:flex;gap:12px;flex-wrap:wrap;">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title, user, device..."
            style="flex:1;min-width:220px;border:1.5px solid #dbe3ea;border-radius:8px;padding:9px 12px;">
        <select name="status" style="border:1.5px solid #dbe3ea;border-radius:8px;padding:9px 12px;background:#fff;">
            <option value="">All Status</option>
            <option value="open" {{ request('status')==='open' ? 'selected' : '' }}>Open</option>
            <option value="in_progress" {{ request('status')==='in_progress' ? 'selected' : '' }}>In Progress</option>
            <option value="resolved" {{ request('status')==='resolved' ? 'selected' : '' }}>Resolved</option>
        </select>
        <select name="issue_type" style="border:1.5px solid #dbe3ea;border-radius:8px;padding:9px 12px;background:#fff;">
            <option value="">All Types</option>
            @foreach($issueTypes as $key => $label)
                <option value="{{ $key }}" {{ request('issue_type')===$key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" style="background:#063A1C;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-weight:700;">
            <i class="fas fa-search"></i> Filter
        </button>
        @if(request()->hasAny(['search','status','issue_type']))
            <a href="{{ route('admin.desktop-issue-reports.index') }}" style="padding:9px 12px;color:#64748b;text-decoration:none;">Clear</a>
        @endif
    </form>

    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
        @if($reports->isEmpty())
            <div style="text-align:center;padding:60px 20px;color:#64748b;">
                <i class="fas fa-inbox" style="font-size:42px;color:#cbd5e1;margin-bottom:14px;"></i>
                <h3 style="margin:0 0 6px;color:#334155;">No desktop reports found</h3>
                <p style="margin:0;">Desktop app se issue report aayegi to yahan dikhegi.</p>
            </div>
        @else
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                        <th style="padding:12px;text-align:left;font-size:11px;text-transform:uppercase;color:#64748b;">User</th>
                        <th style="padding:12px;text-align:left;font-size:11px;text-transform:uppercase;color:#64748b;">Issue</th>
                        <th style="padding:12px;text-align:left;font-size:11px;text-transform:uppercase;color:#64748b;">Version / Device</th>
                        <th style="padding:12px;text-align:left;font-size:11px;text-transform:uppercase;color:#64748b;">Status</th>
                        <th style="padding:12px;text-align:left;font-size:11px;text-transform:uppercase;color:#64748b;">Reported</th>
                        <th style="padding:12px;text-align:center;font-size:11px;text-transform:uppercase;color:#64748b;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:14px 12px;">
                                <div style="font-weight:700;color:#0f172a;">{{ $report->reporter->name ?? 'Unknown' }}</div>
                                <div style="font-size:12px;color:#64748b;">{{ $report->reporter->email ?? '' }}</div>
                            </td>
                            <td style="padding:14px 12px;max-width:360px;">
                                <div style="font-size:12px;color:#0f766e;font-weight:800;text-transform:uppercase;">{{ $report->issue_type_label }}</div>
                                <div style="font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $report->title }}</div>
                                <div style="font-size:12px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $report->current_url }}</div>
                            </td>
                            <td style="padding:14px 12px;">
                                <div style="font-weight:700;color:#334155;">v{{ $report->app_version_name }}+{{ $report->app_version_code }}</div>
                                <div style="font-size:12px;color:#64748b;">{{ $report->device_name ?: 'Unknown device' }}</div>
                            </td>
                            <td style="padding:14px 12px;">
                                <span style="background:{{ $report->status_bg }};color:{{ $report->status_color }};padding:5px 10px;border-radius:999px;font-size:12px;font-weight:800;">{{ $report->status_label }}</span>
                            </td>
                            <td style="padding:14px 12px;color:#475569;font-size:13px;">{{ optional($report->reported_at ?? $report->created_at)->format('d M, h:i A') }}</td>
                            <td style="padding:14px 12px;text-align:center;">
                                <a href="{{ route('admin.desktop-issue-reports.show', $report) }}" style="background:#eff6ff;color:#1d4ed8;border-radius:7px;padding:7px 12px;text-decoration:none;font-size:12px;font-weight:700;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($reports->hasPages())
                <div style="padding:14px;border-top:1px solid #f1f5f9;">{{ $reports->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
