<style>
    .expense-stack { display: grid; gap: 22px; }
    .expense-card {
        background: #fff;
        border: 1px solid #ddd7ca;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 10px 24px rgba(10, 25, 16, 0.05);
    }
    .expense-header {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        align-items: flex-start;
        margin-bottom: 18px;
    }
    .expense-header h2 {
        margin: 0;
        font-size: 28px;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #0b2e20;
    }
    .expense-header p {
        margin: 8px 0 0;
        color: #66756f;
        font-size: 14px;
        max-width: 760px;
    }
    .expense-btn {
        padding: 13px 18px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    .expense-btn.primary { background: linear-gradient(135deg, #083725, #0f5b42); color: #fff; }
    .expense-btn.soft { background: #fff; color: #0f5b42; border: 1px solid #d8d2c6; }
    .expense-btn.danger { background: #b42318; color: #fff; }
    .expense-form-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        align-items: end;
    }
    .expense-field label {
        display: block;
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #75847d;
        font-weight: 800;
        margin-bottom: 8px;
    }
    .expense-input {
        width: 100%;
        padding: 13px 14px;
        border-radius: 16px;
        border: 1px solid #d8d2c6;
        background: #f8f6f0;
        color: #0b2e20;
        font-size: 15px;
        font-family: inherit;
    }
    .expense-table-wrap {
        overflow-x: auto;
        border: 1px solid #e8e2d7;
        border-radius: 20px;
    }
    .expense-table {
        width: 100%;
        min-width: 980px;
        border-collapse: collapse;
    }
    .expense-table thead th {
        background: #f8f6f0;
        color: #75847d;
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        font-weight: 800;
        text-align: left;
        padding: 14px 16px;
        border-bottom: 1px solid #e8e2d7;
    }
    .expense-table tbody td {
        padding: 16px;
        border-bottom: 1px solid #f0ece3;
        font-size: 14px;
        color: #153528;
        vertical-align: top;
    }
    .expense-table tbody tr:last-child td { border-bottom: 0; }
    .expense-kpis {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .expense-kpi {
        background: #fff;
        border: 1px solid #ddd7ca;
        border-radius: 22px;
        padding: 20px 22px;
        box-shadow: 0 10px 24px rgba(10, 25, 16, 0.05);
    }
    .expense-kpi-label {
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #75847d;
        font-weight: 800;
    }
    .expense-kpi-value {
        margin-top: 12px;
        font-size: 30px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #0b2e20;
    }
    .expense-kpi-note {
        margin-top: 8px;
        font-size: 13px;
        color: #697771;
    }
    .expense-kpi-note-danger { color: #b42318; font-weight: 700; }
    .expense-mini-list { display: grid; gap: 12px; }
    .expense-mini-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 18px;
        background: #f8f6f0;
        border: 1px solid #e8e2d7;
    }
    .expense-mini-item strong { display: block; color: #0b2e20; font-size: 14px; }
    .expense-mini-item span { display: block; margin-top: 4px; color: #697771; font-size: 12px; }
    .expense-mini-value { color: #0f5b42; font-weight: 800; font-size: 13px; text-align: right; }
    .expense-dual-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }
    .expense-row-rejected td {
        background: linear-gradient(180deg, #fff8f7 0%, #fff 100%);
    }
    .expense-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }
    .expense-status-badge.draft { background: #fff4db; color: #9a6700; }
    .expense-status-badge.approved { background: #e7f8ef; color: #0f766e; }
    .expense-status-badge.rejected { background: #fee2e2; color: #b42318; }
    .expense-status-detail {
        margin-top: 6px;
        font-size: 12px;
        line-height: 1.45;
    }
    .expense-status-detail.rejected { color: #b42318; font-weight: 700; }
    .expense-status-detail.approved { color: #0f766e; }
    .expense-status-meta {
        margin-top: 4px;
        font-size: 12px;
        color: #8c4a40;
    }
    .expense-btn.danger-soft {
        background: #fff1f2;
        color: #b42318;
        border: 1px solid #fecdd3;
    }
    .expense-delete-menu summary {
        list-style: none;
    }
    .expense-delete-menu summary::-webkit-details-marker {
        display: none;
    }
    .expense-delete-box {
        margin-top: 8px;
        width: 260px;
        display: grid;
        gap: 8px;
        border: 1px solid #fecaca;
        border-radius: 16px;
        background: #fff7f7;
        padding: 12px;
    }
    .expense-delete-title {
        color: #7f1d1d;
        font-size: 13px;
        font-weight: 800;
    }
    .expense-delete-input {
        width: 100%;
        border: 1px solid #fecaca;
        border-radius: 12px;
        background: #fff;
        color: #153528;
        font-family: inherit;
        font-size: 13px;
        padding: 10px 12px;
    }
    .expense-owner-note {
        display: inline-flex;
        align-items: center;
        border-radius: 12px;
        background: #f5f5f4;
        color: #6b7280;
        font-size: 12px;
        font-weight: 700;
        padding: 10px 12px;
    }
    .expense-rejection-note {
        margin-bottom: 18px;
        border: 1px solid #fecaca;
        background: linear-gradient(180deg, #fff5f5 0%, #fff7f7 100%);
        color: #7f1d1d;
        border-radius: 18px;
        padding: 16px 18px;
        display: grid;
        gap: 6px;
        font-size: 14px;
    }
    .expense-rejection-note strong {
        font-size: 15px;
        font-weight: 800;
    }
    .expense-rejection-meta {
        color: #991b1b;
        font-size: 13px;
    }
    .expense-hero {
        background: linear-gradient(135deg, #0b2f22 0%, #0f5b42 48%, #1d7a58 100%);
        border-radius: 28px;
        padding: 28px;
        color: #f5fbf7;
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(300px, 0.8fr);
        gap: 24px;
        box-shadow: 0 18px 36px rgba(10, 25, 16, 0.16);
    }
    .expense-hero h2 { margin: 0; font-size: 34px; line-height: 1.02; font-weight: 800; letter-spacing: -0.04em; }
    .expense-hero p { margin: 14px 0 0; color: rgba(245, 251, 247, 0.82); font-size: 15px; max-width: 620px; }
    .expense-hero-actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:24px; }
    .expense-hero-action { padding: 13px 18px; border-radius: 16px; font-size: 14px; font-weight: 800; text-decoration: none; display:inline-flex; align-items:center; gap:8px; }
    .expense-hero-action.primary { background:#fff; color:#0f5b42; }
    .expense-hero-action.secondary { background: rgba(255,255,255,0.1); color:#fff; border:1px solid rgba(255,255,255,0.16); }
    .expense-focus-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .expense-focus-card { background: rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.14); border-radius:20px; padding:18px; }
    .expense-focus-label { font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: rgba(245,251,247,0.64); font-weight:800; }
    .expense-focus-value { margin-top:10px; font-size:30px; font-weight:800; letter-spacing:-0.04em; }
    .expense-focus-note { margin-top:6px; font-size:13px; color: rgba(245,251,247,0.78); }
    .expense-print-shell { background:#fff; color:#111827; padding:32px; max-width:1100px; margin:0 auto; }
    .expense-print-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; padding-bottom:18px; border-bottom:2px solid #d1d5db; }
    .expense-print-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin:24px 0; }
    .expense-print-card { border:1px solid #d1d5db; border-radius:16px; padding:16px; }
    .expense-print-label { font-size:11px; text-transform:uppercase; letter-spacing:.16em; color:#6b7280; font-weight:700; }
    .expense-print-value { margin-top:8px; font-size:24px; font-weight:800; color:#0b2e20; }
    @media print {
        body { background:#fff !important; }
        .no-print { display:none !important; }
        .expense-print-shell { padding:0; max-width:none; }
    }
    .expense-subnav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .expense-subnav-link {
        padding: 12px 18px;
        border-radius: 999px;
        border: 1px solid #ddd7ca;
        background: #fff;
        color: #5c6b65;
        text-decoration: none;
        font-size: 14px;
        font-weight: 800;
    }
    .expense-subnav-link.is-active,
    .expense-subnav-link:hover {
        background: linear-gradient(135deg, #083725, #0f5b42);
        border-color: transparent;
        color: #fff;
    }
    .expense-master-shell {
        display: grid;
        gap: 18px;
    }
    .expense-master-intro {
        border: 1px solid #ddd7ca;
        border-radius: 26px;
        background:
            radial-gradient(circle at top right, rgba(15, 91, 66, 0.09), transparent 34%),
            linear-gradient(180deg, #fffdfa 0%, #f8f5ee 100%);
        padding: 24px 26px;
    }
    .expense-master-intro h2 {
        margin: 0;
        color: #0b2e20;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: -0.04em;
    }
    .expense-master-intro p {
        margin: 10px 0 0;
        max-width: 760px;
        color: #5f6f68;
        font-size: 14px;
    }
    .expense-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }
    .expense-stat-card {
        border: 1px solid #e5ddd0;
        border-radius: 20px;
        background: #fff;
        padding: 18px 20px;
    }
    .expense-stat-card strong {
        display: block;
        margin-top: 10px;
        color: #0b2e20;
        font-size: 28px;
        line-height: 1;
        letter-spacing: -0.04em;
    }
    .expense-stat-card span {
        display: block;
        margin-top: 6px;
        color: #697771;
        font-size: 13px;
    }
    .expense-master-grid {
        display: grid;
        grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
        gap: 20px;
        align-items: start;
    }
    .expense-master-create {
        position: sticky;
        top: 16px;
    }
    .expense-master-list {
        display: grid;
        gap: 14px;
    }
    .expense-master-item {
        border: 1px solid #e5ddd0;
        border-radius: 22px;
        background: #fff;
        padding: 18px;
    }
    .expense-master-topline {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 14px;
    }
    .expense-master-title {
        margin: 0;
        color: #0b2e20;
        font-size: 20px;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.03em;
    }
    .expense-master-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    .expense-master-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        background: #f6f3ec;
        color: #53655d;
        border: 1px solid #e7dfd2;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }
    .expense-master-pill.is-active {
        background: #ebf7f1;
        color: #0f5b42;
        border-color: #c9e8d9;
    }
    .expense-inline-form {
        display: grid;
        gap: 14px;
    }
    .expense-inline-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .expense-inline-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .expense-checkbox {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: #31463d;
        font-size: 13px;
        font-weight: 700;
    }
    .expense-empty-state {
        border: 1px dashed #d6ccbb;
        border-radius: 22px;
        padding: 28px;
        text-align: center;
        color: #6b7a74;
        background: #fffdfa;
    }
    @media (max-width: 1180px) {
        .expense-form-grid, .expense-kpis, .expense-dual-grid, .expense-hero, .expense-print-grid, .expense-stat-grid, .expense-master-grid, .expense-inline-grid { grid-template-columns: 1fr; }
        .expense-master-create { position: static; }
    }
    @media (max-width: 700px) {
        .expense-card, .expense-kpi { padding: 18px; border-radius: 20px; }
        .expense-header h2 { font-size: 24px; }
        .expense-btn { width: 100%; }
    }
</style>
