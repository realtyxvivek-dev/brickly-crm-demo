@extends('layouts.app')

@section('title', 'HR Dashboard')
@section('page-title', 'HR Dashboard')

@push('styles')
<style>
    .main-header {
        display: none !important;
    }

    .container {
        padding-top: 20px !important;
    }

    .hrcrm {
        --line: rgba(15, 23, 42, 0.08);
        --text: #0f172a;
        --muted: #64748b;
        --green: #14532d;
        --green-soft: #ecfdf3;
        --blue: #1d4ed8;
        --blue-soft: #eef4ff;
        --amber: #b45309;
        --amber-soft: #fff7e8;
        --red: #c2410c;
        --red-soft: #fff1eb;
        --violet: #6d28d9;
        --violet-soft: #f5f0ff;
        --card: #ffffff;
        --bg-soft: #f8fafc;
        --shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        display: flex;
        flex-direction: column;
        gap: 18px;
        color: var(--text);
    }

    .hrcrm-card {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 24px;
        box-shadow: var(--shadow);
    }

    .hrcrm-hero {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(360px, 0.9fr);
        gap: 16px;
        padding: 18px 20px;
        align-items: center;
    }

    .hrcrm-kicker {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-title {
        margin-top: 6px;
        font-size: 30px;
        line-height: 1.05;
        letter-spacing: -0.04em;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-copy {
        margin-top: 8px;
        max-width: 760px;
        font-size: 14px;
        line-height: 1.45;
        color: var(--muted);
        font-weight: 500;
    }

    .hrcrm-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
    }

    .hrcrm-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: var(--bg-soft);
        border: 1px solid var(--line);
        font-size: 12px;
        font-weight: 700;
        color: var(--text);
    }

    .hrcrm-filter {
        padding: 12px;
        border-radius: 16px;
        background: #f8fbf9;
        border: 1px solid var(--line);
    }

    .hrcrm-filter form {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) 150px;
        gap: 10px !important;
        align-items: end;
    }

    .hrcrm-side {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .hrcrm-hero-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .hrcrm-hero-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .hrcrm-mini-clock {
        min-width: 130px;
        padding: 9px 12px;
        border-radius: 12px;
        background: #fff;
        border: 1px solid var(--line);
        text-align: center;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
    }

    .hrcrm-mini-time {
        font-size: 15px;
        font-weight: 800;
        color: #205A44;
        letter-spacing: 0.06em;
        font-family: "Courier New", monospace;
    }

    .hrcrm-mini-date {
        margin-top: 3px;
        font-size: 11px;
        font-weight: 700;
        color: var(--muted);
        font-family: "Courier New", monospace;
    }

    .hrcrm-logout {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 0 15px;
        border: none;
        border-radius: 14px;
        background: #ef4444;
        color: #fff;
        font-size: 14px;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
    }

    .hrcrm-filter-label {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-filter select {
        width: 100%;
        min-height: 40px;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 0 12px;
        font-size: 13px;
        font-weight: 700;
        color: var(--text);
        background: #fff;
        outline: none;
    }

    .hrcrm-btn {
        min-height: 40px;
        border: none;
        border-radius: 14px;
        background: #205A44;
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
    }

    .hrcrm-my-attendance {
        overflow: hidden;
        border-radius: 16px;
        border: 1px solid var(--line);
        background: #fff;
    }

    .hrcrm-my-attendance .attendance-widget {
        border-radius: 16px;
        box-shadow: none;
        margin: 0;
        padding: 12px;
        background: #fff;
        color: var(--text);
    }

    .hrcrm-my-attendance .attendance-widget-full {
        padding: 0;
    }

    .hrcrm-my-attendance .attendance-widget-title {
        font-size: 14px;
        line-height: 1.2;
        color: var(--text);
        margin: 0;
    }

    .hrcrm-my-attendance .attendance-widget-copy {
        font-size: 12px;
        color: var(--muted);
        margin-top: 2px;
    }

    .hrcrm-my-attendance .attendance-widget-top {
        display: none;
    }

    .hrcrm-my-attendance .attendance-widget-status {
        min-height: 28px;
        padding: 6px 10px;
        background: var(--bg-soft);
        color: var(--text);
        border: 1px solid var(--line);
    }

    .hrcrm-my-attendance .attendance-widget-grid,
    .hrcrm-my-attendance .attendance-widget-stats {
        gap: 8px;
    }

    .hrcrm-my-attendance .attendance-widget-grid {
        display: none;
    }

    .hrcrm-my-attendance .attendance-widget-card {
        padding: 9px 10px;
        border-radius: 12px;
        background: var(--bg-soft);
        border-color: var(--line);
    }

    .hrcrm-my-attendance .attendance-widget-label {
        margin-bottom: 4px;
        color: var(--muted);
        font-size: 10px;
    }

    .hrcrm-my-attendance .attendance-widget-value {
        color: var(--text);
        font-size: 15px;
    }

    .hrcrm-my-attendance .attendance-widget-stats,
    .hrcrm-my-attendance .attendance-widget-inline {
        display: none;
    }

    .hrcrm-my-attendance .attendance-widget-actions {
        gap: 8px;
        margin: 0;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hrcrm-my-attendance .attendance-widget-btn {
        min-height: 38px;
        padding: 9px 12px;
        border-radius: 12px;
        font-size: 13px;
    }

    .hrcrm-my-attendance .attendance-widget-btn-primary {
        background: #205A44;
        color: #fff;
    }

    .hrcrm-my-attendance .attendance-widget-btn-secondary {
        background: #f8fafc;
        border: 1px solid var(--line);
        color: var(--text);
    }

    .hrcrm-band {
        padding: 20px;
        background: #fff;
    }

    .hrcrm-band-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 16px;
    }

    .hrcrm-band-kicker {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-band-title {
        margin-top: 6px;
        font-size: 22px;
        line-height: 1.15;
        letter-spacing: 0;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-band-copy {
        margin-top: 5px;
        max-width: 680px;
        font-size: 13px;
        line-height: 1.45;
        color: var(--muted);
        font-weight: 500;
    }

    .hrcrm-band-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 38px;
        padding: 8px 13px;
        border-radius: 12px;
        border: 1px solid var(--line);
        background: #fff;
        font-size: 12px;
        font-weight: 800;
        color: var(--text);
        white-space: nowrap;
    }

    .hrcrm-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px;
    }

    .hrcrm-stat {
        position: relative;
        overflow: hidden;
        padding: 16px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.035);
        color: inherit;
        text-decoration: none;
        cursor: pointer;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    .hrcrm-stat:hover {
        border-color: rgba(32, 90, 68, 0.28);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        transform: translateY(-1px);
    }

    .hrcrm-stat::before {
        content: "";
        position: absolute;
        top: 12px;
        left: 0;
        width: 3px;
        height: calc(100% - 24px);
        border-radius: 0 999px 999px 0;
    }

    .hrcrm-stat.green::before { background: #22c55e; }
    .hrcrm-stat.amber::before { background: #f59e0b; }
    .hrcrm-stat.red::before { background: #f97316; }
    .hrcrm-stat.blue::before { background: #3b82f6; }
    .hrcrm-stat.violet::before { background: #8b5cf6; }

    .hrcrm-stat-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .hrcrm-stat-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
    }

    .hrcrm-stat.green .hrcrm-stat-icon { background: rgba(34, 197, 94, 0.12); color: #15803d; }
    .hrcrm-stat.amber .hrcrm-stat-icon { background: rgba(245, 158, 11, 0.14); color: #b45309; }
    .hrcrm-stat.red .hrcrm-stat-icon { background: rgba(249, 115, 22, 0.14); color: #c2410c; }
    .hrcrm-stat.blue .hrcrm-stat-icon { background: rgba(59, 130, 246, 0.14); color: #1d4ed8; }
    .hrcrm-stat.violet .hrcrm-stat-icon { background: rgba(139, 92, 246, 0.14); color: #6d28d9; }

    .hrcrm-stat-index {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 26px;
        padding: 0 10px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid var(--line);
        font-size: 11px;
        font-weight: 800;
        color: var(--muted);
    }

    .hrcrm-stat-label {
        margin-top: 13px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-stat-value {
        margin-top: 9px;
        font-size: 32px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.05em;
        color: var(--text);
    }

    .hrcrm-stat-note {
        margin-top: 8px;
        font-size: 12px;
        color: var(--muted);
        font-weight: 600;
    }

    .hrcrm-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 18px;
    }

    .hrcrm-split-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        align-items: stretch;
    }

    .hrcrm-compact-band {
        padding: 18px;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    .hrcrm-compact-band .hrcrm-band-head {
        align-items: flex-start;
        margin-bottom: 14px;
    }

    .hrcrm-compact-band .hrcrm-band-title {
        font-size: 20px;
        letter-spacing: 0;
        user-select: none;
    }

    .hrcrm-compact-band .hrcrm-band-copy {
        max-width: 100%;
        font-size: 12px;
        line-height: 1.45;
    }

    .hrcrm-compact-stats {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        flex: 1;
    }

    .hrcrm-compact-stats .hrcrm-stat {
        border-radius: 13px;
        padding: 13px 12px 12px 14px;
        box-shadow: none;
        min-height: 126px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .hrcrm-compact-stats .hrcrm-stat-icon {
        width: 30px;
        height: 30px;
        border-radius: 9px;
        font-size: 13px;
    }

    .hrcrm-compact-stats .hrcrm-stat-index {
        min-width: 26px;
        height: 24px;
        padding: 0 8px;
        font-size: 10px;
    }

    .hrcrm-compact-stats .hrcrm-stat-label {
        margin-top: 9px;
        font-size: 10px;
        letter-spacing: 0.07em;
    }

    .hrcrm-compact-stats .hrcrm-stat-value {
        margin-top: 7px;
        font-size: 27px;
    }

    .hrcrm-compact-stats .hrcrm-stat-note {
        margin-top: 6px;
        font-size: 11px;
        line-height: 1.25;
    }

    .hrcrm-activity {
        padding: 20px;
        background: radial-gradient(circle at top right, rgba(255,255,255,.95), transparent 34%), linear-gradient(180deg, #fffdf9 0%, #faf7f2 100%);
        border: 1px solid rgba(15, 23, 42, 0.08);
    }

    .hrcrm-execution-row {
        align-items: start;
    }

    .hrcrm-activity-compact {
        padding: 18px;
        min-width: 0;
    }

    .hrcrm-activity-compact .hrcrm-activity-head {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 14px;
    }

    .hrcrm-activity-compact .hrcrm-activity-title {
        font-size: 20px;
    }

    .hrcrm-activity-compact .hrcrm-activity-copy {
        max-width: 100%;
        font-size: 12px;
        line-height: 1.45;
    }

    .hrcrm-activity-compact .hrcrm-activity-meta {
        align-items: stretch;
        min-width: 0;
        width: 100%;
    }

    .hrcrm-activity-compact .hrcrm-tracker-filters {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    .hrcrm-activity-compact .hrcrm-tracker-filters select,
    .hrcrm-activity-compact .hrcrm-tracker-filters input[type="date"],
    .hrcrm-activity-compact .hrcrm-tracker-export {
        min-width: 0;
        width: 100%;
    }

    .hrcrm-activity-compact .hrcrm-activity-chip {
        justify-content: center;
        width: 100%;
    }

    .hrcrm-activity-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 16px;
    }

    .hrcrm-activity-kicker {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-activity-title {
        margin-top: 6px;
        font-size: 22px;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: var(--text);
    }

    .hrcrm-activity-copy {
        margin-top: 6px;
        max-width: 620px;
        font-size: 13px;
        line-height: 1.6;
        color: var(--muted);
        font-weight: 500;
    }

    .hrcrm-activity-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
        min-width: min(100%, 420px);
    }

    .hrcrm-activity-chip {
        display: inline-flex;
        align-items: center;
        padding: 7px 11px;
        border-radius: 999px;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        font-size: 11px;
        font-weight: 800;
        color: var(--text);
        white-space: nowrap;
    }

    .hrcrm-activity-filter-row,
    .hrcrm-activity-custom {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 8px;
        width: 100%;
    }

    .hrcrm-activity-filter-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 0 12px;
        border-radius: 999px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: rgba(255,255,255,.82);
        color: var(--muted);
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }

    .hrcrm-activity-filter-btn.is-active {
        background: #0f172a;
        border-color: #0f172a;
        color: #fff;
        box-shadow: 0 10px 18px rgba(15, 23, 42, 0.12);
    }

    .hrcrm-activity-custom input[type="date"] {
        min-height: 40px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 12px;
        padding: 0 12px;
        background: #fff;
        font-size: 12px;
        font-weight: 700;
        color: var(--text);
    }

    .hrcrm-activity-custom button {
        min-height: 40px;
        padding: 0 14px;
    }

    .hrcrm-activity-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .hrcrm-activity-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 20px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(250,247,242,.96));
        padding: 16px;
    }

    .hrcrm-activity-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }

    .hrcrm-activity-name {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .hrcrm-activity-icon {
        width: 38px;
        height: 38px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
    }

    .hrcrm-activity-icon.meetings { background: rgba(37, 99, 235, 0.12); color: #2563eb; }
    .hrcrm-activity-icon.visits { background: rgba(22, 163, 74, 0.12); color: #16a34a; }

    .hrcrm-activity-name strong {
        font-size: 17px;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-activity-name span {
        display: block;
        margin-top: 2px;
        font-size: 12px;
        color: var(--muted);
        font-weight: 600;
    }

    .hrcrm-activity-badge {
        flex-shrink: 0;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: #fff;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-activity-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }

    .hrcrm-activity-stat {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 16px;
        background: rgba(255,255,255,.92);
        padding: 12px;
    }

    .hrcrm-activity-stat strong {
        display: block;
        font-size: 24px;
        line-height: 1;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-activity-stat span {
        display: block;
        margin-top: 5px;
        font-size: 10px;
        line-height: 1.2;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-activity-stat.is-completed strong { color: #15803d; }
    .hrcrm-activity-stat.is-pending strong { color: #b45309; }

    .hrcrm-activity-progress {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
    }

    .hrcrm-activity-track {
        height: 8px;
        border-radius: 999px;
        background: #e9eef3;
        overflow: hidden;
    }

    .hrcrm-activity-fill {
        height: 100%;
        border-radius: 999px;
    }

    .hrcrm-activity-fill.meetings { background: linear-gradient(90deg, #60a5fa 0%, #2563eb 100%); }
    .hrcrm-activity-fill.visits { background: linear-gradient(90deg, #4ade80 0%, #16a34a 100%); }

    .hrcrm-activity-percent {
        font-size: 11px;
        font-weight: 800;
        color: var(--muted);
    }

    .hrcrm-tracker-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .hrcrm-tracker-stat {
        padding: 16px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.92);
    }

    .hrcrm-activity-compact .hrcrm-tracker-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }

    .hrcrm-activity-compact .hrcrm-tracker-stat {
        padding: 12px;
        border-radius: 14px;
    }

    .hrcrm-tracker-stat span {
        display: block;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-tracker-stat strong {
        display: block;
        margin-top: 8px;
        font-size: 30px;
        line-height: 1;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-activity-compact .hrcrm-tracker-stat strong {
        margin-top: 6px;
        font-size: 24px;
    }

    .hrcrm-tracker-filters {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 10px;
        width: 100%;
    }

    .hrcrm-tracker-filters select,
    .hrcrm-tracker-filters input[type="date"] {
        min-height: 40px;
        min-width: 150px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        padding: 0 12px;
        background: #fff;
        color: var(--text);
        font-size: 12px;
        font-weight: 800;
        outline: none;
    }

    .hrcrm-tracker-export {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 0 14px;
        border-radius: 14px;
        border: 1px solid rgba(32, 90, 68, 0.18);
        background: #fff;
        color: #205A44;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .hrcrm-tracker-table {
        width: 100%;
        min-width: 860px;
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        background: #fff;
    }

    .hrcrm-activity-compact .hrcrm-table-wrap {
        max-height: min(420px, 58vh);
        overflow: auto;
    }

    .hrcrm-activity-compact .hrcrm-table-wrap:has(.hrcrm-tracker-table tbody tr:nth-child(-n+6):last-child) {
        max-height: none;
    }

    .hrcrm-activity-compact .hrcrm-tracker-table {
        min-width: 620px;
        border-radius: 14px;
    }

    .hrcrm-tracker-table th,
    .hrcrm-tracker-table td {
        padding: 14px 16px;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        text-align: left;
        vertical-align: middle;
    }

    .hrcrm-activity-compact .hrcrm-tracker-table th,
    .hrcrm-activity-compact .hrcrm-tracker-table td {
        padding: 10px 12px;
    }

    .hrcrm-register-preview-wrap {
        max-height: 520px;
        overflow: auto;
    }

    .hrcrm-tracker-table th {
        background: #f8fafc;
        color: var(--muted);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .hrcrm-tracker-table td {
        color: var(--text);
        font-size: 14px;
        font-weight: 700;
    }

    .hrcrm-tracker-table tr:last-child td {
        border-bottom: none;
    }

    .hrcrm-tracker-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
        height: 34px;
        border-radius: 999px;
        background: #f8fafc;
        color: var(--text);
        font-weight: 800;
    }

    .hrcrm-tracker-count.verified {
        background: #ecfdf3;
        color: #14532d;
    }

    .hrcrm-visit-monitor {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .hrcrm-visit-filter {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 8px;
        min-width: min(100%, 680px);
    }

    .hrcrm-visit-filter select,
    .hrcrm-visit-filter input[type="date"] {
        min-height: 38px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: #fff;
        padding: 0 12px;
        color: var(--text);
        font-size: 13px;
        font-weight: 800;
    }

    .hrcrm-visit-filter button,
    .hrcrm-visit-filter a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 0 13px;
        border-radius: 12px;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--text);
        font-size: 13px;
        font-weight: 900;
        text-decoration: none;
    }

    .hrcrm-visit-filter button {
        border-color: rgba(20, 83, 45, 0.25);
        background: var(--green);
        color: #fff;
    }

    .hrcrm-visit-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .hrcrm-visit-stat {
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #fbfdfc;
        padding: 14px;
    }

    .hrcrm-visit-stat span {
        display: block;
        color: var(--muted);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .hrcrm-visit-stat strong {
        display: block;
        margin-top: 8px;
        color: var(--text);
        font-size: 26px;
        line-height: 1;
        font-weight: 900;
    }

    .hrcrm-visit-stat small {
        display: block;
        margin-top: 6px;
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
    }

    .hrcrm-visit-table-wrap {
        overflow-x: auto;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #fff;
    }

    .hrcrm-visit-table {
        width: 100%;
        min-width: 900px;
        border-collapse: collapse;
    }

    .hrcrm-visit-table th,
    .hrcrm-visit-table td {
        padding: 9px 12px;
        border-bottom: 1px solid var(--line);
        text-align: left;
        vertical-align: middle;
    }

    .hrcrm-visit-table th {
        background: #f8fafc;
        color: var(--muted);
        font-size: 10px;
        font-weight: 900;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .hrcrm-visit-table td {
        color: var(--text);
        font-size: 13px;
        font-weight: 700;
    }

    .hrcrm-visit-table tr:last-child td {
        border-bottom: none;
    }

    .hrcrm-visit-name {
        display: block;
        font-size: 14px;
        font-weight: 900;
        color: var(--text);
    }

    .hrcrm-visit-role {
        display: block;
        margin-top: 3px;
        font-size: 11px;
        font-weight: 700;
        color: var(--muted);
    }

    .hrcrm-visit-person {
        min-width: 220px;
    }

    .hrcrm-visit-person details {
        min-width: 0;
    }

    .hrcrm-visit-person summary {
        display: grid;
        grid-template-columns: 24px minmax(0, 1fr);
        gap: 8px;
        align-items: center;
        cursor: pointer;
        list-style: none;
    }

    .hrcrm-visit-person summary::-webkit-details-marker {
        display: none;
    }

    .hrcrm-visit-arrow {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 999px;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--green);
        font-size: 11px;
        transition: transform 0.16s ease;
    }

    .hrcrm-visit-person details[open] .hrcrm-visit-arrow {
        transform: rotate(90deg);
    }

    .hrcrm-visit-leads {
        margin: 10px 0 2px 32px;
        display: grid;
        gap: 6px;
    }

    .hrcrm-visit-lead {
        display: grid;
        grid-template-columns: minmax(160px, 1fr) 120px 92px 178px;
        gap: 8px;
        align-items: center;
        padding: 8px 10px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: #fbfdfc;
        color: var(--text);
        font-size: 12px;
        font-weight: 800;
    }

    .hrcrm-visit-lead span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .hrcrm-visit-lead .muted {
        color: var(--muted);
        font-weight: 700;
    }

    .hrcrm-visit-lead .hrcrm-visit-dates {
        display: grid;
        gap: 2px;
        overflow: visible;
        white-space: normal;
    }

    .hrcrm-visit-dates strong {
        color: var(--text);
        font-weight: 800;
    }

    .hrcrm-visit-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 30px;
        padding: 0 10px;
        border-radius: 999px;
        background: #f8fafc;
        color: var(--text);
        font-weight: 900;
    }

    .hrcrm-visit-num.done {
        background: var(--green-soft);
        color: #14532d;
    }

    .hrcrm-visit-num.warn {
        background: var(--amber-soft);
        color: var(--amber);
    }

    .hrcrm-visit-num.danger {
        background: #fff1f2;
        color: #be123c;
    }

    .hrcrm-visit-progress {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 120px;
    }

    .hrcrm-visit-progress-bar {
        position: relative;
        flex: 1;
        height: 8px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }

    .hrcrm-visit-progress-fill {
        position: absolute;
        inset: 0 auto 0 0;
        border-radius: inherit;
        background: #15803d;
    }

    .hrcrm-visit-empty {
        padding: 20px;
        text-align: center;
        color: var(--muted);
        font-weight: 800;
    }

    .hrcrm-section {
        padding: 22px;
    }

    .hrcrm-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }

    .hrcrm-section-kicker {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-section-title {
        margin-top: 6px;
        font-size: 24px;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: var(--text);
    }

    .hrcrm-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .hrcrm-action,
    .hrcrm-list-item,
    .hrcrm-report {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #fff;
        color: inherit;
        text-decoration: none;
        transition: all 0.18s ease;
    }

    .hrcrm-action:hover,
    .hrcrm-list-item:hover,
    .hrcrm-report:hover {
        background: #fbfdfc;
        border-color: rgba(32, 90, 68, 0.18);
    }

    .hrcrm-action strong,
    .hrcrm-list-item strong,
    .hrcrm-report strong {
        display: block;
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-action span,
    .hrcrm-list-item span,
    .hrcrm-report span {
        display: block;
        margin-top: 4px;
        font-size: 12px;
        color: var(--muted);
        font-weight: 600;
    }

    .hrcrm-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
        height: 42px;
        padding: 0 12px;
        border-radius: 14px;
        font-size: 13px;
        font-weight: 800;
    }

    .hrcrm-pill.green { background: var(--green-soft); color: var(--green); }
    .hrcrm-pill.blue { background: var(--blue-soft); color: var(--blue); }
    .hrcrm-pill.amber { background: var(--amber-soft); color: var(--amber); }
    .hrcrm-pill.red { background: var(--red-soft); color: var(--red); }
    .hrcrm-pill.violet { background: var(--violet-soft); color: var(--violet); }

    .hrcrm-stack {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .hrcrm-table-wrap {
        overflow-x: auto;
    }

    .hrcrm-table {
        width: 100%;
        min-width: 760px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .hrcrm-table th,
    .hrcrm-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--line);
        text-align: left;
        white-space: nowrap;
    }

    .hrcrm-table th {
        background: var(--bg-soft);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-table td {
        font-size: 14px;
        color: var(--text);
    }

    .hrcrm-table tr:last-child td {
        border-bottom: none;
    }

    .hrcrm-user {
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-note {
        margin-top: 4px;
        font-size: 12px;
        color: var(--muted);
        font-weight: 600;
    }

    .hrcrm-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        text-transform: capitalize;
        letter-spacing: 0.08em;
    }

    .hrcrm-badge.green { background: var(--green-soft); color: var(--green); }
    .hrcrm-badge.amber { background: var(--amber-soft); color: var(--amber); }
    .hrcrm-badge.red { background: var(--red-soft); color: var(--red); }
    .hrcrm-badge.blue { background: var(--blue-soft); color: var(--blue); }
    .hrcrm-badge.violet { background: var(--violet-soft); color: var(--violet); }

    .hrcrm-notification-slot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .hrcrm-approval-mobile {
        display: none;
    }

    .hrcrm-approval-tabs {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }

    .hrcrm-approval-tab {
        border: 1px solid var(--line);
        background: #fff;
        color: var(--text);
        border-radius: 999px;
        padding: 9px 7px;
        font-size: 11px;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
    }

    .hrcrm-approval-tab.is-active {
        background: #0f5b42;
        border-color: #0f5b42;
        color: #fff;
        box-shadow: 0 8px 20px rgba(15, 91, 66, 0.18);
    }

    .hrcrm-approval-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .hrcrm-approval-item {
        padding: 12px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
    }

    .hrcrm-approval-row {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr);
        gap: 10px;
        align-items: start;
    }

    .hrcrm-approval-icon {
        width: 38px;
        height: 38px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        background: var(--green-soft);
        color: var(--green);
    }

    .hrcrm-approval-icon.amber { background: var(--amber-soft); color: var(--amber); }
    .hrcrm-approval-icon.blue { background: var(--blue-soft); color: var(--blue); }
    .hrcrm-approval-icon.violet { background: var(--violet-soft); color: var(--violet); }

    .hrcrm-approval-titleline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 4px;
    }

    .hrcrm-approval-titleline strong {
        font-size: 14px;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-approval-tag {
        flex-shrink: 0;
        padding: 4px 8px;
        border-radius: 999px;
        background: #f8fafc;
        color: var(--muted);
        font-size: 9px;
        font-weight: 900;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .hrcrm-approval-meta,
    .hrcrm-approval-reason {
        font-size: 11px;
        line-height: 1.45;
        color: var(--muted);
        font-weight: 650;
    }

    .hrcrm-approval-reason {
        margin-top: 5px;
        color: #334155;
    }

    .hrcrm-approval-actions {
        display: grid;
        grid-template-columns: 0.75fr 1fr 1fr;
        gap: 8px;
        margin-top: 10px;
    }

    .hrcrm-approval-btn {
        border: 1px solid var(--line);
        border-radius: 999px;
        min-height: 36px;
        padding: 0 10px;
        background: #fff;
        color: var(--text);
        font-size: 12px;
        font-weight: 800;
        text-align: center;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .hrcrm-approval-btn.approve {
        background: #0f5b42;
        border-color: #0f5b42;
        color: #fff;
    }

    .hrcrm-approval-btn.reject {
        background: #fff7f7;
        border-color: #fecaca;
        color: #b91c1c;
    }

    .hrcrm-approval-empty {
        border: 1px dashed var(--line);
        border-radius: 18px;
        padding: 18px;
        text-align: center;
        color: var(--muted);
        font-size: 13px;
        font-weight: 700;
        background: #fbfdfc;
    }

    .hrcrm-reject-modal {
        position: fixed;
        inset: 0;
        z-index: 3600;
        display: none;
        align-items: flex-end;
        justify-content: center;
        padding: 16px;
        background: rgba(15, 23, 42, 0.46);
    }

    .hrcrm-reject-modal.is-open {
        display: flex;
    }

    .hrcrm-reject-card {
        width: min(520px, 100%);
        border-radius: 24px;
        background: #fff;
        border: 1px solid var(--line);
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
        padding: 18px;
    }

    .hrcrm-reject-card h3 {
        font-size: 18px;
        font-weight: 900;
        color: var(--text);
        margin: 0;
    }

    .hrcrm-reject-card p {
        margin: 6px 0 12px;
        font-size: 13px;
        line-height: 1.5;
        color: var(--muted);
        font-weight: 600;
    }

    .hrcrm-reject-card textarea {
        width: 100%;
        min-height: 110px;
        border: 1px solid var(--line);
        border-radius: 16px;
        padding: 12px;
        resize: vertical;
        font: inherit;
        font-size: 14px;
        outline: none;
    }

    .hrcrm-reject-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 12px;
    }

    .hrcrm-mini-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .hrcrm-mini-stat {
        padding: 16px;
        border: 1px solid var(--line);
        border-radius: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
    }

    .hrcrm-mini-stat-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .hrcrm-mini-stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #eef4ff 0%, #ecfdf3 100%);
        color: #205A44;
        font-size: 14px;
    }

    .hrcrm-mini-stat-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 10px;
        border-radius: 999px;
        background: #fff;
        border: 1px solid var(--line);
        font-size: 11px;
        font-weight: 800;
        color: var(--muted);
    }

    .hrcrm-mini-stat-label {
        margin-top: 14px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-mini-stat-value {
        margin-top: 10px;
        font-size: 24px;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.05em;
        color: var(--text);
    }

    .hrcrm-mini-stat-note {
        margin-top: 6px;
        font-size: 12px;
        color: var(--muted);
        font-weight: 600;
    }

    .hrcrm-attn-shell {
        display: grid;
        grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
        gap: 18px;
        align-items: start;
    }

    .hrcrm-attn-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .hrcrm-attn-user {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
        border: 1px solid var(--line);
        border-radius: 22px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .hrcrm-attn-user.is-active {
        border-color: rgba(29, 78, 216, 0.18);
        background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
    }

    .hrcrm-attn-avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 54px;
        height: 54px;
        border-radius: 18px;
        background: linear-gradient(135deg, #1d4ed8 0%, #205A44 100%);
        color: #fff;
        font-size: 18px;
        font-weight: 800;
        letter-spacing: 0.04em;
        box-shadow: 0 10px 24px rgba(29, 78, 216, 0.18);
    }

    .hrcrm-attn-user-name {
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-attn-user-role {
        margin-top: 3px;
        font-size: 12px;
        font-weight: 600;
        color: var(--muted);
    }

    .hrcrm-attn-user-meta {
        margin-left: auto;
        text-align: right;
    }

    .hrcrm-attn-user-days {
        font-size: 22px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: var(--text);
    }

    .hrcrm-attn-user-caption {
        margin-top: 4px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hrcrm-attn-phone {
        position: relative;
        overflow: hidden;
        padding: 18px;
        border-radius: 30px;
        background:
            radial-gradient(circle at top left, rgba(96, 165, 250, 0.22), transparent 30%),
            radial-gradient(circle at right bottom, rgba(34, 197, 94, 0.18), transparent 28%),
            linear-gradient(180deg, #0d1528 0%, #121b31 100%);
        color: #fff;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
    }

    .hrcrm-attn-phone::before {
        content: "";
        position: absolute;
        inset: 12px;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        pointer-events: none;
    }

    .hrcrm-attn-topbar {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px;
        border-radius: 24px;
        background: linear-gradient(135deg, #1d4ed8 0%, #155e75 100%);
    }

    .hrcrm-attn-topbar-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .hrcrm-attn-photo {
        width: 56px;
        height: 56px;
        border-radius: 18px;
        background: linear-gradient(135deg, #f9a8d4 0%, #fef3c7 100%);
        border: 3px solid rgba(255, 255, 255, 0.18);
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.18);
    }

    .hrcrm-attn-topbar-name {
        font-size: 18px;
        font-weight: 800;
        color: #fff;
    }

    .hrcrm-attn-topbar-role {
        margin-top: 4px;
        font-size: 13px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.84);
    }

    .hrcrm-attn-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .hrcrm-attn-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0 14px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        color: rgba(255, 255, 255, 0.8);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .hrcrm-attn-tab.is-active {
        background: #fff;
        color: #1d4ed8;
    }

    .hrcrm-attn-frame {
        position: relative;
        z-index: 1;
        margin-top: 16px;
        padding: 16px;
        border-radius: 24px;
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        color: var(--text);
    }

    .hrcrm-attn-headline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }

    .hrcrm-attn-headline strong {
        font-size: 16px;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-attn-headline span {
        font-size: 12px;
        font-weight: 700;
        color: var(--muted);
    }

    .hrcrm-attn-month {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 14px;
        background: #fff;
        border: 1px solid var(--line);
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
        font-size: 12px;
        font-weight: 800;
        color: var(--text);
    }

    .hrcrm-attn-status-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .hrcrm-attn-status {
        padding: 14px;
        border-radius: 18px;
        color: #fff;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
    }

    .hrcrm-attn-status.green { background: linear-gradient(135deg, #15803d 0%, #22c55e 100%); }
    .hrcrm-attn-status.red { background: linear-gradient(135deg, #b91c1c 0%, #ef4444 100%); }
    .hrcrm-attn-status.blue { background: linear-gradient(135deg, #1d4ed8 0%, #38bdf8 100%); }
    .hrcrm-attn-status.amber { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); }

    .hrcrm-attn-status-label {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.82);
    }

    .hrcrm-attn-status-value {
        margin-top: 10px;
        font-size: 24px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
    }

    .hrcrm-attn-status-note {
        margin-top: 6px;
        font-size: 11px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.9);
    }

    .hrcrm-attn-calendar {
        margin-top: 16px;
        padding: 16px;
        border-radius: 24px;
        background: #0f172a;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.04);
    }

    .hrcrm-attn-weekdays,
    .hrcrm-attn-days {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 10px;
    }

    .hrcrm-attn-weekdays {
        margin-bottom: 10px;
    }

    .hrcrm-attn-weekday {
        text-align: center;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.56);
    }

    .hrcrm-attn-day {
        min-height: 78px;
        padding: 10px 8px;
        border-radius: 20px;
        background: rgba(148, 163, 184, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.04);
        color: #fff;
    }

    .hrcrm-attn-day.muted {
        opacity: 0.4;
    }

    .hrcrm-attn-day.present {
        background: linear-gradient(180deg, rgba(22, 163, 74, 0.22), rgba(22, 163, 74, 0.1));
        border-color: rgba(34, 197, 94, 0.26);
    }

    .hrcrm-attn-day.absent {
        background: linear-gradient(180deg, rgba(239, 68, 68, 0.26), rgba(239, 68, 68, 0.1));
        border-color: rgba(248, 113, 113, 0.24);
    }

    .hrcrm-attn-day.half {
        background: linear-gradient(180deg, rgba(59, 130, 246, 0.24), rgba(59, 130, 246, 0.1));
        border-color: rgba(96, 165, 250, 0.22);
    }

    .hrcrm-attn-day.leave {
        background: linear-gradient(180deg, rgba(245, 158, 11, 0.24), rgba(245, 158, 11, 0.1));
        border-color: rgba(251, 191, 36, 0.2);
    }

    .hrcrm-attn-date {
        font-size: 16px;
        font-weight: 800;
        color: #fff;
    }

    .hrcrm-attn-tag {
        display: inline-block;
        margin-top: 8px;
        padding: 4px 7px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.88);
    }

    .hrcrm-attn-time {
        margin-top: 6px;
        font-size: 10px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.68);
    }

    .hrcrm-attn-actions {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 16px;
    }

    .hrcrm-attn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0 14px;
        border-radius: 16px;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--text);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-decoration: none;
    }

    .hrcrm-attn-action.primary {
        background: #205A44;
        border-color: #205A44;
        color: #fff;
    }

    @media (max-width: 1160px) {
        .hrcrm-hero,
        .hrcrm-grid {
            grid-template-columns: 1fr;
        }

        .hrcrm-split-summary {
            grid-template-columns: 1fr;
        }

        .hrcrm-stats,
        .hrcrm-activity-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hrcrm-compact-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .hrcrm-tracker-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hrcrm-visit-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hrcrm-mini-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hrcrm-attn-shell,
        .hrcrm-attn-status-grid,
        .hrcrm-attn-actions {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .hrcrm {
            gap: 14px;
        }

        .hrcrm-hero-top {
            flex-direction: column;
        }

        .hrcrm-hero-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .hrcrm-side {
            width: 100%;
        }

        .hrcrm-hero-actions {
            justify-content: flex-start;
        }

        .hrcrm-filter form {
            grid-template-columns: 1fr;
        }

        .hrcrm-hero,
        .hrcrm-section {
            padding: 16px;
        }

        .hrcrm-title {
            font-size: 28px;
        }

        .hrcrm-stats,
        .hrcrm-actions,
        .hrcrm-mini-stats,
        .hrcrm-tracker-summary,
        .hrcrm-visit-summary,
        .hrcrm-activity-grid,
        .hrcrm-activity-stats {
            grid-template-columns: 1fr;
        }

        .hrcrm-compact-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hrcrm-activity-head,
        .hrcrm-activity-meta,
        .hrcrm-activity-filter-row,
        .hrcrm-tracker-filters,
        .hrcrm-activity-custom {
            align-items: flex-start;
            justify-content: flex-start;
        }

        .hrcrm-tracker-filters select,
        .hrcrm-tracker-filters input[type="date"],
        .hrcrm-tracker-export {
            width: 100%;
        }

        .hrcrm-section-title {
            font-size: 20px;
        }

        .hrcrm-attn-topbar,
        .hrcrm-attn-headline {
            flex-direction: column;
            align-items: flex-start;
        }

        .hrcrm-attn-weekdays,
        .hrcrm-attn-days {
            gap: 6px;
        }

        .hrcrm-attn-day {
            min-height: 70px;
            padding: 8px 6px;
            border-radius: 16px;
        }

        .hrcrm-attn-date {
            font-size: 14px;
        }

        .hrcrm-attn-tag,
        .hrcrm-attn-time {
            display: none;
        }

        .hrcrm-approval-mobile {
            display: block;
        }

        .hrcrm-hero-actions .hrcrm-logout {
            display: none;
        }
    }

    @media (max-width: 520px) {
        .hrcrm-compact-stats {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
@php
    $summaryCards = [
        ['label' => 'Present', 'value' => $todaySummary['present'], 'tone' => 'green', 'note' => 'Marked today', 'icon' => 'fa-user-check', 'route' => route('hr-manager.attendance.sheet', array_filter(['office_location_id' => $selectedOfficeLocationId, 'month' => $date->format('Y-m')]))],
        ['label' => 'Late', 'value' => $todaySummary['late'], 'tone' => 'amber', 'note' => 'Need review', 'icon' => 'fa-clock', 'route' => route('hr-manager.attendance.sheet', array_filter(['office_location_id' => $selectedOfficeLocationId, 'month' => $date->format('Y-m')]))],
        ['label' => 'Absent', 'value' => $todaySummary['absent'], 'tone' => 'red', 'note' => 'No mark today', 'icon' => 'fa-user-xmark', 'route' => route('hr-manager.attendance.sheet', array_filter(['office_location_id' => $selectedOfficeLocationId, 'month' => $date->format('Y-m')]))],
        ['label' => 'On Leave', 'value' => $todaySummary['on_leave'], 'tone' => 'blue', 'note' => 'Approved leave', 'icon' => 'fa-umbrella-beach', 'route' => route('hr-manager.attendance.leaves')],
        ['label' => 'Review Queue', 'value' => $reviewQueue, 'tone' => 'violet', 'note' => 'Pending action', 'icon' => 'fa-layer-group', 'route' => route('hr-manager.attendance.sheet', array_filter(['office_location_id' => $selectedOfficeLocationId, 'month' => $date->format('Y-m')]))],
    ];

    $actionTones = ['green', 'blue', 'violet', 'amber', 'red', 'blue'];

    $pendingCards = [
        ['label' => 'Manual Punch', 'value' => $pendingWork['outside_punches'] ?? 0, 'route' => route('hr-manager.attendance.outside-punches', ['only_pending_requests' => 1]), 'tone' => 'amber'],
        ['label' => 'Approve Leave', 'value' => $pendingWork['leave_approvals'], 'route' => route('hr-manager.attendance.leaves'), 'tone' => 'blue'],
        ['label' => 'Regularization', 'value' => $pendingWork['regularizations'], 'route' => route('hr-manager.attendance.regularizations'), 'tone' => 'violet'],
    ];

    $weeklyActivityFilterOptions = [
        'previous_week' => 'Prev Week',
        'this_week' => 'This Week',
        'next_week' => 'Next Week',
        'this_month' => 'This Month',
        'custom' => 'Custom',
    ];

    $weeklyActivityCards = [
        ['label' => 'Meetings', 'key' => 'meetings', 'icon' => 'fa-calendar-check', 'tone' => 'meetings', 'caption' => 'All user meetings'],
        ['label' => 'Visits', 'key' => 'visits', 'icon' => 'fa-map-marker-alt', 'tone' => 'visits', 'caption' => 'All user visits'],
    ];

    $alertCards = [
        ['label' => 'Missing Punch Out', 'value' => $alerts['missing_punch_out'], 'route' => route('hr-manager.attendance.sheet', array_filter(['office_location_id' => $selectedOfficeLocationId, 'month' => $date->format('Y-m')])), 'tone' => 'red'],
        ['label' => 'Outside Office Punches', 'value' => $alerts['outside_office_punches'], 'route' => route('hr-manager.attendance.outside-punches'), 'tone' => 'amber'],
        ['label' => 'Repeated Late Users', 'value' => $alerts['repeated_late_users'], 'route' => route('hr-manager.attendance.sheet', array_filter(['office_location_id' => $selectedOfficeLocationId, 'month' => $date->format('Y-m')])), 'tone' => 'violet'],
    ];

    $hiringCards = [
        ['label' => 'Today Assigned', 'value' => $hiringDashboard['summary']['assigned_today'] ?? 0, 'tone' => 'green', 'note' => 'New HR candidates', 'icon' => 'fa-user-plus'],
        ['label' => 'Call Today', 'value' => $hiringDashboard['summary']['call_today'] ?? 0, 'tone' => 'blue', 'note' => 'Follow-up calls', 'icon' => 'fa-phone'],
        ['label' => 'Interview Today', 'value' => $hiringDashboard['summary']['interview_today'] ?? 0, 'tone' => 'amber', 'note' => 'Scheduled interviews', 'icon' => 'fa-calendar-check'],
        ['label' => 'Pending Follow-up', 'value' => $hiringDashboard['summary']['pending_followup'] ?? 0, 'tone' => 'red', 'note' => 'Overdue actions', 'icon' => 'fa-bell'],
        ['label' => 'Hired This Month', 'value' => $hiringDashboard['summary']['hired_this_month'] ?? 0, 'tone' => 'violet', 'note' => 'Monthly selection', 'icon' => 'fa-user-check'],
    ];

@endphp

<div class="hrcrm">
    @include('attendance._flash')

    <div class="hrcrm-card hrcrm-hero">
        <div>
            <div class="hrcrm-kicker">HR Workspace</div>
            <h1 class="hrcrm-title">HR Daily Work</h1>
            <div class="hrcrm-copy">Daily attendance, approvals, employee work, salary, and reports in one clean screen.</div>

            <div class="hrcrm-meta">
                <div class="hrcrm-chip">Date: {{ $date->format('d M Y') }}</div>
                <div class="hrcrm-chip">Office: {{ $selectedOfficeName ?: 'All Offices' }}</div>
                <div class="hrcrm-chip">Today Count: {{ array_sum($todaySummary) }}</div>
                <div class="hrcrm-chip">Employees: {{ $employeeSummary['active'] }}</div>
            </div>
        </div>

        <div class="hrcrm-side">
            <div class="hrcrm-hero-actions">
                <div class="hrcrm-mini-clock">
                    <div id="adminClockTime" class="hrcrm-mini-time">--:--:--</div>
                    <div id="adminClockDate" class="hrcrm-mini-date">-- --- ----</div>
                </div>
                <div class="hrcrm-notification-slot">
                    @include('components.global-notification-center')
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="hrcrm-logout">Logout</button>
                </form>
            </div>

            <div class="hrcrm-filter">
                <div class="hrcrm-filter-label">Office View</div>
                @if($officeOptions->isNotEmpty())
                    <form method="GET" action="{{ route('hr-manager.dashboard') }}" style="display:flex; flex-direction:column; gap:12px;">
                        <select id="office_location_id" name="office_location_id">
                            <option value="">All Offices</option>
                            @foreach($officeOptions as $office)
                                <option value="{{ $office->id }}" @selected($selectedOfficeLocationId === $office->id)>{{ $office->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="hrcrm-btn">Apply Filter</button>
                    </form>
                @else
                    <div class="hrcrm-note" style="margin-top:0;">No office filter available.</div>
                @endif
            </div>

            @if(auth()->user()?->hasAttendanceRolloutEnabled())
                <div class="hrcrm-my-attendance">
                    @include('attendance._widget', ['hideOvertimeLink' => true])
                </div>
            @endif
        </div>
    </div>

    <div class="hrcrm-split-summary">
        <div class="hrcrm-card hrcrm-band hrcrm-compact-band">
            <div class="hrcrm-band-head">
                <div>
                    <div class="hrcrm-band-kicker">Daily Work</div>
                    <div class="hrcrm-band-title">Today's Attendance</div>
                    <div class="hrcrm-band-copy">Present, late, absent, leave, aur review queue ka quick view.</div>
                </div>
                <span class="hrcrm-band-chip">{{ $date->format('d M Y') }}</span>
            </div>

            <div class="hrcrm-stats hrcrm-compact-stats">
                @foreach($summaryCards as $index => $card)
                    <a href="{{ $card['route'] }}" class="hrcrm-card hrcrm-stat {{ $card['tone'] }}" aria-label="Open {{ $card['label'] }}">
                        <div class="hrcrm-stat-top">
                            <span class="hrcrm-stat-icon"><i class="fas {{ $card['icon'] }}"></i></span>
                            <span class="hrcrm-stat-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="hrcrm-stat-label">{{ $card['label'] }}</div>
                        <div class="hrcrm-stat-value">{{ $card['value'] }}</div>
                        <div class="hrcrm-stat-note">{{ $card['note'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="hrcrm-card hrcrm-band hrcrm-compact-band">
            <div class="hrcrm-band-head">
                <div>
                    <div class="hrcrm-band-kicker">Hiring Queue</div>
                    <div class="hrcrm-band-title">Today hiring snapshot</div>
                    <div class="hrcrm-band-copy">Assigned candidates, calls, interviews aur overdue follow-ups.</div>
                </div>
                <a href="{{ route('hr-manager.hiring.index') }}" class="hrcrm-band-chip">Open Leads</a>
            </div>

            <div class="hrcrm-stats hrcrm-compact-stats">
                @foreach($hiringCards as $card)
                    <a href="{{ route('hr-manager.hiring.index') }}" class="hrcrm-stat {{ $card['tone'] }}" style="text-decoration:none;">
                        <div class="hrcrm-stat-top">
                            <div class="hrcrm-stat-icon"><i class="fas {{ $card['icon'] }}"></i></div>
                        </div>
                        <div class="hrcrm-stat-label">{{ $card['label'] }}</div>
                        <div class="hrcrm-stat-value">{{ $card['value'] }}</div>
                        <div class="hrcrm-stat-note">{{ $card['note'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @foreach([
        ['data' => $siteVisitMonitoring, 'prefix' => 'site_visit', 'kicker' => 'Site Visit Monitoring', 'label' => 'site visits'],
        ['data' => $meetingMonitoring, 'prefix' => 'meeting', 'kicker' => 'Meeting Monitoring', 'label' => 'meetings'],
    ] as $monitoringSection)
    @php
        $activityMonitoring = $monitoringSection['data'];
        $visitSummary = $activityMonitoring['summary'] ?? [];
        $visitPending = (int) ($visitSummary['pending'] ?? 0);
        $visitOverdue = (int) ($visitSummary['overdue'] ?? 0);
        $visitFilters = $activityMonitoring['filters'] ?? [];
    @endphp

    <div class="hrcrm-card hrcrm-band hrcrm-compact-band hrcrm-visit-monitor">
        <div class="hrcrm-band-head">
            <div>
                <div class="hrcrm-band-kicker">{{ $monitoringSection['kicker'] }}</div>
                <div class="hrcrm-band-title">Sales Person performance</div>
                <div class="hrcrm-band-copy">Selected date range ke {{ $monitoringSection['label'] }} aur lead detail.</div>
            </div>
            <form method="GET" action="{{ route('hr-manager.dashboard') }}" class="hrcrm-visit-filter">
                @if($selectedOfficeLocationId)
                    <input type="hidden" name="office_location_id" value="{{ $selectedOfficeLocationId }}">
                @endif
                <select name="{{ $monitoringSection['prefix'] }}_period" onchange="this.form.submit()">
                    @foreach(($activityMonitoring['date_options'] ?? []) as $filterValue => $filterLabel)
                        <option value="{{ $filterValue }}" @selected(($visitFilters['preset'] ?? 'this_month') === $filterValue)>{{ $filterLabel }}</option>
                    @endforeach
                </select>
                @if(($visitFilters['preset'] ?? '') === 'custom')
                    <input type="date" name="{{ $monitoringSection['prefix'] }}_start_date" value="{{ $visitFilters['custom_start'] ?? '' }}">
                    <input type="date" name="{{ $monitoringSection['prefix'] }}_end_date" value="{{ $visitFilters['custom_end'] ?? '' }}">
                @endif
                <button type="submit"><i class="fas fa-filter"></i>&nbsp; Apply</button>
                <a href="{{ route('hr-manager.dashboard', array_filter(['office_location_id' => $selectedOfficeLocationId])) }}">Reset</a>
            </form>
        </div>

        <div class="hrcrm-stats hrcrm-compact-stats">
            <div class="hrcrm-stat green">
                <div class="hrcrm-stat-top">
                    <div class="hrcrm-stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="hrcrm-stat-label">Scheduled</div>
                <div class="hrcrm-stat-value">{{ $visitSummary['scheduled'] ?? 0 }}</div>
                <div class="hrcrm-stat-note">{{ $activityMonitoring['range_label'] ?? '' }}</div>
            </div>
            <div class="hrcrm-stat blue">
                <div class="hrcrm-stat-top">
                    <div class="hrcrm-stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="hrcrm-stat-label">Completed</div>
                <div class="hrcrm-stat-value">{{ $visitSummary['completed'] ?? 0 }}</div>
                <div class="hrcrm-stat-note">Selected period me done</div>
            </div>
            <div class="hrcrm-stat amber">
                <div class="hrcrm-stat-top">
                    <div class="hrcrm-stat-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="hrcrm-stat-label">Pending</div>
                <div class="hrcrm-stat-value">{{ $visitPending }}</div>
                <div class="hrcrm-stat-note">Open {{ $monitoringSection['label'] }}</div>
            </div>
            <div class="hrcrm-stat red">
                <div class="hrcrm-stat-top">
                    <div class="hrcrm-stat-icon"><i class="fas fa-bell"></i></div>
                </div>
                <div class="hrcrm-stat-label">Overdue</div>
                <div class="hrcrm-stat-value">{{ $visitOverdue }}</div>
                <div class="hrcrm-stat-note">Time cross ho chuka</div>
            </div>
            <div class="hrcrm-stat violet">
                <div class="hrcrm-stat-top">
                    <div class="hrcrm-stat-icon"><i class="fas fa-user-check"></i></div>
                </div>
                <div class="hrcrm-stat-label">Verified</div>
                <div class="hrcrm-stat-value">{{ $visitSummary['verified'] ?? 0 }}</div>
                <div class="hrcrm-stat-note">HR/CRM verified</div>
            </div>
        </div>

        <div class="hrcrm-visit-table-wrap">
            <table class="hrcrm-visit-table">
                <thead>
                    <tr>
                        <th>Sales Person</th>
                        <th>Scheduled</th>
                        <th>Completed</th>
                        <th>Pending</th>
                        <th>Overdue</th>
                        <th>Cancelled</th>
                        <th>Verified</th>
                        <th>Completion</th>
                        <th>Last Done</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($activityMonitoring['manager_rows'] ?? []) as $managerRow)
                        <tr class="hrcrm-visit-manager">
                            <td class="hrcrm-visit-person">
                                <details>
                                    <summary>
                                        <span class="hrcrm-visit-arrow"><i class="fas fa-chevron-right"></i></span>
                                        <span>
                                            <span class="hrcrm-visit-name">{{ $managerRow['user_name'] }}</span>
                                        </span>
                                    </summary>
                                    <div class="hrcrm-visit-leads">
                                        @foreach(($managerRow['visits'] ?? []) as $visitRow)
                                            <div class="hrcrm-visit-lead">
                                                <span>{{ $visitRow['lead_name'] }}</span>
                                                <span class="muted">{{ $visitRow['phone'] }}</span>
                                                <span>{{ $visitRow['status'] }}</span>
                                                <span class="muted hrcrm-visit-dates">
                                                    <span><strong>Scheduled:</strong> {{ $visitRow['scheduled_label'] }}</span>
                                                    @if(($visitRow['completed_label'] ?? '-') !== '-')
                                                        <span><strong>Completed:</strong> {{ $visitRow['completed_label'] }}</span>
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            </td>
                            <td><span class="hrcrm-visit-num">{{ $managerRow['scheduled'] }}</span></td>
                            <td><span class="hrcrm-visit-num done">{{ $managerRow['completed'] }}</span></td>
                            <td><span class="hrcrm-visit-num warn">{{ $managerRow['pending'] }}</span></td>
                            <td><span class="hrcrm-visit-num danger">{{ $managerRow['overdue'] }}</span></td>
                            <td><span class="hrcrm-visit-num danger">{{ $managerRow['cancelled'] }}</span></td>
                            <td><span class="hrcrm-visit-num done">{{ $managerRow['verified'] }}</span></td>
                            <td>
                                <div class="hrcrm-visit-progress">
                                    <div class="hrcrm-visit-progress-bar">
                                        <span class="hrcrm-visit-progress-fill" style="width: {{ min(100, $managerRow['completion_rate']) }}%;"></span>
                                    </div>
                                    <strong>{{ $managerRow['completion_rate'] }}%</strong>
                                </div>
                            </td>
                            <td>{{ $managerRow['last_completed_label'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="hrcrm-visit-empty">No sales person {{ $monitoringSection['label'] }} found for selected period.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endforeach

    <div class="hrcrm-card hrcrm-section hrcrm-approval-mobile" data-hr-approval-center>
        <div class="hrcrm-section-head">
            <div>
                <div class="hrcrm-section-kicker">Approval Center</div>
                <div class="hrcrm-section-title">Pending approvals</div>
            </div>
            <span class="hrcrm-pill green">{{ $approvalCenter['counts']['all'] ?? 0 }}</span>
        </div>

        <div class="hrcrm-approval-tabs">
            <button type="button" class="hrcrm-approval-tab is-active" data-approval-filter="all">All {{ $approvalCenter['counts']['all'] ?? 0 }}</button>
            <button type="button" class="hrcrm-approval-tab" data-approval-filter="outside">Manual Punch {{ $approvalCenter['counts']['outside'] ?? 0 }}</button>
            <button type="button" class="hrcrm-approval-tab" data-approval-filter="regularization">Correction {{ $approvalCenter['counts']['regularization'] ?? 0 }}</button>
            <button type="button" class="hrcrm-approval-tab" data-approval-filter="leave">Leave {{ $approvalCenter['counts']['leave'] ?? 0 }}</button>
        </div>

        <div class="hrcrm-approval-list">
            @forelse(($approvalCenter['items'] ?? collect()) as $approval)
                <div class="hrcrm-approval-item" data-approval-item="{{ $approval['type'] }}">
                    <div class="hrcrm-approval-row">
                        <span class="hrcrm-approval-icon {{ $approval['tone'] }}"><i class="fas {{ $approval['icon'] }}"></i></span>
                        <div>
                            <div class="hrcrm-approval-titleline">
                                <strong>{{ $approval['employee'] }}</strong>
                                <span class="hrcrm-approval-tag">{{ $approval['label'] }}</span>
                            </div>
                            <div class="hrcrm-approval-meta">{{ $approval['role'] }} - {{ $approval['meta'] }}</div>
                            <div class="hrcrm-approval-meta">{{ $approval['detail'] }}</div>
                            <div class="hrcrm-approval-reason">{{ $approval['reason'] }}</div>
                        </div>
                    </div>

                    <div class="hrcrm-approval-actions">
                        <a href="{{ $approval['view_route'] }}" class="hrcrm-approval-btn">View</a>
                        <form method="POST" action="{{ $approval['approve_route'] }}">
                            @csrf
                            <button type="submit" class="hrcrm-approval-btn approve" style="width:100%;">Approve</button>
                        </form>
                        <form method="POST" action="{{ $approval['reject_route'] }}" data-hr-reject-form>
                            @csrf
                            <input type="hidden" name="remarks" value="">
                            <button type="button" class="hrcrm-approval-btn reject" style="width:100%;" data-hr-reject-open>Reject</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="hrcrm-approval-empty">No pending HR approvals right now.</div>
            @endforelse
        </div>
    </div>

    <div class="hrcrm-grid hrcrm-execution-row">
        <div class="hrcrm-card hrcrm-activity hrcrm-activity-compact">
        <div class="hrcrm-activity-head">
            <div>
                <div class="hrcrm-activity-kicker">Execution Snapshot</div>
                <div class="hrcrm-activity-title">Activity Tracker</div>
                <div class="hrcrm-activity-copy">User-wise meeting and visit count. Verified means completion approved by senior/CRM verification.</div>
            </div>

            <div class="hrcrm-activity-meta">
                <form method="GET" action="{{ route('hr-manager.dashboard') }}" class="hrcrm-tracker-filters">
                    @if($selectedOfficeLocationId)
                        <input type="hidden" name="office_location_id" value="{{ $selectedOfficeLocationId }}">
                    @endif
                    <select name="activity_date_filter" onchange="this.form.submit()">
                        @foreach(($activityTracker['date_options'] ?? []) as $filterValue => $filterLabel)
                            <option value="{{ $filterValue }}" @selected(($activityTracker['filters']['date_filter'] ?? 'this_month') === $filterValue)>{{ $filterLabel }}</option>
                        @endforeach
                    </select>
                    <select name="activity_team" onchange="this.form.submit()">
                        <option value="all" @selected(($activityTracker['filters']['team'] ?? 'all') === 'all')>All Teams</option>
                        <option value="unassigned" @selected(($activityTracker['filters']['team'] ?? 'all') === 'unassigned')>No Manager</option>
                        @foreach(($activityTracker['team_options'] ?? []) as $teamOption)
                            <option value="{{ $teamOption['value'] }}" @selected(($activityTracker['filters']['team'] ?? 'all') === $teamOption['value'])>{{ $teamOption['label'] }}</option>
                        @endforeach
                    </select>
                    @if(($activityTracker['filters']['date_filter'] ?? '') === 'custom')
                        <input type="date" name="activity_start_date" value="{{ $activityTracker['filters']['custom_start'] ?? '' }}">
                        <input type="date" name="activity_end_date" value="{{ $activityTracker['filters']['custom_end'] ?? '' }}">
                        <button type="submit" class="hrcrm-btn" style="padding:0 14px;">Apply</button>
                    @endif
                    <a
                        class="hrcrm-tracker-export"
                        href="{{ route('hr-manager.dashboard', array_filter([
                            'office_location_id' => $selectedOfficeLocationId,
                            'activity_date_filter' => $activityTracker['filters']['date_filter'] ?? 'this_month',
                            'activity_team' => $activityTracker['filters']['team'] ?? 'all',
                            'activity_start_date' => $activityTracker['filters']['custom_start'] ?? null,
                            'activity_end_date' => $activityTracker['filters']['custom_end'] ?? null,
                            'activity_export' => 'csv',
                        ])) }}"
                    >
                        <i class="fas fa-file-export"></i>&nbsp; Export
                    </a>
                </form>
                <span class="hrcrm-activity-chip">{{ $activityTracker['range_label'] ?? '' }}</span>
            </div>
        </div>

        <div class="hrcrm-tracker-summary">
            <div class="hrcrm-tracker-stat">
                <span>Meetings Sch.</span>
                <strong>{{ $activityTracker['summary']['meeting_scheduled'] ?? 0 }}</strong>
            </div>
            <div class="hrcrm-tracker-stat">
                <span>Meetings Ver.</span>
                <strong>{{ $activityTracker['summary']['meeting_verified'] ?? 0 }}</strong>
            </div>
            <div class="hrcrm-tracker-stat">
                <span>Visits Sch.</span>
                <strong>{{ $activityTracker['summary']['visit_scheduled'] ?? 0 }}</strong>
            </div>
            <div class="hrcrm-tracker-stat">
                <span>Visits Ver.</span>
                <strong>{{ $activityTracker['summary']['visit_verified'] ?? 0 }}</strong>
            </div>
        </div>

        <div class="hrcrm-table-wrap">
            <table class="hrcrm-tracker-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Team</th>
                        <th>Meet Sch.</th>
                        <th>Meet Ver.</th>
                        <th>Visit Sch.</th>
                        <th>Visit Ver.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($activityTracker['rows'] ?? []) as $row)
                        <tr>
                            <td>
                                <div class="hrcrm-user">{{ $row['user_name'] }}</div>
                                <div class="hrcrm-note">{{ $row['role_name'] }}</div>
                            </td>
                            <td>{{ $row['team_name'] }}</td>
                            <td><span class="hrcrm-tracker-count">{{ $row['meeting_scheduled'] }}</span></td>
                            <td><span class="hrcrm-tracker-count verified">{{ $row['meeting_verified'] }}</span></td>
                            <td><span class="hrcrm-tracker-count">{{ $row['visit_scheduled'] }}</span></td>
                            <td><span class="hrcrm-tracker-count verified">{{ $row['visit_verified'] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="hrcrm-note">No meeting or visit count found for selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:18px;">
            <div class="hrcrm-card hrcrm-section">
            <div class="hrcrm-section-head">
                <div>
                    <div class="hrcrm-section-kicker">Quick Actions</div>
                    <div class="hrcrm-section-title">Open work fast</div>
                </div>
            </div>

            <div class="hrcrm-actions">
                @foreach($quickLinks as $index => $link)
                    <a href="{{ $link['route'] }}" class="hrcrm-action">
                        <div>
                            <strong>{{ $link['label'] }}</strong>
                            <span>Open screen</span>
                        </div>
                        <span class="hrcrm-pill {{ $actionTones[$index % count($actionTones)] }}">{{ $index + 1 }}</span>
                    </a>
                @endforeach
            </div>
        </div>

            <div class="hrcrm-card hrcrm-section">
                <div class="hrcrm-section-head">
                    <div>
                        <div class="hrcrm-section-kicker">Pending Work</div>
                        <div class="hrcrm-section-title">What needs action</div>
                    </div>
                </div>

                <div class="hrcrm-stack">
                    @foreach($pendingCards as $card)
                        <a href="{{ $card['route'] }}" class="hrcrm-list-item">
                            <div>
                                <strong>{{ $card['label'] }}</strong>
                                <span>Pending items</span>
                            </div>
                            <span class="hrcrm-pill {{ $card['tone'] }}">{{ $card['value'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="hrcrm-grid">
        <div class="hrcrm-card hrcrm-section">
            <div class="hrcrm-section-head">
                <div>
                    <div class="hrcrm-section-kicker">Attendance</div>
                    <div class="hrcrm-section-title">Today punch-ins</div>
                </div>
                <a href="{{ route('hr-manager.attendance.sheet', array_filter(['office_location_id' => $selectedOfficeLocationId, 'month' => $date->format('Y-m')])) }}" class="hrcrm-report" style="padding: 10px 14px;">
                    <div>
                        <strong>Open Monthly Attendance</strong>
                    </div>
                </a>
            </div>

            <div class="hrcrm-table-wrap hrcrm-register-preview-wrap">
                <table class="hrcrm-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th>Office</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($registerPreview as $record)
                            @php
                                $statusTone = match($record->status) {
                                    'present' => 'green',
                                    'late' => 'amber',
                                    'absent' => 'red',
                                    'leave' => 'blue',
                                    'half_day' => 'violet',
                                    default => 'blue',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="hrcrm-user">{{ optional($record->user)->name ?: 'Unknown User' }}</div>
                                    <div class="hrcrm-note">{{ optional(optional($record->user)->role)->name ?: 'Employee' }}</div>
                                </td>
                                <td><span class="hrcrm-badge {{ $statusTone }}">{{ str_replace('_', ' ', $record->status) }}</span></td>
                                <td>{{ optional($record->first_punch_in_at)->format('h:i A') ?: 'Not marked' }}</td>
                                <td>{{ optional($record->last_punch_out_at)->format('h:i A') ?: 'Pending' }}</td>
                                <td>{{ optional($record->officeLocation)->name ?: 'Not mapped' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="hrcrm-note">Aaj abhi koi punch-in record available nahi hai.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:18px;">
            <div class="hrcrm-card hrcrm-section">
                <div class="hrcrm-section-head">
                    <div>
                        <div class="hrcrm-section-kicker">Alerts</div>
                        <div class="hrcrm-section-title">Need attention</div>
                    </div>
                </div>

                <div class="hrcrm-stack">
                    @foreach($alertCards as $card)
                        <a href="{{ $card['route'] }}" class="hrcrm-list-item">
                            <div>
                                <strong>{{ $card['label'] }}</strong>
                                <span>Check now</span>
                            </div>
                            <span class="hrcrm-pill {{ $card['tone'] }}">{{ $card['value'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="hrcrm-card hrcrm-section">
                <div class="hrcrm-section-head">
                    <div>
                        <div class="hrcrm-section-kicker">Reports</div>
                        <div class="hrcrm-section-title">Exports and summaries</div>
                    </div>
                </div>

                <div class="hrcrm-stack">
                    @foreach($reportLinks as $index => $link)
                        <a href="{{ $link['route'] }}" class="hrcrm-report">
                            <div>
                                <strong>{{ $link['label'] }}</strong>
                                <span>Open report</span>
                            </div>
                            <span class="hrcrm-pill {{ $actionTones[$index % count($actionTones)] }}">{{ $index + 1 }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="hrcrm-reject-modal" data-hr-reject-modal>
    <div class="hrcrm-reject-card">
        <h3>Reject approval</h3>
        <p>Reason required hai, taaki employee ko clear update mil sake.</p>
        <textarea data-hr-reject-remarks placeholder="Write rejection reason..."></textarea>
        <div class="hrcrm-reject-actions">
            <button type="button" class="hrcrm-approval-btn" data-hr-reject-cancel>Cancel</button>
            <button type="button" class="hrcrm-approval-btn reject" data-hr-reject-submit>Reject Request</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const approvalCenter = document.querySelector('[data-hr-approval-center]');
        if (approvalCenter) {
            const tabs = Array.from(approvalCenter.querySelectorAll('[data-approval-filter]'));
            const items = Array.from(approvalCenter.querySelectorAll('[data-approval-item]'));

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const filter = tab.dataset.approvalFilter;
                    tabs.forEach((item) => item.classList.toggle('is-active', item === tab));
                    items.forEach((item) => {
                        item.style.display = filter === 'all' || item.dataset.approvalItem === filter ? '' : 'none';
                    });
                });
            });
        }

        const modal = document.querySelector('[data-hr-reject-modal]');
        const remarksInput = modal?.querySelector('[data-hr-reject-remarks]');
        let activeRejectForm = null;

        document.querySelectorAll('[data-hr-reject-open]').forEach((button) => {
            button.addEventListener('click', () => {
                activeRejectForm = button.closest('form');
                if (remarksInput) {
                    remarksInput.value = '';
                }
                modal?.classList.add('is-open');
                setTimeout(() => remarksInput?.focus(), 50);
            });
        });

        modal?.querySelector('[data-hr-reject-cancel]')?.addEventListener('click', () => {
            modal.classList.remove('is-open');
            activeRejectForm = null;
        });

        modal?.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.remove('is-open');
                activeRejectForm = null;
            }
        });

        modal?.querySelector('[data-hr-reject-submit]')?.addEventListener('click', () => {
            const remarks = (remarksInput?.value || '').trim();
            if (!remarks) {
                remarksInput?.focus();
                return;
            }

            if (activeRejectForm) {
                activeRejectForm.querySelector('input[name="remarks"]').value = remarks;
                activeRejectForm.submit();
            }
        });
    });
</script>
@endpush

