<style>
    .hr-att{--green-900:#0d4a29;--green-700:#2d6a50;--green-500:#4caf82;--green-wash:#f0f7f2;--border:#e2ece3;--border-soft:rgba(13,74,41,.07);--text-900:#162f20;--text-600:#4a6b54;--text-400:#7a9b85;--amber-soft:#fff8e4;--amber:#8a6504;--amber-border:#f5e0a0;--red-soft:#fff0f0;--red:#dc2626;--ease:cubic-bezier(.22,1,.36,1);--ease-spring:cubic-bezier(.34,1.56,.64,1);--shadow-card:0 1px 8px rgba(10,61,36,.06);box-sizing:border-box;}
    .hr-att *,.hr-att ::before,.hr-att ::after{box-sizing:border-box;margin:0;padding:0;}
    .hr-att{font-family:"Instrument Sans",system-ui,sans-serif;color:var(--text-900);background:#f6faf7;padding:0;display:flex;flex-direction:column;gap:14px;-webkit-font-smoothing:antialiased;}
    .hr-att .section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .hr-att .section-title{font-size:18px;font-weight:800;letter-spacing:-.04em;color:var(--text-900);}
    .hr-att .table-card{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow-card);overflow:hidden;}
    .hr-att .table{width:100%;border-collapse:collapse;}
    .hr-att .table th{text-align:left;padding:10px 14px;font-size:10px;font-weight:700;color:var(--text-400);text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid var(--border);background:var(--green-wash);}
    .hr-att .table td{padding:10px 14px;font-size:13px;color:var(--text-600);border-bottom:1px solid var(--border-soft);vertical-align:middle;}
    .hr-att .table tr:last-child td{border-bottom:0;}
    .hr-att .table tr:hover td{background:#fafcfb;}
    .hr-att .user-cell{display:flex;flex-direction:column;gap:2px;}
    .hr-att .user-name{font-size:13px;font-weight:700;color:var(--text-900);}
    .hr-att .user-role{font-size:11px;color:var(--text-400);}
    .hr-att .badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:10px;font-weight:700;letter-spacing:.02em;}
    .hr-att .badge-amber{background:var(--amber-soft);color:var(--amber);border:1px solid var(--amber-border);}
    .hr-att .badge-green{background:var(--green-wash);color:var(--green-900);}
    .hr-att .actions{display:flex;gap:6px;}
    .hr-att .btn-approve{padding:6px 12px;border-radius:10px;border:none;background:var(--green-900);color:#fff;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s var(--ease);}
    .hr-att .btn-approve:hover{background:var(--green-700);transform:translateY(-1px);}
    .hr-att .btn-reject{padding:6px 12px;border-radius:10px;border:1.5px solid #fca5a5;background:var(--red-soft);color:var(--red);font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s var(--ease);}
    .hr-att .btn-reject:hover{background:#fee2e2;}
</style>

<div class="hr-att">
    @include('hr-manager.attendance._nav')

    <div class="section-head">
        <div class="section-title">Overtime</div>
        @if($pendingOvertimes->isNotEmpty())
            <span class="badge badge-amber">{{ $pendingOvertimes->count() }} pending</span>
        @endif
    </div>

    <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Date</th>
                    <th>Worked</th>
                    <th>Overtime</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingOvertimes as $overtime)
                    <tr>
                        <td>
                            <div class="user-cell">
                                <span class="user-name">{{ $overtime->user?->name }}</span>
                                <span class="user-role">{{ $overtime->user?->role?->name }}</span>
                            </div>
                        </td>
                        <td>{{ $overtime->attendance_date->toDateString() }}</td>
                        <td>{{ $overtime->worked_minutes }} min</td>
                        <td><strong style="color:var(--green-900);">{{ $overtime->overtime_minutes }} min</strong></td>
                        <td><span class="badge badge-amber">{{ ucfirst($overtime->status) }}</span></td>
                        <td>
                            <div class="actions">
                                <form method="POST" action="{{ route($approveRoute, $overtime) }}">@csrf<button type="submit" class="btn-approve">Approve</button></form>
                                <form method="POST" action="{{ route($rejectRoute, $overtime) }}">@csrf<button type="submit" class="btn-reject">Reject</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:20px;text-align:center;color:var(--text-400);">No pending overtime requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>