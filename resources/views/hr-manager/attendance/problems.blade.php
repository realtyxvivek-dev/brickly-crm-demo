<style>
    .hr-att{--green-900:#0d4a29;--green-700:#2d6a50;--green-500:#4caf82;--green-wash:#f0f7f2;--border:#e2ece3;--border-soft:rgba(13,74,41,.07);--text-900:#162f20;--text-600:#4a6b54;--text-400:#7a9b85;--amber-soft:#fff8e4;--amber:#8a6504;--amber-border:#f5e0a0;--red-soft:#fff0f0;--red:#dc2626;--ease:cubic-bezier(.22,1,.36,1);--shadow-card:0 1px 8px rgba(10,61,36,.06);--shadow-hover:0 4px 20px rgba(10,61,36,.10);box-sizing:border-box;}
    .hr-att *,.hr-att ::before,.hr-att ::after{box-sizing:border-box;margin:0;padding:0;}
    .hr-att{font-family:"Instrument Sans",system-ui,sans-serif;color:var(--text-900);background:#f6faf7;padding:0;display:flex;flex-direction:column;gap:14px;-webkit-font-smoothing:antialiased;}
    .hr-att .section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .hr-att .section-title{font-size:18px;font-weight:800;letter-spacing:-.04em;color:var(--text-900);}
    .hr-att .stat-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px;background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow-card);}
    .hr-att .stat-label{font-size:11px;font-weight:600;color:var(--text-400);text-transform:uppercase;letter-spacing:.07em;}
    .hr-att .stat-val{font-size:15px;font-weight:800;letter-spacing:-.04em;color:var(--text-900);}
    .hr-att .problem-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
    .hr-att .problem-card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:16px;box-shadow:var(--shadow-card);}
    .hr-att .problem-card-title{font-size:13px;font-weight:700;color:var(--text-900);margin-bottom:12px;display:flex;align-items:center;gap:8px;}
    .hr-att .problem-dot{width:8px;height:8px;border-radius:50%;background:var(--green-500);}
    .hr-att .problem-dot.amber{background:#f59e0b;}
    .hr-att .problem-dot.red{background:var(--red);}
    .hr-att .problem-list{display:flex;flex-direction:column;gap:8px;}
    .hr-att .problem-item{display:flex;align-items:center;justify-content:space-between;padding:9px 12px;background:var(--green-wash);border-radius:10px;font-size:13px;font-weight:600;color:var(--text-900);}
    .hr-att .problem-item-meta{font-size:11px;color:var(--text-400);font-weight:500;}
    .hr-att .problem-empty{font-size:13px;color:var(--text-400);padding:10px 0;}
    .hr-att .problem-link{display:inline-block;margin-top:12px;font-size:12px;color:var(--green-700);font-weight:600;text-decoration:underline;text-underline-offset:2px;}
</style>

<div class="hr-att">
    @include('hr-manager.attendance._nav')

    <div class="stat-bar">
        <div>
            <div class="stat-label">Operational Date</div>
            <div class="stat-val">{{ $date }}</div>
        </div>
        <div style="border-left:1px solid var(--border);height:32px;"></div>
        <div>
            <div class="stat-label">Tracked Users</div>
            <div class="stat-val">{{ $tracked_users_count }}</div>
        </div>
    </div>

    <div class="section-head">
        <div class="section-title">Problems — {{ $date }}</div>
    </div>

    <div class="problem-grid">
        <div class="problem-card">
            <div class="problem-card-title">
                <span class="problem-dot"></span>Missing Punch-In
            </div>
            <div class="problem-list">
                @forelse($missing_punch_in as $profile)
                    <div class="problem-item">
                        {{ $profile->user?->name }}
                        <span class="problem-item-meta">{{ $profile->user?->role?->name }}</span>
                    </div>
                @empty
                    <div class="problem-empty">No missing punch-ins.</div>
                @endforelse
            </div>
        </div>

        <div class="problem-card">
            <div class="problem-card-title">
                <span class="problem-dot amber"></span>Half Day
            </div>
            <div class="problem-list">
                @forelse($half_day as $record)
                    <div class="problem-item">
                        {{ $record->user?->name }}
                        <span class="problem-item-meta">{{ optional($record->first_punch_in_at)->format('h:i A') }}</span>
                    </div>
                @empty
                    <div class="problem-empty">No half-day users.</div>
                @endforelse
            </div>
        </div>

        <div class="problem-card">
            <div class="problem-card-title">
                <span class="problem-dot red"></span>Suspicious Geo
            </div>
            <div class="problem-list">
                @forelse($suspicious as $record)
                    <div class="problem-item">
                        {{ $record->user?->name }}
                        <span class="problem-item-meta">{{ implode(', ', $record->suspicion_flags_json ?? []) ?: 'Flagged' }}</span>
                    </div>
                @empty
                    <div class="problem-empty">No suspicious records.</div>
                @endforelse
            </div>
            <a href="{{ route('hr-manager.attendance.reports', ['date' => $date]) }}" class="problem-link">Review suspicious queue →</a>
        </div>
    </div>
</div>