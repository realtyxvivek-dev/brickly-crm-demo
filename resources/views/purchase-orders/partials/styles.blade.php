<style>
    .po-shell { display: grid; gap: 18px; }
    .po-card { background: #fff; border: 1px solid #ded6c7; border-radius: 22px; padding: 24px; box-shadow: 0 14px 34px rgba(15, 47, 34, .08); }
    .po-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px; }
    .po-title { margin: 0; color: #062d1e; font-size: 28px; font-weight: 800; }
    .po-sub { color: #66756d; margin: 6px 0 0; }
    .po-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .po-btn { border: 0; border-radius: 12px; padding: 11px 16px; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .po-btn-primary { background: #075f3a; color: #fff; }
    .po-btn-soft { background: #eef7f1; color: #075f3a; border: 1px solid #c8dfd1; }
    .po-btn-danger { background: #d93636; color: #fff; }
    .po-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .po-field label, .po-label { display: block; color: #53645c; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 7px; }
    .po-field input, .po-field select, .po-field textarea { width: 100%; border: 1px solid #d7ddda; border-radius: 13px; padding: 12px 14px; color: #0d2b22; background: #fbfaf6; }
    .po-field textarea { min-height: 96px; resize: vertical; }
    .po-full { grid-column: 1 / -1; }
    .po-table { width: 100%; border-collapse: collapse; overflow: hidden; border-radius: 16px; border: 1px solid #e1ddd3; }
    .po-table th { background: #f6f3ea; color: #53645c; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; text-align: left; padding: 12px; }
    .po-table td { border-top: 1px solid #ece7dc; padding: 10px; vertical-align: top; }
    .po-table input, .po-table select { width: 100%; border: 1px solid #d7ddda; border-radius: 10px; padding: 10px; background: #fff; }
    .po-badge { display: inline-flex; margin-left: 6px; padding: 3px 8px; border-radius: 999px; background: #eef7f1; color: #0b6b3a; font-size: 11px; font-weight: 800; }
    .po-items-wrap { display: grid; gap: 12px; border: 1px solid #e1ddd3; border-radius: 18px; padding: 14px; background: #fbfaf6; }
    .po-items-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .po-items-head strong { display: block; color: #071d17; }
    .po-items-head small { display: block; color: #66756d; margin-top: 3px; }
    .po-items-table-wrap { overflow-x: auto; }
    .po-items-table { min-width: 980px; background: #fff; }
    .po-items-table th:nth-child(1) { width: 24%; }
    .po-items-table th:nth-child(2),
    .po-items-table th:nth-child(3) { width: 18%; }
    .po-items-table th:nth-child(4),
    .po-items-table th:nth-child(5),
    .po-items-table th:nth-child(6) { width: 10%; }
    .po-items-table th:nth-child(7) { width: 12%; }
    .po-item-remove { border: 1px solid #ffd0d0; background: #fff1f1; color: #b42318; border-radius: 10px; padding: 10px 12px; font-weight: 800; cursor: pointer; }
    .po-line-total { display: inline-flex; min-height: 40px; align-items: center; white-space: nowrap; color: #062d1e; }
    .po-total-row { display: flex; align-items: center; justify-content: flex-end; gap: 14px; padding-top: 6px; }
    .po-total-row span { color: #53645c; font-size: 12px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
    .po-total-row strong { color: #062d1e; font-size: 22px; }
    .po-chip { display: inline-flex; padding: 7px 11px; border-radius: 999px; font-weight: 800; font-size: 12px; background: #eef7f1; color: #075f3a; }
    .po-chip-warn { background: #fff6db; color: #8a5800; }
    .po-chip-bad { background: #ffe8e8; color: #a31313; }
    .po-stat { display: grid; gap: 4px; padding: 14px; border: 1px solid #e1ddd3; border-radius: 16px; background: #fbfaf6; }
    .po-stat span { color: #697870; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .po-stat strong { color: #071d17; font-size: 17px; }
    .po-timeline { display: grid; gap: 10px; }
    .po-log { padding: 12px 14px; border-left: 4px solid #075f3a; background: #f7fbf8; border-radius: 12px; }
    .po-alert { padding: 12px 14px; border-radius: 14px; margin-bottom: 14px; }
    .po-alert-ok { background: #eefaf1; color: #0f6534; border: 1px solid #bfe4c9; }
    .po-alert-bad { background: #fff0f0; color: #a31313; border: 1px solid #ffc7c7; }
    @media (max-width: 800px) {
        .main-header { display: none !important; }
        .po-shell { gap: 12px; }
        .po-card { padding: 18px 16px; border-radius: 18px; overflow: hidden; }
        .po-title { font-size: 26px; line-height: 1.08; }
        .po-sub { font-size: 14px; line-height: 1.45; }
        .po-grid { grid-template-columns: 1fr; }
        .po-head { flex-direction: column; }
        .po-actions { width: 100%; }
        .po-btn { width: 100%; }
        .po-actions select.po-btn { text-align: left; justify-content: flex-start; }
        .po-table {
            display: block;
            border: 0;
            border-radius: 0;
            overflow: visible;
        }
        .po-table thead { display: none; }
        .po-table tbody,
        .po-table tr,
        .po-table td {
            display: block;
            width: 100%;
        }
        .po-table tr {
            border: 1px solid #e2ddd3;
            border-radius: 18px;
            padding: 12px;
            margin-bottom: 12px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 47, 34, .06);
        }
        .po-table td {
            border-top: 0;
            padding: 9px 0;
        }
        .po-table td::before {
            content: attr(data-label);
            display: block;
            margin-bottom: 4px;
            color: #6b776f;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .po-table td[data-label="Action"] {
            padding-top: 12px;
        }
        .po-items-table { min-width: 0; }
        .po-items-table-wrap { overflow: visible; }
        .po-total-row { justify-content: space-between; }
    }
</style>
