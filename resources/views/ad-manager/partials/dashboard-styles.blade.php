<style>
    .ql-page{background:#eef2f6;min-height:100vh;margin:-1.5rem;padding:18px;color:#111827;font-family:Arial,"Helvetica Neue",sans-serif}
    .ql-shell{width:100%;max-width:none;margin:0;background:#fff;border:1px solid #cfd8e3;box-shadow:0 12px 26px rgba(15,23,42,.08)}
    .ql-hero{background:#107c41;color:#fff;padding:14px 18px;border-bottom:1px solid #0b5f30}
    .ql-hero h1{font-size:24px;font-weight:800;margin:3px 0 0;letter-spacing:0}
    .ql-hero p{margin:5px 0 0;color:#e6f4ea;font-size:13px;line-height:1.45;max-width:1100px}
    .ql-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
    .ql-btn,.ql-input{height:34px;border-radius:3px;border:1px solid #b8c7d9;background:#fff;padding:0 10px;font-size:12px;color:#1f2937}
    .ql-input{min-width:150px;box-shadow:inset 0 1px 0 rgba(15,23,42,.03)}
    .ql-help{display:block;margin-top:4px;font-size:11px;font-weight:600;text-transform:none;letter-spacing:0;color:#64748b}
    .ql-meta-controls{width:100%;order:30;margin-top:2px}
    .ql-check-group{width:100%;border:1px solid #b7cabb;background:#fff;border-radius:4px;padding:10px}
    .ql-check-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px}
    .ql-check-title{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:#345a46}
    .ql-check-actions{display:flex;gap:6px}
    .ql-mini-btn{border:1px solid #b8c7d9;background:#f8fafc;border-radius:3px;padding:4px 7px;font-size:11px;font-weight:800;color:#1f2937;cursor:pointer}
    .ql-mini-btn:hover{background:#eef6f1}
    .ql-check-search{width:100%;height:32px;border:1px solid #d0dbe6;border-radius:3px;padding:0 8px;margin-bottom:8px;font-size:12px}
    .ql-check-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:6px;max-height:176px;overflow:auto;padding-right:2px}
    .ql-check-option{display:flex!important;grid-template-columns:none!important;align-items:center;gap:8px;padding:6px 7px;border:1px solid #e2e8f0;border-radius:4px;background:#f8fafc;font-size:12px!important;font-weight:700!important;text-transform:none!important;letter-spacing:0!important;color:#0f172a!important}
    .ql-check-option input{width:14px;height:14px;accent-color:#107c41;flex:0 0 auto}
    .ql-check-option span{white-space:normal;line-height:1.25}
    .ql-check-empty{padding:10px;border:1px dashed #cbd5e1;border-radius:4px;color:#64748b;font-size:12px}
    .ql-btn{display:inline-flex;align-items:center;gap:7px;font-weight:700;cursor:pointer;text-decoration:none}
    .ql-btn:hover{background:#f3f6f9}
    .ql-btn.primary{background:#107c41;border-color:#107c41;color:#fff}
    .ql-btn.primary:hover{background:#0b6f39}
    .ql-btn.light{background:#e6f4ea;border-color:#9fd3b2;color:#0f5132}
    .ql-panel{background:#fff;border:0;border-radius:0;box-shadow:none}
    .ql-filter{padding:12px 18px;display:flex;flex-wrap:wrap;gap:10px;align-items:end;background:#f7f9fb;border-bottom:1px solid #cfd8e3}
    .ql-filter label{display:grid;gap:4px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#53657a}
    .ql-grid{display:grid;gap:0;margin-top:0;border-bottom:1px solid #cfd8e3}
    .ql-grid.kpi{grid-template-columns:repeat(6,minmax(0,1fr))}
    .ql-grid.meta{grid-template-columns:1.1fr .9fr}
    .ql-card{padding:13px 16px;border-right:1px solid #dbe3ee;background:#fff}
    .ql-card:last-child{border-right:0}
    .ql-kpi-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#53657a}
    .ql-kpi-value{font-size:25px;font-weight:800;margin-top:6px;line-height:1.05}
    .ql-kpi-sub{font-size:12px;color:#5f6f84;margin-top:5px}
    .ql-score{display:flex;align-items:center;gap:18px}
    .ql-ring{width:98px;height:98px;border-radius:999px;background:conic-gradient(#107c41 calc(var(--score)*1%),#d9e1ea 0);display:grid;place-items:center;flex:0 0 auto}
    .ql-ring span{width:70px;height:70px;border-radius:999px;background:#fff;display:grid;place-items:center;font-size:21px;font-weight:800;border:1px solid #dbe3ee}
    .ql-section{padding:16px 18px;border-bottom:1px solid #cfd8e3;overflow-x:auto}
    .ql-section-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:10px}
    .ql-section h2{margin:0;font-size:16px;font-weight:800;color:#111827}
    .ql-section p{margin:3px 0 0;color:#5f6f84;font-size:12px;line-height:1.45}
    .ql-two{display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid #cfd8e3}
    .ql-two>.ql-section{border-bottom:0}
    .ql-two>.ql-section:first-child{border-right:1px solid #cfd8e3}
    .ql-table{width:100%;border-collapse:collapse;font-size:12px;line-height:1.35;background:#fff}
    .ql-table-scroll{width:100%;overflow-x:auto;border:1px solid #d7e0ea}
    .ql-section>.ql-table{min-width:820px;border:1px solid #d7e0ea}
    .ql-table.funnel{min-width:1320px;border-collapse:separate;border-spacing:0;border:0}
    .ql-table.funnel th,.ql-table.funnel td{text-align:center;white-space:nowrap}
    .ql-table.funnel th:first-child,.ql-table.funnel td:first-child{text-align:left}
    .ql-funnel-source{position:sticky;left:0;background:#fff;z-index:1;box-shadow:5px 0 0 rgba(207,216,227,.45)}
    .ql-table.funnel thead .ql-funnel-source{background:#edf4ff;z-index:3}
    .ql-funnel-group th{font-size:10px;color:#203247;background:#e8f3ec;border-bottom:1px solid #bfd6c7;text-align:center}
    .ql-funnel-group .raw{background:#fff3df;color:#7c3d00}
    .ql-funnel-group .pipe{background:#e8f3ec;color:#0f5132}
    .ql-count{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:23px;border-radius:3px;padding:0 8px;font-weight:800;font-size:11px;border:1px solid transparent}
    .ql-count.neutral{background:#edf2f7;color:#111827;border-color:#d9e1ea}.ql-count.good{background:#e8f5e9;color:#0f6b35;border-color:#bfe3ca}.ql-count.warn{background:#fff4ce;color:#8a5600;border-color:#f4d781}.ql-count.info{background:#e7f0ff;color:#1959b3;border-color:#c1d8ff}.ql-count.bad{background:#fde7e9;color:#b4232e;border-color:#f5c2c7}.ql-count.muted{background:#f7f9fb;color:#5f6f84;border-color:#e2e8f0}
    .ql-funnel-total td{background:#edf4ff;font-weight:800;border-top:2px solid #8eb4e3}
    .ql-funnel-legend{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:flex-end}
    .ql-funnel-legend span{display:inline-flex;align-items:center;gap:6px;color:#53657a;font-size:11px;font-weight:700}
    .ql-dot{width:8px;height:8px;border-radius:2px;display:inline-block}.ql-dot.raw{background:#f4b183}.ql-dot.pipe{background:#70ad47}.ql-dot.bad{background:#d9534f}
    .ql-table th{position:sticky;top:0;z-index:2;background:#edf4ff;color:#203247;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;padding:9px 10px;border-right:1px solid #d7e0ea;border-bottom:1px solid #b7c9dd;white-space:nowrap}
    .ql-table td{padding:9px 10px;border-right:1px solid #e1e7ef;border-bottom:1px solid #e1e7ef;vertical-align:top}
    .ql-table th:last-child,.ql-table td:last-child{border-right:0}
    .ql-table tr:last-child td{border-bottom:0}
    .ql-badge{display:inline-flex;border-radius:3px;padding:4px 8px;font-size:11px;font-weight:800;border:1px solid transparent;white-space:nowrap}
    .ql-badge.good{background:#e8f5e9;color:#0f6b35;border-color:#bfe3ca}.ql-badge.bad{background:#fde7e9;color:#b4232e;border-color:#f5c2c7}.ql-badge.warn{background:#fff4ce;color:#8a5600;border-color:#f4d781}.ql-badge.info{background:#e7f0ff;color:#1959b3;border-color:#c1d8ff}.ql-badge.muted{background:#f7f9fb;color:#5f6f84;border-color:#dbe3ee}
    .ql-bar{height:12px;border-radius:0;background:#edf2f7;overflow:hidden;min-width:95px;border:1px solid #d7e0ea}
    .ql-bar span{display:block;height:100%;border-radius:0;background:#107c41;width:var(--w)}
    .ql-bar.bad span{background:#d9534f}.ql-bar.warn span{background:#f4b183}.ql-bar.info span{background:#4472c4}
    .ql-note{border:1px solid #bfe3ca;border-left:5px solid #107c41;background:#f3fbf5;border-radius:0;padding:12px;color:#164b2f;font-size:12px;line-height:1.55}
    .ql-list{margin:0;padding-left:18px;color:#334155;font-size:12px;line-height:1.75}
    .ql-trend-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px;align-items:stretch}
    .ql-trend-card{border:1px solid #d7e0ea;background:#fff;min-width:0}
    .ql-trend-card-head{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:9px 10px;background:#edf4ff;border-bottom:1px solid #b7c9dd}
    .ql-trend-card-head h3{margin:0;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#203247}
    .ql-trend-card-head span{font-size:11px;font-weight:700;color:#53657a}
    .ql-trend-chart{display:grid;gap:9px;padding:10px}
    .ql-trend-row{display:grid;grid-template-columns:74px minmax(0,1fr) 44px;gap:8px;align-items:center}
    .ql-trend-date{font-size:11px;font-weight:700;color:#334155;white-space:nowrap}
    .ql-trend-track{height:18px;background:#f2f6fa;border:1px solid #d7e0ea;display:flex;overflow:hidden}
    .ql-trend-segment{height:100%;min-width:1px}
    .ql-trend-segment.not-interested{background:#d9534f}.ql-trend-segment.junk{background:#f4b183}.ql-trend-segment.cnp{background:#ffd966}
    .ql-trend-total{font-size:11px;font-weight:800;text-align:right;color:#111827}
    .ql-trend-legend{display:flex;flex-wrap:wrap;gap:10px;padding:0 10px 10px;color:#53657a;font-size:11px;font-weight:700}
    .ql-trend-legend span{display:inline-flex;align-items:center;gap:5px}
    .ql-trend-legend i{width:9px;height:9px;display:inline-block;border-radius:2px}
    .ql-page{background:linear-gradient(180deg,#eaf3ef 0,#f6f8fb 210px);color:#111827}
    .ql-shell{background:#fff;border-color:#c9d8cf;box-shadow:0 14px 30px rgba(6,58,28,.12)}
    .ql-hero{background:linear-gradient(135deg,#052e1d 0,#0b5f30 58%,#128447 100%);border-bottom-color:#0f7a42}
    .ql-hero p{color:#e6f7ec}
    .ql-filter{background:#f1f8f4;border-bottom-color:#c9d8cf}
    .ql-filter label{color:#345a46}
    .ql-input{background:#fff;border-color:#b7cabb;color:#0f172a}
    .ql-btn.primary{background:#0b6b34;border-color:#0b6b34;color:#fff}
    .ql-btn.light{background:#eef8f2;border-color:#cce7d5;color:#064326}
    .ql-grid,.ql-section,.ql-two{border-color:#c9d8cf}
    .ql-card{background:linear-gradient(180deg,#ffffff 0,#f7fbf8 100%);border-right-color:#d8e4dc}
    .ql-kpi-label{color:#3f6651}
    .ql-kpi-sub,.ql-section p{color:#64748b}
    .ql-section h2{color:#0f172a}
    .ql-table,.ql-trend-card{background:#fff;color:#0f172a}
    .ql-table-scroll,.ql-section>.ql-table,.ql-trend-card{border-color:#cfded5}
    .ql-table th{background:#eaf4ee;color:#183c2a;border-right-color:#cfded5;border-bottom-color:#abc7b5}
    .ql-table td{background:#fff;border-right-color:#e1e7ef;border-bottom-color:#e1e7ef}
    .ql-table tbody tr:hover td,.ql-table.funnel tbody tr:hover{background:#f7fbf8}
    .ql-funnel-source{background:#fff;box-shadow:5px 0 0 rgba(207,216,227,.45)}
    .ql-table.funnel thead .ql-funnel-source{background:#eaf4ee}
    .ql-funnel-group th,.ql-funnel-group .pipe{background:#e6f3eb;color:#14532d;border-bottom-color:#c0d8c8}
    .ql-funnel-group .raw{background:#fff3df;color:#7c3d00}
    .ql-funnel-total td{background:#eaf4ee;color:#0f172a;border-top-color:#0f7a42}
    .ql-note{background:#f0fbf4;border-color:#bfe3ca;border-left-color:#0f7a42;color:#14532d}
    .ql-ring{background:conic-gradient(#0f7a42 calc(var(--score)*1%),#d9e1ea 0)}
    .ql-ring span{background:#fff;border-color:#dbe3ee;color:#0f172a}
    .ql-trend-card-head{background:#eaf4ee;border-bottom-color:#abc7b5}
    .ql-trend-card-head h3{color:#183c2a}
    .ql-trend-card-head span,.ql-trend-date,.ql-trend-legend{color:#53657a}
    .ql-trend-track{background:#f2f6fa;border-color:#d7e0ea}
    .ql-trend-total{color:#111827}
    .am-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:800}
    .am-pill.success{background:#e8f5e9;color:#0f6b35;border:1px solid #bfe3ca}
    .am-pill.warn{background:#fff4ce;color:#8a5600;border:1px solid #f4d781}
    .am-pill.danger{background:#fde7e9;color:#b4232e;border:1px solid #f5c2c7}
    .am-attendance-card{background:linear-gradient(135deg,#082f49 0,#0c628f 100%);color:#fff;border:1px solid #0c628f;border-radius:8px;padding:16px}
    .am-attendance-card p,.am-attendance-card .sub{color:rgba(255,255,255,.78)}
    .am-attendance-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
    .am-attendance-stat{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);border-radius:8px;padding:12px}
    .am-attendance-stat .label{font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.7)}
    .am-attendance-stat .value{font-size:18px;font-weight:800;margin-top:6px}
    .am-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
    .am-actions button{border-radius:8px;padding:10px 14px;font-size:12px;font-weight:800;border:0;cursor:pointer}
    .am-actions .primary{background:#fff;color:#082f49}
    .am-actions .secondary{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.18)}
    .am-actions button[disabled]{opacity:.6;cursor:not-allowed}
    @media(max-width:1100px){.ql-grid.kpi{grid-template-columns:repeat(2,minmax(0,1fr))}.ql-grid.meta,.ql-two,.ql-trend-layout{grid-template-columns:1fr}.ql-two>.ql-section:first-child{border-right:0;border-bottom:1px solid #cfd8e3}}
    @media(max-width:640px){.ql-page{margin:-1rem;padding:1rem}.ql-grid.kpi{grid-template-columns:1fr}.ql-card{border-right:0;border-bottom:1px solid #dbe3ee}.ql-card:last-child{border-bottom:0}.ql-hero h1{font-size:22px}.ql-actions,.ql-filter{display:grid}.ql-btn,.ql-input{width:100%;min-width:0}.ql-section-head{display:grid}.ql-score{align-items:flex-start}.am-attendance-grid{grid-template-columns:1fr}}
</style>
