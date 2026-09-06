@extends('sales-manager.layout')

@section('title', request('focus') === 'followups' ? 'Follow Ups - Senior Manager' : 'Tasks - Senior Manager')
@section('page-title', request('focus') === 'followups' ? 'Follow Ups' : 'Tasks')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/manager-lead-form.css') }}?v={{ @filemtime(public_path('css/manager-lead-form.css')) ?: time() }}">
<style>
    .tasks-container {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        width: 100%;
        box-sizing: border-box;
        max-width: 100%;
        overflow-x: hidden;
    }
    .filter-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .task-filter-layout {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .task-filter-main {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        min-width: 0;
        flex: 1 1 auto;
    }
    .task-filter-main .task-type-filter-desktop,
    .task-filter-main .date-filter-desktop {
        margin-left: 0 !important;
    }
    .task-filter-main .date-filter-select,
    .task-filter-main .task-filter-select {
        min-height: 48px;
    }
    .task-type-primary-select {
        min-width: 168px;
    }
    .task-status-primary-select {
        min-width: 168px;
    }
    .task-filter-extra {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-left: auto;
    }
    .filter-btn {
        padding: 10px 20px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        background: white;
        color: #063A1C;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .filter-btn:hover {
        border-color: #205A44;
        color: #205A44;
    }
    .filter-btn.active {
        background: #205A44;
        color: white;
        border-color: #205A44;
    }
    .tasks-container.asm-tasks-pro {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 14px 34px rgba(6, 58, 28, 0.08);
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfb 100%);
    }
    .tasks-container.asm-tasks-pro .filter-bar {
        background: linear-gradient(135deg, #f8fcfa 0%, #edf6f2 100%);
        border: 1px solid #d9e7df;
        border-radius: 14px;
        padding: 14px;
        margin-bottom: 12px;
    }
    .tasks-container.asm-tasks-pro .task-filter-layout {
        gap: 14px;
    }
    .tasks-container.asm-tasks-pro .task-filter-main {
        gap: 10px;
    }
    .tasks-container.asm-tasks-pro .filter-btn {
        border: 1px solid #d0d8d4;
        border-radius: 999px;
        padding: 10px 18px;
        font-weight: 600;
        letter-spacing: 0.01em;
        color: #0f3f2d;
        background: #ffffff;
    }
    .tasks-container.asm-tasks-pro .filter-btn:hover {
        background: #ffffff;
        border-color: #205A44;
        color: #205A44;
        box-shadow: 0 4px 10px rgba(15, 63, 45, 0.1);
    }
    .tasks-container.asm-tasks-pro .filter-btn.active {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        border-color: #0b4b30;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(6, 58, 28, 0.2);
    }
    .tasks-container.asm-tasks-pro .date-filter-select,
    .tasks-container.asm-tasks-pro .task-filter-select {
        border-radius: 12px;
        border-width: 1px;
        border-color: #205A44;
        min-height: 46px;
        font-weight: 600;
        color: #0f3f2d;
        background-color: #fff;
    }
    .tasks-container.asm-tasks-pro .date-filter-select:focus,
    .tasks-container.asm-tasks-pro .task-filter-select:focus {
        box-shadow: 0 0 0 3px rgba(32, 90, 68, 0.14);
    }
    .tasks-container.asm-tasks-pro #customDatePicker {
        border-radius: 12px;
        border-width: 1px;
        min-height: 44px;
    }
    .tasks-container.asm-tasks-pro .task-type-filter-desktop,
    .tasks-container.asm-tasks-pro .task-type-filter-mobile,
    .tasks-container.asm-tasks-pro #taskFilterDropdown,
    .tasks-container.asm-tasks-pro #removeAllOverdueBtn {
        display: none !important;
    }
    .tasks-container.asm-tasks-pro .task-filter-layout,
    .tasks-container.asm-tasks-pro .task-filter-main,
    .tasks-container.asm-tasks-pro .date-filter-desktop {
        width: 100%;
    }
    .tasks-container.asm-tasks-pro .task-filter-main {
        align-items: center;
    }
    .tasks-container.asm-tasks-pro .date-filter-desktop {
        flex: 1 1 100%;
    }
    .tasks-container.asm-tasks-pro #dateFilterDropdownDesktop,
    .tasks-container.asm-tasks-pro #dateFilterDropdown {
        width: 100%;
    }
    .asm-pod-toggle {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px;
        border-radius: 999px;
        border: 1px solid #d7e4dc;
        background: #ffffff;
        box-shadow: 0 8px 18px rgba(6, 58, 28, 0.08);
        margin-left: auto;
        flex: 0 0 auto;
    }
    .asm-pod-toggle-btn {
        border: 0;
        background: transparent;
        color: #4a6457;
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
        padding: 10px 14px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    }
    .asm-pod-toggle-btn.active {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(6, 58, 28, 0.18);
    }
    .asm-pod-toggle-btn.is-pod .asm-pod-toggle-count {
        background: rgba(220, 38, 38, 0.12);
        color: #b91c1c;
    }
    .asm-pod-toggle-btn.is-pod.active {
        background: linear-gradient(135deg, #991b1b 0%, #dc2626 100%);
    }
    .asm-pod-toggle-btn.is-pod.active .asm-pod-toggle-count {
        background: rgba(255, 255, 255, 0.16);
        color: #ffffff;
    }
    .asm-pod-toggle-count {
        min-width: 24px;
        height: 24px;
        padding: 0 8px;
        border-radius: 999px;
        background: #ecfdf3;
        color: #166534;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 800;
    }
    .asm-pod-summary {
        display: none;
        width: 100%;
        margin-top: 10px;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid #fecaca;
        background: linear-gradient(180deg, #fff7f7 0%, #fff1f1 100%);
        color: #991b1b;
    }
    .asm-pod-summary.active {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .asm-pod-summary-copy {
        min-width: 0;
    }
    .asm-pod-summary-title {
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }
    .asm-pod-summary-note {
        font-size: 13px;
        color: #7f1d1d;
        margin-top: 4px;
    }
    .asm-pod-summary-count {
        min-width: 44px;
        height: 44px;
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid #fecaca;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 800;
        color: #b91c1c;
    }
    .tasks-container.asm-tasks-pro .tasks-grid {
        gap: 16px;
        margin-top: 14px;
    }
    .asm-desktop-shell {
        display: none;
    }
    .asm-desktop-queue {
        min-width: 0;
    }
    .asm-desktop-summary {
        display: grid;
        gap: 10px;
    }
    .asm-desktop-summary-card {
        border: 1px solid #d9e5dd;
        border-radius: 18px;
        padding: 14px 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f7fbf8 100%);
        box-shadow: 0 8px 20px rgba(6, 58, 28, 0.06);
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 6px 12px;
        align-items: center;
        text-align: left;
    }
    .asm-desktop-summary-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(6, 58, 28, 0.1);
    }
    .asm-desktop-summary-card.active {
        border-color: #0b4b30;
        box-shadow: 0 14px 28px rgba(6, 58, 28, 0.12);
    }
    .asm-desktop-summary-card.overdue.active,
    .asm-desktop-summary-card.pod.active {
        border-color: #b91c1c;
    }
    .asm-desktop-summary-label {
        display: block;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6a8377;
    }
    .asm-desktop-summary-value {
        margin-top: 0;
        font-size: 30px;
        font-weight: 800;
        line-height: 1;
        color: #123b2b;
        justify-self: end;
    }
    .asm-desktop-summary-note {
        margin-top: 0;
        grid-column: 1 / -1;
        font-size: 12px;
        line-height: 1.45;
        color: #5f7469;
    }
    .asm-desktop-summary-card.overdue {
        background: linear-gradient(180deg, #ffffff 0%, #fff5f5 100%);
    }
    .asm-desktop-summary-card.overdue .asm-desktop-summary-value,
    .asm-desktop-summary-card.pod .asm-desktop-summary-value {
        color: #b91c1c;
    }
    .asm-desktop-summary-card.pod {
        background: linear-gradient(180deg, #ffffff 0%, #fff1f1 100%);
    }
    .asm-desktop-queue-panel {
        border: 1px solid #d9e7df;
        border-radius: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfb 100%);
        box-shadow: 0 16px 28px rgba(6, 58, 28, 0.07);
        overflow: hidden;
    }
    .asm-desktop-queue-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 16px 20px 14px;
        border-bottom: 1px solid #e4ece7;
        background: linear-gradient(180deg, rgba(248, 252, 250, 0.95) 0%, rgba(255, 255, 255, 0.98) 100%);
    }
    .asm-desktop-queue-title {
        margin: 0;
        font-size: 20px;
        font-weight: 800;
        color: #123b2b;
    }
    .asm-desktop-queue-subtitle {
        margin-top: 4px;
        font-size: 13px;
        color: #667c70;
    }
    .asm-desktop-queue-chip {
        min-width: 44px;
        height: 44px;
        padding: 0 14px;
        border-radius: 14px;
        background: #ecfdf3;
        color: #166534;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 800;
    }
    .asm-desktop-calendar-shell {
        padding: 16px 20px 18px;
        border-bottom: 1px solid #e7efe9;
        background: linear-gradient(180deg, #f8fcfa 0%, #fdfefd 100%);
    }
    .asm-desktop-calendar-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 12px;
    }
    .asm-desktop-calendar-label p {
        margin: 0;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6a8377;
    }
    .asm-desktop-calendar-label div {
        margin-top: 4px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 800;
        color: #123b2b;
    }
    .asm-desktop-calendar-label div i {
        color: #205A44;
    }
    .asm-desktop-calendar-nav {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .asm-desktop-calendar-nav button {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        border: 1px solid #d7e4dc;
        background: #ffffff;
        color: #205A44;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 6px 14px rgba(6, 58, 28, 0.06);
    }
    .asm-desktop-task-week {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 8px;
        padding: 8px;
        border-radius: 20px;
        border: 1px solid #deebe4;
        background: linear-gradient(180deg, #ffffff 0%, #f2f8f5 100%);
    }
    .asm-desktop-day {
        border: 0;
        border-radius: 16px;
        background: transparent;
        color: #254a3c;
        min-height: 106px;
        padding: 14px 10px 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    }
    .asm-desktop-day:hover {
        background: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(6, 58, 28, 0.08);
    }
    .asm-desktop-day-name {
        font-size: 12px;
        font-weight: 700;
        color: #6a8377;
    }
    .asm-desktop-day-date {
        width: 40px;
        height: 40px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 800;
        color: #143a2c;
    }
    .asm-desktop-day-count {
        min-width: 30px;
        padding: 4px 8px;
        border-radius: 999px;
        background: #eef7f1;
        color: #205A44;
        font-size: 12px;
        font-weight: 800;
        line-height: 1;
    }
    .asm-desktop-day-meta {
        font-size: 11px;
        font-weight: 700;
        color: #7b8f84;
        min-height: 16px;
    }
    .asm-desktop-day.active {
        background: linear-gradient(180deg, #eef7f1 0%, #ffffff 100%);
        box-shadow: 0 16px 28px rgba(6, 58, 28, 0.1);
    }
    .asm-desktop-day.active .asm-desktop-day-date {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        color: #fff;
    }
    .asm-desktop-day.is-today .asm-desktop-day-meta {
        color: #205A44;
    }
    .asm-desktop-calendar-pod {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 18px;
        border: 1px solid #fecaca;
        background: linear-gradient(180deg, #fff7f7 0%, #fff1f1 100%);
    }
    .asm-desktop-calendar-pod.active {
        display: flex;
    }
    .asm-desktop-calendar-pod-copy strong {
        display: block;
        font-size: 15px;
        color: #991b1b;
    }
    .asm-desktop-calendar-pod-copy span {
        display: block;
        margin-top: 4px;
        font-size: 13px;
        color: #7f1d1d;
    }
    .asm-desktop-calendar-pod-count {
        min-width: 42px;
        height: 42px;
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid #fecaca;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 800;
        color: #b91c1c;
    }
    .asm-desktop-row {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(150px, 0.8fr) minmax(220px, 0.95fr) 220px;
        gap: 16px;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #edf2ef;
        background: #ffffff;
    }
    .asm-desktop-row:last-child {
        border-bottom: 0;
    }
    .asm-desktop-row.overdue {
        background: linear-gradient(90deg, rgba(254, 242, 242, 0.88) 0%, rgba(255,255,255,0.96) 38%);
    }
    .asm-desktop-row-main {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
    }
    .asm-desktop-row-avatar {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        font-weight: 800;
        flex: 0 0 46px;
    }
    .asm-desktop-row-copy {
        min-width: 0;
    }
    .asm-desktop-row-title {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
        color: #123b2b;
        line-height: 1.2;
    }
    .asm-desktop-row-title a {
        color: inherit;
        text-decoration: none;
    }
    .asm-desktop-row-title a:hover {
        color: #205A44;
    }
    .asm-desktop-row-subtitle,
    .asm-desktop-row-phone,
    .asm-desktop-row-note {
        margin-top: 5px;
        font-size: 13px;
        color: #5f7469;
        line-height: 1.45;
    }
    .asm-desktop-row-time {
        font-size: 14px;
        font-weight: 700;
        color: #173b2c;
    }
    .asm-desktop-row-time small {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        font-weight: 700;
        color: #6a8377;
    }
    .asm-desktop-row-status {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-start;
    }
    .asm-desktop-row-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }
    .asm-desktop-row-actions .task-action-btn {
        min-width: 104px;
        min-height: 42px;
        border-radius: 12px;
    }
    .tasks-container.asm-tasks-list-view .tasks-grid {
        grid-template-columns: 1fr;
    }
    .tasks-container.asm-tasks-list-view .task-card {
        display: grid;
        grid-template-columns: minmax(0, 1.3fr) minmax(180px, 0.7fr);
        gap: 16px;
        align-items: start;
    }
    .tasks-container.asm-tasks-list-view .task-actions {
        flex-direction: column;
        justify-content: stretch;
    }
    .tasks-container.asm-tasks-list-view .task-action-btn {
        width: 100%;
    }
    .task-view-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem;
        border-radius: 999px;
        border: 1px solid #d7dfdb;
        background: #fff;
    }
    .task-view-toggle button {
        border: 0;
        background: transparent;
        color: #527166;
        padding: 0.58rem 0.95rem;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 700;
    }
    .task-view-toggle button.active {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        color: #fff;
        box-shadow: 0 8px 18px rgba(6, 58, 28, 0.18);
    }
    .tasks-container.asm-tasks-pro .task-card {
        border-width: 1px;
        border-color: #d9e5dd;
        border-radius: 14px;
        box-shadow: 0 4px 16px rgba(6, 58, 28, 0.06);
        padding: 16px;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .tasks-container.asm-tasks-pro .task-card:hover {
        border-color: #b8d0c4;
        box-shadow: 0 8px 20px rgba(6, 58, 28, 0.12);
        transform: translateY(-2px);
    }
    .tasks-container.asm-tasks-pro .task-card.overdue {
        border-left: 4px solid #dc2626;
        border-color: #fecaca;
        background: linear-gradient(180deg, #ffffff 0%, #fff5f5 100%);
    }
    .tasks-container.asm-tasks-pro .task-header {
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5ece8;
        align-items: flex-start;
        gap: 10px;
    }
    .tasks-container.asm-tasks-pro .task-status-row {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .tasks-container.asm-tasks-pro .task-title-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 8px;
    }
    .tasks-container.asm-tasks-pro .task-title-copy {
        min-width: 0;
        flex: 1 1 auto;
    }
    .tasks-container.asm-tasks-pro .task-sequence-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
        height: 28px;
        padding: 0 12px;
        border-radius: 10px 14px 14px 10px;
        background: linear-gradient(135deg, #f97316 0%, #dc2626 100%);
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.02em;
    }
    .tasks-container.asm-tasks-pro .task-head-main {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        width: 100%;
        min-width: 0;
    }
    .tasks-container.asm-tasks-pro .task-head-content {
        min-width: 0;
        flex: 1;
    }
    .tasks-container.asm-tasks-pro .task-name {
        font-size: 17px;
        color: #0b3d29;
        line-height: 1.25;
        margin-bottom: 4px;
    }
    .tasks-container.asm-tasks-pro .task-subtitle {
        font-size: 14px;
        color: #536d61;
        line-height: 1.35;
        margin: 0;
    }
    .tasks-container.asm-tasks-pro .task-badge-row {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
    }
    .tasks-container.asm-tasks-pro .task-info {
        margin-bottom: 10px;
    }
    .tasks-container.asm-tasks-pro .task-info-row {
        font-size: 13px;
        color: #1f3f31;
        margin-bottom: 6px;
    }
    .tasks-container.asm-tasks-pro .task-info-row i {
        color: #205A44;
        width: 14px;
        margin-top: 2px;
    }
    .tasks-container.asm-tasks-pro .task-actions {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e5ece8;
        gap: 8px;
    }
    .tasks-container.asm-tasks-pro .task-action-btn {
        min-height: 40px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
    }
    .tasks-container.asm-tasks-pro .btn-call {
        flex: 1 1 0;
    }
    .tasks-container.asm-tasks-pro .btn-call span {
        display: inline;
    }
    .tasks-container.asm-tasks-pro .btn-view-detail {
        border: 1px solid #d1ddd6;
        background: #f8faf9;
        color: #0f3f2d;
    }
    .tasks-container.asm-tasks-pro .btn-view-detail:hover {
        background: #eff5f1;
        border-color: #bdd0c6;
    }
    .task-mini-tag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
    }
    .task-mini-tag.meeting { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .task-mini-tag.visit { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
    .task-mini-tag.prospect { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
    .task-mini-tag.site-visit { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
    .task-mini-tag.followup { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .task-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1;
        background: #ecfdf5;
        color: #065f46;
    }
    .task-chip.meeting { background: #dcfce7; color: #065f46; }
    .task-chip.visit { background: #dbeafe; color: #1d4ed8; }
    .task-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    .task-chip-icon {
        font-size: 10px;
    }
    .asm-mobile-task-shell {
        display: none;
    }
    .asm-mobile-task-topbar,
    .asm-mobile-task-week,
    .asm-mobile-task-summary {
        display: none;
    }
    @media (max-width: 768px) {
        .tasks-container.asm-tasks-pro .filter-bar {
            padding: 10px;
            border-radius: 12px;
        }
        .tasks-container.asm-tasks-pro .task-filter-layout {
            gap: 8px;
        }
        .tasks-container.asm-tasks-pro .asm-pod-toggle {
            width: 100%;
            justify-content: stretch;
            margin-left: 0;
        }
        .tasks-container.asm-tasks-pro .asm-pod-toggle-btn {
            flex: 1 1 50%;
        }
        .tasks-container.asm-tasks-pro .asm-pod-summary.active {
            align-items: flex-start;
        }
        .tasks-container.asm-tasks-pro .task-actions {
            flex-direction: column;
        }
        .tasks-container.asm-tasks-pro .task-action-btn {
            width: 100%;
        }
        .tasks-container.asm-tasks-pro .task-name {
            font-size: 15px;
        }
        .tasks-container.asm-tasks-pro .task-subtitle {
            font-size: 12px;
        }
        .tasks-container.asm-tasks-pro .task-info-row {
            align-items: flex-start;
        }
        .tasks-container.asm-tasks-pro .task-topbar {
            margin-bottom: 8px;
        }
        .tasks-container.asm-tasks-pro .task-sequence-chip {
            min-width: 38px;
            height: 24px;
            padding: 0 10px;
            font-size: 12px;
        }
    }
    .btn-remove-overdue {
        padding: 10px 20px;
        border: 2px solid #ef4444;
        border-radius: 8px;
        background: #ef4444;
        color: white;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-remove-overdue:hover {
        background: #dc2626;
        border-color: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    .btn-remove-overdue:disabled {
        background: #d1d5db;
        border-color: #d1d5db;
        cursor: not-allowed;
        transform: none;
    }
    .tasks-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .asm-outcome-modal {
        border-radius: 22px;
        overflow: hidden;
        border: 1px solid rgba(0, 115, 177, 0.14);
        box-shadow: 0 24px 70px rgba(2, 32, 54, 0.24);
    }
    .asm-outcome-modal .modal-header {
        padding: 28px 28px 20px;
        border-bottom: 1px solid #e6eef4;
        background: linear-gradient(135deg, #f8fbff 0%, #ffffff 68%);
        align-items: flex-start;
    }
    .asm-outcome-modal .modal-header h3 {
        margin: 0;
        color: #08283d;
        font-size: 24px;
        line-height: 1.15;
        font-weight: 800;
    }
    .asm-outcome-subtitle {
        margin: 7px 0 0;
        color: #5b7082;
        font-size: 13px;
        line-height: 1.45;
        font-weight: 600;
    }
    .asm-outcome-modal .close-modal {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #6f7d8a;
        background: #eef5fb;
        border: 1px solid #d8e6f0;
        font-size: 24px;
        line-height: 1;
        transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
    }
    .asm-outcome-modal .close-modal:hover {
        background: #dff0fb;
        color: #005f91;
        transform: translateY(-1px);
    }
    .asm-outcome-modal .modal-body {
        padding: 22px 28px 28px;
        background: #fff;
    }
    .asm-outcome-modal-body {
        padding: 0;
        text-align: center;
    }
    .asm-outcome-copy {
        font-size: 15px;
        color: #374151;
        margin: 0 0 24px;
        line-height: 1.6;
    }
    .asm-outcome-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .asm-outcome-btn {
        position: relative;
        min-height: 64px;
        border: none;
        border-radius: 14px;
        color: #fff;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 15px 16px;
        text-align: center;
        overflow: hidden;
        box-shadow: 0 12px 24px rgba(15, 76, 117, 0.14);
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }
    .asm-outcome-btn::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.16), rgba(255,255,255,0));
        pointer-events: none;
    }
    .asm-outcome-btn i,
    .asm-outcome-btn span {
        position: relative;
        z-index: 1;
    }
    .asm-outcome-btn:hover {
        transform: translateY(-1px);
        filter: saturate(1.05);
        box-shadow: 0 16px 30px rgba(15, 76, 117, 0.2);
    }
    .asm-outcome-btn-green { background: linear-gradient(135deg, #0073b1 0%, #005f91 100%); }
    .asm-outcome-btn-slate { background: linear-gradient(135deg, #6b7280 0%, #475569 100%); }
    .asm-outcome-btn-blue { background: linear-gradient(135deg, #2f6bed 0%, #1556c0 100%); }
    .asm-outcome-btn-amber { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .asm-outcome-btn-red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
    .asm-outcome-btn-full {
        grid-column: 1 / -1;
    }
    @media (max-width: 768px) {
        .tasks-container {
            padding: 12px !important;
        }
        
        .tasks-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px !important;
        }
        
        .task-card {
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
            padding: 12px !important;
            border-radius: 10px !important;
            margin-bottom: 0 !important;
        }
        
        /* Improve card appearance - less dense */
        .task-card.overdue {
            border-width: 2px !important;
            border-color: #fca5a5 !important;
            background: #fff5f5 !important;
        }
        
        /* Task header - more compact */
        .task-header {
            margin-bottom: 10px !important;
            padding-bottom: 10px !important;
        }
        
        .task-avatar {
            width: 40px !important;
            height: 40px !important;
            font-size: 16px !important;
            margin-right: 8px !important;
        }
        
        .task-name {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.3 !important;
        }
        
        /* Badges - smaller and cleaner */
        .overdue-badge {
            padding: 4px 8px !important;
            font-size: 9px !important;
            margin-bottom: 4px !important;
            display: inline-block !important;
        }
        
        .status-badge {
            padding: 3px 8px !important;
            font-size: 9px !important;
            margin-top: 4px !important;
        }
        
        /* Task info - more compact */
        .task-info {
            margin-bottom: 10px !important;
        }
        
        .task-info-row {
            font-size: 11px !important;
            margin-bottom: 6px !important;
            gap: 6px !important;
        }
        
        .task-info-row i {
            width: 12px !important;
            font-size: 11px !important;
        }
        
        /* Action buttons - larger and more touch-friendly */
        .task-actions {
            margin-top: 10px !important;
            padding-top: 10px !important;
            gap: 6px !important;
            flex-direction: column !important;
        }
        
        .task-action-btn {
            width: 100% !important;
            padding: 10px 8px !important;
            font-size: 11px !important;
            border-radius: 6px !important;
            min-height: 36px !important;
        }
        
        .task-action-btn i {
            font-size: 12px !important;
        }
        
        .tasks-container {
            padding: 12px !important;
        }
        .filter-bar {
            flex-direction: row;
            gap: 8px;
            align-items: center;
        }
        .task-filter-layout {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .task-filter-main {
            width: 100%;
        }
        .task-filter-extra {
            width: 100%;
            justify-content: flex-end;
            margin-left: 0;
            flex-wrap: wrap;
        }
        .task-view-toggle {
            width: 100%;
            justify-content: stretch;
        }
        .task-view-toggle button {
            flex: 1 1 0;
        }
        .tasks-container.asm-tasks-list-view .task-card {
            grid-template-columns: 1fr;
        }
        
        /* Hide desktop buttons on mobile */
        .filter-buttons-desktop {
            display: none !important;
        }
        
        /* Show dropdowns on mobile - 50% each */
        .filter-dropdowns-mobile {
            display: flex !important;
            flex: 1;
            gap: 8px;
            min-width: 0;
            flex-wrap: wrap;
        }

        .task-type-filter-mobile {
            display: flex !important;
        }
        
        .task-filter-select,
        .date-filter-select {
            flex: 1;
            width: 50%;
            padding: 10px 16px;
            border: 2px solid #205A44;
            border-radius: 8px;
            background: white;
            color: #063A1C;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23063A1C' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }
        
        .task-filter-select:focus,
        .date-filter-select:focus {
            outline: none;
            border-color: #063A1C;
            box-shadow: 0 0 0 3px rgba(6, 58, 28, 0.1);
        }
        
        /* Custom date picker on mobile */
        #customDatePicker {
            width: 100%;
            margin-top: 8px;
        }
        
        .filter-btn {
            display: none;
        }
        
        .btn-remove-overdue {
            width: auto;
            margin-left: 0;
            padding: 10px 16px;
            font-size: 12px;
            justify-content: center;
        }
        .asm-outcome-modal {
            width: calc(100vw - 24px);
            max-width: 420px !important;
        }
        .asm-outcome-modal .modal-header {
            padding: 18px 18px 14px;
        }
        .asm-outcome-modal .modal-header h3 {
            font-size: 18px;
        }
        .asm-outcome-modal .modal-body {
            padding: 0 18px 18px;
        }
        .asm-outcome-copy {
            font-size: 14px;
            margin-bottom: 18px;
        }
        .asm-outcome-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .asm-outcome-btn {
            min-height: 54px;
            font-size: 14px;
            padding: 12px 10px;
            gap: 6px;
        }
        .tasks-container.asm-tasks-pro {
            padding: 12px !important;
            border-radius: 20px;
            background: linear-gradient(180deg, #fdfefe 0%, #f4f8f7 100%);
            box-shadow: none;
            border-color: #e4ece8;
        }
        .tasks-container.asm-tasks-pro .filter-bar {
            display: none !important;
        }
        .asm-mobile-task-shell {
            display: block !important;
        }
        .task-filter-layout {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            align-items: stretch;
        }
        .task-filter-main.filter-dropdowns-mobile {
            width: auto;
            display: contents !important;
            flex: none;
        }
        .task-type-filter-mobile {
            display: block !important;
            width: auto;
            min-width: 0;
        }
        .task-filter-main.filter-dropdowns-mobile select,
        .task-type-filter-mobile .task-filter-select {
            width: 100%;
            min-width: 0;
            min-height: 50px;
            padding: 0 14px;
            border: 1.5px solid #2c6b56;
            border-radius: 16px;
            background-color: #fff;
            font-size: 13px;
            font-weight: 700;
            color: #113b2b;
            box-shadow: none;
        }
        .task-filter-extra {
            grid-column: 1 / -1;
            width: 100%;
            justify-content: flex-end;
        }
        .asm-mobile-task-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .asm-mobile-task-headline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }
        .asm-mobile-task-date {
            min-width: 0;
            flex: 1 1 auto;
        }
        .asm-mobile-task-date-label {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #7f948a;
        }
        .asm-mobile-task-date-value {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 3px;
            font-size: 18px;
            font-weight: 800;
            color: #133b2b;
            line-height: 1.1;
        }
        .asm-mobile-task-date-value i {
            font-size: 14px;
            color: #205A44;
        }
        .asm-mobile-task-head-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex: 0 0 auto;
        }
        .asm-mobile-mode-toggle {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px;
            border-radius: 999px;
            border: 1px solid #dbe7e1;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 10px 22px rgba(6, 58, 28, 0.08);
        }
        .asm-mobile-task-nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            flex-wrap: wrap;
            max-width: 210px;
        }
        .asm-mobile-task-nav,
        .asm-mobile-task-toolbar {
            display: none !important;
        }
        .asm-mobile-range-btn,
        .asm-mobile-today-btn {
            border: 1px solid #d7e1dc;
            background: rgba(255, 255, 255, 0.92);
            color: #12412f;
            border-radius: 10px;
            height: 34px;
            min-width: 34px;
            padding: 0 10px;
            font-size: 11px;
            font-weight: 800;
            box-shadow: 0 6px 14px rgba(6, 58, 28, 0.08);
        }
        .asm-mobile-head-btn {
            min-width: 0;
            height: 32px;
            padding: 0 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            white-space: nowrap;
        }
        .asm-mobile-mode-toggle .asm-mobile-head-btn {
            border: 0;
            background: transparent;
            box-shadow: none;
            color: #36574b;
            min-width: 62px;
            font-size: 12px;
            font-weight: 800;
        }
        .asm-mobile-mode-toggle .asm-mobile-head-btn.active {
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
            color: #ffffff;
            box-shadow: 0 8px 18px rgba(6, 58, 28, 0.18);
        }
        .asm-mobile-mode-toggle .asm-mobile-head-btn.is-pod.active {
            background: linear-gradient(135deg, #991b1b 0%, #dc2626 100%);
        }
        .asm-mobile-range-btn {
            padding: 0 10px;
        }
        .asm-mobile-range-btn.active {
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
            color: #ffffff;
            border-color: #12412f;
        }
        .asm-mobile-range-btn.is-pod {
            position: relative;
        }
        .asm-mobile-range-btn.is-pod.active {
            background: linear-gradient(135deg, #991b1b 0%, #dc2626 100%);
            border-color: #991b1b;
        }
        .asm-mobile-range-badge {
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: rgba(220, 38, 38, 0.12);
            color: #b91c1c;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 800;
            margin-left: 4px;
        }
        .asm-mobile-head-btn .asm-mobile-range-badge {
            margin-left: 0;
        }
        .asm-mobile-range-btn.is-pod.active .asm-mobile-range-badge {
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
        }
        .asm-mobile-task-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }
        .asm-mobile-task-toolbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .asm-mobile-task-week-viewport {
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            touch-action: pan-y;
            scroll-behavior: smooth;
            scroll-snap-type: x proximity;
        }
        .asm-mobile-task-week-viewport::-webkit-scrollbar {
            display: none;
        }
        .asm-mobile-task-week {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 4px;
            padding: 10px 4px;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f3f9f6 100%);
            border: 1px solid #dfe8e3;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8), 0 16px 34px rgba(6, 58, 28, 0.06);
            width: 100%;
            min-width: 0;
        }
        .asm-mobile-day {
            text-align: center;
            min-width: 0;
            appearance: none;
            border: 0;
            background: rgba(241, 247, 244, 0.72);
            cursor: pointer;
            border-radius: 14px;
            padding: 7px 2px 8px;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
            scroll-snap-align: start;
        }
        .asm-mobile-day.is-today {
            background: rgba(224, 239, 232, 0.96);
        }
        .asm-mobile-day.active {
            background: linear-gradient(180deg, rgba(32, 90, 68, 0.12) 0%, rgba(6, 58, 28, 0.03) 100%);
            box-shadow: 0 12px 22px rgba(6, 58, 28, 0.1);
            transform: translateY(-1px);
        }
        .asm-mobile-day-name {
            display: block;
            font-size: 9px;
            font-weight: 700;
            color: #7a8f85;
            margin-bottom: 4px;
        }
        .asm-mobile-day-date {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 9px;
            font-size: 11px;
            font-weight: 800;
            color: #416155;
            background: transparent;
        }
        .asm-mobile-day.active .asm-mobile-day-date {
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
            color: #fff;
            box-shadow: 0 8px 18px rgba(6, 58, 28, 0.2);
        }
        .asm-mobile-day-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 20px;
            padding: 0 7px;
            border-radius: 999px;
            background: #eef7f1;
            color: #205A44;
            font-size: 10px;
            font-weight: 800;
            line-height: 1;
        }
        .asm-mobile-day-meta {
            display: block;
            margin-top: 5px;
            font-size: 9px;
            font-weight: 700;
            color: #7f948a;
            min-height: 12px;
        }
        .asm-mobile-day.active .asm-mobile-day-meta {
            color: #205A44;
        }
        .asm-mobile-task-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 10px;
            margin-bottom: 2px;
            padding: 0;
        }
        .asm-mobile-summary-chip {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px 12px 10px;
            border-radius: 16px;
            border: 1px solid #deebe4;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 10px 24px rgba(6, 58, 28, 0.05);
        }
        .asm-mobile-summary-chip strong {
            font-size: 18px;
            line-height: 1;
            color: #123b2b;
        }
        .asm-mobile-summary-chip span {
            font-size: 11px;
            font-weight: 700;
            color: #70867c;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .asm-mobile-summary-chip.overdue strong {
            color: #b42318;
        }
        .asm-mobile-summary-chip.focus strong {
            color: #205A44;
        }
        .tasks-grid {
            grid-template-columns: 1fr;
            gap: 8px;
            margin-top: 8px !important;
        }
        .tasks-container.asm-tasks-pro .task-card,
        .task-card {
            padding: 8px !important;
            border-radius: 14px !important;
            background: #ffffff;
            border: 1px solid #e3ebe7 !important;
            box-shadow: 0 4px 12px rgba(6, 58, 28, 0.04);
        }
        .tasks-container.asm-tasks-pro .task-card.overdue,
        .task-card.overdue {
            border-left: 2px solid #dc2626 !important;
            border-color: #f2cccc !important;
            background: linear-gradient(180deg, #ffffff 0%, #fff6f6 100%) !important;
        }
        .task-sequence-chip {
            min-width: 32px !important;
            height: 20px !important;
            padding: 0 8px !important;
            font-size: 10px !important;
            border-radius: 8px 12px 12px 8px !important;
        }
        .task-header {
            margin-bottom: 6px !important;
            padding-bottom: 6px !important;
            border-bottom: 1px solid #ecf1ee !important;
        }
        .task-avatar {
            width: 26px !important;
            height: 26px !important;
            font-size: 11px !important;
            margin-right: 6px !important;
            border-radius: 8px !important;
        }
        .task-name {
            font-size: 13px !important;
            line-height: 1.15 !important;
            font-weight: 700 !important;
            margin-bottom: 2px !important;
        }
        .task-info {
            margin-bottom: 0 !important;
        }
        .task-info-row {
            font-size: 10px !important;
            margin-bottom: 4px !important;
            gap: 5px !important;
            color: #506a5f !important;
        }
        .task-info-row i {
            width: 10px !important;
            font-size: 10px !important;
        }
        .task-actions {
            margin-top: 6px !important;
            padding-top: 6px !important;
            border-top: 1px solid #ecf1ee !important;
            flex-direction: row !important;
            gap: 5px !important;
        }
        .task-action-btn {
            min-height: 30px !important;
            border-radius: 8px !important;
            font-size: 10px !important;
            font-weight: 700 !important;
            padding: 6px 8px !important;
        }
        .tasks-container.asm-tasks-pro .btn-call {
            flex: 1 1 0 !important;
            padding: 6px 8px !important;
        }
        .tasks-container.asm-tasks-pro .btn-call span {
            display: inline !important;
        }
        .btn-view-detail {
            flex: 1 1 auto;
            background: #dfeaf6 !important;
            color: #1a4464 !important;
            border: 1px solid #c7d8e8 !important;
        }
        .task-head-main {
            gap: 6px !important;
        }
        .task-head-content {
            min-width: 0;
        }
        .task-subtitle {
            font-size: 10px !important;
            line-height: 1.15 !important;
        }
        .task-title-row {
            gap: 6px !important;
        }
        .task-status-row {
            gap: 4px !important;
            flex-wrap: nowrap !important;
            justify-content: flex-end !important;
        }
        .status-badge,
        .overdue-badge {
            font-size: 8px !important;
            padding: 2px 5px !important;
            border-radius: 999px !important;
            margin-top: 0 !important;
        }
    }
    @media (max-width: 420px) {
        .asm-outcome-grid {
            grid-template-columns: 1fr 1fr;
        }
        .asm-outcome-btn {
            min-height: 52px;
            font-size: 13px;
        }
    }
    @media (max-width: 768px) {
        .asm-desktop-priority-layout .asm-desktop-shell {
            display: block;
            margin-top: 8px;
        }
        .asm-desktop-priority-layout #tasksGrid.tasks-grid {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 10px !important;
            margin-top: 12px !important;
            min-height: 120px;
        }
        .asm-desktop-priority-layout .asm-desktop-calendar-shell {
            display: none;
        }
        .asm-desktop-priority-layout .asm-desktop-summary {
            display: none;
        }
        .asm-desktop-priority-layout .asm-desktop-queue-panel {
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            overflow: visible;
        }
        .asm-desktop-priority-layout .asm-desktop-queue-head {
            display: none;
        }
    }
    
    /* Desktop: Show buttons, hide mobile dropdowns */
    @media (min-width: 769px) {
        .asm-desktop-priority-layout .asm-desktop-shell {
            display: grid;
            grid-template-columns: 224px minmax(0, 1fr);
            gap: 16px;
            align-items: start;
            margin-top: 14px;
        }
        .asm-desktop-priority-layout #tasksGrid.tasks-grid {
            grid-template-columns: 1fr !important;
            gap: 0 !important;
            margin-top: 0 !important;
        }
        .asm-desktop-priority-layout .task-filter-container {
            padding: 10px 14px !important;
        }
        .asm-desktop-priority-layout .task-filter-main {
            gap: 10px;
        }
        .asm-desktop-priority-layout .asm-desktop-queue-head {
            display: none;
        }
        .asm-desktop-priority-layout .asm-mobile-task-shell {
            display: none !important;
        }
        .asm-desktop-priority-layout .task-filter-main .date-filter-select,
        .asm-desktop-priority-layout .task-filter-main .task-filter-select {
            min-height: 42px;
            padding-top: 9px;
            padding-bottom: 9px;
            border-radius: 14px;
            font-size: 14px;
        }
        .asm-desktop-priority-layout .task-card {
            display: block !important;
            position: relative !important;
            min-height: 180px;
            padding: 16px 260px 16px 18px !important;
            border-radius: 0 !important;
            border: 0 !important;
            border-bottom: 1px solid #edf2ef !important;
            box-shadow: none !important;
            background: #fff !important;
        }
        .asm-desktop-priority-layout .task-card.overdue {
            background: linear-gradient(90deg, rgba(254, 242, 242, 0.88) 0%, rgba(255,255,255,0.96) 38%) !important;
            border-left: 0 !important;
        }
        .asm-desktop-priority-layout .task-header,
        .asm-desktop-priority-layout .task-info,
        .asm-desktop-priority-layout .task-actions {
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
        }
        .asm-desktop-priority-layout .task-info {
            padding-left: 60px !important;
            max-width: calc(100% - 20px);
        }
        .asm-desktop-priority-layout .task-actions {
            position: absolute !important;
            top: 28px !important;
            right: 18px !important;
            width: 210px !important;
        }
        .asm-desktop-priority-layout .task-head-main {
            align-items: flex-start;
            gap: 12px;
        }
        .asm-desktop-priority-layout .task-title-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .asm-desktop-priority-layout .task-status-row {
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 6px;
        }
        .asm-desktop-priority-layout .task-avatar {
            width: 48px;
            height: 48px;
            border-radius: 15px;
            font-size: 20px;
            flex: 0 0 48px;
        }
        .asm-desktop-priority-layout .task-name {
            font-size: 17px;
            line-height: 1.2;
        }
        .asm-desktop-priority-layout .task-subtitle {
            font-size: 12px;
            line-height: 1.4;
        }
        .asm-desktop-priority-layout .task-info-row {
            font-size: 13px;
            margin-bottom: 7px;
        }
        .asm-desktop-priority-layout .task-actions {
            flex-direction: column !important;
            justify-content: flex-start !important;
            align-items: stretch;
            gap: 8px !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
            border-top: 0 !important;
        }
        .asm-desktop-priority-layout .task-action-btn {
            width: 100%;
            min-width: 0;
            min-height: 40px;
            font-size: 13px;
            border-radius: 14px;
        }
        .asm-desktop-priority-layout .task-info-row:last-child {
            margin-bottom: 0;
        }
        .asm-desktop-priority-layout .overdue-badge,
        .asm-desktop-priority-layout .status-badge {
            padding: 6px 12px;
            font-size: 11px;
        }
        .asm-desktop-priority-layout .btn-view-detail {
            background: #f3f7f4 !important;
            border-color: #d9e5dd !important;
            color: #0f3f2b !important;
        }
        .task-filter-main {
            flex-wrap: nowrap;
        }
        .filter-buttons-desktop {
            display: flex !important;
        }
        .filter-dropdowns-mobile {
            display: none !important;
        }
        
        /* Desktop date filter styles */
        .date-filter-desktop {
            display: block;
        }
        
        .date-filter-select {
            padding: 10px 16px;
            border: 2px solid #205A44;
            border-radius: 8px;
            background: white;
            color: #063A1C;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23063A1C' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
            min-width: 150px;
        }
        
        .date-filter-select:focus {
            outline: none;
            border-color: #063A1C;
            box-shadow: 0 0 0 3px rgba(6, 58, 28, 0.1);
        }
        
        #customDatePicker {
            display: block !important;
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
            border: 0;
            padding: 0;
            margin: 0;
        }

        .tasks-container.asm-tasks-pro .tasks-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
            gap: 14px;
            margin-top: 18px !important;
        }
        .tasks-container.asm-tasks-pro .task-card {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: stretch;
            padding: 14px;
            border-radius: 16px;
            min-width: 0;
        }
        .tasks-container.asm-tasks-list-view .tasks-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
        }
        .tasks-container.asm-tasks-list-view .task-card {
            display: flex !important;
            flex-direction: column;
            gap: 10px;
            min-width: 0;
        }
        .tasks-container.asm-tasks-pro .task-header {
            margin-bottom: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5ece8;
        }
        .tasks-container.asm-tasks-pro .task-head-main {
            align-items: flex-start;
        }
        .tasks-container.asm-tasks-pro .task-title-row {
            align-items: flex-start;
            gap: 8px;
            flex-direction: column;
        }
        .tasks-container.asm-tasks-pro .task-status-row {
            justify-content: flex-start;
            flex-wrap: wrap;
            min-width: 0;
        }
        .tasks-container.asm-tasks-pro .task-avatar {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            font-size: 20px;
            margin-right: 0;
            flex: 0 0 44px;
        }
        .tasks-container.asm-tasks-pro .task-name {
            font-size: 16px;
            line-height: 1.2;
            margin-bottom: 4px;
        }
        .tasks-container.asm-tasks-pro .task-subtitle {
            font-size: 12px;
            line-height: 1.3;
        }
        .tasks-container.asm-tasks-pro .task-info {
            margin-bottom: 0;
            padding-left: 0;
            border-left: 0;
            border-top: 1px solid #eef3f0;
            padding-top: 10px;
        }
        .tasks-container.asm-tasks-pro .task-info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .tasks-container.asm-tasks-pro .task-info-row:last-child {
            margin-bottom: 0;
        }
        .tasks-container.asm-tasks-pro .task-info-row i {
            width: 16px;
            font-size: 14px;
            margin-top: 0;
        }
        .tasks-container.asm-tasks-pro .task-actions {
            margin-top: 0;
            padding-top: 10px;
            border-top: 1px solid #e5ece8;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .tasks-container.asm-tasks-pro .task-action-btn {
            min-height: 42px;
            font-size: 14px;
            border-radius: 12px;
        }
        .tasks-container.asm-tasks-pro .btn-call,
        .tasks-container.asm-tasks-pro .btn-view-detail {
            flex: 1 1 auto;
        }
        .tasks-container.asm-tasks-pro .status-badge,
        .tasks-container.asm-tasks-pro .overdue-badge,
        .tasks-container.asm-tasks-pro .task-sequence-chip {
            font-size: 11px;
            min-height: 24px;
        }
        .tasks-container.asm-tasks-pro .task-sequence-chip {
            min-width: 34px;
            padding: 0 8px;
        }
    }

    @media (min-width: 1440px) {
        .tasks-container.asm-tasks-pro .tasks-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
        }
        .tasks-container.asm-tasks-list-view .tasks-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
        }
    }
    
    .task-card {
        padding: 16px;
    }
        .task-card h3 {
            font-size: 16px !important;
        }
        .task-lead-link {
            color: inherit;
            text-decoration: none;
        }
        .task-lead-link:hover {
            color: #205A44;
            text-decoration: underline;
        }
        .task-card p {
            font-size: 13px !important;
        }
        /* Modal responsive */
        .modal-content {
            width: 95% !important;
            max-width: 95% !important;
            margin: 10px auto !important;
            padding: 16px !important;
        }
        .modal-header h2 {
            font-size: 18px !important;
        }
        /* Form inputs responsive */
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            font-size: 14px;
            margin-bottom: 6px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            font-size: 14px;
        }
        /* Form buttons responsive */
        .form-actions {
            flex-direction: column;
            gap: 10px;
        }
        .form-actions button {
            width: 100%;
            padding: 12px;
        }
    }
    .task-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s;
    }
    .task-card:hover {
        border-color: #205A44;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }
    .task-card.overdue {
        border-color: #ef4444;
        border-width: 3px;
        background: #fef2f2;
    }
    .task-actions {
        display: flex;
        gap: 8px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid #f0f0f0;
    }
    .task-action-btn {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .task-action-btn i {
        font-size: 14px;
    }
    .btn-call {
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        color: white;
    }
    .btn-call:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(32, 90, 68, 0.3);
    }
    .btn-whatsapp {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
    }
    .btn-whatsapp:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
    }
    .btn-view-detail {
        background: #e0e0e0;
        color: #063A1C;
    }
    .btn-view-detail:hover {
        background: #d0d0d0;
        transform: translateY(-1px);
    }
    .task-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }
    .task-detail-card {
        border: 1px solid #d9e7df;
        border-radius: 14px;
        background: #f8fcfa;
        padding: 14px;
    }
    .task-detail-card h4 {
        margin: 0 0 10px 0;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #205A44;
    }
    .task-detail-meta-line {
        font-size: 14px;
        color: #334155;
        line-height: 1.6;
    }
    .task-detail-player {
        border: 1px solid #b7e4d6;
        border-radius: 16px;
        background: #f0fdf8;
        padding: 14px;
    }
    .task-detail-player-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }
    .task-detail-player-btn {
        width: 42px;
        height: 42px;
        border-radius: 999px;
        border: none;
        background: #0f766e;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .task-detail-player-time {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        min-width: 90px;
    }
    .task-detail-speed {
        display: inline-flex;
        gap: 6px;
        align-items: center;
        margin-left: auto;
    }
    .task-detail-speed-btn {
        border: 1px solid #99f6e4;
        background: #fff;
        color: #115e59;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }
    .task-detail-speed-btn.is-active {
        background: #115e59;
        color: #fff;
        border-color: #115e59;
    }
    .task-detail-progress {
        width: 100%;
        accent-color: #111827;
    }
    .task-detail-qa-list {
        display: grid;
        gap: 12px;
    }
    .task-detail-qa-item {
        border: 1px solid #dbe4dc;
        border-radius: 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f9fcfa 100%);
        padding: 14px 16px;
    }
    .task-detail-qa-label {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #5f7469;
        margin-bottom: 8px;
    }
    .task-detail-qa-value {
        font-size: 14px;
        line-height: 1.6;
        color: #0f172a;
        word-break: break-word;
    }
    .task-detail-qa-value.is-empty {
        color: #94a3b8;
        font-style: italic;
    }
    .task-detail-qa-group {
        display: grid;
        gap: 10px;
    }
    .task-detail-qa-inline {
        display: grid;
        grid-template-columns: minmax(160px, 220px) minmax(0, 1fr);
        gap: 12px;
        padding: 10px 0;
        border-top: 1px solid #ecf2ee;
    }
    .task-detail-qa-inline:first-child {
        border-top: 0;
        padding-top: 0;
    }
    .task-detail-qa-inline:last-child {
        padding-bottom: 0;
    }
    .task-detail-qa-inline-label {
        font-size: 12px;
        font-weight: 700;
        color: #486257;
    }
    .task-detail-qa-inline-value {
        font-size: 14px;
        line-height: 1.55;
        color: #0f172a;
        word-break: break-word;
    }
    .task-detail-qa-inline-value.is-empty {
        color: #94a3b8;
        font-style: italic;
    }
    .task-detail-raw-toggle {
        margin-top: 10px;
        border: 0;
        background: transparent;
        color: #0f766e;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 0;
        cursor: pointer;
    }
    .task-detail-raw-toggle:hover {
        color: #115e59;
    }
    .task-detail-payload {
        margin-top: 12px;
        border: 1px solid #dbe4dc;
        border-radius: 14px;
        background: #fff;
        padding: 14px;
        max-height: 280px;
        overflow: auto;
        font-size: 12px;
        color: #0f172a;
        white-space: pre-wrap;
        word-break: break-word;
        display: none;
    }
    .task-detail-payload.active {
        display: block;
    }
    @media (max-width: 768px) {
        .task-detail-grid {
            grid-template-columns: 1fr;
        }
        .task-detail-qa-inline {
            grid-template-columns: 1fr;
            gap: 6px;
        }
    }
    .task-header {
        display: flex;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f0f0;
    }
    .task-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        font-weight: 700;
        margin-right: 12px;
    }
    .task-name {
        font-size: 18px;
        font-weight: 600;
        color: #063A1C;
        margin: 0;
    }
    .task-info {
        margin-bottom: 12px;
    }
    .task-info-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 14px;
        color: #063A1C;
    }
    .task-info-row i {
        color: #205A44;
        width: 16px;
    }
    .overdue-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #ef4444;
        color: white;
        margin-top: 8px;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        margin-top: 8px;
    }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-in_progress { background: #dbeafe; color: #1e40af; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .loading-state, .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #B3B5B4;
    }
    
    /* Keep mobile feedback visible; otherwise API/render errors look like a blank page. */
    @media (max-width: 767px) {
        .tasks-container .empty-state,
        .tasks-container .loading-state {
            display: block !important;
        }
    }
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
        overflow-y: auto;
    }
    .modal.active,
    .modal.show {
        display: flex;
    }
    body.modal-open {
        overflow: hidden;
        overscroll-behavior: none;
    }
    .spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #205A44;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        display: inline-block;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 30px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        margin: auto;
        position: relative;
    }
    .task-meeting-action-hub-modal {
        width: min(92vw, 1100px);
        max-width: 1100px;
        max-height: calc(100dvh - 48px);
        border-radius: 24px;
        overflow: hidden;
        padding: 0;
        display: flex;
        flex-direction: column;
    }
    .task-meeting-action-hub-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 28px 30px;
        background: linear-gradient(135deg, #0d4f2f 0%, #1f6647 100%);
        color: #fff;
        flex: 0 0 auto;
    }
    .task-meeting-action-hub-header h3 {
        margin: 0;
        color: #fff;
        font-size: 20px;
        font-weight: 800;
    }
    .task-meeting-action-hub-header .close-modal {
        color: #fff;
        background: transparent;
        border: 0;
        font-size: 28px;
        cursor: pointer;
    }
    .task-meeting-action-hub-subtitle {
        margin: 10px 0 0;
        color: rgba(255,255,255,0.88);
        font-size: 15px;
        line-height: 1.5;
    }
    .task-meeting-action-hub-modal .modal-body {
        padding: 28px 30px 30px;
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    .task-meeting-action-hub-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px;
    }
    .task-meeting-action-tile {
        border: 1px solid #d9e2ec;
        border-radius: 22px;
        background: #fff;
        padding: 22px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        text-align: left;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .task-meeting-action-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        border-color: #c7d2fe;
    }
    .task-meeting-action-icon {
        width: 56px;
        height: 56px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex: 0 0 56px;
    }
    .task-meeting-action-copy strong {
        display: block;
        font-size: 17px;
        color: #1e293b;
        margin-bottom: 6px;
    }
    .task-meeting-action-copy small {
        display: block;
        font-size: 14px;
        line-height: 1.45;
        color: #64748b;
    }
    .task-meeting-action-tile-blue .task-meeting-action-icon { background: #dbeafe; color: #2563eb; }
    .task-meeting-action-tile-green .task-meeting-action-icon { background: #dcfce7; color: #16a34a; }
    .task-meeting-action-tile-slate .task-meeting-action-icon { background: #e2e8f0; color: #475569; }
    .task-meeting-action-tile-emerald .task-meeting-action-icon { background: #d1fae5; color: #059669; }
    .task-meeting-action-tile-cyan .task-meeting-action-icon { background: #cffafe; color: #0891b2; }
    .task-meeting-more-wrap {
        margin-top: 18px;
    }
    .task-meeting-more-toggle {
        width: 100%;
        border: 1px solid #dbe2ea;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 18px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        color: #1e293b;
        cursor: pointer;
    }
    .task-meeting-more-panel {
        display: none;
        margin-top: 12px;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }
    .task-meeting-more-panel.active {
        display: grid;
    }
    .task-meeting-more-btn {
        border: 1px solid #dbe2ea;
        border-radius: 16px;
        background: #fff;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: #334155;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .task-meeting-more-btn:hover {
        border-color: #cbd5e1;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    }
    @media (max-width: 768px) {
        #taskMeetingActionHubModal.modal,
        #taskFollowUpActionHubModal.modal {
            align-items: flex-start;
            padding: 12px 0 calc(92px + env(safe-area-inset-bottom, 0px));
        }
        .modal-content {
            width: 95%;
            padding: 20px;
            margin: 20px auto;
        }
        .task-meeting-action-hub-modal {
            width: calc(100vw - 18px);
            max-width: 420px;
            max-height: calc(100dvh - 118px - env(safe-area-inset-bottom, 0px));
            margin: 0 auto;
        }
        .task-meeting-action-hub-header {
            padding: 18px;
        }
        .task-meeting-action-hub-modal .modal-body {
            padding: 18px 18px calc(22px + env(safe-area-inset-bottom, 0px));
        }
        .task-meeting-action-hub-grid,
        .task-meeting-more-panel {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .task-meeting-action-tile {
            padding: 16px;
            border-radius: 18px;
        }
        .task-meeting-action-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            font-size: 18px;
            flex-basis: 48px;
        }
        .task-meeting-action-copy strong {
            font-size: 15px;
        }
        .task-meeting-action-copy small {
            font-size: 13px;
        }
        #managerLeadRequirementFormModal.modal {
            padding: 0;
            align-items: stretch;
        }
        #managerLeadRequirementFormModal .modal-content {
            width: 100vw;
            max-width: 100vw;
            min-height: 100dvh;
            max-height: 100dvh;
            margin: 0;
            border-radius: 0;
        }
        #managerLeadRequirementFormModal .modal-header {
            margin: 0;
            padding: 14px 16px;
            border-radius: 0;
        }
        #managerLeadRequirementFormModal .modal-header h3 {
            font-size: 24px;
        }
        #managerLeadRequirementFormModal .modal-body {
            padding: 0;
        }
        #managerLeadRequirementFormModal #managerLeadFormContainer {
            padding: 6px;
            background: #f4f6f5;
        }
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f0f0;
    }
    .modal-header h3 {
        margin: 0;
        font-size: 24px;
        color: #063A1C;
    }
    #managerLeadRequirementFormModal .modal-header {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        margin: 0;
        padding: 18px 20px;
        border-bottom: none;
        border-radius: 12px 12px 0 0;
    }
    #managerLeadRequirementFormModal .modal-content {
        padding: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: calc(100dvh - 32px);
    }
    #managerLeadRequirementFormModal .modal-body {
        padding: 20px;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        flex: 1;
        min-height: 0;
    }
    #managerLeadRequirementFormModal .modal-header h3 {
        color: #ffffff;
        font-size: 34px;
    }
    #managerLeadRequirementFormModal .modal-header .close-btn {
        width: 44px;
        height: 44px;
        border: 1px solid rgba(255, 255, 255, 0.24);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-size: 26px;
        line-height: 1;
        cursor: pointer;
    }
    #managerLeadRequirementFormModal .modal-header .close-btn:hover {
        background: rgba(255, 255, 255, 0.22);
    }
    #managerLeadRequirementFormModal.modal {
        align-items: flex-start;
        padding: 12px 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
    .close-modal {
        background: none;
        border: none;
        font-size: 28px;
        color: #B3B5B4;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .close-modal:hover {
        color: #063A1C;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #063A1C;
    }
    .form-group label .required {
        color: #ef4444;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        background: #ffffff;
        color: #063A1C;
        box-sizing: border-box;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #205A44;
    }
    .form-footer {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        padding-top: 24px;
        border-top: 2px solid #f0f0f0;
    }
    .btn-save {
        flex: 1;
        padding: 12px;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    .btn-cancel {
        flex: 1;
        padding: 12px;
        background: #e0e0e0;
        color: #063A1C;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-cancel:hover {
        background: #d0d0d0;
    }
    .btn-verify {
        flex: 1;
        padding: 12px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-verify:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    .btn-verify:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .btn-reject {
        flex: 1;
        padding: 12px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-reject:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    .btn-reject:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .btn-cnp {
        background: #f59e0b;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .btn-cnp:hover {
        background: #d97706;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
    }
    .btn-cnp:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .time-option-btn {
        transition: all 0.2s ease;
    }
    .time-option-btn:hover {
        border-color: #f59e0b !important;
        background: #fff9e6 !important;
        transform: translateY(-1px);
    }
    .time-option-btn.selected {
        border-color: #f59e0b !important;
        background: #f59e0b !important;
        color: white !important;
        font-weight: 600;
    }
    #cnpTimeSelectionModal .outcome-time-modal {
        border-radius: 22px;
        overflow: hidden;
        border: 1px solid rgba(0, 115, 177, 0.14);
        box-shadow: 0 24px 70px rgba(2, 32, 54, 0.24);
        max-height: calc(100dvh - 48px);
        display: flex;
        flex-direction: column;
    }
    #cnpTimeSelectionModal .modal-header {
        padding: 26px 28px 18px;
        border-bottom: 1px solid #e6eef4;
        background: linear-gradient(135deg, #f8fbff 0%, #ffffff 68%);
        align-items: flex-start;
    }
    #cnpTimeSelectionModal .modal-header h3 {
        margin: 0;
        color: #08283d;
        font-size: 24px;
        line-height: 1.15;
        font-weight: 800;
    }
    #cnpTimeSelectionModal .close-modal {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #6f7d8a;
        background: #eef5fb;
        border: 1px solid #d8e6f0;
        font-size: 24px;
        line-height: 1;
        transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
    }
    #cnpTimeSelectionModal .close-modal:hover {
        background: #dff0fb;
        color: #005f91;
        transform: translateY(-1px);
    }
    #cnpTimeSelectionModal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        padding: 0;
        background: #fff;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    .outcome-time-body {
        padding: 24px 28px 20px !important;
    }
    #outcomeDateTimeModalText {
        margin: 0 0 18px !important;
        color: #40566a !important;
        font-size: 14px !important;
        line-height: 1.5 !important;
        font-weight: 600;
    }
    .outcome-time-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 12px !important;
        margin-bottom: 14px !important;
    }
    #cnpTimeSelectionModal .time-option-btn {
        min-height: 54px;
        border: 1.5px solid #d7e4ee !important;
        border-radius: 14px !important;
        background: #f8fbfd !important;
        color: #102f45 !important;
        font-size: 14px !important;
        font-weight: 800 !important;
        box-shadow: 0 8px 20px rgba(15, 76, 117, 0.07);
    }
    #cnpTimeSelectionModal .time-option-btn:hover {
        border-color: #0073b1 !important;
        background: #eef8ff !important;
        transform: translateY(-1px);
    }
    #cnpTimeSelectionModal .time-option-btn.selected,
    #cnpTimeSelectionModal #customTimeOptionBtn.selected {
        border-color: #0073b1 !important;
        background: linear-gradient(135deg, #0073b1 0%, #005f91 100%) !important;
        color: #fff !important;
        box-shadow: 0 12px 26px rgba(0, 115, 177, 0.22);
    }
    #customTimePickerContainer {
        padding: 18px !important;
        background: #f6fbff !important;
        border: 1px solid #dceaf4;
        border-radius: 16px !important;
        margin-bottom: 16px !important;
    }
    #customTimePickerContainer label {
        color: #102f45 !important;
        font-weight: 800 !important;
    }
    #customTimePickerContainer input,
    #outcomeDateTimeRemark {
        border: 1.5px solid #d7e4ee !important;
        border-radius: 12px !important;
        color: #102f45 !important;
        font-weight: 600;
        box-shadow: none;
    }
    #customTimePickerContainer input:focus,
    #outcomeDateTimeRemark:focus {
        border-color: #0073b1 !important;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 115, 177, 0.12);
    }
    #selectedTimeDisplay {
        padding: 14px 16px !important;
        background: #eaf6ff !important;
        border: 1px solid #cbe6f8;
        border-radius: 14px !important;
        margin-bottom: 16px !important;
    }
    #selectedTimeDisplay p {
        color: #005f91 !important;
        font-weight: 700;
    }
    #cnpTimeSelectionModal .form-group label {
        color: #102f45 !important;
        font-weight: 800 !important;
    }
    #cnpTimeSelectionModal .modal-footer {
        flex: 0 0 auto;
        padding: 16px 28px 24px !important;
        border-top: 1px solid #e6eef4 !important;
        background: #fbfdff;
        position: sticky;
        bottom: 0;
        z-index: 4;
        box-shadow: 0 -14px 28px rgba(2, 32, 54, 0.08);
    }
    #cnpTimeSelectionModal .modal-footer button {
        min-height: 46px;
        border-radius: 12px !important;
        font-weight: 800 !important;
    }
    #outcomeDateTimeConfirmBtn {
        background: linear-gradient(135deg, #0073b1 0%, #005f91 100%) !important;
        box-shadow: 0 12px 26px rgba(0, 115, 177, 0.22);
    }
    @media (max-width: 560px) {
        #cnpTimeSelectionModal {
            align-items: flex-start !important;
            justify-content: center !important;
            padding: 14px 12px calc(112px + env(safe-area-inset-bottom, 0px)) !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }
        #cnpTimeSelectionModal .outcome-time-modal {
            width: calc(100vw - 24px) !important;
            max-width: calc(100vw - 24px) !important;
            max-height: calc(100dvh - 132px - env(safe-area-inset-bottom, 0px)) !important;
            border-radius: 20px;
            margin: 0 auto !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }
        #cnpTimeSelectionModal .modal-header {
            flex: 0 0 auto !important;
            padding: 20px 20px 15px;
        }
        #cnpTimeSelectionModal .modal-header h3 {
            font-size: 22px;
            padding-right: 8px;
        }
        #cnpTimeSelectionModal .modal-body {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }
        .outcome-time-body {
            padding: 20px 20px 18px !important;
        }
        .outcome-time-grid {
            gap: 10px !important;
        }
        #outcomeDateTimeRemark {
            min-height: 92px !important;
        }
        #cnpTimeSelectionModal .modal-footer {
            flex: 0 0 auto !important;
            position: sticky !important;
            bottom: 0 !important;
            z-index: 4 !important;
            padding: 14px 20px calc(18px + env(safe-area-inset-bottom, 0px)) !important;
            box-shadow: 0 -14px 28px rgba(2, 32, 54, 0.08);
        }
        #cnpTimeSelectionModal .modal-footer button {
            flex: 1 1 0;
        }
    }
    .form-group input[readonly],
    .form-group select[readonly],
    .form-group textarea[readonly] {
        background: #f5f5f5;
        cursor: not-allowed;
    }
    /* Project Tags Styling (YouTube-style) */
    .project-tags-wrapper {
        margin-top: 8px;
    }
    .project-tags-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        background: #fafafa;
        min-height: 60px;
    }
    .project-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 20px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        color: #333;
        transition: all 0.2s ease;
        user-select: none;
        position: relative;
    }
    .project-tag:hover {
        border-color: #205A44;
        background: #f0f7f4;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(32, 90, 68, 0.1);
    }
    .project-tag.selected {
        background: #205A44;
        border-color: #205A44;
        color: white;
    }
    .project-tag.selected:hover {
        background: #15803d;
        border-color: #15803d;
    }
    .project-tag-text {
        white-space: nowrap;
    }
    .project-tag-check {
        display: none;
        font-size: 12px;
    }
    .project-tag.selected .project-tag-check {
        display: inline-block;
    }

    @media (max-width: 768px) {
        #taskVisitActionHubModal {
            align-items: flex-start;
            padding: 18px 14px calc(94px + env(safe-area-inset-bottom));
            overflow-y: auto;
        }

        #taskVisitActionHubModal .modal-content {
            width: 100% !important;
            max-width: 100% !important;
            max-height: calc(100dvh - 120px) !important;
            border-radius: 22px !important;
            overflow-y: auto !important;
            margin: 54px auto 0 !important;
        }

        #taskVisitActionHubModal .modal-content > div:first-child {
            padding: 22px 20px 20px !important;
        }

        #taskVisitActionHubModal .modal-content > div:first-child h3 {
            font-size: 28px !important;
            line-height: 1.05 !important;
            margin-bottom: 8px !important;
        }

        #taskVisitActionHubModal .modal-content > div:first-child p {
            max-width: 240px;
            font-size: 14px !important;
            line-height: 1.45 !important;
        }

        #taskVisitActionHubModal .modal-content > div:nth-child(2) {
            padding: 16px !important;
        }

        #taskVisitActionHubModal .modal-content > div:nth-child(2) > div {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 10px !important;
        }

        #taskVisitActionHubModal .modal-content > div:nth-child(2) button {
            min-height: 76px !important;
            width: 100% !important;
            padding: 14px 15px !important;
            border-radius: 18px !important;
            align-items: center !important;
            gap: 13px !important;
        }

        #taskVisitActionHubModal .modal-content > div:nth-child(2) button > span:first-child {
            width: 48px !important;
            height: 48px !important;
            min-width: 48px !important;
            border-radius: 16px !important;
            font-size: 20px !important;
        }

        #taskVisitActionHubModal .modal-content > div:nth-child(2) button strong {
            font-size: 17px !important;
            line-height: 1.15 !important;
            white-space: normal !important;
            word-break: normal !important;
        }

        #taskVisitActionHubModal .modal-content > div:nth-child(2) button small {
            font-size: 12.5px !important;
            line-height: 1.35 !important;
            margin-top: 3px !important;
            white-space: normal !important;
            word-break: normal !important;
        }
    }

    @media (max-width: 380px) {
        #taskVisitActionHubModal .modal-content > div:first-child h3 {
            font-size: 25px !important;
        }

        #taskVisitActionHubModal .modal-content > div:nth-child(2) button strong {
            font-size: 16px !important;
        }
    }

    @media (max-width: 768px) {
        #managerLeadRequirementFormModal.modal.manager-lead-modal {
            inset: 0 !important;
            padding: 0 0 calc(76px + env(safe-area-inset-bottom, 0px)) !important;
            align-items: stretch !important;
            justify-content: flex-start !important;
            overflow: hidden !important;
            background: #f5f8fa !important;
        }

        #managerLeadRequirementFormModal .modal-content {
            width: 100vw !important;
            max-width: 100vw !important;
            min-width: 0 !important;
            height: calc(100dvh - 76px - env(safe-area-inset-bottom, 0px)) !important;
            min-height: 0 !important;
            max-height: calc(100dvh - 76px - env(safe-area-inset-bottom, 0px)) !important;
            margin: 0 !important;
            padding: 0 !important;
            border-radius: 0 !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            box-shadow: none !important;
            display: flex !important;
            flex-direction: column !important;
            -webkit-overflow-scrolling: touch !important;
        }

        #managerLeadRequirementFormModal .modal-body,
        #managerLeadRequirementFormModal #managerLeadTaskFormContainer,
        #managerLeadRequirementFormModal #managerLeadFormContainer {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow-x: hidden !important;
            box-sizing: border-box !important;
        }

        #managerLeadRequirementFormModal .modal-body {
            flex: 0 0 auto !important;
            min-height: 0 !important;
            overflow: visible !important;
            background: #f5f8fa !important;
        }

        #managerLeadRequirementFormModal #managerLeadTaskFormContainer,
        #managerLeadRequirementFormModal #managerLeadFormContainer {
            height: auto !important;
            flex: 0 0 auto !important;
            overflow: visible !important;
            padding: 8px 8px 12px !important;
            padding-bottom: 16px !important;
        }

        #managerLeadRequirementFormModal .manager-lead-shell,
        #managerLeadRequirementFormModal .manager-lead-form,
        #managerLeadRequirementFormModal .manager-lead-section {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
        }

        #managerLeadRequirementFormModal .manager-lead-shell {
            min-height: 0 !important;
            display: block !important;
        }

        #managerLeadRequirementFormModal .manager-lead-form {
            padding: 6px !important;
        }

        #managerLeadRequirementFormModal .manager-lead-section {
            border-radius: 12px !important;
            margin: 0 0 12px !important;
        }

        #managerLeadRequirementFormModal .manager-lead-section-head {
            gap: 10px !important;
            padding: 14px !important;
        }

        #managerLeadRequirementFormModal .manager-lead-section-copy h3 {
            font-size: 15px !important;
            line-height: 1.25 !important;
        }

        #managerLeadRequirementFormModal .manager-lead-section-copy span,
        #managerLeadRequirementFormModal .manager-lead-section-pill {
            white-space: normal !important;
            line-height: 1.4 !important;
        }

        #managerLeadRequirementFormModal .manager-lead-section-body {
            padding: 16px 14px !important;
        }

        #managerLeadRequirementFormModal .manager-lead-grid {
            grid-template-columns: 1fr !important;
            gap: 12px !important;
        }

        #managerLeadRequirementFormModal .manager-lead-output-grid {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 8px !important;
            overflow-x: hidden !important;
        }

        #managerLeadRequirementFormModal .manager-lead-output-btn {
            width: 100% !important;
            min-width: 0 !important;
            min-height: 92px !important;
            padding: 11px 6px !important;
            border-radius: 14px !important;
            gap: 7px !important;
        }

        #managerLeadRequirementFormModal .manager-lead-output-icon {
            width: 34px !important;
            height: 34px !important;
            font-size: 14px !important;
        }

        #managerLeadRequirementFormModal .manager-lead-output-copy {
            gap: 0 !important;
            min-width: 0 !important;
            width: 100% !important;
        }

        #managerLeadRequirementFormModal .manager-lead-output-label {
            font-size: 11px !important;
            line-height: 1.15 !important;
            white-space: normal !important;
            word-break: normal !important;
        }

        #managerLeadRequirementFormModal .manager-lead-output-sub {
            display: none !important;
        }

        #managerLeadRequirementFormModal .manager-lead-output-btn:nth-child(n + 4) {
            display: none !important;
        }

        #managerLeadRequirementFormModal .modal-header,
        #managerLeadRequirementFormModal .manager-lead-header {
            background: linear-gradient(135deg, #003b5c 0%, #0073b1 58%, #0a88c8 100%) !important;
            border-radius: 0 !important;
        }

        #managerLeadRequirementFormModal .modal-header {
            flex: 0 0 auto !important;
            min-height: 76px !important;
            padding: 18px 18px !important;
            margin: 0 !important;
            border-bottom: 0 !important;
        }

        #managerLeadRequirementFormModal .modal-header h3 {
            font-size: 26px !important;
            line-height: 1.1 !important;
            color: #fff !important;
            max-width: calc(100% - 62px) !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        #managerLeadRequirementFormModal .modal-header .close-btn {
            width: 48px !important;
            height: 48px !important;
            border-radius: 16px !important;
            color: #fff !important;
            background: rgba(255, 255, 255, 0.15) !important;
            border: 1px solid rgba(255, 255, 255, 0.28) !important;
            font-size: 28px !important;
            line-height: 1 !important;
            flex: 0 0 48px !important;
        }

        #managerLeadRequirementFormModal .manager-lead-section-icon-green {
            background: #e8f4fb !important;
            color: #0073b1 !important;
        }

        #managerLeadRequirementFormModal .manager-lead-btn-primary,
        #managerLeadRequirementFormModal .convert-project-chip.active {
            background: linear-gradient(135deg, #0073b1 0%, #005f91 100%) !important;
            border-color: #0073b1 !important;
            box-shadow: 0 14px 24px rgba(0, 115, 177, 0.18) !important;
        }

        #managerLeadRequirementFormModal .manager-lead-output-meeting.is-active,
        #managerLeadRequirementFormModal .manager-lead-input:focus,
        #managerLeadRequirementFormModal .manager-lead-select:focus,
        #managerLeadRequirementFormModal .manager-lead-textarea:focus {
            border-color: #0073b1 !important;
        }

        #managerLeadRequirementFormModal .manager-lead-footer {
            position: static !important;
            bottom: auto !important;
            z-index: auto !important;
            margin: 12px 0 0 !important;
            padding: 12px 0 calc(18px + env(safe-area-inset-bottom, 0px)) !important;
            border-top: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        #managerLeadRequirementFormModal .manager-lead-footer .manager-lead-btn {
            min-height: 56px !important;
            border-radius: 14px !important;
        }

        .asm-mobile-task-date-value,
        .asm-mobile-summary-chip strong {
            color: #003b5c !important;
        }

        .asm-mobile-task-date-value i,
        .asm-mobile-summary-chip.focus strong,
        .asm-mobile-day-count,
        .asm-mobile-day.active .asm-mobile-day-meta {
            color: #0073b1 !important;
        }

        .asm-mobile-mode-toggle .asm-mobile-head-btn.active,
        .asm-mobile-range-btn.active,
        .asm-mobile-day.active .asm-mobile-day-date,
        .task-action-btn.btn-call,
        .task-card .btn-call,
        .task-card button[onclick*="Call"],
        .task-card button[onclick*="call"] {
            background: linear-gradient(135deg, #0073b1 0%, #005f91 100%) !important;
            border-color: #0073b1 !important;
            color: #ffffff !important;
            box-shadow: 0 8px 18px rgba(0, 115, 177, 0.18) !important;
        }

        .asm-mobile-day.active {
            background: linear-gradient(180deg, rgba(0, 115, 177, 0.12) 0%, rgba(0, 115, 177, 0.03) 100%) !important;
            box-shadow: 0 12px 22px rgba(0, 115, 177, 0.1) !important;
        }

        .asm-mobile-day.is-today,
        .asm-mobile-day-count {
            background: #e8f4fb !important;
        }

        .asm-mobile-task-week,
        .asm-mobile-summary-chip {
            border-color: #d7e9f5 !important;
            box-shadow: 0 10px 24px rgba(0, 73, 112, 0.05) !important;
        }

        .task-card .avatar,
        .task-card .task-avatar,
        .task-card [class*="avatar"],
        .task-card .task-mini-tag {
            background: linear-gradient(135deg, #0073b1 0%, #005f91 100%) !important;
            color: #ffffff !important;
        }

        .task-card .task-name,
        .task-card .task-info-row i,
        .tasks-container.asm-tasks-pro .task-card h3,
        .tasks-container.asm-tasks-pro .task-card strong {
            color: #003b5c !important;
        }

        .tasks-container.asm-tasks-pro .task-card .task-chip,
        .tasks-container.asm-tasks-pro .task-card .status-chip,
        .tasks-container.asm-tasks-pro .task-card .badge {
            background: #e8f4fb !important;
            color: #0073b1 !important;
        }
    }

    /* Green workspace overrides: task/follow-up screen */
    .tasks-container.asm-tasks-pro,
    .tasks-container.asm-tasks-pro .filter-bar,
    .asm-desktop-queue-panel,
    .asm-desktop-calendar-shell,
    .asm-desktop-task-week,
    .asm-desktop-summary-card,
    .asm-mobile-task-week,
    .asm-mobile-mode-toggle,
    .asm-mobile-summary-chip {
        border-color: #d9e7df !important;
        box-shadow: 0 14px 34px rgba(6, 58, 28, 0.06) !important;
    }

    .tasks-container.asm-tasks-pro,
    .tasks-container.asm-tasks-pro .filter-bar,
    .asm-desktop-calendar-shell,
    .asm-desktop-task-week,
    .asm-mobile-task-week {
        background: linear-gradient(135deg, #ffffff 0%, #f5fbf7 100%) !important;
    }

    .tasks-container.asm-tasks-pro .date-filter-select,
    .tasks-container.asm-tasks-pro .task-filter-select,
    .tasks-container.asm-tasks-pro #customDatePicker,
    .asm-mobile-range-btn,
    .asm-mobile-today-btn,
    .asm-desktop-calendar-nav button {
        border-color: #b7d8c8 !important;
        color: #063A1C !important;
        background: #ffffff !important;
    }

    .tasks-container.asm-tasks-pro .date-filter-select:focus,
    .tasks-container.asm-tasks-pro .task-filter-select:focus,
    .tasks-container.asm-tasks-pro #customDatePicker:focus {
        border-color: #205A44 !important;
        box-shadow: 0 0 0 3px rgba(32, 90, 68, 0.14) !important;
    }

    .tasks-container.asm-tasks-pro .filter-btn.active,
    .asm-pod-toggle-btn.active,
    .asm-mobile-mode-toggle .asm-mobile-head-btn.active,
    .asm-mobile-range-btn.active,
    .asm-desktop-day.active .asm-desktop-day-date,
    .asm-mobile-day.active .asm-mobile-day-date {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%) !important;
        border-color: #205A44 !important;
        color: #ffffff !important;
        box-shadow: 0 8px 18px rgba(6, 58, 28, 0.18) !important;
    }

    .asm-desktop-summary-card {
        background: linear-gradient(180deg, #ffffff 0%, #f7fbf9 100%) !important;
    }

    .asm-desktop-summary-card.active {
        border-color: #205A44 !important;
        box-shadow: 0 14px 28px rgba(32, 90, 68, 0.14) !important;
    }

    .asm-desktop-summary-card:not(.overdue).active {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%) !important;
    }

    .asm-desktop-summary-card:not(.overdue).active .asm-desktop-summary-label,
    .asm-desktop-summary-card:not(.overdue).active .asm-desktop-summary-value,
    .asm-desktop-summary-card:not(.overdue).active .asm-desktop-summary-note {
        color: #ffffff !important;
    }

    .asm-desktop-summary-label,
    .asm-desktop-calendar-label p,
    .asm-desktop-day-name,
    .asm-desktop-day-meta,
    .asm-mobile-task-date-label,
    .asm-mobile-day-name,
    .asm-mobile-day-meta,
    .asm-mobile-summary-chip span,
    .asm-pod-toggle-btn {
        color: #4a6457 !important;
    }

    .asm-desktop-summary-value,
    .asm-desktop-queue-title,
    .asm-desktop-calendar-label div,
    .asm-desktop-day,
    .asm-desktop-day-date,
    .asm-mobile-task-date-value,
    .asm-mobile-summary-chip strong {
        color: #063A1C !important;
    }

    .asm-desktop-calendar-label div i,
    .asm-desktop-calendar-nav button,
    .asm-mobile-task-date-value i,
    .asm-mobile-summary-chip.focus strong,
    .asm-mobile-day-count,
    .asm-desktop-day-count,
    .asm-desktop-day.is-today .asm-desktop-day-meta,
    .asm-mobile-day.active .asm-mobile-day-meta {
        color: #205A44 !important;
    }

    .asm-pod-toggle,
    .asm-mobile-mode-toggle,
    .asm-mobile-day.is-today,
    .asm-mobile-day-count,
    .asm-desktop-day-count {
        background: #ecfdf3 !important;
    }

    .asm-desktop-day:hover,
    .asm-desktop-day.active,
    .asm-mobile-day.active {
        background: linear-gradient(180deg, rgba(32, 90, 68, 0.12) 0%, rgba(32, 90, 68, 0.03) 100%) !important;
        box-shadow: 0 12px 22px rgba(32, 90, 68, 0.1) !important;
    }

    .asm-desktop-queue-chip,
    .task-action-btn.btn-call,
    .task-card .btn-call,
    .task-card button[onclick*="Call"],
    .task-card button[onclick*="call"] {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%) !important;
        border-color: #205A44 !important;
        color: #ffffff !important;
    }

    .task-card .avatar,
    .task-card .task-avatar,
    .task-card [class*="avatar"],
    .task-card .task-mini-tag {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%) !important;
        color: #ffffff !important;
    }

    .task-card .task-name,
    .task-card .task-info-row i,
    .tasks-container.asm-tasks-pro .task-card h3,
    .tasks-container.asm-tasks-pro .task-card strong {
        color: #063A1C !important;
    }

    .tasks-container.asm-tasks-pro .task-card .task-chip,
    .tasks-container.asm-tasks-pro .task-card .status-chip,
    .tasks-container.asm-tasks-pro .task-card .badge {
        background: #ecfdf3 !important;
        color: #166534 !important;
    }
</style>
@endpush

@section('content')
  @php($usesManagerTaskDesktopLayout = auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager()))
  @php($usesManagerTaskPodToggle = auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager()))
  <div class="tasks-container {{ $usesManagerTaskPodToggle ? 'asm-tasks-pro' : '' }} {{ $usesManagerTaskDesktopLayout ? 'asm-desktop-priority-layout' : '' }}">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="task-filter-layout">
        <!-- Desktop: Button Filters -->
        <div class="task-filter-main filter-buttons-desktop">
            <div class="task-status-filter-desktop">
                <select id="taskStatusFilterDesktop" class="date-filter-select task-status-primary-select">
                    <option value="all">All Tasks</option>
                    <option value="pending">Pending</option>
                    <option value="overdue">Overdue</option>
                    <option value="rescheduled">Rescheduled</option>
                </select>
            </div>
            @unless($usesManagerTaskPodToggle)
            <div class="task-type-filter-desktop">
                <select id="taskTypeFilterDesktop" class="date-filter-select task-type-primary-select">
                    <option value="all">All Types</option>
                    <option value="fresh_lead">Fresh Lead</option>
                    <option value="follow_up">Follow Up</option>
                    <option value="meeting">Meeting</option>
                    <option value="site_visit">Site Visit</option>
                    <option value="prospect">Prospect</option>
                    <option value="closer">Closer</option>
                    <option value="other">Other</option>
                </select>
            </div>
            @endunless
            
            <!-- Desktop Date Filter -->
            <div class="date-filter-desktop">
                <select id="dateFilterDropdownDesktop" class="date-filter-select">
                    <option value="today" selected>Today</option>
                    <option value="pod">POD</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month">This Month</option>
                    <option value="this_year">This Year</option>
                    <option value="custom">Custom Date</option>
                </select>
            </div>
        </div>
        @if($usesManagerTaskPodToggle)
        <div class="asm-pod-toggle" id="asmPodToggle" aria-label="Task mode">
            <button type="button" id="asmToggleTodayBtn" class="asm-pod-toggle-btn active" onclick="setAsmTaskMode('today')">
                <span>Today</span>
            </button>
            <button type="button" id="asmTogglePodBtn" class="asm-pod-toggle-btn is-pod" onclick="setAsmTaskMode('pod')">
                <span>POD</span>
                <span class="asm-pod-toggle-count" id="asmPodCount">0</span>
            </button>
        </div>
        @endif
        
        <!-- Mobile: Dropdown Filters (50% each) -->
        <div class="task-filter-main filter-dropdowns-mobile" style="display: none;">
            @unless($usesManagerTaskPodToggle)
            <select id="taskFilterDropdown" class="task-filter-select">
                <option value="all">All Tasks</option>
                <option value="pending">Pending</option>
                <option value="overdue">Overdue</option>
                <option value="rescheduled">Rescheduled</option>
            </select>
            @endunless
            <select id="dateFilterDropdown" class="date-filter-select">
                <option value="today" selected>Today</option>
                <option value="pod">POD</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="this_year">This Year</option>
                <option value="custom">Custom Date</option>
            </select>
        </div>

        @unless($usesManagerTaskPodToggle)
        <div class="task-type-filter-mobile" style="display: none;">
            <select id="taskTypeFilterMobile" class="task-filter-select">
                <option value="all">All Types</option>
                <option value="fresh_lead">Fresh Lead</option>
                <option value="follow_up">Follow Up</option>
                <option value="meeting">Meeting</option>
                <option value="site_visit">Site Visit</option>
                <option value="prospect">Prospect</option>
                <option value="closer">Closer</option>
                <option value="other">Other</option>
            </select>
        </div>
        @endunless
        
        <div class="task-filter-extra">
            <!-- Custom Date Picker (hidden by default) -->
            <input type="date" id="customDatePicker" style="display: none; margin-left: 8px; padding: 8px 12px; border: 2px solid #205A44; border-radius: 8px; font-size: 14px;">
        </div>
        </div>
        @if($usesManagerTaskPodToggle)
        <div class="asm-pod-summary" id="asmPodSummary">
            <div class="asm-pod-summary-copy">
                <div class="asm-pod-summary-title">Previous Overdue</div>
                <div class="asm-pod-summary-note">Earlier pending tasks in one place for quick closure.</div>
            </div>
            <div class="asm-pod-summary-count" id="asmPodSummaryCount">0</div>
        </div>
        @endif
    </div>

    <div id="asmMobileTaskShell" class="asm-mobile-task-shell">
        <div class="asm-mobile-task-headline">
            <div class="asm-mobile-task-date">
                <p class="asm-mobile-task-date-label">Task Day</p>
                <div id="asmMobileTaskDateValue" class="asm-mobile-task-date-value">
                    <i class="fas fa-calendar-day"></i>
                    <span>{{ now()->format('d M Y') }}</span>
                </div>
            </div>
            <div class="asm-mobile-task-head-actions">
                <div class="asm-mobile-mode-toggle">
                    <button type="button" id="asmMobileTodayBtn" class="asm-mobile-range-btn asm-mobile-head-btn active" onclick="setAsmTaskMode('today')">Today</button>
                    <button type="button" id="asmMobilePodBtn" class="asm-mobile-range-btn asm-mobile-head-btn is-pod" onclick="setAsmTaskMode('pod')">
                        POD
                        <span class="asm-mobile-range-badge" id="asmMobilePodCount">0</span>
                    </button>
                </div>
            </div>
            <div class="asm-mobile-task-nav">
                <button type="button" id="asmRangeTodayBtn" class="asm-mobile-range-btn" onclick="selectAsmMobileRange('today')">Today</button>
                <button type="button" id="asmRangeWeekBtn" class="asm-mobile-range-btn" onclick="selectAsmMobileRange('this_week')">This Week</button>
                <button type="button" id="asmRangeMonthBtn" class="asm-mobile-range-btn" onclick="selectAsmMobileRange('this_month')">This Month</button>
                <button type="button" id="asmRangeYearBtn" class="asm-mobile-range-btn" onclick="selectAsmMobileRange('this_year')">This Year</button>
            </div>
        </div>
        <div class="asm-mobile-task-toolbar">
            <button type="button" class="asm-mobile-today-btn" onclick="openAsmMobileTaskDatePicker()">Pick Date</button>
        </div>
        <div id="asmMobileTaskWeekViewport" class="asm-mobile-task-week-viewport">
            <div id="asmMobileTaskWeek" class="asm-mobile-task-week"></div>
        </div>
    </div>

    <div class="asm-desktop-shell">
        @if($usesManagerTaskDesktopLayout)
        <aside class="asm-desktop-summary" id="asmDesktopSummaryRail">
            <button type="button" class="asm-desktop-summary-card today active" id="asmDesktopTodayCard" onclick="applyAsmDesktopPreset('today')">
                <span class="asm-desktop-summary-label">Today</span>
                <span class="asm-desktop-summary-value" id="asmDesktopTodayCount">0</span>
                <span class="asm-desktop-summary-note">Current queue for today's tasks</span>
            </button>
            <button type="button" class="asm-desktop-summary-card pending" id="asmDesktopPendingCard" onclick="applyAsmDesktopPreset('pending')">
                <span class="asm-desktop-summary-label">Pending</span>
                <span class="asm-desktop-summary-value" id="asmDesktopPendingCount">0</span>
                <span class="asm-desktop-summary-note">Due now and upcoming callbacks</span>
            </button>
            <button type="button" class="asm-desktop-summary-card overdue" id="asmDesktopOverdueCard" onclick="applyAsmDesktopPreset('overdue')">
                <span class="asm-desktop-summary-label">Overdue</span>
                <span class="asm-desktop-summary-value" id="asmDesktopOverdueCount">0</span>
                <span class="asm-desktop-summary-note">Carry-forward and delayed tasks needing action</span>
            </button>
        </aside>
        @endif
        <section class="asm-desktop-queue">
            @if($usesManagerTaskDesktopLayout)
            <div class="asm-desktop-queue-panel">
                <div class="asm-desktop-queue-head">
                    <div>
                        <h2 class="asm-desktop-queue-title" id="asmDesktopQueueTitle">Lead / Task Queue</h2>
                        <div class="asm-desktop-queue-subtitle" id="asmDesktopQueueSubtitle">Call, update outcome, and move to the next lead.</div>
                    </div>
                    <span class="asm-desktop-queue-chip" id="asmDesktopQueueCount">0</span>
                </div>
                <div class="asm-desktop-calendar-shell" id="asmDesktopCalendarShell">
                    <div class="asm-desktop-calendar-top">
                        <div class="asm-desktop-calendar-label">
                            <p>Task Day</p>
                            <div id="asmDesktopTaskDateValue">
                                <i class="fas fa-calendar-day"></i>
                                <span>{{ now()->format('d M Y') }}</span>
                            </div>
                        </div>
                        <div class="asm-desktop-calendar-nav">
                            <button type="button" aria-label="Previous week" onclick="navigateAsmDesktopWeek(-1)"><i class="fas fa-chevron-left"></i></button>
                            <button type="button" aria-label="Next week" onclick="navigateAsmDesktopWeek(1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div id="asmDesktopCalendarPod" class="asm-desktop-calendar-pod">
                        <div class="asm-desktop-calendar-pod-copy">
                            <strong>Previous Overdue</strong>
                            <span>Carry-forward tasks waiting for closure.</span>
                        </div>
                        <span class="asm-desktop-calendar-pod-count" id="asmDesktopCalendarPodCount">0</span>
                    </div>
                    <div id="asmDesktopTaskWeek" class="asm-desktop-task-week"></div>
                </div>
                <div id="tasksGrid" class="tasks-grid">
                    <div class="loading-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading tasks...</p>
                    </div>
                </div>
            </div>
            @else
            <div id="tasksGrid" class="tasks-grid">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading tasks...</p>
                </div>
            </div>
            @endif
        </section>
    </div>
</div>

<!-- Step 1: Call Outcome Modal -->
<div id="verifyRejectPromptModal" class="modal">
    <div class="modal-content asm-outcome-modal" style="max-width: 500px;">
        <div class="modal-header">
            <div>
                <h3>Call Outcome</h3>
                <p class="asm-outcome-subtitle">Choose the result of this customer call.</p>
            </div>
            <button class="close-modal" onclick="closeTaskOutcomeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="asm-outcome-modal-body">
                <div class="asm-outcome-grid">
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-green" onclick="selectTaskOutcome('interested')">
                        <i class="fas fa-thumbs-up"></i>
                        <span>Interested</span>
                    </button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-slate" onclick="selectTaskOutcome('not_interested')">
                        <i class="fas fa-user-slash"></i>
                        <span>Not Interested</span>
                    </button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-blue" onclick="selectTaskOutcome('follow_up')">
                        <i class="fas fa-clock"></i>
                        <span id="taskCallLaterLabel">Follow Up</span>
                    </button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-amber" onclick="selectTaskOutcome('cnp')">
                        <i class="fas fa-phone-slash"></i>
                        <span>CNP</span>
                    </button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-red asm-outcome-btn-full" onclick="selectTaskOutcome('junk')">
                        <i class="fas fa-trash"></i>
                        <span>Junk</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step 2: Full Lead Requirement Form Modal (shown after Verify clicked) -->
<div id="managerLeadRequirementFormModal" class="modal manager-lead-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Lead Requirement Form - Verify Prospect</h3>
            <button class="close-btn" onclick="cancelManagerLeadRequirementForm()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="managerLeadFormContainer">
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner" style="display: inline-block;"></div>
                    <p style="margin-top: 15px; color: #666;">Loading form...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="taskDetailModal" class="modal">
    <div class="modal-content" style="max-width: 760px;">
        <div class="modal-header">
            <h3 id="taskDetailModalTitle">Lead Detail</h3>
            <button class="close-modal" onclick="closeTaskDetailModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="taskDetailModalContent">
                <div style="text-align:center; padding:32px; color:#64748b;">Select a task to view detail.</div>
            </div>
        </div>
    </div>
</div>

<!-- Outcome Remark Modal -->
<div id="rejectReasonModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="rejectReasonModalTitle">Add Remark</h3>
            <button class="close-modal" onclick="cancelJunkRemarkModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="rejectReasonInput">Remark <span style="color:#6b7280; font-weight:400;">(optional)</span></label>
                <textarea id="rejectReasonInput" rows="4" placeholder="Add context for this outcome..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"></textarea>
            </div>
            <div class="form-footer" style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn-cancel" onclick="cancelJunkRemarkModal()">Cancel</button>
                <button type="button" class="btn-reject" id="rejectReasonSubmitBtn" onclick="submitOutcomeRemark()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Outcome Date/Time Modal -->
<div id="cnpTimeSelectionModal" class="modal">
    <div class="modal-content outcome-time-modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="outcomeDateTimeModalTitle">Select Retry Time for CNP</h3>
            <button class="close-modal" onclick="cancelOutcomeDateTimeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="outcome-time-body" style="padding: 20px;">
                <p id="outcomeDateTimeModalText" style="font-size: 14px; color: #666; margin-bottom: 20px;">
                    Choose when to retry this call:
                </p>
                
                <!-- Quick Time Options -->
                <div class="outcome-time-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
                    <button type="button" class="time-option-btn" onclick="selectCnpTime(15, event)" data-minutes="15" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                        15 Minutes
                    </button>
                    <button type="button" class="time-option-btn" onclick="selectCnpTime(30, event)" data-minutes="30" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                        30 Minutes
                    </button>
                    <button type="button" class="time-option-btn" onclick="selectCnpTime(60, event)" data-minutes="60" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                        1 Hour
                    </button>
                    <button type="button" class="time-option-btn" onclick="selectCnpTime(120, event)" data-minutes="120" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                        2 Hours
                    </button>
                </div>
                
                <!-- Custom Option -->
                <div style="margin-bottom: 20px;">
                    <button type="button" class="time-option-btn" onclick="showCustomTimePicker()" id="customTimeOptionBtn" style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                        Custom Date & Time
                    </button>
                </div>
                
                <!-- Custom Date-Time Picker (hidden by default) -->
                <div id="customTimePickerContainer" style="display: none; padding: 16px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                            <strong>Date</strong> <span style="color: #d32f2f;">*</span>
                        </label>
                        <input type="date" 
                               id="cnpCustomDate" 
                               min=""
                               style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                            <strong>Time</strong> <span style="color: #d32f2f;">*</span>
                        </label>
                        <input type="time" 
                               id="cnpCustomTime"
                               style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <small style="display: block; margin-top: 8px; color: #666; font-size: 12px;">
                        Select a future date and time
                    </small>
                </div>
                
                <!-- Selected Time Display -->
                <div id="selectedTimeDisplay" style="padding: 12px; background: #e8f5e9; border-radius: 6px; margin-bottom: 20px; display: none;">
                    <p style="font-size: 14px; color: #2e7d32; margin: 0;">
                        <strong>Selected:</strong> <span id="selectedTimeText"></span>
                    </p>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="outcomeDateTimeRemark" style="display:block; font-size:14px; font-weight:500; color:#333; margin-bottom:6px;">
                        Remark <span style="color:#6b7280; font-weight:400;">(optional)</span>
                    </label>
                    <textarea id="outcomeDateTimeRemark" rows="3" placeholder="Add follow-up or CNP context..." style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 20px; border-top: 1px solid #e0e0e0;">
            <button type="button" onclick="cancelOutcomeDateTimeModal()" style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 6px; background: white; color: #333; cursor: pointer; font-size: 14px; font-weight: 500;">
                Cancel
            </button>
            <button type="button" id="outcomeDateTimeConfirmBtn" onclick="confirmOutcomeDateTimeSelection()" style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">
                Confirm
            </button>
        </div>
    </div>
</div>

<!-- View Detail Modal (for viewing only) -->
<div id="prospectDetailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Lead Form</h3>
            <button class="close-modal" onclick="closeProspectDetailModal()">&times;</button>
        </div>
        <div id="prospectDetailContent" style="padding: 24px;">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<div id="taskMeetingActionHubModal" class="modal">
    <div class="modal-content task-meeting-action-hub-modal">
        <div class="task-meeting-action-hub-header">
            <div>
                <h3>Meeting Actions</h3>
                <p class="task-meeting-action-hub-subtitle">Capture proof or choose the next business step for this meeting.</p>
            </div>
            <button class="close-modal" onclick="closeTaskMeetingActionHubModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="task-meeting-action-hub-grid">
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-green" onclick="handleTaskMeetingHubAction('complete')">
                    <span class="task-meeting-action-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Meeting Complete</strong>
                        <small>Upload proof and close this meeting task.</small>
                    </span>
                </button>
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-blue" onclick="handleTaskMeetingHubAction('reschedule')">
                    <span class="task-meeting-action-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Reschedule Meeting</strong>
                        <small>Choose a new meeting slot and close this task.</small>
                    </span>
                </button>
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-slate" onclick="handleTaskMeetingHubAction('edit_requirement')">
                    <span class="task-meeting-action-icon"><i class="fas fa-file-signature"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Edit Requirement Form</strong>
                        <small>Update requirement details and keep the meeting task open.</small>
                    </span>
                </button>
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-cyan" onclick="handleTaskMeetingHubAction('visit')">
                    <span class="task-meeting-action-icon"><i class="fas fa-map-marker-alt"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Schedule Visit</strong>
                        <small>Open lead detail flow to schedule a site visit from this meeting.</small>
                    </span>
                </button>
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-emerald" onclick="handleTaskMeetingHubAction('follow_up')">
                    <span class="task-meeting-action-icon"><i class="fas fa-phone"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Schedule Follow Up</strong>
                        <small>Create a follow-up task on the selected date and time.</small>
                    </span>
                </button>
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-cyan" onclick="handleTaskMeetingHubAction('send_to_closer')">
                    <span class="task-meeting-action-icon"><i class="fas fa-trophy"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Send to Closer</strong>
                        <small>Create a closer draft from this completed meeting.</small>
                    </span>
                </button>
            </div>

            <div class="task-meeting-more-wrap">
                <button type="button" class="task-meeting-more-toggle" onclick="toggleTaskMeetingMoreActions()">
                    <span><i class="fas fa-ellipsis-h"></i> More Outcomes</span>
                    <i class="fas fa-chevron-down" id="taskMeetingMoreActionsIcon"></i>
                </button>

                <div id="taskMeetingMoreActionsPanel" class="task-meeting-more-panel">
                    <button type="button" class="task-meeting-more-btn" onclick="handleTaskMeetingHubAction('interested')">
                        <i class="fas fa-thumbs-up"></i>
                        <span>Interested</span>
                    </button>
                    <button type="button" class="task-meeting-more-btn" onclick="handleTaskMeetingHubAction('not_interested')">
                        <i class="fas fa-user-slash"></i>
                        <span>Not Interested</span>
                    </button>
                    <button type="button" class="task-meeting-more-btn" onclick="handleTaskMeetingHubAction('junk')">
                        <i class="fas fa-trash"></i>
                        <span>Junk</span>
                    </button>
                    <button type="button" class="task-meeting-more-btn" onclick="handleTaskMeetingHubAction('dead')">
                        <i class="fas fa-ban"></i>
                        <span>Mark as Dead</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="taskMeetingSendCloserModal" class="modal">
    <div class="modal-content" style="max-width: 620px;">
        <div class="modal-header">
            <h3>Send to Closer</h3>
            <button class="close-modal" onclick="closeTaskMeetingSendCloserModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="taskMeetingCloserProject">Project <span style="color: #ef4444;">*</span></label>
                <input type="text" id="taskMeetingCloserProject" placeholder="Enter project discussed">
            </div>
            <div class="form-group">
                <label for="taskMeetingCloserBudget">Budget Range <span style="color: #ef4444;">*</span></label>
                <select id="taskMeetingCloserBudget">
                    <option value="">Select budget range</option>
                    <option value="Below 50 Lacs">Below 50 Lacs</option>
                    <option value="50-75 Lacs">50-75 Lacs</option>
                    <option value="75 Lacs-1 Cr">75 Lacs-1 Cr</option>
                    <option value="Above 1 Cr">Above 1 Cr</option>
                    <option value="Above 2 Cr">Above 2 Cr</option>
                    <option value="N.A">N.A</option>
                </select>
            </div>
            <div class="form-group">
                <label for="taskMeetingCloserRemark">Remark / Meeting Summary <span style="color: #ef4444;">*</span></label>
                <textarea id="taskMeetingCloserRemark" rows="4" placeholder="Add closer handoff summary, customer intent, next commercial discussion, or booking context..."></textarea>
            </div>
            <div class="form-group">
                <label for="taskMeetingCloserProofPhotos">Proof Photos <span style="color:#6b7280; font-weight:400;">(optional)</span></label>
                <input type="file" id="taskMeetingCloserProofPhotos" multiple accept="image/*">
                <small style="display:block; margin-top:6px; color:#64748b;">Optional meeting proof or customer discussion images.</small>
            </div>
        </div>
        <div class="modal-footer" style="display:flex; gap:12px; justify-content:flex-end; padding:16px 0 0;">
            <button type="button" class="btn btn-secondary" onclick="closeTaskMeetingSendCloserModal()">Cancel</button>
            <button type="button" id="taskMeetingCloserSubmitBtn" class="btn" style="background:#0f766e; color:#fff;" onclick="submitTaskMeetingSendCloser()">Send to Closer</button>
        </div>
    </div>
</div>

<div id="taskFollowUpActionHubModal" class="modal">
    <div class="modal-content task-meeting-action-hub-modal">
        <div class="task-meeting-action-hub-header">
            <div>
                <h3>Follow-Up Actions</h3>
                <p class="task-meeting-action-hub-subtitle">Choose the next step for this follow-up conversation.</p>
            </div>
            <button class="close-modal" onclick="closeTaskFollowUpActionHubModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="task-meeting-action-hub-grid">
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-blue" onclick="handleTaskFollowUpHubAction('reschedule')">
                    <span class="task-meeting-action-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Reschedule Follow Up</strong>
                        <small>Pick the next call date and time.</small>
                    </span>
                </button>
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-green" onclick="handleTaskFollowUpHubAction('complete')">
                    <span class="task-meeting-action-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Follow Up Complete</strong>
                        <small>Close this task without creating another one.</small>
                    </span>
                </button>
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-slate" onclick="handleTaskFollowUpHubAction('edit_requirement')">
                    <span class="task-meeting-action-icon"><i class="fas fa-file-signature"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Edit Requirement Form</strong>
                        <small>Update requirement details and keep the task open.</small>
                    </span>
                </button>
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-emerald" onclick="handleTaskFollowUpHubAction('meeting')">
                    <span class="task-meeting-action-icon"><i class="fas fa-handshake"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Schedule Meeting</strong>
                        <small>Open lead detail flow to create a meeting.</small>
                    </span>
                </button>
                <button type="button" class="task-meeting-action-tile task-meeting-action-tile-cyan" onclick="handleTaskFollowUpHubAction('visit')">
                    <span class="task-meeting-action-icon"><i class="fas fa-map-marker-alt"></i></span>
                    <span class="task-meeting-action-copy">
                        <strong>Schedule Visit</strong>
                        <small>Open lead detail flow to create a site visit.</small>
                    </span>
                </button>
            </div>

            <div class="task-meeting-more-wrap">
                <button type="button" class="task-meeting-more-toggle" onclick="toggleTaskFollowUpMoreActions()">
                    <span><i class="fas fa-ellipsis-h"></i> More Outcomes</span>
                    <i class="fas fa-chevron-down" id="taskFollowUpMoreActionsIcon"></i>
                </button>

                <div id="taskFollowUpMoreActionsPanel" class="task-meeting-more-panel">
                    <button type="button" class="task-meeting-more-btn" onclick="handleTaskFollowUpHubAction('interested')">
                        <i class="fas fa-thumbs-up"></i>
                        <span>Interested</span>
                    </button>
                    <button type="button" class="task-meeting-more-btn" onclick="handleTaskFollowUpHubAction('not_interested')">
                        <i class="fas fa-user-slash"></i>
                        <span>Not Interested</span>
                    </button>
                    <button type="button" class="task-meeting-more-btn" onclick="handleTaskFollowUpHubAction('cnp')">
                        <i class="fas fa-phone-slash"></i>
                        <span>CNP</span>
                    </button>
                    <button type="button" class="task-meeting-more-btn" onclick="handleTaskFollowUpHubAction('junk')">
                        <i class="fas fa-trash"></i>
                        <span>Junk</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="taskWorkflowActionModal" class="modal">
    <div class="modal-content" style="max-width: 460px;">
        <h3 id="taskWorkflowActionTitle" style="font-size: 22px; font-weight: 700; margin-bottom: 10px;">Workflow Actions</h3>
        <p id="taskWorkflowActionText" style="margin-bottom: 18px; color: #6b7280;">Choose an action.</p>
        <div style="display: grid; gap: 10px;">
            <button type="button" id="taskWorkflowCompleteBtn" class="btn btn-success" onclick="openTaskWorkflowCompleteModal()">Complete</button>
            <button type="button" id="taskWorkflowRescheduleBtn" class="btn" style="background: #f59e0b; color: #fff;" onclick="openTaskWorkflowReschedule()">Reschedule</button>
            <button type="button" id="taskWorkflowDeadBtn" class="btn btn-danger" onclick="openTaskWorkflowMarkDead()">Mark as Dead</button>
            <button type="button" class="btn btn-secondary" onclick="closeTaskWorkflowActionModal()">Cancel</button>
        </div>
    </div>
</div>

<div id="taskWorkflowMarkDeadModal" class="modal">
    <div class="modal-content" style="max-width: 520px;">
        <h3 id="taskWorkflowDeadTitle" style="font-size: 22px; font-weight: 700; margin-bottom: 10px;">Mark as Dead</h3>
        <p style="margin-bottom: 16px; color: #6b7280;">Add a clear reason before moving this workflow out of the active queue.</p>
        <div class="form-group">
            <label for="taskWorkflowDeadReason">Reason <span style="color: #ef4444;">*</span></label>
            <textarea id="taskWorkflowDeadReason" rows="4" placeholder="Enter reason..." required></textarea>
        </div>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeTaskWorkflowMarkDeadModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="submitTaskWorkflowMarkDead()">Mark as Dead</button>
        </div>
    </div>
</div>

<div id="taskWorkflowRescheduleModal" class="modal">
    <div class="modal-content" style="max-width: 520px;">
        <h3 id="taskWorkflowRescheduleTitle" style="font-size: 22px; font-weight: 700; margin-bottom: 10px;">Reschedule</h3>
        <div class="form-group">
            <label for="taskWorkflowRescheduleScheduledAt">New Scheduled Date & Time <span style="color: #ef4444;">*</span></label>
            <input type="datetime-local" id="taskWorkflowRescheduleScheduledAt" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
        </div>
        <div class="form-group">
            <label for="taskWorkflowRescheduleReason">Reason <span style="color: #ef4444;">*</span></label>
            <textarea id="taskWorkflowRescheduleReason" rows="4" placeholder="Enter reason for rescheduling..." required></textarea>
        </div>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeTaskWorkflowRescheduleModal()">Cancel</button>
            <button type="button" class="btn" style="background: #f59e0b; color: #fff;" onclick="submitTaskWorkflowReschedule()">Reschedule</button>
        </div>
    </div>
</div>

<div id="taskInterestedSiteVisitModal" class="modal">
    @include('components.site-visit-form-card', [
        'title' => 'Schedule Site Visit',
        'icon' => 'fas fa-map-marker-alt',
        'closeHandler' => 'closeTaskInterestedSiteVisitModal()',
        'formId' => 'taskInterestedSiteVisitForm',
        'submitHandler' => 'submitTaskInterestedSiteVisit(event)',
        'projectInputId' => 'taskInterestedSiteVisitProjectInput',
        'projectOptionsId' => 'taskInterestedSiteVisitProjectOptions',
        'projectHiddenId' => 'taskInterestedSiteVisitProjectHidden',
        'scheduledAtId' => 'taskInterestedSiteVisitScheduledAt',
        'visitSequenceId' => 'taskInterestedSiteVisitVisitSequence',
        'submitLabel' => 'Schedule Visit',
        'cancelLabel' => 'Cancel',
    ])
</div>

<div id="taskWorkflowCompleteMeetingModal" class="modal">
    <div class="modal-content" style="max-width: 760px;">
        <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 10px;">Complete Meeting</h3>
        <p style="margin-bottom: 16px; color: #6b7280;">Upload proof and record the meeting outcome.</p>
        <div class="form-group">
            <label for="taskWorkflowMeetingProofPhotosInput">Proof Photos <span style="color: #ef4444;">*</span></label>
            <input type="file" id="taskWorkflowMeetingProofPhotosInput" multiple accept="image/*" onchange="handleTaskWorkflowMeetingProofPhotosChange(event)">
            <div id="taskWorkflowMeetingProofPhotosPreview" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;"></div>
        </div>
        <div class="form-group">
            <label for="taskWorkflowMeetingFeedback">Feedback</label>
            <textarea id="taskWorkflowMeetingFeedback" rows="3" placeholder="Summarize discussion, interest level, and next steps..."></textarea>
        </div>
        <div class="form-group">
            <label for="taskWorkflowMeetingRating">Rating</label>
            <select id="taskWorkflowMeetingRating">
                <option value="">Select rating</option>
                <option value="1">1 - Poor</option>
                <option value="2">2 - Fair</option>
                <option value="3">3 - Good</option>
                <option value="4">4 - Very Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>
        <div class="form-group">
            <label for="taskWorkflowMeetingNotes">Notes</label>
            <textarea id="taskWorkflowMeetingNotes" rows="3" placeholder="Add internal notes, objections, or follow-up context..."></textarea>
        </div>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeTaskWorkflowCompleteMeetingModal()">Cancel</button>
            <button type="button" class="btn btn-success" onclick="submitTaskWorkflowCompleteMeeting()">Submit</button>
        </div>
    </div>
</div>

<div id="taskVisitActionHubModal" class="modal">
    <div class="modal-content" style="max-width: 760px; border-radius: 24px; overflow: hidden; padding: 0;">
        <div style="background: linear-gradient(135deg, #063A1C 0%, #205A44 100%); color: #fff; padding: 28px 30px 22px;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px;">
                <div>
                    <h3 style="font-size: 24px; font-weight: 800; margin: 0 0 8px;">Visit Actions</h3>
                    <p style="margin:0; color: rgba(255,255,255,0.88); font-size: 15px;">Choose the next step for this visit workflow.</p>
                </div>
                <button type="button" class="close-modal" onclick="closeTaskVisitActionHubModal()" style="color:#fff;">&times;</button>
            </div>
        </div>
        <div style="padding: 28px; background:#fff;">
            <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px;">
                <button type="button" onclick="handleTaskVisitHubAction('visited')" style="display:flex; align-items:center; gap:16px; text-align:left; border:1px solid #dbe5ef; border-radius:22px; padding:20px; background:#fff;">
                    <span style="width:56px; height:56px; border-radius:18px; display:inline-flex; align-items:center; justify-content:center; background:#d1fae5; color:#16a34a; font-size:24px;"><i class="fas fa-check-circle"></i></span>
                    <span>
                        <strong style="display:block; font-size:18px; color:#0f172a;">Visited</strong>
                        <small style="display:block; margin-top:4px; color:#64748b; font-size:14px;">Upload proof and close this site visit reminder.</small>
                    </span>
                </button>
                <button type="button" onclick="handleTaskVisitHubAction('reschedule')" style="display:flex; align-items:center; gap:16px; text-align:left; border:1px solid #dbe5ef; border-radius:22px; padding:20px; background:#fff;">
                    <span style="width:56px; height:56px; border-radius:18px; display:inline-flex; align-items:center; justify-content:center; background:#dbeafe; color:#2563eb; font-size:24px;"><i class="fas fa-calendar-alt"></i></span>
                    <span>
                        <strong style="display:block; font-size:18px; color:#0f172a;">Reschedule Visit</strong>
                        <small style="display:block; margin-top:4px; color:#64748b; font-size:14px;">Pick a new visit date and time for this lead.</small>
                    </span>
                </button>
                <button type="button" onclick="handleTaskVisitHubAction('customer_not_available')" style="display:flex; align-items:center; gap:16px; text-align:left; border:1px solid #dbe5ef; border-radius:22px; padding:20px; background:#fff;">
                    <span style="width:56px; height:56px; border-radius:18px; display:inline-flex; align-items:center; justify-content:center; background:#e2e8f0; color:#475569; font-size:24px;"><i class="fas fa-user-clock"></i></span>
                    <span>
                        <strong style="display:block; font-size:18px; color:#0f172a;">Customer Not Available</strong>
                        <small style="display:block; margin-top:4px; color:#64748b; font-size:14px;">Save the failed attempt with a short remark.</small>
                    </span>
                </button>
                <button type="button" onclick="handleTaskVisitHubAction('follow_up_needed')" style="display:flex; align-items:center; gap:16px; text-align:left; border:1px solid #dbe5ef; border-radius:22px; padding:20px; background:#fff;">
                    <span style="width:56px; height:56px; border-radius:18px; display:inline-flex; align-items:center; justify-content:center; background:#ccfbf1; color:#0f766e; font-size:24px;"><i class="fas fa-phone"></i></span>
                    <span>
                        <strong style="display:block; font-size:18px; color:#0f172a;">Follow-up Needed</strong>
                        <small style="display:block; margin-top:4px; color:#64748b; font-size:14px;">Create the next follow-up task from this visit flow.</small>
                    </span>
                </button>
                <button type="button" onclick="handleTaskVisitHubAction('cancelled')" style="display:flex; align-items:center; gap:16px; text-align:left; border:1px solid #dbe5ef; border-radius:22px; padding:20px; background:#fff;">
                    <span style="width:56px; height:56px; border-radius:18px; display:inline-flex; align-items:center; justify-content:center; background:#cffafe; color:#0891b2; font-size:24px;"><i class="fas fa-ban"></i></span>
                    <span>
                        <strong style="display:block; font-size:18px; color:#0f172a;">Cancelled</strong>
                        <small style="display:block; margin-top:4px; color:#64748b; font-size:14px;">Cancel the site visit with a mandatory reason.</small>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<div id="taskWorkflowCancelSiteVisitModal" class="modal">
    <div class="modal-content" style="max-width: 520px; border-radius: 22px; overflow: hidden; padding: 0;">
        <div style="background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 100%); color: #fff; padding: 24px 26px 20px;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px;">
                <div>
                    <h3 style="font-size: 23px; font-weight: 800; margin: 0 0 7px;">Cancel Site Visit</h3>
                    <p id="taskWorkflowCancelSiteVisitSummary" style="margin:0; color: rgba(255,255,255,0.88); font-size: 14px;">Add a clear cancellation reason.</p>
                </div>
                <button type="button" class="close-modal" onclick="closeTaskWorkflowCancelSiteVisitModal()" style="color:#fff;">&times;</button>
            </div>
        </div>
        <div style="padding: 24px 26px; background:#fff;">
            <div style="display:flex; gap:12px; align-items:flex-start; border:1px solid #fee2e2; background:#fff7f7; border-radius:16px; padding:14px; margin-bottom:18px;">
                <span style="width:38px; height:38px; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; background:#fee2e2; color:#b91c1c; flex:0 0 auto;">
                    <i class="fas fa-ban"></i>
                </span>
                <div style="font-size:13px; color:#7f1d1d; line-height:1.5;">
                    This will cancel only the scheduled site visit/task. It will not mark the lead as dead.
                </div>
            </div>
            <div class="form-group">
                <label for="taskWorkflowCancelSiteVisitReason">Cancellation Reason <span style="color: #ef4444;">*</span></label>
                <textarea id="taskWorkflowCancelSiteVisitReason" rows="4" placeholder="Enter cancellation reason..." style="resize: vertical;"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary" onclick="closeTaskWorkflowCancelSiteVisitModal()">Back</button>
                <button type="button" id="taskWorkflowCancelSiteVisitSubmitBtn" class="btn" style="background:#dc2626; color:white;" onclick="submitTaskWorkflowCancelSiteVisit()">Cancel Site Visit</button>
            </div>
        </div>
    </div>
</div>

<div id="taskWorkflowCompleteSiteVisitModal" class="modal">
    <div class="modal-content" style="max-width: 760px;">
        <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 10px;">Complete Site Visit</h3>
        <p style="margin-bottom: 16px; color: #6b7280;">Choose the site visit outcome, then complete the required fields.</p>
        <div class="form-group">
            <label for="taskWorkflowSiteVisitOutcome">Visit Outcome <span style="color: #ef4444;">*</span></label>
            <select id="taskWorkflowSiteVisitOutcome" onchange="toggleTaskWorkflowSiteVisitOutcomeFields()">
                <option value="visited">Visited</option>
                <option value="reschedule">Reschedule</option>
                <option value="customer_not_available">Customer Not Available</option>
                <option value="cancelled">Cancelled</option>
                <option value="follow_up_needed">Follow-up Needed</option>
            </select>
        </div>
        <div id="taskWorkflowSiteVisitVisitedFields">
        <div class="form-group">
            <label for="taskWorkflowSiteVisitProofPhotosInput">Proof Photos <span style="color: #ef4444;">*</span></label>
            <input type="file" id="taskWorkflowSiteVisitProofPhotosInput" multiple accept="image/*" onchange="handleTaskWorkflowSiteVisitProofPhotosChange(event)">
            <div id="taskWorkflowSiteVisitProofPhotosPreview" style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;"></div>
        </div>
        <div class="form-group">
            <label for="taskWorkflowVisitedProjects">Visited Projects</label>
            <input type="text" id="taskWorkflowVisitedProjects" placeholder="Example: Jash Elevate, Oro Constella">
        </div>
        <div class="form-group">
            <label>Property Type</label>
            <div style="display:flex; flex-wrap:wrap; gap:10px;">
                <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #d7e5de; border-radius:999px; background:#fff;">
                    <input type="checkbox" class="taskWorkflowVisitedPropertyType" value="plot"> Plot
                </label>
                <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #d7e5de; border-radius:999px; background:#fff;">
                    <input type="checkbox" class="taskWorkflowVisitedPropertyType" value="villa"> Villa
                </label>
                <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #d7e5de; border-radius:999px; background:#fff;">
                    <input type="checkbox" class="taskWorkflowVisitedPropertyType" value="apartment"> Apartments
                </label>
                <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #d7e5de; border-radius:999px; background:#fff;">
                    <input type="checkbox" class="taskWorkflowVisitedPropertyType" value="commercial"> Commercial
                </label>
                <label style="display:flex; align-items:center; gap:8px; padding:10px 12px; border:1px solid #d7e5de; border-radius:999px; background:#fff;">
                    <input type="checkbox" class="taskWorkflowVisitedPropertyType" value="other"> Other
                </label>
            </div>
            <small style="display:block; margin-top:6px; color:#64748b;">Multiple property types select kar sakte hain.</small>
        </div>
        <div class="form-group">
            <label for="taskWorkflowTentativeClosingTime">Tentative Closing Time</label>
            <select id="taskWorkflowTentativeClosingTime">
                <option value="">Select an option</option>
                <option value="within_3_days">Within 3 Days</option>
                <option value="tomorrow">Tomorrow</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="it_will_take_time">It Will Take Time</option>
            </select>
        </div>
        <div class="form-group">
            <label for="taskWorkflowSiteVisitFeedback">Feedback</label>
            <textarea id="taskWorkflowSiteVisitFeedback" rows="3" placeholder="Summarize site visit response, interest level, and objections..."></textarea>
        </div>
        <div class="form-group">
            <label for="taskWorkflowSiteVisitRating">Rating</label>
            <select id="taskWorkflowSiteVisitRating">
                <option value="">Select rating</option>
                <option value="1">1 - Poor</option>
                <option value="2">2 - Fair</option>
                <option value="3">3 - Good</option>
                <option value="4">4 - Very Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>
        <div class="form-group">
            <label for="taskWorkflowSiteVisitNotes">Notes</label>
            <textarea id="taskWorkflowSiteVisitNotes" rows="3" placeholder="Add internal notes, next steps, or commercial details..."></textarea>
        </div>
        </div>
        <div id="taskWorkflowSiteVisitRescheduleFields" style="display:none;">
            <div class="form-group">
                <label for="taskWorkflowSiteVisitRescheduleAt">New Visit Date & Time <span style="color: #ef4444;">*</span></label>
                <input type="datetime-local" id="taskWorkflowSiteVisitRescheduleAt" style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px;">
            </div>
        </div>
        <div id="taskWorkflowSiteVisitFollowUpFields" style="display:none;">
            <div class="form-group">
                <label for="taskWorkflowSiteVisitFollowUpAt">Follow-up Date & Time <span style="color: #ef4444;">*</span></label>
                <input type="datetime-local" id="taskWorkflowSiteVisitFollowUpAt" style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px;">
            </div>
        </div>
        <div id="taskWorkflowSiteVisitRemarkField" class="form-group" style="display:none;">
            <label for="taskWorkflowSiteVisitRemark">Remark <span style="color: #ef4444;">*</span></label>
            <textarea id="taskWorkflowSiteVisitRemark" rows="3" placeholder="Add outcome reason or context..."></textarea>
        </div>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeTaskWorkflowCompleteSiteVisitModal()">Cancel</button>
            <button type="button" class="btn btn-success" onclick="submitTaskWorkflowCompleteSiteVisit()">Submit</button>
        </div>
    </div>
</div>

@include('partials.lead-cloud-call-menu')
@endsection

@push('scripts')
<script>
    const ASM_SECTION_VIEW_PREFERENCES = @json($sectionViewPreferences ?? []);
    const ASM_SECTION_VIEW_SAVE_URL = @json(route('sales-manager.settings.update'));

    function getAsmPreferredView(sectionKey, fallbackView) {
        const preferred = ASM_SECTION_VIEW_PREFERENCES?.[sectionKey];
        return preferred === 'list' || preferred === 'card' ? preferred : fallbackView;
    }

    async function persistAsmSectionViewPreference(sectionKey, view) {
        try {
            ASM_SECTION_VIEW_PREFERENCES[sectionKey] = view;
            await fetch(ASM_SECTION_VIEW_SAVE_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    section_view_preferences: {
                        [sectionKey]: view
                    }
                })
            });
        } catch (error) {
            console.error('Failed to persist section view preference:', error);
        }
    }

    // Global variables and functions for onclick handlers
    // Use relative path to avoid APP_URL misconfig issues
    const API_BASE_URL = '/api/sales-manager';
    const API_TOKEN = '{{ $api_token ?? session("api_token") ?? "" }}';
    window.API_BASE_URL = API_BASE_URL;
    window.API_TOKEN = API_TOKEN || document.querySelector('meta[name="api-token"]')?.content || '';

    function getToken() {
        return window.API_TOKEN
            || window.getManagerApiToken?.()
            || document.querySelector('meta[name="api-token"]')?.content
            || '';
    }

    const USES_MANAGER_TASK_DESKTOP_LAYOUT = @json($usesManagerTaskDesktopLayout);
    const USES_MANAGER_TASK_POD_TOGGLE = @json($usesManagerTaskPodToggle);
    const LEAD_DETAIL_URL_TEMPLATE = @json(route('leads.show', '__LEAD_ID__'));
    window.managerLeadMeetingCreateUrl = '{{ route("sales-manager.meetings.create") }}';
    window.managerLeadSiteVisitCreateUrl = '{{ route("sales-manager.site-visits.create") }}';
    
    let currentStatus = 'all';
    let currentCategory = 'all';
    let currentTaskId = null;
    let currentTasksView = 'list';
    let asmMobileAnchorDateIso = null;
    let asmMobileSelectedDateIso = null;
    let asmMobileVisibleWeekStartIso = null;
    let asmMobileWeekTouchStartX = null;
    let asmMobileWeekTouchDeltaX = 0;
    let asmDesktopAnchorDateIso = null;
    let asmDesktopSelectedDateIso = null;
    let asmDesktopVisibleWeekStartIso = null;
    const asmWeekCountCache = {};
    const asmWeekCountRequests = {};
    let asmTaskMode = 'today';
    let asmLastTodayStatus = 'all';
    function getStoredTaskFilter(key, fallback = null) {
        try {
            const value = localStorage.getItem(key);
            return value !== null && value !== '' ? value : fallback;
        } catch (e) {
            return fallback;
        }
    }
    function normalizeTaskStatusFilter(status) {
        if (!status) {
            return 'all';
        }
        if (status === 'completed') {
            return 'all';
        }
        return status;
    }
    function getAsmTaskModeFromFilters(status, dateFilter) {
        return USES_MANAGER_TASK_POD_TOGGLE && status === 'overdue' && dateFilter === 'pod' ? 'pod' : 'today';
    }
    function getVisibleAsmTasks(tasks, status, dateFilter) {
        const items = Array.isArray(tasks) ? tasks : [];
        if (status === 'pending') {
            return items.filter((task) => task?.is_overdue !== true);
        }
        if (status === 'overdue') {
            return items.filter((task) => task?.is_overdue === true);
        }
        if (dateFilter === 'pod') {
            return items.filter((task) => task?.is_overdue === true);
        }
        return items;
    }
    function updateAsmDesktopSummarySelection(mode) {
        if (!USES_MANAGER_TASK_DESKTOP_LAYOUT) {
            return;
        }
        document.getElementById('asmDesktopTodayCard')?.classList.toggle('active', mode === 'today');
        document.getElementById('asmDesktopPendingCard')?.classList.toggle('active', mode === 'pending');
        document.getElementById('asmDesktopOverdueCard')?.classList.toggle('active', mode === 'overdue' || mode === 'pod');
    }
    async function refreshAsmDesktopSummary(category = null) {
        if (!USES_MANAGER_TASK_DESKTOP_LAYOUT) {
            return;
        }

        const normalizedCategory = category || window.currentCategory || currentCategory || 'all';
        const nowMs = Date.now();
        const applySummaryCounts = (counts) => {
            if (!counts) {
                return;
            }
            document.getElementById('asmDesktopTodayCount').textContent = String(counts.today || 0);
            document.getElementById('asmDesktopPendingCount').textContent = String(counts.pending || 0);
            document.getElementById('asmDesktopOverdueCount').textContent = String(counts.overdue || 0);
        };

        if (asmDesktopSummaryPromise && asmDesktopSummaryCategory === normalizedCategory) {
            return asmDesktopSummaryPromise;
        }

        if (asmDesktopSummaryCache && asmDesktopSummaryCategory === normalizedCategory && (nowMs - asmDesktopSummaryLastAt) < 15000) {
            applySummaryCounts(asmDesktopSummaryCache);
            return;
        }

        const queryFor = function(statusValue, dateFilterValue) {
            const params = new URLSearchParams();
            if (statusValue && statusValue !== 'all') {
                params.set('status', statusValue);
            }
            if (dateFilterValue && dateFilterValue !== 'all') {
                params.set('date_filter', dateFilterValue);
            }
            if (normalizedCategory && normalizedCategory !== 'all') {
                params.set('category', normalizedCategory);
            }
            return `/tasks?${params.toString()}`;
        };

        asmDesktopSummaryCategory = normalizedCategory;
        asmDesktopSummaryPromise = (async () => {
        try {
            const [todayResult, pendingResult, overdueResult] = await Promise.all([
                apiCall(queryFor('all', 'today')),
                apiCall(queryFor('pending', 'today')),
                apiCall(queryFor('overdue', 'all')),
            ]);

            const getCount = (result, statusValue = 'all', dateFilterValue = 'today') => getVisibleAsmTasks(result?.data, statusValue, dateFilterValue).length;
            asmDesktopSummaryCache = {
                today: getCount(todayResult),
                pending: getCount(pendingResult, 'pending', 'today'),
                overdue: getCount(overdueResult, 'overdue', 'all'),
            };
            asmDesktopSummaryLastAt = Date.now();
            applySummaryCounts(asmDesktopSummaryCache);
        } catch (error) {
            console.error('Failed to refresh ASM desktop summary:', error);
        } finally {
            asmDesktopSummaryPromise = null;
        }
        })();

        return asmDesktopSummaryPromise;
    }
    function updateAsmDesktopQueueMeta(tasks, status, dateFilter) {
        if (!USES_MANAGER_TASK_DESKTOP_LAYOUT) {
            return;
        }

        const activeMode = status === 'overdue' && dateFilter === 'pod'
            ? 'pod'
            : status === 'overdue'
                ? 'overdue'
                : status === 'pending'
                    ? 'pending'
                    : 'today';

        updateAsmDesktopSummarySelection(activeMode);
        const titleMap = {
            today: 'Today Queue',
            pending: 'Pending Queue',
            overdue: 'Overdue Queue',
            pod: 'Overdue Queue',
        };
        const subtitleMap = {
            today: "Today's actionable calls and follow-ups.",
            pending: 'Due now and upcoming callbacks for the day.',
            overdue: 'Carry-forward and delayed tasks needing action.',
            pod: 'Carry-forward and delayed tasks needing action.',
        };
        const queueTitle = document.getElementById('asmDesktopQueueTitle');
        const queueSubtitle = document.getElementById('asmDesktopQueueSubtitle');
        const queueCount = document.getElementById('asmDesktopQueueCount');
        if (queueTitle) queueTitle.textContent = titleMap[activeMode] || 'Lead / Task Queue';
        if (queueSubtitle) queueSubtitle.textContent = subtitleMap[activeMode] || 'Call, update outcome, and move to the next lead.';
        if (queueCount) queueCount.textContent = String(Array.isArray(tasks) ? tasks.length : 0);
    }

    function isAsmFollowupsDemoContext(status, dateFilter, category) {
        const params = new URLSearchParams(window.location.search);
        const focus = String(params.get('focus') || '').toLowerCase();
        const normalizedStatus = status || window.currentStatus || currentStatus || 'all';
        const normalizedDate = normalizeAsmVisibleDateFilter(dateFilter || 'today');
        const normalizedCategory = category || window.currentCategory || currentCategory || 'all';

        return focus === 'followups'
            && normalizedStatus !== 'overdue'
            && normalizedDate !== 'pod'
            && (normalizedCategory === 'all' || normalizedCategory === 'follow_up');
    }

    function buildAsmFollowupsDemoTasks(status, dateFilter, category) {
        if (!isAsmFollowupsDemoContext(status, dateFilter, category)) {
            return [];
        }

        const now = new Date();
        const names = [
            ['Aarav Sharma', '9000001101', 'Independent Floor'],
            ['Priya Bansal', '9000001102', 'Omaxe Front Street'],
            ['Diya Pandey', '9000001103', 'Omaxe Lincoln'],
            ['Rohit Verma', '9000001104', 'Anaxe Lincoln'],
            ['Neha Gupta', '9000001105', 'Independent Floor'],
            ['Karan Mehta', '9000001106', 'Omaxe Front Street'],
            ['Simran Kaur', '9000001107', 'Omaxe Lincoln'],
            ['Vikas Singh', '9000001108', 'Anaxe Lincoln'],
            ['Meera Joshi', '9000001109', 'Independent Floor'],
            ['Nitin Yadav', '9000001110', 'Omaxe Front Street'],
        ];

        return names.map(([name, phone, project], index) => {
            const scheduledAt = new Date(now.getTime() + ((index + 1) * 18 * 60000));
            return {
                id: `demo-followup-${index + 1}`,
                is_ui_demo: true,
                category: 'follow_up',
                status: 'pending',
                is_overdue: false,
                title: `Follow-up call: ${name}`,
                display_title: name,
                display_subtitle: `Follow-up for ${project}`,
                lead_phone: phone,
                scheduled_at: scheduledAt.toISOString(),
                scheduled_at_formatted: scheduledAt.toISOString(),
                notes: `UI demo follow-up for ${project}. Use this card to test the follow-up queue layout.`,
                lead: {
                    id: '',
                    name,
                    phone,
                },
            };
        });
    }

    function applyAsmFollowupsDemoCounts(count) {
        if (!USES_MANAGER_TASK_DESKTOP_LAYOUT) {
            return;
        }

        const countLabel = String(count || 0);
        const todayCount = document.getElementById('asmDesktopTodayCount');
        const pendingCount = document.getElementById('asmDesktopPendingCount');
        const overdueCount = document.getElementById('asmDesktopOverdueCount');
        const queueCount = document.getElementById('asmDesktopQueueCount');

        if (todayCount) todayCount.textContent = countLabel;
        if (pendingCount) pendingCount.textContent = countLabel;
        if (overdueCount) overdueCount.textContent = '0';
        if (queueCount) queueCount.textContent = countLabel;
    }

    function applyAsmDesktopPreset(mode) {
        if (!USES_MANAGER_TASK_DESKTOP_LAYOUT) {
            return;
        }
        const activeCategory = window.currentCategory || currentCategory || 'all';
        if (mode === 'pending') {
            filterTasks('pending', 'today', null, activeCategory);
            return;
        }
        if (mode === 'overdue') {
            filterTasks('overdue', 'all', null, activeCategory);
            return;
        }
        setAsmTaskMode('today');
    }
    function syncAsmTaskModeUi(mode, podCount = null) {
        if (!USES_MANAGER_TASK_POD_TOGGLE) {
            return;
        }
        asmTaskMode = mode === 'pod' ? 'pod' : 'today';
        document.getElementById('asmToggleTodayBtn')?.classList.toggle('active', asmTaskMode === 'today');
        document.getElementById('asmTogglePodBtn')?.classList.toggle('active', asmTaskMode === 'pod');
        document.getElementById('asmMobileTodayBtn')?.classList.toggle('active', asmTaskMode === 'today');
        document.getElementById('asmMobilePodBtn')?.classList.toggle('active', asmTaskMode === 'pod');
        document.getElementById('asmPodSummary')?.classList.toggle('active', asmTaskMode === 'pod');

        if (podCount !== null && podCount !== undefined) {
            const nextCount = Math.max(0, Number(podCount) || 0);
            const countLabel = String(nextCount);
            const podCountNode = document.getElementById('asmPodCount');
            const podMobileCountNode = document.getElementById('asmMobilePodCount');
            const podSummaryCountNode = document.getElementById('asmPodSummaryCount');
            if (podCountNode) {
                podCountNode.textContent = countLabel;
            }
            if (podMobileCountNode) {
                podMobileCountNode.textContent = countLabel;
            }
            if (podSummaryCountNode) {
                podSummaryCountNode.textContent = countLabel;
            }
        }
    }
    async function refreshAsmPodCount(category = null) {
        if (!USES_MANAGER_TASK_POD_TOGGLE) {
            return;
        }

        const normalizedCategory = category || window.currentCategory || currentCategory || 'all';
        const nowMs = Date.now();
        if (asmPodCountPromise && asmPodCountCategory === normalizedCategory) {
            return asmPodCountPromise;
        }

        if (asmPodCountCache !== null && asmPodCountCategory === normalizedCategory && (nowMs - asmPodCountLastAt) < 15000) {
            syncAsmTaskModeUi(asmTaskMode, asmPodCountCache);
            return;
        }

        const params = new URLSearchParams();
        params.set('status', 'overdue');
        params.set('date_filter', 'pod');

        if (normalizedCategory && normalizedCategory !== 'all') {
            params.set('category', normalizedCategory);
        }

        asmPodCountCategory = normalizedCategory;
        asmPodCountPromise = (async () => {
        try {
            const result = await apiCall(`/tasks?${params.toString()}`);
            asmPodCountCache = Array.isArray(result?.data) ? result.data.length : 0;
            asmPodCountLastAt = Date.now();
            syncAsmTaskModeUi(asmTaskMode, asmPodCountCache);
        } catch (error) {
            console.error('Failed to refresh POD count:', error);
        } finally {
            asmPodCountPromise = null;
        }
        })();

        return asmPodCountPromise;
    }
    function setAsmTaskMode(mode) {
        if (!USES_MANAGER_TASK_POD_TOGGLE) {
            return;
        }

        const normalizedMode = mode === 'pod' ? 'pod' : 'today';
        const customDatePicker = document.getElementById('customDatePicker');
        const activeCategory = window.currentCategory || currentCategory || 'all';

        if (normalizedMode === 'pod') {
            const currentStatusValue = normalizeTaskStatusFilter(window.currentStatus || currentStatus || 'all');
            if (currentStatusValue !== 'overdue') {
                asmLastTodayStatus = currentStatusValue;
            }
            syncAsmTaskModeUi('pod');
            filterTasks('overdue', 'pod', null, activeCategory);
            return;
        }

        const restoredStatus = normalizeTaskStatusFilter(asmLastTodayStatus || window.currentStatus || currentStatus || 'all');
        const restoredDateFilter = 'today';
        const restoredCustomDate = customDatePicker && restoredDateFilter === 'custom' ? customDatePicker.value : null;
        syncAsmTaskModeUi('today');
        filterTasks(restoredStatus, restoredDateFilter, restoredCustomDate, activeCategory);
    }
    function setCurrentTaskId(value) {
        currentTaskId = value;
        window.currentTaskId = value;
        const cnpModal = document.getElementById('cnpTimeSelectionModal');
        if (cnpModal) {
            if (value) {
                cnpModal.dataset.taskId = value;
            } else {
                delete cnpModal.dataset.taskId;
            }
        }
        const managerTaskInput = document.querySelector('#managerLeadRequirementForm input[name="task_id"]');
        if (managerTaskInput) {
            managerTaskInput.value = value || '';
        }
    }

    function syncModalBodyLock() {
        const hasActiveModal = document.querySelector('.modal.active');
        document.body.classList.toggle('modal-open', !!hasActiveModal);
    }
    
    // Attach to window for global access
    currentStatus = normalizeTaskStatusFilter(currentStatus);
    window.currentStatus = currentStatus;
    window.currentCategory = currentCategory;
    window.currentTaskId = currentTaskId;

    function setTasksView(view, shouldPersist = true) {
        currentTasksView = view === 'list' ? 'list' : 'card';
        const shell = document.querySelector('.tasks-container');
        if (shell) {
            shell.classList.toggle('asm-tasks-list-view', currentTasksView === 'list');
        }
        document.getElementById('taskCardsViewBtn')?.classList.toggle('active', currentTasksView === 'card');
        document.getElementById('taskListViewBtn')?.classList.toggle('active', currentTasksView === 'list');
        document.getElementById('asmMobileCardBtn')?.classList.toggle('active', currentTasksView === 'card');
        document.getElementById('asmMobileListBtn')?.classList.toggle('active', currentTasksView === 'list');
        try {
            localStorage.setItem('asm_tasks_view', currentTasksView);
        } catch (e) {}
        if (shouldPersist) {
            persistAsmSectionViewPreference('tasks', currentTasksView);
        }
    }

    function getAuthHeaders() {
        return window.getManagerAuthHeaders({
            'Content-Type': 'application/json'
        });
    }

    async function apiCall(endpoint, options = {}) {
        try {
            const url = `${API_BASE_URL}${endpoint}`;
            console.log('Manager task API request', {
                endpoint: url,
                method: options.method || 'GET',
                auth_source: 'meta',
                pwa: typeof window.isManagerPwaContext === 'function' ? window.isManagerPwaContext() : false
            });
            
            const response = await fetch(url, {
                method: options.method || 'GET',
                headers: {
                    ...getAuthHeaders(),
                    ...(options.headers || {})
                },
                cache: 'no-store',
                body: options.body || undefined,
                credentials: 'same-origin'
            });

            console.log('Manager task API response', {
                endpoint,
                status: response.status,
                auth_source: 'meta',
                pwa: typeof window.isManagerPwaContext === 'function' ? window.isManagerPwaContext() : false
            });

            if (response.status === 401 || response.status === 403) {
                const authFailure = typeof window.handleManagerAuthFailure === 'function'
                    ? window.handleManagerAuthFailure(`tasks${endpoint}`)
                    : { success: false, auth_failed: true, message: 'Session expired. Please refresh and sign in again.' };
                showAlert(authFailure.message, 'error');
                setTimeout(() => {
                    window.location.href = '{{ route("login") }}';
                }, 2000);
                return authFailure;
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error(`API Error (${response.status}):`, errorText);
                console.error('Error response headers:', Object.fromEntries(response.headers.entries()));
                try {
                    const errorJson = JSON.parse(errorText);
                    return { success: false, ...errorJson };
                } catch (e) {
                    return { success: false, message: errorText || `HTTP ${response.status}: ${response.statusText}` };
                }
            }

            const responseText = await response.text();
            console.log('Raw API response text:', responseText.substring(0, 500));
            
            let data;
            try {
                data = JSON.parse(responseText);
                console.log(`API Success for ${endpoint}:`, {
                    success: data.success,
                    data_length: data.data ? (Array.isArray(data.data) ? data.data.length : 'not array') : 'no data',
                    total: data.total,
                    current_page: data.current_page
                });
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', responseText);
                return { success: false, message: 'Invalid JSON response from server' };
            }
            
            return data;
        } catch (error) {
            console.error('API Call Error:', error);
            console.error('Error details:', error.message, error.stack);
            console.error('Error name:', error.name);
            showAlert('Network error: ' + error.message, 'error');
            return { success: false, message: error.message || 'Network error occurred' };
        }
    }

    function showAlert(message, type = 'info', duration = 3000) {
        const notification = document.getElementById('customNotification');
        const messageEl = document.getElementById('notificationMessage');
        const overlay = document.getElementById('notificationOverlay');
        
        if (notification && messageEl && overlay) {
            messageEl.textContent = message;
            overlay.style.display = 'flex';
            notification.classList.remove('hide');
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
                notification.classList.add('hide');
                setTimeout(() => {
                    overlay.style.display = 'none';
                }, 300);
            }, duration);
        } else {
            alert(message);
        }
    }

    function parseAsmDateTime(dateValue) {
        if (!dateValue) return null;
        if (dateValue instanceof Date) {
            return Number.isNaN(dateValue.getTime()) ? null : new Date(dateValue.getTime());
        }
        if (typeof dateValue !== 'string') {
            const parsedNonString = new Date(dateValue);
            return Number.isNaN(parsedNonString.getTime()) ? null : parsedNonString;
        }

        const trimmedValue = dateValue.trim();
        const localDateMatch = trimmedValue.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
        if (localDateMatch && !/[zZ]|[+\-]\d{2}:?\d{2}$/.test(trimmedValue)) {
            const [, year, month, day, hour, minute, second = '00'] = localDateMatch;
            return new Date(
                Number(year),
                Number(month) - 1,
                Number(day),
                Number(hour),
                Number(minute),
                Number(second)
            );
        }

        const parsed = new Date(trimmedValue);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function formatDateForApi(dateValue) {
        const safeDate = parseAsmDateTime(dateValue);
        if (!safeDate) return '';

        const year = safeDate.getFullYear();
        const month = String(safeDate.getMonth() + 1).padStart(2, '0');
        const day = String(safeDate.getDate()).padStart(2, '0');
        const hours = String(safeDate.getHours()).padStart(2, '0');
        const minutes = String(safeDate.getMinutes()).padStart(2, '0');
        const seconds = String(safeDate.getSeconds()).padStart(2, '0');

        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    function formatAsmLocalDate(dateValue) {
        const safeDate = parseAsmDateTime(dateValue);
        if (!safeDate) return '';

        const year = safeDate.getFullYear();
        const month = String(safeDate.getMonth() + 1).padStart(2, '0');
        const day = String(safeDate.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function formatAsmLocalDateTime(dateValue) {
        const safeDate = parseAsmDateTime(dateValue);
        if (!safeDate) return '';

        const hours = String(safeDate.getHours()).padStart(2, '0');
        const minutes = String(safeDate.getMinutes()).padStart(2, '0');

        return `${formatAsmLocalDate(safeDate)}T${hours}:${minutes}`;
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = parseAsmDateTime(dateString);
        if (!date) return '-';
        return date.toLocaleString('en-IN', { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function getTaskAnchorDate(dateFilter = null, customDate = null, tasks = []) {
        if (dateFilter === 'custom' && customDate) {
            return new Date(`${customDate}T00:00:00`);
        }
        if (dateFilter === 'tomorrow') {
            const date = new Date();
            date.setDate(date.getDate() + 1);
            return date;
        }
        if (Array.isArray(tasks) && tasks.length > 0) {
            const firstScheduledAt = tasks.find(task => task?.scheduled_at_formatted || task?.scheduled_at)?.scheduled_at_formatted
                || tasks.find(task => task?.scheduled_at_formatted || task?.scheduled_at)?.scheduled_at;
            if (firstScheduledAt) {
                return parseAsmDateTime(firstScheduledAt) || new Date();
            }
        }
        return new Date();
    }

    function formatAsmMobileTaskDate(date) {
        return date.toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function toAsmIsoDate(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0')
        ].join('-');
    }

    function parseAsmIsoDate(isoDate) {
        if (!isoDate) {
            return null;
        }
        const parsed = new Date(`${isoDate}T00:00:00`);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function getAsmStartOfWeek(date) {
        const normalizedDate = new Date(date);
        normalizedDate.setHours(0, 0, 0, 0);
        normalizedDate.setDate(normalizedDate.getDate() - normalizedDate.getDay());
        return normalizedDate;
    }

    function getAsmSelectedDate() {
        return parseAsmIsoDate(asmMobileSelectedDateIso) || parseAsmIsoDate(asmMobileAnchorDateIso) || new Date();
    }

    function syncAsmMobileWeekViewport() {
        const viewport = document.getElementById('asmMobileTaskWeekViewport');
        if (viewport) {
            viewport.scrollLeft = 0;
        }
    }

    function countAsmTasksForDate(tasks, isoDate) {
        if (!Array.isArray(tasks) || !isoDate) {
            return 0;
        }

        return tasks.filter(task => {
            const raw = task?.scheduled_at_formatted || task?.scheduled_at;
            if (!raw) return false;
            const parsed = parseAsmDateTime(raw);
            return parsed ? toAsmIsoDate(parsed) === isoDate : false;
        }).length;
    }

    function getAsmWeekCountCacheKey(weekStartIso, category = 'all') {
        return `${weekStartIso || 'unknown'}::${category || 'all'}`;
    }

    function getAsmCachedWeekCounts(weekStartIso, category = 'all') {
        return asmWeekCountCache[getAsmWeekCountCacheKey(weekStartIso, category)] || null;
    }

    function getAsmCachedDayCount(weekStartIso, isoDate, category = 'all') {
        const weekCounts = getAsmCachedWeekCounts(weekStartIso, category);
        if (!weekCounts) {
            return null;
        }
        return Object.prototype.hasOwnProperty.call(weekCounts, isoDate) ? weekCounts[isoDate] : null;
    }

    function buildAsmWeekDates(weekStartDate) {
        return Array.from({ length: 7 }, (_, index) => {
            const current = new Date(weekStartDate);
            current.setDate(weekStartDate.getDate() + index);
            return {
                date: current,
                isoDate: toAsmIsoDate(current),
            };
        });
    }

    async function refreshAsmWeekCounts(anchorDate, category = 'all') {
        if (!anchorDate || Number.isNaN(anchorDate?.getTime?.())) {
            return;
        }

        const weekStart = getAsmStartOfWeek(anchorDate);
        const weekStartIso = toAsmIsoDate(weekStart);
        const normalizedCategory = category || 'all';
        const cacheKey = getAsmWeekCountCacheKey(weekStartIso, normalizedCategory);

        if (asmWeekCountCache[cacheKey]) {
            return asmWeekCountCache[cacheKey];
        }

        if (asmWeekCountRequests[cacheKey]) {
            return asmWeekCountRequests[cacheKey];
        }

        const weekDates = buildAsmWeekDates(weekStart);
        asmWeekCountRequests[cacheKey] = Promise.all(
            weekDates.map(({ isoDate }) => {
                const params = new URLSearchParams();
                params.set('status', 'all');
                params.set('date_filter', 'custom');
                params.set('custom_date', isoDate);
                if (normalizedCategory !== 'all') {
                    params.set('category', normalizedCategory);
                }
                return apiCall(`/tasks?${params.toString()}`)
                    .then((result) => ({
                        isoDate,
                        count: Array.isArray(result?.data) ? result.data.length : 0,
                    }))
                    .catch(() => ({
                        isoDate,
                        count: 0,
                    }));
            })
        ).then((entries) => {
            const counts = {};
            entries.forEach(({ isoDate, count }) => {
                counts[isoDate] = count;
            });
            asmWeekCountCache[cacheKey] = counts;
            delete asmWeekCountRequests[cacheKey];

            const desktopWeekStartIso = asmDesktopVisibleWeekStartIso;
            if (desktopWeekStartIso === weekStartIso && USES_MANAGER_TASK_DESKTOP_LAYOUT) {
                renderAsmDesktopTaskWeek(parseAsmIsoDate(asmDesktopSelectedDateIso || asmDesktopAnchorDateIso || weekStartIso), []);
            }
            const mobileWeekStartIso = asmMobileVisibleWeekStartIso;
            if (mobileWeekStartIso === weekStartIso) {
                renderAsmMobileTaskWeek(parseAsmIsoDate(asmMobileSelectedDateIso || asmMobileAnchorDateIso || weekStartIso), []);
            }

            return counts;
        }).catch((error) => {
            delete asmWeekCountRequests[cacheKey];
            console.error('Failed to refresh ASM week counts:', error);
            return null;
        });

        return asmWeekCountRequests[cacheKey];
    }

    function renderAsmMobileTaskWeek(anchorDate, tasks = []) {
        const weekRoot = document.getElementById('asmMobileTaskWeek');
        if (!weekRoot || Number.isNaN(anchorDate?.getTime?.())) {
            return;
        }

        const activeDate = new Date(anchorDate);
        asmMobileAnchorDateIso = toAsmIsoDate(activeDate);
        asmMobileSelectedDateIso = asmMobileAnchorDateIso;
        const weekStart = parseAsmIsoDate(asmMobileVisibleWeekStartIso) || getAsmStartOfWeek(activeDate);
        asmMobileVisibleWeekStartIso = toAsmIsoDate(weekStart);
        const activeCategory = window.currentCategory || currentCategory || 'all';

        weekRoot.innerHTML = Array.from({ length: 7 }, (_, index) => {
            const current = new Date(weekStart);
            current.setDate(weekStart.getDate() + index);
            const isActive = current.toDateString() === activeDate.toDateString();
            const isToday = current.toDateString() === new Date().toDateString();
            const isoDate = toAsmIsoDate(current);
            const badgeText = isToday ? 'Today' : '';
            const cachedCount = getAsmCachedDayCount(asmMobileVisibleWeekStartIso, isoDate, activeCategory);
            const taskCount = cachedCount === null ? countAsmTasksForDate(tasks, isoDate) : cachedCount;

            return `
                <button type="button" class="asm-mobile-day ${isActive ? 'active' : ''} ${isToday ? 'is-today' : ''}" data-asm-date="${isoDate}" onclick="selectAsmMobileTaskDate('${isoDate}')">
                    <span class="asm-mobile-day-name">${current.toLocaleDateString('en-IN', { weekday: 'short' })}</span>
                    <span class="asm-mobile-day-date">${current.getDate()}</span>
                    <span class="asm-mobile-day-count">${taskCount}</span>
                    <span class="asm-mobile-day-meta">${badgeText}</span>
                </button>
            `;
        }).join('');
        syncAsmMobileWeekViewport();
    }

    function renderAsmDesktopTaskWeek(tasks = [], anchorDate = null) {
        const weekRoot = document.getElementById('asmDesktopTaskWeek');
        if (!weekRoot) {
            return;
        }

        const activeDate = Number.isNaN(anchorDate?.getTime?.()) ? new Date() : new Date(anchorDate);
        asmDesktopAnchorDateIso = toAsmIsoDate(activeDate);
        asmDesktopSelectedDateIso = asmDesktopAnchorDateIso;
        const weekStart = parseAsmIsoDate(asmDesktopVisibleWeekStartIso) || getAsmStartOfWeek(activeDate);
        asmDesktopVisibleWeekStartIso = toAsmIsoDate(weekStart);
        const activeCategory = window.currentCategory || currentCategory || 'all';

        weekRoot.innerHTML = Array.from({ length: 7 }, (_, index) => {
            const current = new Date(weekStart);
            current.setDate(weekStart.getDate() + index);
            const isoDate = toAsmIsoDate(current);
            const isActive = current.toDateString() === activeDate.toDateString();
            const isToday = current.toDateString() === new Date().toDateString();
            const cachedCount = getAsmCachedDayCount(asmDesktopVisibleWeekStartIso, isoDate, activeCategory);
            const count = cachedCount === null ? countAsmTasksForDate(tasks, isoDate) : cachedCount;

            return `
                <button type="button" class="asm-desktop-day ${isActive ? 'active' : ''} ${isToday ? 'is-today' : ''}" data-asm-date="${isoDate}" onclick="selectAsmDesktopTaskDate('${isoDate}')">
                    <span class="asm-desktop-day-name">${current.toLocaleDateString('en-IN', { weekday: 'short' })}</span>
                    <span class="asm-desktop-day-date">${current.getDate()}</span>
                    <span class="asm-desktop-day-count">${count}</span>
                    <span class="asm-desktop-day-meta">${isToday ? 'Today' : ''}</span>
                </button>
            `;
        }).join('');
    }

    function updateAsmDesktopTaskChrome(tasks = [], status = null, dateFilter = null, customDate = null) {
        if (!USES_MANAGER_TASK_DESKTOP_LAYOUT) {
            return;
        }

        const dateValue = document.getElementById('asmDesktopTaskDateValue');
        const podCard = document.getElementById('asmDesktopCalendarPod');
        const podCount = document.getElementById('asmDesktopCalendarPodCount');
        const weekRoot = document.getElementById('asmDesktopTaskWeek');

        if (dateFilter === 'pod' || status === 'overdue') {
            if (dateValue) {
                dateValue.innerHTML = '<i class="fas fa-layer-group"></i><span>Previous Overdue</span>';
            }
            if (podCard) {
                podCard.classList.add('active');
            }
            if (podCount) {
                podCount.textContent = String(Array.isArray(tasks) ? tasks.length : 0);
            }
            if (weekRoot) {
                weekRoot.style.display = 'none';
            }
            return;
        }

        const anchorDate = getTaskAnchorDate(dateFilter, customDate, tasks);
        asmDesktopSelectedDateIso = toAsmIsoDate(anchorDate);
        asmDesktopVisibleWeekStartIso = toAsmIsoDate(getAsmStartOfWeek(anchorDate));

        if (dateValue) {
            dateValue.innerHTML = `<i class="fas fa-calendar-day"></i><span>${formatAsmMobileTaskDate(anchorDate)}</span>`;
        }
        if (podCard) {
            podCard.classList.remove('active');
        }
        if (weekRoot) {
            weekRoot.style.display = 'grid';
        }

        renderAsmDesktopTaskWeek(tasks, anchorDate);
        refreshAsmWeekCounts(anchorDate, window.currentCategory || currentCategory || 'all');
    }

    function updateAsmMobileTaskChrome(tasks = [], dateFilter = null, customDate = null) {
        const dateValue = document.getElementById('asmMobileTaskDateValue');
        if (dateFilter === 'pod') {
            if (dateValue) {
                dateValue.innerHTML = '<i class="fas fa-layer-group"></i><span>Previous Overdue</span>';
            }
            return;
        }
        const anchorDate = getTaskAnchorDate(dateFilter, customDate, tasks);
        asmMobileSelectedDateIso = toAsmIsoDate(anchorDate);
        asmMobileVisibleWeekStartIso = toAsmIsoDate(getAsmStartOfWeek(anchorDate));

        if (dateValue) {
            dateValue.innerHTML = `<i class="fas fa-calendar-day"></i><span>${formatAsmMobileTaskDate(anchorDate)}</span>`;
        }

        renderAsmMobileTaskWeek(anchorDate, tasks);
        refreshAsmWeekCounts(anchorDate, window.currentCategory || currentCategory || 'all');
    }

    function syncAsmMobileRangeButtons(dateFilter) {
        const normalized = dateFilter || 'today';
        document.getElementById('asmRangeTodayBtn')?.classList.toggle('active', normalized === 'today');
        document.getElementById('asmRangeWeekBtn')?.classList.toggle('active', normalized === 'this_week');
        document.getElementById('asmRangeMonthBtn')?.classList.toggle('active', normalized === 'this_month');
        document.getElementById('asmRangeYearBtn')?.classList.toggle('active', normalized === 'this_year');
        document.getElementById('asmMobileTodayBtn')?.classList.toggle('active', normalized !== 'pod');
        document.getElementById('asmMobilePodBtn')?.classList.toggle('active', normalized === 'pod');
    }

    function selectAsmMobileRange(range) {
        const normalizedRange = range || 'today';
        if (normalizedRange === 'pod') {
            syncAsmMobileRangeButtons('pod');
            setAsmTaskMode('pod');
            return;
        }
        syncAsmMobileRangeButtons(normalizedRange);
        const rangeAnchorDate = normalizedRange === 'today' ? new Date() : getAsmSelectedDate();
        asmMobileSelectedDateIso = toAsmIsoDate(rangeAnchorDate);
        asmMobileVisibleWeekStartIso = toAsmIsoDate(getAsmStartOfWeek(rangeAnchorDate));
        filterTasks(currentStatus || 'all', normalizedRange, null, currentCategory || 'all');
    }

    function selectAsmMobileTaskDate(isoDate) {
        if (!isoDate) {
            return;
        }

        syncAsmMobileRangeButtons('custom');
        asmMobileSelectedDateIso = isoDate;
        const selectedDate = parseAsmIsoDate(isoDate);
        if (selectedDate) {
            asmMobileVisibleWeekStartIso = toAsmIsoDate(getAsmStartOfWeek(selectedDate));
        }
        filterTasks(currentStatus || 'all', 'custom', isoDate, currentCategory || 'all');
    }

    function navigateAsmMobileWeek(direction) {
        const currentSelectedDate = getAsmSelectedDate();
        const currentWeekStart = parseAsmIsoDate(asmMobileVisibleWeekStartIso) || getAsmStartOfWeek(currentSelectedDate);
        const weekdayOffset = currentSelectedDate.getDay();
        const nextWeekStart = new Date(currentWeekStart);
        nextWeekStart.setDate(currentWeekStart.getDate() + (direction * 7));
        const nextSelectedDate = new Date(nextWeekStart);
        nextSelectedDate.setDate(nextWeekStart.getDate() + weekdayOffset);
        selectAsmMobileTaskDate(toAsmIsoDate(nextSelectedDate));
    }

    function selectAsmDesktopTaskDate(isoDate) {
        if (!isoDate) {
            return;
        }

        asmDesktopSelectedDateIso = isoDate;
        const selectedDate = parseAsmIsoDate(isoDate);
        if (selectedDate) {
            asmDesktopVisibleWeekStartIso = toAsmIsoDate(getAsmStartOfWeek(selectedDate));
        }

        const activeStatus = (window.currentStatus || currentStatus || 'all') === 'overdue'
            ? 'pending'
            : (window.currentStatus || currentStatus || 'all');
        filterTasks(activeStatus, 'custom', isoDate, currentCategory || 'all');
    }

    function navigateAsmDesktopWeek(direction) {
        const currentSelectedDate = parseAsmIsoDate(asmDesktopSelectedDateIso) || new Date();
        const currentWeekStart = parseAsmIsoDate(asmDesktopVisibleWeekStartIso) || getAsmStartOfWeek(currentSelectedDate);
        const weekdayOffset = currentSelectedDate.getDay();
        const nextWeekStart = new Date(currentWeekStart);
        nextWeekStart.setDate(currentWeekStart.getDate() + (direction * 7));
        const nextSelectedDate = new Date(nextWeekStart);
        nextSelectedDate.setDate(nextWeekStart.getDate() + weekdayOffset);
        selectAsmDesktopTaskDate(toAsmIsoDate(nextSelectedDate));
    }

    function initAsmMobileWeekSwipe() {
        const viewport = document.getElementById('asmMobileTaskWeekViewport');
        if (!viewport || viewport.dataset.swipeReady === '1') {
            return;
        }

        viewport.dataset.swipeReady = '1';
        viewport.addEventListener('touchstart', function(event) {
            if (!event.touches || event.touches.length !== 1) {
                return;
            }
            asmMobileWeekTouchStartX = event.touches[0].clientX;
            asmMobileWeekTouchDeltaX = 0;
        }, { passive: true });

        viewport.addEventListener('touchmove', function(event) {
            if (asmMobileWeekTouchStartX === null || !event.touches || event.touches.length !== 1) {
                return;
            }
            asmMobileWeekTouchDeltaX = event.touches[0].clientX - asmMobileWeekTouchStartX;
        }, { passive: true });

        viewport.addEventListener('touchend', function() {
            if (asmMobileWeekTouchStartX === null) {
                return;
            }
            if (Math.abs(asmMobileWeekTouchDeltaX) > 45) {
                navigateAsmMobileWeek(asmMobileWeekTouchDeltaX < 0 ? 1 : -1);
            }
            asmMobileWeekTouchStartX = null;
            asmMobileWeekTouchDeltaX = 0;
        }, { passive: true });

        viewport.addEventListener('wheel', function(event) {
            if (Math.abs(event.deltaY) <= Math.abs(event.deltaX) && event.deltaX === 0) {
                return;
            }

            const scrollAmount = event.deltaX !== 0 ? event.deltaX : event.deltaY;
            viewport.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
            event.preventDefault();
        }, { passive: false });
    }

    function openAsmMobileTaskDatePicker() {
        syncAsmMobileRangeButtons('custom');
        const picker = document.getElementById('customDatePicker');
        if (!picker) {
            selectAsmMobileRange('today');
            return;
        }

        picker.style.display = 'block';
        picker.value = asmMobileAnchorDateIso || picker.value || toAsmIsoDate(new Date());
        picker.showPicker?.();
        picker.focus();
    }

    window.selectAsmMobileTaskDate = selectAsmMobileTaskDate;
    window.selectAsmMobileRange = selectAsmMobileRange;
    window.openAsmMobileTaskDatePicker = openAsmMobileTaskDatePicker;
    window.navigateAsmMobileWeek = navigateAsmMobileWeek;
    window.selectAsmDesktopTaskDate = selectAsmDesktopTaskDate;
    window.navigateAsmDesktopWeek = navigateAsmDesktopWeek;
    window.setAsmTaskMode = setAsmTaskMode;

    function primeCustomDatePicker(customDatePicker) {
        if (!customDatePicker) {
            return null;
        }

        if (!customDatePicker.value) {
            const savedCustomDate = localStorage.getItem('salesManagerCustomDate');
            customDatePicker.value = savedCustomDate || formatAsmLocalDate(new Date());
        }

        return customDatePicker.value || null;
    }

    function normalizeAsmVisibleDateFilter(dateFilter) {
        return ['today', 'pod', 'this_week', 'this_month', 'this_year', 'custom'].includes(dateFilter)
            ? dateFilter
            : 'today';
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function getLeadDetailUrl(leadId) {
        if (!leadId) {
            return '#';
        }

        return LEAD_DETAIL_URL_TEMPLATE.replace('__LEAD_ID__', String(leadId));
    }

    function getCurrentTaskFilterState() {
        return {
            status: window.currentStatus || currentStatus || 'all',
            dateFilter: document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today',
            customDate: (() => {
                const selectedDateFilter = document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today';
                return selectedDateFilter === 'custom' ? (document.getElementById('customDatePicker')?.value || null) : null;
            })(),
            category: document.getElementById('taskTypeFilterDesktop')?.value || document.getElementById('taskTypeFilterMobile')?.value || 'all',
        };
    }

    function reloadCurrentTaskList(delayMs = 0) {
        const runReload = function() {
            const { status, dateFilter, customDate, category } = getCurrentTaskFilterState();
            loadTasks(status, dateFilter, customDate, category);
        };

        if (delayMs > 0) {
            setTimeout(runReload, delayMs);
            return;
        }

        runReload();
    }

    let currentTaskWorkflowContext = null;

    function resolveActiveTaskId() {
        const cnpModal = document.getElementById('cnpTimeSelectionModal');
        const managerTaskInput = document.querySelector('#managerLeadRequirementForm input[name="task_id"]');
        const candidates = [
            currentTaskId,
            window.currentTaskId,
            cnpModal?.dataset?.taskId,
            currentTaskWorkflowContext?.taskId,
            managerTaskInput?.value,
        ];

        const resolved = candidates.find((value) => value !== null && value !== undefined && String(value).trim() !== '');
        if (resolved) {
            setCurrentTaskId(resolved);
            return resolved;
        }

        return null;
    }

    function extractWorkflowIdFromTask(task, workflow) {
        const directValue = workflow === 'meeting' ? task?.meeting_id : task?.site_visit_id;
        if (directValue) {
            return String(directValue).trim();
        }

        const taskNotes = String(task?.notes || '');
        const taskTitle = String(task?.title || '');
        const taskDescription = String(task?.description || '');
        const taskText = `${taskNotes}\n${taskTitle}\n${taskDescription}`;

        if (workflow === 'meeting') {
            const meetingMatch = taskText.match(/(?:Linked\s+)?Meeting(?:\s+ID\s*[:#]?|\s*#)\s*(\d+)/i);
            return meetingMatch ? meetingMatch[1] : '';
        }

        const visitMatch = taskText.match(/(?:Linked\s+)?(?:Site\s*Visit|Visit)(?:\s+ID\s*[:#]?|\s*#)\s*(\d+)/i);
        return visitMatch ? visitMatch[1] : '';
    }

    function openTaskWorkflowActionModal(task, workflow, workflowId) {
        currentTaskWorkflowContext = {
            taskId: task?.id || null,
            taskType: 'Task',
            workflow,
            workflowId: Number(workflowId) || null,
            leadId: task?.lead_id || null,
        };

        const titleEl = document.getElementById('taskWorkflowActionTitle');
        const textEl = document.getElementById('taskWorkflowActionText');
        const completeBtn = document.getElementById('taskWorkflowCompleteBtn');
        const deadBtn = document.getElementById('taskWorkflowDeadBtn');
        const rescheduleBtn = document.getElementById('taskWorkflowRescheduleBtn');
        const modal = document.getElementById('taskWorkflowActionModal');

        const workflowLabel = workflow === 'meeting' ? 'Meeting' : 'Site Visit';
        if (titleEl) titleEl.textContent = `${workflowLabel} Actions`;
        if (textEl) textEl.textContent = `Choose the same action flow you use inside the ${workflowLabel.toLowerCase()} section.`;
        if (completeBtn) completeBtn.textContent = `Complete ${workflowLabel}`;
        if (deadBtn) deadBtn.textContent = 'Mark as Dead';
        if (rescheduleBtn) rescheduleBtn.textContent = `Reschedule ${workflowLabel}`;

        modal?.classList.add('show');
    }

    function openTaskMeetingActionHubModal(task, workflowId) {
        currentTaskWorkflowContext = {
            taskId: task?.id || null,
            taskType: 'Task',
            workflow: 'meeting',
            workflowId: Number(workflowId) || null,
            leadId: task?.lead_id || null,
        };

        document.getElementById('taskMeetingActionHubModal')?.classList.add('active');
        toggleTaskMeetingMoreActions(false);
        syncModalBodyLock();
    }

    function closeTaskMeetingActionHubModal() {
        document.getElementById('taskMeetingActionHubModal')?.classList.remove('active');
        toggleTaskMeetingMoreActions(false);
        syncModalBodyLock();
    }

    function openTaskFollowUpActionHubModal(task) {
        currentTaskWorkflowContext = {
            taskId: task?.id || null,
            taskType: 'Task',
            workflow: 'follow_up',
            workflowId: task?.follow_up_id ? Number(task.follow_up_id) : null,
            leadId: task?.lead_id || task?.lead?.id || null,
        };

        setCurrentTaskId(currentTaskWorkflowContext.taskId);
        currentTaskCategory = 'follow_up';
        document.getElementById('taskFollowUpActionHubModal')?.classList.add('active');
        toggleTaskFollowUpMoreActions(false);
        syncModalBodyLock();
    }

    function closeTaskFollowUpActionHubModal() {
        document.getElementById('taskFollowUpActionHubModal')?.classList.remove('active');
        toggleTaskFollowUpMoreActions(false);
        syncModalBodyLock();
    }

    function toggleTaskFollowUpMoreActions(forceState = null) {
        const panel = document.getElementById('taskFollowUpMoreActionsPanel');
        const icon = document.getElementById('taskFollowUpMoreActionsIcon');
        if (!panel) {
            return;
        }

        const shouldOpen = typeof forceState === 'boolean'
            ? forceState
            : !panel.classList.contains('active');

        panel.classList.toggle('active', shouldOpen);
        if (icon) {
            icon.classList.toggle('fa-chevron-up', shouldOpen);
            icon.classList.toggle('fa-chevron-down', !shouldOpen);
        }
    }

    function toggleTaskMeetingMoreActions(forceState = null) {
        const panel = document.getElementById('taskMeetingMoreActionsPanel');
        const icon = document.getElementById('taskMeetingMoreActionsIcon');
        if (!panel) {
            return;
        }

        const shouldOpen = typeof forceState === 'boolean'
            ? forceState
            : !panel.classList.contains('active');

        panel.classList.toggle('active', shouldOpen);
        if (icon) {
            icon.classList.toggle('fa-chevron-up', shouldOpen);
            icon.classList.toggle('fa-chevron-down', !shouldOpen);
        }
    }

    function closeTaskWorkflowActionModal() {
        document.getElementById('taskWorkflowActionModal')?.classList.remove('show');
    }

    function openTaskVisitActionHubModal() {
        document.getElementById('taskVisitActionHubModal')?.classList.add('active');
    }

    function closeTaskVisitActionHubModal() {
        document.getElementById('taskVisitActionHubModal')?.classList.remove('active');
    }

    function openTaskMeetingSendCloserModal() {
        if (!currentTaskWorkflowContext?.taskId || !currentTaskWorkflowContext?.workflowId) {
            showAlert('Meeting task context not found', 'warning');
            return;
        }

        closeTaskMeetingActionHubModal();
        document.getElementById('taskMeetingCloserProject').value = '';
        document.getElementById('taskMeetingCloserBudget').value = '';
        document.getElementById('taskMeetingCloserRemark').value = '';
        document.getElementById('taskMeetingCloserProofPhotos').value = '';
        const submitBtn = document.getElementById('taskMeetingCloserSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send to Closer';
        }
        document.getElementById('taskMeetingSendCloserModal')?.classList.add('active');
        syncModalBodyLock();
    }

    function closeTaskMeetingSendCloserModal() {
        document.getElementById('taskMeetingSendCloserModal')?.classList.remove('active');
        const submitBtn = document.getElementById('taskMeetingCloserSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send to Closer';
        }
        syncModalBodyLock();
    }

    function handleTaskVisitHubAction(outcome) {
        closeTaskVisitActionHubModal();
        if (outcome === 'cancelled') {
            showTaskWorkflowCancelSiteVisitModal(currentTaskWorkflowContext?.workflowId);
            return;
        }
        showTaskWorkflowCompleteSiteVisitModal(currentTaskWorkflowContext?.workflowId, outcome || 'visited');
    }

    function closeAllTaskWorkflowModals() {
        closeTaskFollowUpActionHubModal();
        closeTaskVisitActionHubModal();
        closeTaskMeetingActionHubModal();
        closeTaskMeetingSendCloserModal();
        closeTaskWorkflowActionModal();
        closeTaskWorkflowMarkDeadModal();
        closeTaskWorkflowRescheduleModal();
        closeTaskWorkflowCompleteMeetingModal();
        closeTaskWorkflowCompleteSiteVisitModal();
        closeTaskWorkflowCancelSiteVisitModal();
    }

    function openTaskWorkflowCompleteModal() {
        if (!currentTaskWorkflowContext?.workflowId) {
            showAlert('Workflow record not found for this task', 'warning');
            return;
        }

        closeTaskMeetingActionHubModal();
        closeTaskWorkflowActionModal();

        if (currentTaskWorkflowContext.workflow === 'meeting') {
            showTaskWorkflowCompleteMeetingModal(currentTaskWorkflowContext.workflowId);
            return;
        }

        showTaskWorkflowCompleteSiteVisitModal(currentTaskWorkflowContext.workflowId);
    }

    function openTaskWorkflowMarkDead() {
        if (!currentTaskWorkflowContext?.workflowId) {
            showAlert('Workflow record not found for this task', 'warning');
            return;
        }

        closeTaskMeetingActionHubModal();
        closeTaskWorkflowActionModal();
        const titleEl = document.getElementById('taskWorkflowDeadTitle');
        if (titleEl) {
            titleEl.textContent = currentTaskWorkflowContext.workflow === 'meeting'
                ? 'Mark Meeting as Dead'
                : 'Mark Site Visit as Dead';
        }
        document.getElementById('taskWorkflowDeadReason').value = '';
        document.getElementById('taskWorkflowMarkDeadModal')?.classList.add('show');
    }

    function closeTaskWorkflowMarkDeadModal() {
        document.getElementById('taskWorkflowMarkDeadModal')?.classList.remove('show');
        const reasonEl = document.getElementById('taskWorkflowDeadReason');
        if (reasonEl) {
            reasonEl.value = '';
        }
    }

    function openTaskWorkflowReschedule() {
        if (!currentTaskWorkflowContext?.workflowId) {
            showAlert('Workflow record not found for this task', 'warning');
            return;
        }

        closeTaskMeetingActionHubModal();
        closeTaskWorkflowActionModal();
        const minDateTime = new Date();
        minDateTime.setMinutes(minDateTime.getMinutes() + 30);
        const input = document.getElementById('taskWorkflowRescheduleScheduledAt');
        const reason = document.getElementById('taskWorkflowRescheduleReason');
        const titleEl = document.getElementById('taskWorkflowRescheduleTitle');

        if (titleEl) {
            titleEl.textContent = currentTaskWorkflowContext.workflow === 'meeting'
                ? 'Reschedule Meeting'
                : 'Reschedule Site Visit';
        }
        if (input) {
            input.value = '';
            input.min = formatAsmLocalDateTime(minDateTime);
        }
        if (reason) {
            reason.value = '';
        }

        document.getElementById('taskWorkflowRescheduleModal')?.classList.add('show');
    }

    function closeTaskWorkflowRescheduleModal() {
        document.getElementById('taskWorkflowRescheduleModal')?.classList.remove('show');
        const input = document.getElementById('taskWorkflowRescheduleScheduledAt');
        const reason = document.getElementById('taskWorkflowRescheduleReason');
        if (input) input.value = '';
        if (reason) reason.value = '';
    }

    function parseJsonFromResponseText(responseText) {
        if (!responseText) return null;

        const trimmed = responseText.trim();
        try {
            return JSON.parse(trimmed);
        } catch (error) {
            const firstBrace = trimmed.indexOf('{');
            const lastBrace = trimmed.lastIndexOf('}');
            if (firstBrace === -1 || lastBrace === -1 || lastBrace <= firstBrace) {
                return null;
            }

            try {
                return JSON.parse(trimmed.slice(firstBrace, lastBrace + 1));
            } catch (parseError) {
                return null;
            }
        }
    }

    async function completeCallingTask(taskId, taskType = 'Task') {
        if (!taskId) return true;

        try {
            const endpoint = taskType === 'Task'
                ? `${API_BASE_URL}/tasks/${taskId}/complete`
                : `${API_BASE_URL.replace('/sales-manager', '/telecaller')}/tasks/${taskId}/complete`;

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getToken()}`,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({}),
            });

            if (response.ok) {
                return true;
            }

            const result = await response.json().catch(() => ({}));
            console.error('Failed to complete calling task', result);
            return false;
        } catch (error) {
            console.error('Error completing linked task', error);
            return false;
        }
    }

    async function finishTaskWorkflowSuccess(message) {
        if (currentTaskWorkflowContext?.taskId) {
            await completeCallingTask(currentTaskWorkflowContext.taskId, currentTaskWorkflowContext.taskType || 'Task');
        }

        const taskId = currentTaskWorkflowContext?.taskId;
        closeAllTaskWorkflowModals();
        currentTaskWorkflowContext = null;

        const taskCard = taskId ? document.getElementById(`task-card-${taskId}`) : null;
        if (taskCard) {
            taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
            taskCard.style.opacity = '0';
            taskCard.style.transform = 'scale(0.96)';
            setTimeout(() => {
                taskCard.remove();
                reloadCurrentTaskList();
            }, 300);
        } else {
            reloadCurrentTaskList();
        }

        showAlert(message || 'Action completed successfully', 'success');
    }

    async function submitTaskMeetingSendCloser() {
        if (!currentTaskWorkflowContext?.taskId || !currentTaskWorkflowContext?.workflowId) {
            showAlert('Meeting task context not found', 'error');
            return;
        }

        const project = document.getElementById('taskMeetingCloserProject')?.value?.trim() || '';
        const budgetRange = document.getElementById('taskMeetingCloserBudget')?.value || '';
        const remark = document.getElementById('taskMeetingCloserRemark')?.value?.trim() || '';
        const proofInput = document.getElementById('taskMeetingCloserProofPhotos');

        if (!project) {
            showAlert('Please enter project name', 'warning');
            return;
        }

        if (!budgetRange) {
            showAlert('Please select budget range', 'warning');
            return;
        }

        if (!remark) {
            showAlert('Please enter meeting summary', 'warning');
            return;
        }

        const submitBtn = document.getElementById('taskMeetingCloserSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
        }

        const formData = new FormData();
        formData.append('workflow', 'meeting_send_to_closer');
        formData.append('meeting_id', currentTaskWorkflowContext.workflowId);
        formData.append('project', project);
        formData.append('budget_range', budgetRange);
        formData.append('remark', remark);

        if (proofInput?.files?.length) {
            Array.from(proofInput.files).forEach((file) => formData.append('proof_photos[]', file));
        }

        try {
            const response = await fetch(`${API_BASE_URL}/tasks/${currentTaskWorkflowContext.taskId}/workflow-action`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getToken()}`,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const responseText = await response.text();
            const result = parseJsonFromResponseText(responseText);

            if (!response.ok || !result?.success) {
                const firstValidationError = result?.errors
                    ? Object.values(result.errors).flat().find(Boolean)
                    : null;
                showAlert(firstValidationError || result?.message || 'Failed to send lead to closer', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Send to Closer';
                }
                return;
            }

            await finishTaskWorkflowSuccess(result.message || 'Lead sent to closer successfully.');
        } catch (error) {
            console.error('Error sending meeting to closer', error);
            showAlert('Network error while sending lead to closer', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send to Closer';
            }
        }
    }

    function showTaskWorkflowCompleteMeetingModal(id) {
        currentTaskWorkflowContext = {
            ...(currentTaskWorkflowContext || {}),
            workflow: 'meeting',
            workflowId: Number(id) || null,
        };

        const modal = document.getElementById('taskWorkflowCompleteMeetingModal');
        document.getElementById('taskWorkflowMeetingProofPhotosInput').value = '';
        document.getElementById('taskWorkflowMeetingProofPhotosPreview').innerHTML = '';
        document.getElementById('taskWorkflowMeetingFeedback').value = '';
        document.getElementById('taskWorkflowMeetingRating').value = '';
        document.getElementById('taskWorkflowMeetingNotes').value = '';
        modal?.classList.add('show');
    }

    function closeTaskWorkflowCompleteMeetingModal() {
        document.getElementById('taskWorkflowCompleteMeetingModal')?.classList.remove('show');
        document.getElementById('taskWorkflowMeetingProofPhotosInput').value = '';
        document.getElementById('taskWorkflowMeetingProofPhotosPreview').innerHTML = '';
    }

    function handleTaskWorkflowMeetingProofPhotosChange(event) {
        const files = event.target.files || [];
        const preview = document.getElementById('taskWorkflowMeetingProofPhotosPreview');
        if (!preview) return;
        preview.innerHTML = '';

        Array.from(files).forEach((file) => {
            const reader = new FileReader();
            reader.onload = function(loadEvent) {
                const img = document.createElement('img');
                img.src = loadEvent.target.result;
                img.style.width = '88px';
                img.style.height = '88px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '10px';
                img.style.border = '1px solid #d7e5de';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    }

    async function submitTaskWorkflowCompleteMeeting() {
        const workflowId = currentTaskWorkflowContext?.workflowId;
        if (!workflowId) {
            showAlert('Meeting record not found', 'error');
            return;
        }

        const photosInput = document.getElementById('taskWorkflowMeetingProofPhotosInput');
        if (!photosInput?.files || photosInput.files.length === 0) {
            showAlert('Please upload at least one proof photo', 'warning');
            return;
        }

        const formData = new FormData();
        Array.from(photosInput.files).forEach((file) => formData.append('proof_photos[]', file));

        const feedback = document.getElementById('taskWorkflowMeetingFeedback')?.value?.trim() || '';
        const rating = document.getElementById('taskWorkflowMeetingRating')?.value || '';
        const notes = document.getElementById('taskWorkflowMeetingNotes')?.value?.trim() || '';

        if (feedback) formData.append('feedback', feedback);
        if (rating) formData.append('rating', rating);
        if (notes) formData.append('meeting_notes', notes);

        try {
            const response = await fetch(`${API_BASE_URL}/meetings/${workflowId}/complete`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getToken()}`,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const responseText = await response.text();
            const result = parseJsonFromResponseText(responseText);

            if (!response.ok || !result?.success) {
                const firstValidationError = result?.errors
                    ? Object.values(result.errors).flat().find(Boolean)
                    : null;
                showAlert(firstValidationError || result?.message || 'Failed to complete meeting', 'error');
                return;
            }

            await finishTaskWorkflowSuccess(result.message || 'Meeting completed successfully');
        } catch (error) {
            console.error('Error completing meeting from task', error);
            showAlert('Network error while completing meeting', 'error');
        }
    }

    function showTaskWorkflowCancelSiteVisitModal(id) {
        currentTaskWorkflowContext = {
            ...(currentTaskWorkflowContext || {}),
            workflow: 'visit',
            workflowId: Number(id) || null,
        };

        const summary = document.getElementById('taskWorkflowCancelSiteVisitSummary');
        if (summary) {
            const visitId = currentTaskWorkflowContext?.workflowId ? `#${currentTaskWorkflowContext.workflowId}` : '';
            summary.textContent = `Site Visit ${visitId} will be cancelled after you submit a reason.`;
        }

        const reason = document.getElementById('taskWorkflowCancelSiteVisitReason');
        if (reason) {
            reason.value = '';
        }

        const submitBtn = document.getElementById('taskWorkflowCancelSiteVisitSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Cancel Site Visit';
        }

        document.getElementById('taskWorkflowCancelSiteVisitModal')?.classList.add('show');
    }

    function closeTaskWorkflowCancelSiteVisitModal() {
        document.getElementById('taskWorkflowCancelSiteVisitModal')?.classList.remove('show');
        const reason = document.getElementById('taskWorkflowCancelSiteVisitReason');
        if (reason) {
            reason.value = '';
        }
        const submitBtn = document.getElementById('taskWorkflowCancelSiteVisitSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Cancel Site Visit';
        }
    }

    async function submitTaskWorkflowCancelSiteVisit() {
        const workflowId = currentTaskWorkflowContext?.workflowId;
        const taskId = currentTaskWorkflowContext?.taskId;
        const reason = document.getElementById('taskWorkflowCancelSiteVisitReason')?.value?.trim() || '';
        const submitBtn = document.getElementById('taskWorkflowCancelSiteVisitSubmitBtn');

        if (!workflowId || !taskId) {
            showAlert('Site visit task not found', 'error');
            return;
        }

        if (!reason) {
            showAlert('Please enter a cancellation reason', 'warning');
            return;
        }

        if (submitBtn?.disabled) {
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Cancelling...';
        }

        const formData = new FormData();
        formData.append('workflow', 'visit_complete');
        formData.append('site_visit_id', workflowId);
        formData.append('outcome', 'cancelled');
        formData.append('remark', reason);

        try {
            const response = await fetch(`${API_BASE_URL}/tasks/${taskId}/workflow-action`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getToken()}`,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const responseText = await response.text();
            const result = parseJsonFromResponseText(responseText);

            if (!response.ok || !result?.success) {
                const firstValidationError = result?.errors
                    ? Object.values(result.errors).flat().find(Boolean)
                    : null;
                showAlert(firstValidationError || result?.message || 'Failed to cancel site visit', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Cancel Site Visit';
                }
                return;
            }

            await finishTaskWorkflowSuccess(result.message || 'Site visit cancelled successfully');
        } catch (error) {
            console.error('Error cancelling site visit from task', error);
            showAlert('Network error while cancelling site visit', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Cancel Site Visit';
            }
        }
    }

    async function showTaskWorkflowCompleteSiteVisitModal(id, presetOutcome = null) {
        currentTaskWorkflowContext = {
            ...(currentTaskWorkflowContext || {}),
            workflow: 'visit',
            workflowId: Number(id) || null,
        };

        document.getElementById('taskWorkflowSiteVisitProofPhotosInput').value = '';
        document.getElementById('taskWorkflowSiteVisitProofPhotosPreview').innerHTML = '';
        document.getElementById('taskWorkflowSiteVisitFeedback').value = '';
        document.getElementById('taskWorkflowSiteVisitRating').value = '';
        document.getElementById('taskWorkflowSiteVisitNotes').value = '';
        document.getElementById('taskWorkflowVisitedProjects').value = '';
        document.querySelectorAll('.taskWorkflowVisitedPropertyType').forEach((input) => { input.checked = false; });
        document.getElementById('taskWorkflowTentativeClosingTime').value = '';
        document.getElementById('taskWorkflowSiteVisitOutcome').value = presetOutcome || 'visited';
        document.getElementById('taskWorkflowSiteVisitRescheduleAt').value = '';
        document.getElementById('taskWorkflowSiteVisitFollowUpAt').value = '';
        document.getElementById('taskWorkflowSiteVisitRemark').value = '';
        toggleTaskWorkflowSiteVisitOutcomeFields();
        document.getElementById('taskWorkflowCompleteSiteVisitModal')?.classList.add('show');
    }

    function closeTaskWorkflowCompleteSiteVisitModal() {
        document.getElementById('taskWorkflowCompleteSiteVisitModal')?.classList.remove('show');
        document.getElementById('taskWorkflowSiteVisitProofPhotosInput').value = '';
        document.getElementById('taskWorkflowSiteVisitProofPhotosPreview').innerHTML = '';
    }

    function toggleTaskWorkflowSiteVisitOutcomeFields() {
        const outcome = document.getElementById('taskWorkflowSiteVisitOutcome')?.value || 'visited';
        const visitedFields = document.getElementById('taskWorkflowSiteVisitVisitedFields');
        const rescheduleFields = document.getElementById('taskWorkflowSiteVisitRescheduleFields');
        const followUpFields = document.getElementById('taskWorkflowSiteVisitFollowUpFields');
        const remarkField = document.getElementById('taskWorkflowSiteVisitRemarkField');

        if (visitedFields) visitedFields.style.display = outcome === 'visited' ? '' : 'none';
        if (rescheduleFields) rescheduleFields.style.display = outcome === 'reschedule' ? '' : 'none';
        if (followUpFields) followUpFields.style.display = outcome === 'follow_up_needed' ? '' : 'none';
        if (remarkField) remarkField.style.display = ['customer_not_available', 'cancelled', 'follow_up_needed', 'reschedule'].includes(outcome) ? '' : 'none';
    }

    function handleTaskWorkflowSiteVisitProofPhotosChange(event) {
        const files = event.target.files || [];
        const preview = document.getElementById('taskWorkflowSiteVisitProofPhotosPreview');
        if (!preview) return;
        preview.innerHTML = '';

        Array.from(files).forEach((file) => {
            const reader = new FileReader();
            reader.onload = function(loadEvent) {
                const img = document.createElement('img');
                img.src = loadEvent.target.result;
                img.style.width = '88px';
                img.style.height = '88px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '10px';
                img.style.border = '1px solid #d7e5de';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    }

    async function submitTaskWorkflowCompleteSiteVisit() {
        const workflowId = currentTaskWorkflowContext?.workflowId;
        if (!workflowId) {
            showAlert('Site visit record not found', 'error');
            return;
        }

        const outcome = document.getElementById('taskWorkflowSiteVisitOutcome')?.value || 'visited';
        const remark = document.getElementById('taskWorkflowSiteVisitRemark')?.value?.trim() || '';
        const visitedProjects = document.getElementById('taskWorkflowVisitedProjects')?.value?.trim() || '';
        const visitedPropertyTypes = Array.from(document.querySelectorAll('.taskWorkflowVisitedPropertyType:checked')).map((input) => input.value);
        const tentativeClosingTime = document.getElementById('taskWorkflowTentativeClosingTime')?.value || '';
        const feedback = document.getElementById('taskWorkflowSiteVisitFeedback')?.value?.trim() || '';
        const rating = document.getElementById('taskWorkflowSiteVisitRating')?.value || '';
        const notes = document.getElementById('taskWorkflowSiteVisitNotes')?.value?.trim() || '';

        if (['customer_not_available', 'cancelled', 'follow_up_needed', 'reschedule'].includes(outcome) && !remark) {
            showAlert('Please enter a remark', 'warning');
            return;
        }

        const photosInput = document.getElementById('taskWorkflowSiteVisitProofPhotosInput');
        if (outcome === 'visited' && (!photosInput?.files || photosInput.files.length === 0)) {
            showAlert('Please upload at least one proof photo', 'warning');
            return;
        }

        const formData = new FormData();
        if (outcome === 'visited') {
            Array.from(photosInput.files).forEach((file) => formData.append('proof_photos[]', file));
        }

        if (feedback) formData.append('feedback', feedback);
        if (rating) formData.append('rating', rating);
        if (notes) formData.append('notes', notes);
        if (visitedProjects) formData.append('visited_projects', visitedProjects);
        if (outcome === 'visited') {
            visitedPropertyTypes.forEach((type) => formData.append('visited_property_types[]', type));
        }
        if (tentativeClosingTime) formData.append('tentative_closing_time', tentativeClosingTime);
        if (remark) formData.append('remark', remark);

        try {
            if (outcome === 'reschedule') {
                const scheduledAt = document.getElementById('taskWorkflowSiteVisitRescheduleAt')?.value || '';
                if (!scheduledAt) {
                    showAlert('Please select a new visit date and time', 'warning');
                    return;
                }
                formData.append('workflow', 'visit_reschedule');
                formData.append('site_visit_id', workflowId);
                formData.append('scheduled_at', scheduledAt);
            } else {
                formData.append('workflow', 'visit_complete');
                formData.append('site_visit_id', workflowId);
                formData.append('outcome', outcome);
                if (outcome === 'follow_up_needed') {
                    const followUpAt = document.getElementById('taskWorkflowSiteVisitFollowUpAt')?.value || '';
                    if (!followUpAt) {
                        showAlert('Please select the follow-up date and time', 'warning');
                        return;
                    }
                    formData.append('scheduled_at', followUpAt);
                }
            }

            const response = await fetch(`${API_BASE_URL}/tasks/${currentTaskWorkflowContext.taskId}/workflow-action`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getToken()}`,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const responseText = await response.text();
            const result = parseJsonFromResponseText(responseText);

            if (!response.ok || !result?.success) {
                const firstValidationError = result?.errors
                    ? Object.values(result.errors).flat().find(Boolean)
                    : null;
                showAlert(firstValidationError || result?.message || 'Failed to complete site visit', 'error');
                return;
            }

            if (result?.keep_task_open) {
                closeTaskWorkflowCompleteSiteVisitModal();
                currentTaskWorkflowContext = null;
                showAlert(result.message || 'Visit outcome saved successfully', 'success');
                setTimeout(() => window.location.reload(), 400);
                return;
            }

            await finishTaskWorkflowSuccess(result.message || 'Site visit completed successfully');
        } catch (error) {
            console.error('Error completing site visit from task', error);
            showAlert('Network error while completing site visit', 'error');
        }
    }

    async function submitTaskWorkflowMarkDead() {
        const workflowId = currentTaskWorkflowContext?.workflowId;
        const workflow = currentTaskWorkflowContext?.workflow;
        const reason = document.getElementById('taskWorkflowDeadReason')?.value?.trim() || '';

        if (!workflowId || !workflow) {
            showAlert('Workflow record not found', 'error');
            return;
        }

        if (!reason) {
            showAlert('Please enter a reason', 'warning');
            return;
        }

        const endpoint = workflow === 'meeting'
            ? `/meetings/${workflowId}/mark-dead`
            : `/site-visits/${workflowId}/mark-dead`;

        const result = await apiCall(endpoint, {
            method: 'POST',
            body: JSON.stringify({ reason }),
        });

        if (!result?.success) {
            const firstValidationError = result?.errors
                ? Object.values(result.errors).flat().find(Boolean)
                : null;
            showAlert(firstValidationError || result?.message || 'Failed to mark as dead', 'error');
            return;
        }

        await finishTaskWorkflowSuccess(result.message || 'Marked as dead successfully');
    }

    async function submitTaskWorkflowReschedule() {
        const workflowId = currentTaskWorkflowContext?.workflowId;
        const workflow = currentTaskWorkflowContext?.workflow;
        const scheduledAt = document.getElementById('taskWorkflowRescheduleScheduledAt')?.value || '';
        const reason = document.getElementById('taskWorkflowRescheduleReason')?.value?.trim() || '';

        if (!workflowId || !workflow) {
            showAlert('Workflow record not found', 'error');
            return;
        }

        if (!scheduledAt) {
            showAlert('Please select a new scheduled date and time', 'warning');
            return;
        }

        if (!reason) {
            showAlert('Please enter a reason for rescheduling', 'warning');
            return;
        }

        const endpoint = workflow === 'meeting'
            ? `/meetings/${workflowId}/reschedule`
            : `/site-visits/${workflowId}/reschedule`;

        const result = await apiCall(endpoint, {
            method: 'POST',
            body: JSON.stringify({
                scheduled_at: scheduledAt,
                reason,
            }),
        });

        if (!result?.success) {
            const firstValidationError = result?.errors
                ? Object.values(result.errors).flat().find(Boolean)
                : null;
            showAlert(firstValidationError || result?.message || 'Failed to reschedule', 'error');
            return;
        }

        await finishTaskWorkflowSuccess(result.message || 'Rescheduled successfully');
    }

    async function completeTaskFollowUpDirectly() {
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }

        const completed = await completeCallingTask(taskId, 'Task');
        if (!completed) {
            showAlert('Failed to complete follow-up task', 'error');
            return;
        }

        closeTaskFollowUpActionHubModal();
        const taskCard = document.getElementById(`task-card-${taskId}`);
        if (taskCard) {
            taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
            taskCard.style.opacity = '0';
            taskCard.style.transform = 'scale(0.95)';
            setTimeout(() => {
                taskCard.remove();
                reloadCurrentTaskList();
            }, 300);
        } else {
            reloadCurrentTaskList();
        }

        setCurrentTaskId(null);
        currentTaskCategory = 'other';
        currentTaskWorkflowContext = null;
        showAlert('Follow-up completed successfully', 'success');
    }

    async function handleTaskFollowUpHubAction(action) {
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }

        if (action === 'reschedule') {
            closeTaskFollowUpActionHubModal();
            selectedTaskOutcome = 'follow_up';
            const title = document.getElementById('outcomeDateTimeModalTitle');
            const text = document.getElementById('outcomeDateTimeModalText');
            const confirmBtn = document.getElementById('outcomeDateTimeConfirmBtn');

            if (title) title.textContent = 'Schedule Follow Up';
            if (text) text.textContent = 'Choose when the next follow-up call should happen:';
            if (confirmBtn) {
                confirmBtn.textContent = 'Schedule Follow Up';
                confirmBtn.style.background = '#2563eb';
            }

            openCnpTimeSelectionModal();
            showCustomTimePicker();
            return;
        }

        if (action === 'complete') {
            await completeTaskFollowUpDirectly();
            return;
        }

        if (action === 'edit_requirement' || action === 'interested') {
            closeTaskFollowUpActionHubModal();
            window.managerTaskOutcomeContext = { outcome: 'interested', taskId };
            await openManagerLeadRequirementFormModal(taskId);
            return;
        }

        if (action === 'meeting' || action === 'visit') {
            closeTaskFollowUpActionHubModal();
            const leadUrl = getLeadDetailUrl(currentTaskWorkflowContext?.leadId);
            if (!leadUrl || leadUrl === '#') {
                showAlert('Lead detail not found for this follow-up', 'warning');
                return;
            }

            const targetAction = action === 'meeting' ? 'meeting' : 'site_visit';
            showAlert('Opening lead detail to continue this follow-up flow', 'info', 1800);
            setTimeout(() => {
                window.location.href = `${leadUrl}?task=${taskId}&action=${targetAction}`;
            }, 300);
            return;
        }

        if (action === 'not_interested' || action === 'junk') {
            closeTaskFollowUpActionHubModal();
            openOutcomeRemarkModal(action);
            return;
        }

        if (action === 'cnp') {
            closeTaskFollowUpActionHubModal();
            selectedTaskOutcome = 'cnp';
            const title = document.getElementById('outcomeDateTimeModalTitle');
            const text = document.getElementById('outcomeDateTimeModalText');
            const confirmBtn = document.getElementById('outcomeDateTimeConfirmBtn');

            if (title) title.textContent = 'Select Retry Time for CNP';
            if (text) text.textContent = 'Choose when to retry this call:';
            if (confirmBtn) {
                confirmBtn.textContent = 'Confirm CNP';
                confirmBtn.style.background = '#f59e0b';
            }

            openCnpTimeSelectionModal();
        }
    }

    async function handleTaskMeetingHubAction(action) {
        if (!currentTaskWorkflowContext?.taskId || !currentTaskWorkflowContext?.workflowId) {
            showAlert('Meeting task context not found', 'warning');
            return;
        }

        if (action === 'complete') {
            openTaskWorkflowCompleteModal();
            return;
        }

        if (action === 'reschedule') {
            openTaskWorkflowReschedule();
            return;
        }

        if (action === 'edit_requirement') {
            closeTaskMeetingActionHubModal();
            await openManagerLeadRequirementFormModal(currentTaskWorkflowContext.taskId);
            return;
        }

        if (action === 'follow_up') {
            setCurrentTaskId(currentTaskWorkflowContext.taskId);
            closeTaskMeetingActionHubModal();
            selectedTaskOutcome = 'follow_up';

            const title = document.getElementById('outcomeDateTimeModalTitle');
            const text = document.getElementById('outcomeDateTimeModalText');
            const confirmBtn = document.getElementById('outcomeDateTimeConfirmBtn');

            if (title) title.textContent = 'Schedule Follow Up';
            if (text) text.textContent = 'Choose when the next follow-up call should happen:';
            if (confirmBtn) {
                confirmBtn.textContent = 'Schedule Follow Up';
                confirmBtn.style.background = '#2563eb';
            }

            openCnpTimeSelectionModal();
            showCustomTimePicker();
            return;
        }

        if (action === 'send_to_closer') {
            openTaskMeetingSendCloserModal();
            return;
        }

        if (action === 'interested') {
            closeTaskMeetingActionHubModal();
            window.managerTaskOutcomeContext = { outcome: 'interested' };
            await openManagerLeadRequirementFormModal(currentTaskWorkflowContext.taskId);
            return;
        }

        if (action === 'not_interested' || action === 'junk') {
            closeTaskMeetingActionHubModal();
            openOutcomeRemarkModal(action);
            return;
        }

        if (action === 'dead') {
            openTaskWorkflowMarkDead();
            return;
        }

        if (action === 'visit') {
            closeTaskMeetingActionHubModal();

            const leadUrl = getLeadDetailUrl(currentTaskWorkflowContext.leadId);
            if (!leadUrl || leadUrl === '#') {
                showAlert('Lead detail not found for this meeting', 'warning');
                return;
            }

            showAlert('Opening lead detail to schedule visit from the meeting flow', 'info', 1800);
            setTimeout(() => {
                window.location.href = leadUrl;
            }, 300);
        }
    }

    function openManagerTaskCompletion(task) {
        const taskCategory = String(task?.category || 'other').trim().toLowerCase();
        const isFollowUpTask = taskCategory === 'follow_up' || Boolean(task?.follow_up_id);
        if (isFollowUpTask) {
            openTaskFollowUpActionHubModal(task);
            return;
        }

        const meetingId = extractWorkflowIdFromTask(task, 'meeting');
        if (meetingId) {
            openTaskMeetingActionHubModal(task, meetingId);
            return;
        }

        const siteVisitId = extractWorkflowIdFromTask(task, 'visit');
        if (siteVisitId) {
            currentTaskWorkflowContext = {
                taskId: task?.id || null,
                taskType: 'Task',
                workflow: 'visit',
                workflowId: Number(siteVisitId) || null,
                leadId: task?.lead_id || null,
            };
            openTaskVisitActionHubModal();
            return;
        }

        openTaskOutcomeModal(task.id, taskCategory);
    }

    function deriveTaskDateFilterFromScheduledAt(scheduledAt) {
        if (!scheduledAt) {
            return { dateFilter: 'today', customDate: null };
        }

        const taskDate = parseAsmDateTime(scheduledAt);
        if (!taskDate || Number.isNaN(taskDate.getTime())) {
            return { dateFilter: 'today', customDate: null };
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        const taskOnly = new Date(taskDate);
        taskOnly.setHours(0, 0, 0, 0);

        if (taskOnly.getTime() === today.getTime()) {
            return { dateFilter: 'today', customDate: null };
        }

        if (taskOnly.getTime() === tomorrow.getTime()) {
            return { dateFilter: 'tomorrow', customDate: null };
        }

        const startOfWeek = new Date(today);
        startOfWeek.setDate(today.getDate() - today.getDay());
        const endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + 6);

        if (taskOnly >= startOfWeek && taskOnly <= endOfWeek) {
            return { dateFilter: 'this_week', customDate: null };
        }

        if (taskOnly.getMonth() === today.getMonth() && taskOnly.getFullYear() === today.getFullYear()) {
            return { dateFilter: 'this_month', customDate: null };
        }

        if (taskOnly.getFullYear() === today.getFullYear()) {
            return { dateFilter: 'this_year', customDate: null };
        }

        const yyyy = taskOnly.getFullYear();
        const mm = String(taskOnly.getMonth() + 1).padStart(2, '0');
        const dd = String(taskOnly.getDate()).padStart(2, '0');
        return { dateFilter: 'custom', customDate: `${yyyy}-${mm}-${dd}` };
    }

    async function resolveRequestedTaskContext(taskId) {
        if (!taskId) return null;

        try {
            const result = await apiCall(`/tasks/${taskId}`);
            const task = result?.data || result;
            if (!task?.id) {
                return null;
            }

            const derivedDate = deriveTaskDateFilterFromScheduledAt(task.scheduled_at || task.scheduled_at_formatted);

            return {
                taskId: Number(task.id),
                status: normalizeTaskStatusFilter(task.status || 'pending'),
                category: String(task.category || 'all'),
                dateFilter: derivedDate.dateFilter,
                customDate: derivedDate.customDate,
                task: {
                    id: Number(task.id),
                    category: String(task.category || 'other'),
                    title: task.title || '',
                    description: task.description || '',
                    notes: task.notes || '',
                    scheduled_at: task.scheduled_at || task.scheduled_at_formatted || '',
                    meeting_id: task.meeting_id || '',
                    site_visit_id: task.site_visit_id || '',
                    follow_up_id: task.follow_up_id || '',
                    lead_id: task.lead_id || task.lead?.id || '',
                },
            };
        } catch (error) {
            console.error('Failed to resolve requested task context:', error);
            return null;
        }
    }

    function focusRequestedTaskCard(taskId, attempts = 10) {
        if (!taskId || attempts <= 0) return;

        const card = document.getElementById(`task-card-${taskId}`);
        if (!card) {
            setTimeout(() => focusRequestedTaskCard(taskId, attempts - 1), 300);
            return;
        }

        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.style.borderColor = '#205A44';
        card.style.boxShadow = '0 0 0 3px rgba(32, 90, 68, 0.18), 0 12px 26px rgba(15, 23, 42, 0.12)';
        setTimeout(() => {
            card.style.borderColor = '';
            card.style.boxShadow = '';
        }, 2200);
    }

    // Filter tasks function - must be globally accessible for onclick handlers
    function filterTasks(status, dateFilter = null, customDate = null, category = null) {
        status = normalizeTaskStatusFilter(status);
        console.log('filterTasks called with status:', status, 'dateFilter:', dateFilter, 'customDate:', customDate, 'category:', category);
        
        // Get date filter from dropdown if not provided
        if (dateFilter === null) {
            const dateDropdown = document.getElementById('dateFilterDropdown') || document.getElementById('dateFilterDropdownDesktop');
            dateFilter = dateDropdown ? dateDropdown.value : 'today';
        }

        if (category === null) {
            const categoryDropdown = document.getElementById('taskTypeFilterDesktop') || document.getElementById('taskTypeFilterMobile');
            category = categoryDropdown ? categoryDropdown.value : (currentCategory || 'all');
        }
        currentCategory = category;
        const normalizedDateFilter = normalizeAsmVisibleDateFilter(dateFilter);
        const nextTaskMode = getAsmTaskModeFromFilters(status, normalizedDateFilter);

        if (USES_MANAGER_TASK_POD_TOGGLE && nextTaskMode === 'today' && status !== 'overdue') {
            asmLastTodayStatus = status;
        }
        currentStatus = status;
        
        // Save to localStorage
        try {
            localStorage.setItem('salesManagerTasksFilter', status);
            localStorage.setItem('salesManagerDateFilter', normalizedDateFilter);
            localStorage.setItem('salesManagerTaskCategory', category || 'all');
            if (normalizedDateFilter === 'custom' && customDate) {
                localStorage.setItem('salesManagerCustomDate', customDate);
            } else {
                localStorage.removeItem('salesManagerCustomDate');
            }
        } catch (e) {
            console.error('Failed to save filter to localStorage:', e);
        }
        
        // Update status dropdown value
        const filterDropdown = document.getElementById('taskFilterDropdown');
        if (filterDropdown) {
            filterDropdown.value = status;
        }
        const filterDropdownDesktop = document.getElementById('taskStatusFilterDesktop');
        if (filterDropdownDesktop) {
            filterDropdownDesktop.value = status;
        }
        
        // Update date dropdown values
        const dateDropdownMobile = document.getElementById('dateFilterDropdown');
        const dateDropdownDesktop = document.getElementById('dateFilterDropdownDesktop');
        if (dateDropdownMobile) {
            dateDropdownMobile.value = normalizedDateFilter;
        }
        if (dateDropdownDesktop) {
            dateDropdownDesktop.value = normalizedDateFilter;
        }

        const categoryDropdownDesktop = document.getElementById('taskTypeFilterDesktop');
        const categoryDropdownMobile = document.getElementById('taskTypeFilterMobile');
        if (categoryDropdownDesktop) {
            categoryDropdownDesktop.value = category;
        }
        if (categoryDropdownMobile) {
            categoryDropdownMobile.value = category;
        }
        
        const customDatePicker = document.getElementById('customDatePicker');
        if (customDatePicker) {
            if (normalizedDateFilter === 'custom') {
                customDatePicker.value = customDate || primeCustomDatePicker(customDatePicker) || '';
            }
        }

        // Show/hide remove all overdue button
        const removeAllOverdueBtn = document.getElementById('removeAllOverdueBtn');
        if (removeAllOverdueBtn) {
            if (status === 'overdue' && normalizedDateFilter !== 'pod') {
                removeAllOverdueBtn.style.display = 'flex';
            } else {
                removeAllOverdueBtn.style.display = 'none';
            }
        }

        syncAsmTaskModeUi(nextTaskMode);
        loadTasks(status, normalizedDateFilter, customDate || (normalizedDateFilter === 'custom' && customDatePicker ? customDatePicker.value : null), category);
    }
    
    // Attach to window for global access (critical for onclick handlers)
    window.filterTasks = filterTasks;
    
    console.log('filterTasks function defined and attached to window:', typeof window.filterTasks);

    let taskLoadInFlight = null;
    let lastTaskLoadKey = null;
    let lastTaskLoadAt = 0;
    let taskInitBooted = false;
    let taskAutoRefreshStarted = false;
    let asmDesktopSummaryPromise = null;
    let asmDesktopSummaryLastAt = 0;
    let asmDesktopSummaryCache = null;
    let asmDesktopSummaryCategory = null;
    let asmPodCountPromise = null;
    let asmPodCountLastAt = 0;
    let asmPodCountCache = null;
    let asmPodCountCategory = null;

    function getTaskLoadKey(status, dateFilter, customDate, category) {
        return JSON.stringify({
            status: status || 'all',
            dateFilter: dateFilter || 'today',
            customDate: customDate || '',
            category: category || 'all',
        });
    }

    function ensureTaskAutoRefreshStarted() {
        if (taskAutoRefreshStarted) {
            return;
        }

        taskAutoRefreshStarted = true;
        setInterval(function() {
            if (document.hidden) {
                return;
            }

            const status = window.currentStatus || currentStatus || 'all';
            const dateFilter = document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today';
            const customDate = document.getElementById('customDatePicker')?.value || null;
            const category = document.getElementById('taskTypeFilterDesktop')?.value || document.getElementById('taskTypeFilterMobile')?.value || 'all';
            loadTasks(status, dateFilter, customDate, category);
        }, 60000);
    }

    async function loadTasks(status = null, dateFilter = null, customDate = null, category = null) {
        console.log('=== loadTasks() CALLED ===', { status, dateFilter, customDate, category });
        const tasksGrid = document.getElementById('tasksGrid');
        if (!tasksGrid) {
            console.error('ERROR: Tasks grid element not found!');
            return;
        }
        
        // Get current filters if not provided
        if (status === null) {
            status = window.currentStatus || currentStatus || 'all';
        }
        if (dateFilter === null) {
            const dateDropdown = document.getElementById('dateFilterDropdown') || document.getElementById('dateFilterDropdownDesktop');
            dateFilter = dateDropdown ? dateDropdown.value : 'today';
        }
        dateFilter = normalizeAsmVisibleDateFilter(dateFilter);
        if (dateFilter === 'custom' && customDate === null) {
            const customDatePicker = document.getElementById('customDatePicker');
            customDate = customDatePicker && customDatePicker.value ? customDatePicker.value : null;
        }

        if (category === null) {
            const categoryDropdown = document.getElementById('taskTypeFilterDesktop') || document.getElementById('taskTypeFilterMobile');
            category = categoryDropdown ? categoryDropdown.value : (currentCategory || 'all');
        }

        const requestKey = getTaskLoadKey(status, dateFilter, customDate, category);
        const nowMs = Date.now();
        if (taskLoadInFlight && requestKey === lastTaskLoadKey) {
            console.log('Skipping duplicate loadTasks call while request is already in flight');
            return taskLoadInFlight;
        }
        if (!taskLoadInFlight && requestKey === lastTaskLoadKey && (nowMs - lastTaskLoadAt) < 1200) {
            console.log('Skipping repeated loadTasks call inside cooldown window');
            return;
        }

        lastTaskLoadKey = requestKey;
        lastTaskLoadAt = nowMs;
        
        console.log('Setting loading state...');
        tasksGrid.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading tasks...</p></div>';
        updateAsmMobileTaskChrome([], dateFilter, customDate);
        syncAsmTaskModeUi(getAsmTaskModeFromFilters(status, dateFilter));

        // Set a timeout to show error if API call takes too long
        let timeoutId = setTimeout(() => {
            console.error('API call timeout after 30 seconds');
            const currentGrid = document.getElementById('tasksGrid');
            if (currentGrid && currentGrid.innerHTML.includes('Loading tasks')) {
                currentGrid.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Request Timeout</h3><p>The request is taking too long. Please check your connection and try again.</p><button onclick="if(window.filterTasks) { const status = window.currentStatus || \'all\'; const dateFilter = document.getElementById(\'dateFilterDropdown\')?.value || document.getElementById(\'dateFilterDropdownDesktop\')?.value || \'all\'; const customDate = document.getElementById(\'customDatePicker\')?.value || null; window.filterTasks(status, dateFilter, customDate); } else { window.location.reload(); }" style="margin-top: 12px; padding: 8px 16px; background: #205A44; color: white; border: none; border-radius: 6px; cursor: pointer;">Retry</button></div>';
            }
        }, 30000);

        taskLoadInFlight = (async () => {
        try {
            console.log('=== STARTING API CALL ===');
            console.log('Current status filter:', currentStatus);
            console.log('API_BASE_URL:', API_BASE_URL);
            console.log('API_TOKEN:', API_TOKEN ? 'Present (' + API_TOKEN.substring(0, 20) + '...)' : 'Missing');
            
            const params = new URLSearchParams();
            if (status && status !== 'all') {
                params.append('status', status);
            }
            
            // Add date filter parameters
            if (dateFilter && dateFilter !== 'all') {
                params.append('date_filter', dateFilter);
                if (dateFilter === 'custom' && customDate) {
                    params.append('custom_date', customDate);
                }
            }

            if (category && category !== 'all') {
                params.append('category', category);
            }
            
            const endpoint = `/tasks${params.toString() ? '?' + params.toString() : ''}`;
            const fullUrl = `${API_BASE_URL}${endpoint}`;
            console.log('Full API URL:', fullUrl);
            console.log('Calling API endpoint:', endpoint);
            console.log('Filters:', { status, dateFilter, customDate, category });
            
            const result = await apiCall(endpoint);
            clearTimeout(timeoutId); // Clear timeout on success
            
            console.log('=== API CALL COMPLETED ===');
            console.log('Tasks API response:', result);
            console.log('Response type:', typeof result);
            console.log('Response keys:', result ? Object.keys(result) : 'null');
            
            // Check if result exists and has the expected structure
            if (!result) {
                console.error('API returned null or undefined');
                tasksGrid.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error loading tasks</h3><p>No response from server. Please refresh the page.</p></div>';
                updateAsmMobileTaskChrome([], dateFilter, customDate);
                return;
            }
            
            // Handle error response
            if (result.success === false) {
                console.error('API returned error:', result.message || result.error);
                const errorMsg = result.message || result.error || 'Unknown error occurred';
                tasksGrid.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error loading tasks</h3><p>${errorMsg}</p><button onclick="if(window.filterTasks) { const status = window.currentStatus || 'all'; const dateFilter = document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today'; const customDate = document.getElementById('customDatePicker')?.value || null; window.filterTasks(status, dateFilter, customDate); } else { window.location.reload(); }" style="margin-top: 12px; padding: 8px 16px; background: #205A44; color: white; border: none; border-radius: 6px; cursor: pointer;">Retry</button></div>`;
                updateAsmMobileTaskChrome([], dateFilter, customDate);
                return;
            }
            
            // Check if data exists and is an array
            console.log('Checking response data...');
            console.log('result.success:', result.success);
            console.log('result.data exists:', result.data !== undefined);
            console.log('result.data type:', typeof result.data);
            console.log('result.data is array:', Array.isArray(result.data));
            
            if (result.data !== undefined) {
                if (Array.isArray(result.data)) {
                    console.log(`Data is array with ${result.data.length} items`);
                    syncAsmTaskModeUi(getAsmTaskModeFromFilters(status, dateFilter), result.data.length);
                    if (!isAsmFollowupsDemoContext(status, dateFilter, category) || result.data.length > 0) {
                        refreshAsmPodCount(category);
                        refreshAsmDesktopSummary(category);
                    }
                    if (result.data.length > 0) {
                        console.log(`Found ${result.data.length} tasks out of ${result.total || result.data.length} total`);
                        console.log('First task sample:', result.data[0]);
                        renderTasks(result.data);
                        updateAsmDesktopQueueMeta(result.data, status, dateFilter);
                        updateAsmDesktopTaskChrome(result.data, status, dateFilter, customDate);
                        updateAsmMobileTaskChrome(result.data, dateFilter, customDate);
                    } else {
                        console.log('No tasks found (empty array)');
                        const demoTasks = buildAsmFollowupsDemoTasks(status, dateFilter, category);
                        if (demoTasks.length > 0) {
                            renderTasks(demoTasks);
                            syncAsmTaskModeUi(getAsmTaskModeFromFilters(status, dateFilter), demoTasks.length);
                            applyAsmFollowupsDemoCounts(demoTasks.length);
                            updateAsmDesktopQueueMeta(demoTasks, status, dateFilter);
                            updateAsmDesktopTaskChrome(demoTasks, status, dateFilter, customDate);
                            updateAsmMobileTaskChrome(demoTasks, dateFilter, customDate);
                        } else {
                            tasksGrid.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No tasks found</h3><p>No tasks match the current filters.</p></div>';
                            syncAsmTaskModeUi(getAsmTaskModeFromFilters(status, dateFilter), 0);
                            refreshAsmPodCount(category);
                            refreshAsmDesktopSummary(category);
                            updateAsmDesktopQueueMeta([], status, dateFilter);
                            updateAsmDesktopTaskChrome([], status, dateFilter, customDate);
                            updateAsmMobileTaskChrome([], dateFilter, customDate);
                        }
                    }
                } else {
                    console.error('Invalid response format - data is not an array:', result);
                    console.error('Data type:', typeof result.data, 'Value:', result.data);
                    console.error('Full result:', JSON.stringify(result, null, 2));
                    tasksGrid.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error loading tasks</h3><p>Invalid response format from server. Data is not an array. Check console for details.</p></div>';
                    updateAsmDesktopTaskChrome([], status, dateFilter, customDate);
                    updateAsmMobileTaskChrome([], dateFilter, customDate);
                }
            } else {
                console.error('Response missing data field:', result);
                console.error('Full result object:', JSON.stringify(result, null, 2));
                tasksGrid.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error loading tasks</h3><p>Response missing data field from server. Check console for details.</p></div>';
                updateAsmDesktopTaskChrome([], status, dateFilter, customDate);
                updateAsmMobileTaskChrome([], dateFilter, customDate);
            }
        } catch (error) {
            clearTimeout(timeoutId); // Clear timeout on error
            console.error('=== ERROR IN loadTasks() ===');
            console.error('Error loading tasks:', error);
            console.error('Error name:', error.name);
            console.error('Error message:', error.message);
            console.error('Error stack:', error.stack);
            const errorMsg = error.message || 'Unknown error occurred';
            tasksGrid.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error loading tasks</h3><p>${errorMsg}. Please refresh the page.</p><button onclick="if(window.filterTasks) { const status = window.currentStatus || 'all'; const dateFilter = document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today'; const customDate = document.getElementById('customDatePicker')?.value || null; window.filterTasks(status, dateFilter, customDate); } else { window.location.reload(); }" style="margin-top: 12px; padding: 8px 16px; background: #205A44; color: white; border: none; border-radius: 6px; cursor: pointer;">Retry</button></div>`;
            updateAsmDesktopTaskChrome([], status, dateFilter, customDate);
            updateAsmMobileTaskChrome([], dateFilter, customDate);
        } finally {
            taskLoadInFlight = null;
        }
        })();

        return taskLoadInFlight;
    }
    
    // Make loadTasks globally accessible (critical for onclick handlers in error messages)
    window.loadTasks = loadTasks;
    console.log('loadTasks function defined and attached to window:', typeof window.loadTasks);

    function renderTasks(tasks) {
        const tasksGrid = document.getElementById('tasksGrid');
        const items = Array.isArray(tasks) ? tasks : [];

        const activeStatusFilter = window.currentStatus || currentStatus || 'all';
        const activeDateFilter = normalizeAsmVisibleDateFilter(
            document.getElementById('dateFilterDropdown')?.value
            || document.getElementById('dateFilterDropdownDesktop')?.value
            || 'today'
        );
        const showStatusBadge = activeStatusFilter !== 'overdue' && activeDateFilter !== 'pod';

        if (items.length === 0) {
            tasksGrid.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><h3>No tasks found</h3><p>No tasks match the current filters.</p></div>';
            return;
        }

        tasksGrid.innerHTML = items.map(task => {
            const lead = task.lead || {};
            // Extract lead name from title if lead name not available
            let leadName = lead.name || 'Prospect';
            if (task.title) {
                const titleMatch = task.title.match(/prospect verification:\s*(.+)/i);
                if (titleMatch) {
                    leadName = titleMatch[1].trim();
                } else if (!lead.name) {
                    leadName = task.title.replace('Call for prospect verification: ', '').trim() || 'Prospect';
                }
            }
            const leadPhone = lead.phone || task.lead_phone || 'N/A';
            const initial = leadName.charAt(0).toUpperCase();
            const isOverdue = task.is_overdue === true;
            const statusText = String(task.status || 'pending').replace(/_/g, ' ').toUpperCase();
            const scheduledDate = formatDate(task.scheduled_at_formatted || task.scheduled_at);
            const overdueClass = isOverdue ? 'overdue' : '';
            const statusClass = `status-${task.status || 'pending'}`;
            const displayTitle = task.display_title || leadName;
            const displaySubtitle = task.display_subtitle || scheduledDate;
            const cnpSequence = Number(task.cnp_sequence || 0);
            
            // Check if lead has prospect (from telecaller) - needs verification
            const hasProspect = task.has_prospect === true && 
                               task.prospect && 
                               task.prospect.is_pending_verification === true;
            const isFollowUpTask = task.category === 'follow_up';
            const followUpRemark = escapeHtml((task.notes || '').trim());
            const titleHtml = escapeHtml(displayTitle);
            const subtitleHtml = escapeHtml(displaySubtitle);
            const leadDetailUrl = lead.id ? getLeadDetailUrl(lead.id) : '#';
            const hasMeetingWorkflow = task.category === 'meeting' && (Boolean(task.meeting_id) || (task.notes && task.notes.includes('Pre-meeting reminder')));
            const hasVisitWorkflow = task.category === 'site_visit' && Boolean(task.site_visit_id);
            const isUiDemo = task.is_ui_demo === true;
            const taskCompletionPayload = {
                id: task.id,
                category: task.category || 'other',
                title: task.title || '',
                description: task.description || '',
                notes: task.notes || '',
                scheduled_at: task.scheduled_at || '',
                meeting_id: task.meeting_id || '',
                site_visit_id: task.site_visit_id || '',
                follow_up_id: task.follow_up_id || '',
                lead: { id: lead.id || '' },
            };
            const taskCompletionPayloadJson = escapeHtml(JSON.stringify(taskCompletionPayload));

            const cleanPhone = String(leadPhone).replace(/[^0-9]/g, '');
            return `
                <div id="task-card-${task.id}" class="task-card ${overdueClass}">
                    <div class="task-header">
                        <div class="task-head-main">
                            <div class="task-avatar">${initial}</div>
                            <div class="task-head-content">
                                <div class="task-title-row">
                                    <div class="task-title-copy">
                                        <h3 class="task-name">${lead.id ? `<a href="${leadDetailUrl}" class="task-lead-link">${titleHtml}</a>` : titleHtml}</h3>
                                        <p class="task-subtitle">${subtitleHtml}</p>
                                    </div>
                                    <div class="task-status-row">
                                        ${isOverdue ? '<span class="overdue-badge">OVERDUE</span>' : ''}
                                        ${showStatusBadge ? `<span class="status-badge ${statusClass}">${statusText}</span>` : ''}
                                        ${cnpSequence > 0 ? `<span class="task-sequence-chip">C${cnpSequence}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="task-info">
                        <div class="task-info-row">
                            <i class="fas fa-phone"></i>
                            <span>${leadPhone}</span>
                        </div>
                        <div class="task-info-row">
                            <i class="fas fa-clock"></i>
                            <span>Scheduled: ${scheduledDate}</span>
                        </div>
                        ${isFollowUpTask && followUpRemark ? `
                        <div class="task-info-row" style="align-items:flex-start;">
                            <i class="fas fa-note-sticky" style="margin-top:3px;"></i>
                            <span style="line-height:1.5;">${followUpRemark}</span>
                        </div>
                        ` : ''}
                        ${(hasMeetingWorkflow || hasVisitWorkflow) ? `
                        <div class="task-chip-row">
                            ${hasMeetingWorkflow ? '<span class="task-chip meeting"><i class="fas fa-calendar-check task-chip-icon"></i> Meeting</span>' : ''}
                            ${hasVisitWorkflow ? '<span class="task-chip visit"><i class="fas fa-map-marker-alt task-chip-icon"></i> Visit</span>' : ''}
                        </div>
                        ` : ''}
                    </div>
                    <div class="task-actions">
                        <button class="task-action-btn btn-call" ${isUiDemo ? 'type="button" disabled title="Demo card"' : `onclick="handleManagerCallClick(${task.id}, '${leadPhone}', ${hasProspect})" title="Call"`}>
                            <i class="fas fa-phone"></i>
                            <span>Call Now</span>
                        </button>
                        <button class="task-action-btn btn-view-detail" ${isUiDemo ? 'type="button" disabled title="Demo card"' : `onclick="openTaskDetailModal(${task.id})" title="Detail"`}>
                            <i class="fas fa-circle-info"></i>
                            <span>Detail</span>
                        </button>
                        <button class="task-action-btn btn-view-detail" ${isUiDemo ? 'type="button" disabled title="Demo card"' : `onclick='openManagerTaskCompletion(${taskCompletionPayloadJson})' title="Mark Complete"`}>
                            <i class="fas fa-check-circle"></i>
                            <span>${isUiDemo ? 'UI Demo' : 'Mark Complete'}</span>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function openWhatsApp(phone) {
        if (!phone || phone === 'N/A') {
            showAlert('Phone number not available', 'warning');
            return;
        }
        // Remove all non-numeric characters and ensure it starts with country code
        const cleanPhone = phone.replace(/[^0-9]/g, '');
        if (cleanPhone.length < 10) {
            showAlert('Invalid phone number', 'warning');
            return;
        }
        // If phone doesn't start with country code, assume it's Indian (+91)
        const phoneWithCountryCode = cleanPhone.startsWith('91') && cleanPhone.length === 12 
            ? cleanPhone 
            : (cleanPhone.length === 10 ? '91' + cleanPhone : cleanPhone);
        window.open(`https://wa.me/${phoneWithCountryCode}`, '_blank');
    }

    async function loadInterestedProjects() {
        try {
            const response = await fetch('/api/interested-project-names', {
                headers: getAuthHeaders(),
            });
            const result = await response.json();
            
            if (result && result.success && result.data) {
                const projectSelect = document.getElementById('interestedProjects');
                projectSelect.innerHTML = ''; // Clear existing options
                
                result.data.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name;
                    projectSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading interested projects:', error);
        }
    }

    // Handle manager call click - Check if lead has prospect or is direct assignment
    function formatPhoneForDialer(phoneNumber) {
        const cleanPhone = String(phoneNumber || '').replace(/[^0-9]/g, '');
        if (cleanPhone.length < 10) {
            return '';
        }

        if (cleanPhone.length === 10) {
            return `+91${cleanPhone}`;
        }

        if (cleanPhone.length === 12 && cleanPhone.startsWith('91')) {
            return `+${cleanPhone}`;
        }

        return cleanPhone.startsWith('+') ? cleanPhone : `+${cleanPhone}`;
    }

    async function resolveTaskLeadIdForMcube(taskId) {
        if (!taskId) return null;
        try {
            const taskResponse = await apiCall(`/tasks/${taskId}`);
            const task = taskResponse?.data || taskResponse?.task || null;
            return task?.lead_id || task?.lead?.id || null;
        } catch (error) {
            console.warn('Unable to resolve lead for MCube outbound call:', error);
            return null;
        }
    }

    async function initiateMcubeCallOrFallback(taskId, phoneNumber, leadId = null) {
        const dialerPhone = formatPhoneForDialer(phoneNumber);
        if (!dialerPhone) {
            showAlert('Phone number not available', 'warning');
            return false;
        }

        const resolvedLeadId = leadId || await resolveTaskLeadIdForMcube(taskId);
        if (!resolvedLeadId) {
            window.location.href = `tel:${dialerPhone}`;
            return false;
        }

        try {
            const response = await fetch('/api/mcube/outbound-call', {
                method: 'POST',
                headers: {
                    ...getAuthHeaders(),
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    lead_id: resolvedLeadId,
                    task_id: taskId || null,
                    phone: phoneNumber || null,
                }),
            });
            const result = await response.json().catch(() => ({}));

            if (response.ok && result.success) {
                showAlert(result.message || 'Call initiated via MCube.', 'success');
                return true;
            }

            showAlert(result.message || 'MCube call failed. Opening phone dialer.', 'warning');
            window.location.href = `tel:${dialerPhone}`;
            return false;
        } catch (error) {
            console.warn('MCube outbound call failed, falling back to phone dialer:', error);
            showAlert('MCube call failed. Opening phone dialer.', 'warning');
            window.location.href = `tel:${dialerPhone}`;
            return false;
        }
    }

    async function openManagerTaskCallMenu(taskId, phoneNumber, leadId = null, afterCall = null) {
        const dialerPhone = formatPhoneForDialer(phoneNumber);
        if (!dialerPhone) {
            showAlert('Phone number not available', 'warning');
            if (typeof afterCall === 'function') {
                afterCall();
            }
            return false;
        }

        const resolvedLeadId = leadId || await resolveTaskLeadIdForMcube(taskId);
        if (typeof openLeadCallMenu === 'function') {
            openLeadCallMenu(resolvedLeadId, phoneNumber, taskId, {
                afterCall: typeof afterCall === 'function' ? afterCall : null,
            });
            return true;
        }

        await initiateMcubeCallOrFallback(taskId, phoneNumber, resolvedLeadId);
        if (typeof afterCall === 'function') {
            afterCall();
        }
        return true;
    }

    async function handleManagerCallClick(taskId, phoneNumber, hasProspect = null) {
        setCurrentTaskId(taskId);
        
        // First, check if this is a meeting task
        let isMeetingTask = false;
        let meetingId = null;
        let taskLeadId = null;
        
        try {
            const taskResponse = await apiCall(`/tasks/${taskId}`);
            if (taskResponse && taskResponse.success && taskResponse.data) {
                const taskData = taskResponse.data;
                taskLeadId = taskData?.lead_id || taskData?.lead?.id || null;
                // Check if task is related to meeting
                isMeetingTask = taskData.type === 'meeting' || 
                               (taskData.notes && taskData.notes.includes('Pre-meeting reminder'));
                
                // Extract meeting ID from notes if available
                if (isMeetingTask && taskData.notes) {
                    const meetingIdMatch = taskData.notes.match(/Meeting ID:\s*(\d+)/i);
                    if (meetingIdMatch) {
                        meetingId = parseInt(meetingIdMatch[1]);
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching task data:', error);
        }
        
        // If this is a meeting task, show meeting popup after call
        if (isMeetingTask && meetingId) {
            if (phoneNumber && phoneNumber !== 'N/A' && phoneNumber !== '') {
                const dialerPhone = formatPhoneForDialer(phoneNumber);
                if (dialerPhone) {
                    await openManagerTaskCallMenu(taskId, phoneNumber, taskLeadId, () => {
                        if (typeof showPostCallPopup === 'function') {
                            showPostCallPopup(meetingId, null, taskId, 'Task');
                        } else {
                            showAlert('Meeting popup not available', 'error');
                        }
                    });
                } else {
                    showAlert('Phone number not available', 'warning');
                    if (typeof showPostCallPopup === 'function') {
                        showPostCallPopup(meetingId, null, taskId, 'Task');
                    }
                }
            } else {
                showAlert('Phone number not available', 'warning');
                if (typeof showPostCallPopup === 'function') {
                    showPostCallPopup(meetingId, null, taskId, 'Task');
                }
            }
            return; // Exit early for meeting tasks
        }
        
        // If hasProspect not provided, fetch task data to check
        if (hasProspect === null) {
            try {
                const tasksResponse = await apiCall('/tasks');
                if (tasksResponse && tasksResponse.success && tasksResponse.data) {
                    const taskData = tasksResponse.data.find(t => t.id === taskId);
                    hasProspect = taskData?.has_prospect === true && 
                                 taskData?.prospect?.is_pending_verification === true;
                }
            } catch (error) {
                console.error('Error fetching task data:', error);
                // Safer fallback: treat as direct lead to avoid wrong prospect actions.
                hasProspect = false;
            }
        }
        
        // If lead has prospect from telecaller → show verification popup
        // If no prospect (direct assignment) → open Lead Requirement Form directly
        if (hasProspect) {
            // Lead from telecaller - show verification popup (current behavior)
            if (phoneNumber && phoneNumber !== 'N/A' && phoneNumber !== '') {
                const dialerPhone = formatPhoneForDialer(phoneNumber);
                if (dialerPhone) {
                    await openManagerTaskCallMenu(taskId, phoneNumber, taskLeadId, () => {
                        showVerifyRejectPrompt();
                    });
                } else {
                    showAlert('Phone number not available', 'warning');
                    showVerifyRejectPrompt();
                }
            } else {
                showAlert('Phone number not available', 'warning');
                showVerifyRejectPrompt();
            }
        } else {
            // Directly assigned lead (no prospect) - only open the dialer from Call Now.
            // Lead form stays on Mark Complete / outcome flow so users do not get interrupted.
            if (phoneNumber && phoneNumber !== 'N/A' && phoneNumber !== '') {
                const dialerPhone = formatPhoneForDialer(phoneNumber);
                if (dialerPhone) {
                    await openManagerTaskCallMenu(taskId, phoneNumber, taskLeadId);
                } else {
                    showAlert('Phone number not available', 'warning');
                }
            } else {
                showAlert('Phone number not available', 'warning');
            }
        }
    }
    
    function showVerifyRejectPrompt() {
        // Show verify/reject prompt modal (Step 1)
        const promptModal = document.getElementById('verifyRejectPromptModal');
        promptModal.classList.add('active');
    }

    function closeVerifyRejectPromptModal() {
        const modal = document.getElementById('verifyRejectPromptModal');
        modal.classList.remove('active');
        // Don't reset currentTaskId here - it's needed for proceedToVerifyForm() and proceedToReject()
        // Only reset when form is submitted successfully or user explicitly cancels
    }
    
    function cancelVerifyRejectPrompt() {
        // User clicked close/cancel button - reset task ID
        closeVerifyRejectPromptModal();
        setCurrentTaskId(null);
    }

    // Step 2a: Proceed to Verify - Load full form
    async function proceedToVerifyForm() {
        // Don't close modal here - just hide it, keep currentTaskId
        const promptModal = document.getElementById('verifyRejectPromptModal');
        promptModal.classList.remove('active');
        
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }
        
        // Open full form modal
        await openManagerLeadRequirementFormModal(taskId);
    }

    // Step 2b: Proceed to Reject - Show reject reason modal
    function proceedToReject() {
        // Don't close modal here - just hide it, keep currentTaskId
        const promptModal = document.getElementById('verifyRejectPromptModal');
        promptModal.classList.remove('active');
        
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }
        
        // Show reject reason modal
        const rejectModal = document.getElementById('rejectReasonModal');
        rejectModal.classList.add('active');
        document.getElementById('rejectReasonInput').value = '';
    }

    // Step 2c: Proceed to CNP - Open time selection modal
    function proceedToCNP() {
        // Hide prompt modal, keep currentTaskId
        const promptModal = document.getElementById('verifyRejectPromptModal');
        promptModal.classList.remove('active');
        
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }
        
        // Open CNP time selection modal
        openCnpTimeSelectionModal();
    }
    
    // CNP Time Selection Variables
    let selectedCnpMinutes = null;
    let selectedCnpCustomDateTime = null;
    let isCustomTimeSelected = false;
    const managerUsesUnifiedCnpScheduling = @json(optional(auth()->user())->isAssistantSalesManager() || optional(auth()->user())->isSeniorManager());
    
    // Open CNP time selection modal
    function openCnpTimeSelectionModal() {
        const modal = document.getElementById('cnpTimeSelectionModal');
        if (modal) {
            const activeTaskId = resolveActiveTaskId();
            if (activeTaskId) {
                modal.dataset.taskId = activeTaskId;
            }
            modal.classList.add('active');
            // Reset selections
            selectedCnpMinutes = null;
            selectedCnpCustomDateTime = null;
            isCustomTimeSelected = false;
            document.getElementById('customTimePickerContainer').style.display = 'none';
            document.getElementById('selectedTimeDisplay').style.display = 'none';
            // Clear button selections
            document.querySelectorAll('.time-option-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            // Set minimum date to today
            const today = formatAsmLocalDate(new Date());
            const dateInput = document.getElementById('cnpCustomDate');
            if (dateInput) {
                dateInput.min = today;
                dateInput.value = '';
            }
            const timeInput = document.getElementById('cnpCustomTime');
            if (timeInput) {
                timeInput.value = '';
            }
            const remarkInput = document.getElementById('outcomeDateTimeRemark');
            if (remarkInput) {
                remarkInput.value = '';
                remarkInput.placeholder = selectedTaskOutcome === 'follow_up'
                    ? 'Add follow-up context...'
                    : 'Add CNP context...';
            }
        }
    }
    
    // Select quick time option (15 min, 30 min, 1 hr, 2 hr)
    function selectCnpTime(minutes, event) {
        selectedCnpMinutes = minutes;
        selectedCnpCustomDateTime = null;
        isCustomTimeSelected = false;
        
        // Hide custom picker
        document.getElementById('customTimePickerContainer').style.display = 'none';
        
        // Clear custom inputs
        document.getElementById('cnpCustomDate').value = '';
        document.getElementById('cnpCustomTime').value = '';
        
        // Remove selected class from all buttons
        document.querySelectorAll('.time-option-btn').forEach(btn => {
            btn.classList.remove('selected');
        });
        
        // Add selected class to clicked button
        if (event && event.target) {
            event.target.classList.add('selected');
        }
        
        // Calculate and display selected time
        const now = new Date();
        const retryTime = new Date(now.getTime() + minutes * 60000);
        const formattedTime = retryTime.toLocaleString('en-IN', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        
        const quickPrefix = selectedTaskOutcome === 'follow_up' ? 'Follow up in' : 'Retry call in';
        document.getElementById('selectedTimeText').textContent = `${quickPrefix} ${minutes} minutes (${formattedTime})`;
        document.getElementById('selectedTimeDisplay').style.display = 'block';
    }
    
    // Show custom date-time picker
    function showCustomTimePicker() {
        isCustomTimeSelected = true;
        selectedCnpMinutes = null;
        
        // Remove selected class from quick option buttons
        document.querySelectorAll('.time-option-btn[data-minutes]').forEach(btn => {
            btn.classList.remove('selected');
        });
        document.getElementById('customTimeOptionBtn')?.classList.add('selected');
        
        // Show custom picker container
        const customContainer = document.getElementById('customTimePickerContainer');
        customContainer.style.display = 'block';
        
        // Hide selected time display initially
        document.getElementById('selectedTimeDisplay').style.display = 'none';
        
        // Set minimum date to today and default time to next hour
        const today = formatAsmLocalDate(new Date());
        const dateInput = document.getElementById('cnpCustomDate');
        const timeInput = document.getElementById('cnpCustomTime');
        
        if (dateInput) {
            dateInput.min = today;
            if (!dateInput.value) {
                dateInput.value = today;
            }
        }
        
        if (timeInput && !timeInput.value) {
            const nextHour = new Date();
            nextHour.setHours(nextHour.getHours() + 1);
            nextHour.setMinutes(0);
            const timeStr = nextHour.toTimeString().slice(0, 5); // HH:MM format
            timeInput.value = timeStr;
        }
        
        // Add change listeners to update selected time display
        if (dateInput && timeInput) {
            const updateCustomTimeDisplay = () => {
                const date = dateInput.value;
                const time = timeInput.value;
                if (date && time) {
                    const dateTime = new Date(`${date}T${time}`);
                    if (dateTime > new Date()) {
                        const formattedTime = dateTime.toLocaleString('en-IN', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });
                        const customPrefix = selectedTaskOutcome === 'follow_up' ? 'Follow up on' : 'Retry call on';
                        document.getElementById('selectedTimeText').textContent = `${customPrefix} ${formattedTime}`;
                        document.getElementById('selectedTimeDisplay').style.display = 'block';
                        selectedCnpCustomDateTime = formatDateForApi(dateTime);
                    } else {
                        document.getElementById('selectedTimeDisplay').style.display = 'none';
                    }
                } else {
                    document.getElementById('selectedTimeDisplay').style.display = 'none';
                }
            };
            
            // Keep a single active binding for native pickers.
            dateInput.onchange = updateCustomTimeDisplay;
            timeInput.onchange = updateCustomTimeDisplay;
            
            // Initial update
            updateCustomTimeDisplay();
        }
    }
    
    // Confirm CNP time selection and submit
    async function confirmCnpTimeSelection() {
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }
        
        let retryAt = null;
        let retryMinutes = null;
        
        // Validate selection
        if (isCustomTimeSelected) {
            const dateInput = document.getElementById('cnpCustomDate');
            const timeInput = document.getElementById('cnpCustomTime');
            const date = dateInput.value;
            const time = timeInput.value;
            
            if (!date || !time) {
                showAlert('Please select both date and time for custom option', 'warning');
                return;
            }
            
            const selectedDateTime = new Date(`${date}T${time}`);
            const now = new Date();
            
            if (selectedDateTime <= now) {
                showAlert('Please select a future date and time', 'warning');
                return;
            }
            
            retryAt = formatDateForApi(selectedDateTime);
        } else if (selectedCnpMinutes !== null) {
            retryMinutes = selectedCnpMinutes;
        } else {
            showAlert('Please select a retry time option', 'warning');
            return;
        }
        
        try {
            const requestBody = {};
            if (retryAt) {
                requestBody.retry_at = retryAt;
            } else if (retryMinutes !== null) {
                requestBody.retry_minutes = retryMinutes;
            }
            
            const result = await apiCall(`/tasks/${taskId}/cnp`, {
                method: 'POST',
                body: JSON.stringify(requestBody)
            });
            
            if (result && result.success) {
                const timeMsg = isCustomTimeSelected 
                    ? `New calling task created for selected time.`
                    : `New calling task created for ${retryMinutes} minutes later.`;
                showAlert(`Call Not Picked marked. ${timeMsg}`, 'success', 4000);
                closeCnpTimeSelectionModal();
                setCurrentTaskId(null); // Reset after successful submission
                // Refresh tasks list after a short delay (preserve current filters)
                setTimeout(() => {
                    const status = window.currentStatus || currentStatus || 'all';
                    const dateFilter = document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today';
                    const customDate = document.getElementById('customDatePicker')?.value || null;
                    loadTasks(status, dateFilter, customDate);
                }, 500);
            } else {
                showAlert(result?.message || result?.error || 'Failed to mark as CNP', 'error');
            }
        } catch (error) {
            console.error('Error marking as CNP:', error);
            showAlert('Error marking as CNP: ' + error.message, 'error');
        }
    }
    
    // Cancel CNP time selection
    function cancelCnpTimeSelection() {
        closeCnpTimeSelectionModal();
        // Don't reset currentTaskId here - user might want to try again
    }
    
    // Close CNP time selection modal
    function closeCnpTimeSelectionModal() {
        const modal = document.getElementById('cnpTimeSelectionModal');
        if (modal) {
            modal.classList.remove('active');
            // Reset selections
            selectedCnpMinutes = null;
            selectedCnpCustomDateTime = null;
            isCustomTimeSelected = false;
            document.getElementById('customTimePickerContainer').style.display = 'none';
            document.getElementById('selectedTimeDisplay').style.display = 'none';
            document.getElementById('cnpCustomDate').value = '';
            document.getElementById('cnpCustomTime').value = '';
            const remarkInput = document.getElementById('outcomeDateTimeRemark');
            if (remarkInput) {
                remarkInput.value = '';
            }
            document.querySelectorAll('.time-option-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
        }
    }

    function closeRejectReasonModal() {
        const modal = document.getElementById('rejectReasonModal');
        modal.classList.remove('active');
        document.getElementById('rejectReasonInput').value = '';
    }
    
    function cancelRejectReasonModal() {
        // User cancelled - reset task ID and close modal
        closeRejectReasonModal();
        setCurrentTaskId(null);
    }

    // Submit reject
    async function submitRejectProspect() {
        const rejectionReason = document.getElementById('rejectReasonInput').value.trim();
        
        if (!rejectionReason) {
            showAlert('Please enter a rejection reason', 'warning');
            return;
        }
        
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }
        
        try {
            const result = await apiCall(`/tasks/${taskId}/reject`, {
                method: 'POST',
                body: JSON.stringify({
                    rejection_reason: rejectionReason
                })
            });
            
            if (result && result.success) {
                // Remove task card immediately from DOM
                const taskCard = document.getElementById(`task-card-${taskId}`);
                if (taskCard) {
                    taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
                    taskCard.style.opacity = '0';
                    taskCard.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        taskCard.remove();
                    // Reload tasks after card removal to ensure consistency (preserve current filters)
                    const status = window.currentStatus || currentStatus || 'all';
                    const dateFilter = document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today';
                    const customDate = document.getElementById('customDatePicker')?.value || null;
                    loadTasks(status, dateFilter, customDate);
                    }, 300);
                } else {
                    // Fallback if card not found by ID
                    loadTasks();
                }
                
                showAlert('Prospect rejected successfully', 'success');
                closeRejectReasonModal();
                setCurrentTaskId(null); // Reset after successful submission
            } else {
                showAlert(result?.message || 'Failed to reject prospect', 'error');
            }
        } catch (error) {
            console.error('Error rejecting prospect:', error);
            showAlert('Error rejecting prospect: ' + error.message, 'error');
        }
    }

    // Open manager lead requirement form modal (Step 2 - after verify clicked)
    async function openManagerLeadRequirementFormModal(taskId) {
        setCurrentTaskId(taskId);
        const modal = document.getElementById('managerLeadRequirementFormModal');
        const container = document.getElementById('managerLeadFormContainer');
        
        modal.classList.add('active');
        syncModalBodyLock();
        container.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="display: inline-block;"></div><p style="margin-top: 15px; color: #666;">Loading form...</p></div>';
        
        try {
            const result = await apiCall(`/tasks/${taskId}/lead-requirement-form`);
            
            if (result && result.success) {
                const activeRenderer = typeof window.renderManagerLeadForm === 'function'
                    ? window.renderManagerLeadForm
                    : renderManagerLeadForm;
                activeRenderer(result);
            } else {
                showAlert('Failed to load form: ' + (result?.error || result?.message || 'Unknown error'), 'error');
                closeManagerLeadRequirementFormModal();
            }
        } catch (error) {
            console.error('Error loading form:', error);
            showAlert('Error loading form: ' + error.message, 'error');
            closeManagerLeadRequirementFormModal();
        }
    }

    function closeManagerLeadRequirementFormModal() {
        const modal = document.getElementById('managerLeadRequirementFormModal');
        modal.classList.remove('active');
        syncModalBodyLock();
        document.getElementById('managerLeadFormContainer').innerHTML = '';
        window.managerTaskOutcomeContext = null;
        // Reset currentTaskId when modal is closed (user cancelled)
        setCurrentTaskId(null);
    }
    
    function cancelManagerLeadRequirementForm() {
        // User clicked close/cancel button - reset task ID and close modal
        closeManagerLeadRequirementFormModal();
    }

    // Render manager lead requirement form (similar to telecaller but all fields visible)
    function renderManagerLeadForm(data) {
        const container = document.getElementById('managerLeadFormContainer');
        
        // Update modal title based on whether it's a prospect or direct lead
        const modalTitle = document.querySelector('#managerLeadRequirementFormModal .modal-header h3');
        if (modalTitle) {
            const hasProspect = data.has_prospect === true;
            modalTitle.textContent = 'Lead Form';
        }
        
        const formValues = data.form_values || {};
        
        // Get existing values for pre-population
        const existingCategory = formValues.category || '';
        const existingPreferredLocation = formValues.preferred_location || '';
        const existingType = formValues.type || '';
        const existingPurpose = formValues.purpose || '';
        const existingPossession = formValues.possession || '';
        const existingBudget = formValues.budget || '';
        
        let formHTML = `
            <form id="managerLeadRequirementForm" novalidate onsubmit="submitManagerLeadRequirementForm(event); return false;">
                <input type="hidden" name="task_id" value="${currentTaskId}">
                
                <div style="margin-bottom: 24px;">
                    <h3 style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0;">Basic Information</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Name <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="text" 
                                   name="name" 
                                   id="manager_form_name"
                                   value="${data.lead_name || ''}"
                                   required
                                   placeholder="Enter lead name"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Mobile Number <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="tel" 
                                   name="phone" 
                                   id="manager_form_phone"
                                   value="${data.lead_phone || ''}"
                                   required
                                   placeholder="Enter phone number"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <h3 style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0;">Lead Requirements</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <!-- Category Field -->
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Category <span style="color: #d32f2f;">*</span>
                            </label>
                            <select name="category" 
                                    id="manager_form_category" 
                                    required
                                    onchange="handleManagerCategoryChange(this.value)"
                                    style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">-- Select Category --</option>
                                <option value="Residential" ${existingCategory === 'Residential' ? 'selected' : ''}>Residential</option>
                                <option value="Commercial" ${existingCategory === 'Commercial' ? 'selected' : ''}>Commercial</option>
                                <option value="Both" ${existingCategory === 'Both' ? 'selected' : ''}>Both</option>
                                <option value="N.A" ${existingCategory === 'N.A' ? 'selected' : ''}>N.A</option>
                            </select>
                        </div>
                        
                        <!-- Preferred Location Field -->
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Preferred Location <span style="color: #d32f2f;">*</span>
                            </label>
                            <select name="preferred_location" 
                                    id="manager_form_preferred_location" 
                                    required
                                    style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">-- Select Preferred Location --</option>
                                <option value="Inside City" ${existingPreferredLocation === 'Inside City' ? 'selected' : ''}>Inside City</option>
                                <option value="Sitapur Road" ${existingPreferredLocation === 'Sitapur Road' ? 'selected' : ''}>Sitapur Road</option>
                                <option value="Hardoi Road" ${existingPreferredLocation === 'Hardoi Road' ? 'selected' : ''}>Hardoi Road</option>
                                <option value="Faizabad Road" ${existingPreferredLocation === 'Faizabad Road' ? 'selected' : ''}>Faizabad Road</option>
                                <option value="Sultanpur Road" ${existingPreferredLocation === 'Sultanpur Road' ? 'selected' : ''}>Sultanpur Road</option>
                                <option value="Shaheed Path" ${existingPreferredLocation === 'Shaheed Path' ? 'selected' : ''}>Shaheed Path</option>
                                <option value="Raebareily Road" ${existingPreferredLocation === 'Raebareily Road' ? 'selected' : ''}>Raebareily Road</option>
                                <option value="Kanpur Road" ${existingPreferredLocation === 'Kanpur Road' ? 'selected' : ''}>Kanpur Road</option>
                                <option value="Outer Ring Road" ${existingPreferredLocation === 'Outer Ring Road' ? 'selected' : ''}>Outer Ring Road</option>
                                <option value="Bijnor Road" ${existingPreferredLocation === 'Bijnor Road' ? 'selected' : ''}>Bijnor Road</option>
                                <option value="Deva Road" ${existingPreferredLocation === 'Deva Road' ? 'selected' : ''}>Deva Road</option>
                                <option value="Sushant Golf City" ${existingPreferredLocation === 'Sushant Golf City' ? 'selected' : ''}>Sushant Golf City</option>
                                <option value="Vrindavan Yojana" ${existingPreferredLocation === 'Vrindavan Yojana' ? 'selected' : ''}>Vrindavan Yojana</option>
                                <option value="N.A" ${existingPreferredLocation === 'N.A' ? 'selected' : ''}>N.A</option>
                            </select>
                        </div>
                        
                        <!-- Type Field (dependent on Category) -->
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Type <span style="color: #d32f2f;">*</span>
                            </label>
                            <select name="type" 
                                    id="manager_form_type" 
                                    required
                                    ${!existingCategory ? 'disabled' : ''}
                                    style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; ${!existingCategory ? 'background-color: #f5f5f5;' : ''}">
                                <option value="">-- Select Type (select category first) --</option>
                            </select>
                        </div>
                        
                        <!-- Purpose Field -->
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Purpose <span style="color: #d32f2f;">*</span>
                            </label>
                            <select name="purpose" 
                                    id="manager_form_purpose" 
                                    required
                                    style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">-- Select Purpose --</option>
                                <option value="End Use" ${existingPurpose === 'End Use' ? 'selected' : ''}>End Use</option>
                                <option value="Short Term Investment" ${existingPurpose === 'Short Term Investment' ? 'selected' : ''}>Short Term Investment</option>
                                <option value="Long Term Investment" ${existingPurpose === 'Long Term Investment' ? 'selected' : ''}>Long Term Investment</option>
                                <option value="Rental Income" ${existingPurpose === 'Rental Income' ? 'selected' : ''}>Rental Income</option>
                                <option value="Investment + End Use" ${existingPurpose === 'Investment + End Use' ? 'selected' : ''}>Investment + End Use</option>
                                <option value="N.A" ${existingPurpose === 'N.A' ? 'selected' : ''}>N.A</option>
                            </select>
                        </div>
                        
                        <!-- Possession Field -->
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Possession <span style="color: #d32f2f;">*</span>
                            </label>
                            <select name="possession" 
                                    id="manager_form_possession" 
                                    required
                                    style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">-- Select Possession --</option>
                                <option value="Under Construction" ${existingPossession === 'Under Construction' ? 'selected' : ''}>Under Construction</option>
                                <option value="Ready To Move" ${existingPossession === 'Ready To Move' ? 'selected' : ''}>Ready To Move</option>
                                <option value="Pre Launch" ${existingPossession === 'Pre Launch' ? 'selected' : ''}>Pre Launch</option>
                                <option value="Both" ${existingPossession === 'Both' ? 'selected' : ''}>Both</option>
                                <option value="N.A" ${existingPossession === 'N.A' ? 'selected' : ''}>N.A</option>
                            </select>
                        </div>
                        
                        <!-- Budget Field -->
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Budget <span style="color: #d32f2f;">*</span>
                            </label>
                            <select name="budget" 
                                    id="manager_form_budget" 
                                    required
                                    style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">-- Select Budget --</option>
                                <option value="Below 50 Lacs" ${existingBudget === 'Below 50 Lacs' ? 'selected' : ''}>Below 50 Lacs</option>
                                <option value="50-75 Lacs" ${existingBudget === '50-75 Lacs' ? 'selected' : ''}>50-75 Lacs</option>
                                <option value="75 Lacs-1 Cr" ${existingBudget === '75 Lacs-1 Cr' ? 'selected' : ''}>75 Lacs-1 Cr</option>
                                <option value="Above 1 Cr" ${existingBudget === 'Above 1 Cr' ? 'selected' : ''}>Above 1 Cr</option>
                                <option value="Above 2 Cr" ${existingBudget === 'Above 2 Cr' ? 'selected' : ''}>Above 2 Cr</option>
                                <option value="N.A" ${existingBudget === 'N.A' ? 'selected' : ''}>N.A</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Follow Up Required Section -->
                <div style="margin-bottom: 24px;">
                    <div style="padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <div style="display: flex; align-items: center; margin-bottom: 12px;">
                            <input type="checkbox" 
                                   name="follow_up_required" 
                                   id="manager_form_follow_up_required"
                                   style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                            <label for="manager_form_follow_up_required" style="font-size: 14px; font-weight: 500; color: #333; cursor: pointer; margin: 0;">
                                <strong>Follow Up Required</strong>
                            </label>
                        </div>
                        <small style="display: block; color: #666; font-size: 12px; margin-left: 28px;">Check this if you need to schedule a follow-up call for this lead</small>
                        
                        <!-- Follow Up Date & Time Picker (shown conditionally when Follow Up Required is checked) -->
                        <div id="followUpDateContainer" style="display: none; margin-top: 16px;">
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                <strong>Follow Up Date & Time</strong> <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="datetime-local" 
                                   name="follow_up_date" 
                                   id="manager_form_follow_up_date"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <small style="display: block; margin-top: 4px; color: #666; font-size: 12px;">Select date and time for the follow-up call. A calling task will be created automatically.</small>
                            
                            <!-- Create Sales Executive Task Option (shown when Follow Up Required is checked) -->
                            <div id="createTelecallerTaskContainer" style="display: none; margin-top: 12px;">
                                <div style="display: flex; align-items: center;">
                                    <input type="checkbox" 
                                           name="create_telecaller_task" 
                                           id="create_telecaller_task_checkbox"
                                           style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">
                                    <label for="create_telecaller_task_checkbox" style="font-size: 14px; font-weight: 500; color: #333; cursor: pointer; margin: 0;">
                                        Create calling task for Sales Executive also
                                    </label>
                                </div>
                                <small style="display: block; color: #666; font-size: 12px; margin-left: 28px; margin-top: 4px;">
                                    This will create a calling task for the original Sales Executive who provided this lead
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <h3 style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0;">Verification Details</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                <strong>Lead Status</strong> <span style="color: #d32f2f;">*</span>
                            </label>
                            <select name="lead_status" id="manager_form_lead_status" required style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">-- Select Lead Status --</option>
                                <option value="hot">Hot</option>
                                <option value="warm">Warm</option>
                                <option value="cold">Cold</option>
                                <option value="junk">Junk</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                <strong>Lead Quality</strong> <span style="color: #d32f2f;">*</span>
                            </label>
                            <select name="lead_quality" id="manager_form_lead_quality" required style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">-- Select Lead Quality --</option>
                                <option value="1">1 - Bad</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5 - Best Lead</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                <strong>Interested Projects</strong> <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="text"
                                   id="manager_project_input"
                                   placeholder="Type project name and press Enter"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; margin-bottom: 12px;">
                            <div id="project-tags-container" class="project-tags-wrapper">
                                <div class="project-tags-grid" id="project-tags-grid">
                                    <!-- Project tags will be loaded dynamically -->
                                </div>
                            </div>
                            <input type="hidden" name="interested_projects" id="manager_form_interested_projects_hidden">
                        </div>
                    </div>
                    
                    <!-- Customer Profiling Section -->
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
                        <h3 style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0;">Customer Profiling <span style="color: #666; font-weight: 400; font-size: 14px;">(Optional)</span></h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                    Customer Job
                                </label>
                                <input type="text" 
                                       name="customer_job" 
                                       id="manager_form_customer_job"
                                       value="${formValues.customer_job || ''}"
                                       placeholder="Enter customer job / occupation"
                                       style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                            
                            <div>
                                <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                    Industry / Sector
                                </label>
                                <select name="industry_sector" 
                                        id="manager_form_industry_sector"
                                        style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                    <option value="">-- Select Industry / Sector --</option>
                                    <option value="IT" ${formValues.industry_sector === 'IT' ? 'selected' : ''}>IT</option>
                                    <option value="Education" ${formValues.industry_sector === 'Education' ? 'selected' : ''}>Education</option>
                                    <option value="Healthcare" ${formValues.industry_sector === 'Healthcare' ? 'selected' : ''}>Healthcare</option>
                                    <option value="Business" ${formValues.industry_sector === 'Business' ? 'selected' : ''}>Business</option>
                                    <option value="FMCG" ${formValues.industry_sector === 'FMCG' ? 'selected' : ''}>FMCG</option>
                                    <option value="Government" ${formValues.industry_sector === 'Government' ? 'selected' : ''}>Government</option>
                                    <option value="Other" ${formValues.industry_sector === 'Other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                    Buying Frequency
                                </label>
                                <select name="buying_frequency" 
                                        id="manager_form_buying_frequency"
                                        style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                    <option value="">-- Select Buying Frequency --</option>
                                    <option value="Regular" ${formValues.buying_frequency === 'Regular' ? 'selected' : ''}>Regular</option>
                                    <option value="Occasional" ${formValues.buying_frequency === 'Occasional' ? 'selected' : ''}>Occasional</option>
                                    <option value="First-time" ${formValues.buying_frequency === 'First-time' ? 'selected' : ''}>First-time</option>
                                </select>
                            </div>
                            
                            <div>
                                <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                    Living City
                                </label>
                                <input type="text" 
                                       name="living_city" 
                                       id="manager_form_living_city"
                                       value="${formValues.living_city || ''}"
                                       placeholder="Enter living city"
                                       style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                            
                            <div>
                                <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                    City Type
                                </label>
                                <select name="city_type" 
                                        id="manager_form_city_type"
                                        style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                    <option value="">-- Select City Type --</option>
                                    <option value="Metro" ${formValues.city_type === 'Metro' ? 'selected' : ''}>Metro</option>
                                    <option value="Tier 1" ${formValues.city_type === 'Tier 1' ? 'selected' : ''}>Tier 1</option>
                                    <option value="Tier 2" ${formValues.city_type === 'Tier 2' ? 'selected' : ''}>Tier 2</option>
                                    <option value="Tier 3" ${formValues.city_type === 'Tier 3' ? 'selected' : ''}>Tier 3</option>
                                    <option value="Local Resident" ${formValues.city_type === 'Local Resident' ? 'selected' : ''}>Local Resident</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 16px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                            <strong>Remark</strong>
                        </label>
                        <textarea name="manager_remark" id="manager_form_manager_remark" rows="3" placeholder="Enter remarks or notes..." style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"></textarea>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 20px; border-top: 1px solid #e0e0e0; margin-top: 24px;">
                    <button type="button" 
                            onclick="cancelManagerLeadRequirementForm()" 
                            style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 6px; background: white; color: #333; cursor: pointer; font-size: 14px; font-weight: 500;">
                        Cancel
                    </button>
                    <button type="submit" 
                            style="padding: 10px 20px; background: #205A44; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>Verify Prospect
                    </button>
                </div>
            </form>
        `;
        
        try {
            container.innerHTML = formHTML;
            
            // Load interested projects
            loadInterestedProjectsForManager();
            
            // Add Enter key handler for custom project input
            const projectInput = document.getElementById('manager_project_input');
            if (projectInput) {
                projectInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const value = this.value.trim();
                        if (value) {
                            addManagerProjectTag(value);
                            this.value = ''; // Clear input after adding
                        }
                    }
                });
            }
            
            // Initialize dependent fields (category -> type)
            const categorySelect = document.getElementById('manager_form_category');
            const typeSelect = document.getElementById('manager_form_type');
            
            if (categorySelect && typeSelect) {
                // Initialize Type field based on existing category
                if (categorySelect.value) {
                    updateManagerTypeOptions(categorySelect.value, typeSelect, existingType);
                }
            }
        } catch (error) {
            console.error('Error inserting form HTML:', error);
            container.innerHTML = `
                <div style="padding: 20px; background: #fee; border: 1px solid #fcc; border-radius: 8px; color: #c33;">
                    <h4 style="margin: 0 0 10px 0;">Error Loading Form</h4>
                    <p style="margin: 0;">${error.message}</p>
                </div>
            `;
        }
        
        // Initialize Follow Up Required checkbox handler
        const followUpRequiredCheckbox = document.getElementById('manager_form_follow_up_required');
        if (followUpRequiredCheckbox) {
            followUpRequiredCheckbox.addEventListener('change', function() {
                handleFollowUpRequiredChange(this.checked);
            });
            // Initialize on page load if checkbox is already checked
            if (followUpRequiredCheckbox.checked) {
                handleFollowUpRequiredChange(true);
            }
        }
    }
    
    // Handle Follow Up Required checkbox change - show/hide follow-up date picker
    function handleFollowUpRequiredChange(isRequired) {
        const followUpContainer = document.getElementById('followUpDateContainer');
        const followUpDateInput = document.getElementById('manager_form_follow_up_date');
        const telecallerTaskContainer = document.getElementById('createTelecallerTaskContainer');
        
        if (isRequired) {
            // Show follow-up date picker
            if (followUpContainer) {
                followUpContainer.style.display = 'block';
                followUpContainer.style.visibility = 'visible';
            }
            if (followUpDateInput) {
                followUpDateInput.style.display = 'block';
                followUpDateInput.style.visibility = 'visible';
                followUpDateInput.removeAttribute('disabled');
                followUpDateInput.removeAttribute('readonly');
                // Don't set required attribute - we'll validate in JavaScript
                followUpDateInput.removeAttribute('required');
                followUpDateInput.required = false;
            }
            // Show telecaller task checkbox
            if (telecallerTaskContainer) {
                telecallerTaskContainer.style.display = 'block';
            }
        } else {
            // Hide follow-up date picker
            if (followUpContainer) {
                followUpContainer.style.display = 'none';
            }
            if (followUpDateInput) {
                // Always remove required attribute when hiding to prevent validation error
                followUpDateInput.removeAttribute('required');
                followUpDateInput.required = false;
                followUpDateInput.value = '';
            }
            // Hide telecaller task checkbox
            if (telecallerTaskContainer) {
                telecallerTaskContainer.style.display = 'none';
            }
            // Uncheck telecaller task checkbox when hiding
            const telecallerTaskCheckbox = document.getElementById('create_telecaller_task_checkbox');
            if (telecallerTaskCheckbox) {
                telecallerTaskCheckbox.checked = false;
            }
        }
    }

    // Handle form field changes for manager form
    function handleManagerFormFieldChange(fieldKey, value, dependentField = null) {
        if (fieldKey === 'category' && dependentField === 'type') {
            const typeSelect = document.getElementById('manager_form_type');
            if (typeSelect) {
                updateManagerTypeOptions(value, typeSelect);
            }
        }
    }

    // Handle category change for manager form
    window.handleManagerCategoryChange = function(category) {
        const typeSelect = document.getElementById('manager_form_type');
        if (typeSelect) {
            updateManagerTypeOptions(category, typeSelect);
        }
    };

    function getUniqueManagerTypeOptions(options) {
        const seen = new Set();
        return (Array.isArray(options) ? options : []).filter(option => {
            const normalized = String(option || '').trim().toLowerCase();
            if (!normalized || seen.has(normalized)) {
                return false;
            }
            seen.add(normalized);
            return true;
        });
    }

    function dedupeManagerSelectDomOptions(selectEl) {
        if (!selectEl) return;
        const currentValue = selectEl.value || '';
        const seen = new Set();
        Array.from(selectEl.options).forEach(option => {
            const normalized = String(option.value || option.textContent || '').trim().toLowerCase();
            if (!normalized) {
                return;
            }
            if (seen.has(normalized)) {
                option.remove();
                return;
            }
            seen.add(normalized);
        });
        if (currentValue) {
            selectEl.value = currentValue;
        }
    }
    
    // Update type options based on category
    function updateManagerTypeOptions(category, typeSelect, existingValue = null) {
        const typeOptions = {
            'Residential': ['Plots & Villas', 'Apartments', 'Studio', 'Farmhouse', 'N.A'],
            'Commercial': ['Retail Shops', 'Office Space', 'Studio', 'N.A'],
            'Both': ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'],
            'N.A': ['N.A']
        };
        
        const currentValue = existingValue || typeSelect.value;
        const options = getUniqueManagerTypeOptions(typeOptions[category] || typeOptions['Both']);
        
        // Enable/disable Type field based on category selection
        if (category && category !== '') {
            typeSelect.disabled = false;
            typeSelect.style.backgroundColor = '';
        } else {
            typeSelect.disabled = true;
            typeSelect.style.backgroundColor = '#f5f5f5';
        }
        
        typeSelect.innerHTML = '<option value="">-- Select Type (select category first) --</option>';
        options.forEach(option => {
            const selected = option === currentValue ? 'selected' : '';
            typeSelect.innerHTML += `<option value="${option}" ${selected}>${option}</option>`;
        });
        dedupeManagerSelectDomOptions(typeSelect);
        setTimeout(() => dedupeManagerSelectDomOptions(typeSelect), 0);
        
        // If current value is not in the new options, clear it
        if (currentValue && !options.includes(currentValue)) {
            typeSelect.value = '';
        }
    }

    // Load interested projects for manager form (render as tags)
    async function loadInterestedProjectsForManager() {
        try {
            const projectTagsGrid = document.getElementById('project-tags-grid');

            if (projectTagsGrid) {
                projectTagsGrid.innerHTML = '';
            }
        } catch (error) {
            console.error('Error loading interested projects:', error);
        }
    }

    // Toggle project tag selection
    function toggleProjectTag(tagElement) {
        tagElement.classList.toggle('selected');
        updateSelectedProjects();
    }

    // Add a custom project tag for manager form
    function addManagerProjectTag(projectName) {
        const projectTagsGrid = document.getElementById('project-tags-grid');
        if (!projectTagsGrid || !projectName || !projectName.trim()) {
            return;
        }
        
        const trimmedName = projectName.trim();
        
        // Check if tag already exists (case-insensitive)
        const existingTags = projectTagsGrid.querySelectorAll('.project-tag');
        for (let tag of existingTags) {
            const tagText = tag.querySelector('.project-tag-text')?.textContent?.trim();
            if (tagText && tagText.toLowerCase() === trimmedName.toLowerCase()) {
                // Tag already exists, just select it
                tag.classList.add('selected');
                updateSelectedProjects();
                return;
            }
        }
        
        // Create new tag element
        const tag = document.createElement('div');
        tag.className = 'project-tag selected'; // Auto-select custom projects
        tag.dataset.projectName = trimmedName; // Use projectName instead of projectId for custom projects
        tag.dataset.isCustom = 'true'; // Flag to identify custom projects
        tag.innerHTML = `
            <span class="project-tag-text">${escapeHtml(trimmedName)}</span>
            <i class="fas fa-check project-tag-check"></i>
        `;
        tag.addEventListener('click', function() {
            toggleProjectTag(this);
        });
        
        projectTagsGrid.appendChild(tag);
        updateSelectedProjects();
    }

    // Update hidden input with selected project IDs
    function updateSelectedProjects() {
        const selectedTags = document.querySelectorAll('#project-tags-grid .project-tag.selected');
        const selectedProjects = Array.from(selectedTags).map(tag => {
            // Check if it's a custom project (has projectName) or database project (has projectId)
            if (tag.dataset.isCustom === 'true' && tag.dataset.projectName) {
                return { name: tag.dataset.projectName, is_custom: true };
            } else if (tag.dataset.projectId) {
                return parseInt(tag.dataset.projectId);
            }
            return null;
        }).filter(p => p !== null);
        
        const hiddenInput = document.getElementById('manager_form_interested_projects_hidden');
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(selectedProjects);
        }
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Submit manager lead requirement form (verify)
    async function submitManagerLeadRequirementForm(event) {
        if (typeof window.submitUnifiedManagerLeadRequirementForm === 'function'
            && window.submitUnifiedManagerLeadRequirementForm !== submitManagerLeadRequirementForm) {
            return window.submitUnifiedManagerLeadRequirementForm(event);
        }

        event.preventDefault();
        
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }
        
        // Get follow-up required checkbox state
        const followUpRequiredCheckbox = document.getElementById('manager_form_follow_up_required');
        const isFollowUpRequired = followUpRequiredCheckbox ? followUpRequiredCheckbox.checked : false;
        
        // Handle required attribute for follow-up date before form validation
        // This prevents HTML5 validation error when field is hidden but required
        const followUpDateInput = document.getElementById('manager_form_follow_up_date');
        const followUpContainer = document.getElementById('followUpDateContainer');
        
        // Always ensure if container is hidden, required is removed (safety check)
        if (followUpContainer && followUpContainer.style.display === 'none') {
            if (followUpDateInput) {
                followUpDateInput.removeAttribute('required');
                followUpDateInput.required = false;
            }
        }
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Convert FormData to object
        const data = {};
        
        formData.forEach((value, key) => {
            // Skip interested_projects as we'll get it from selected tags
            if (key !== 'interested_projects') {
                data[key] = value;
            }
        });
        
        // Add follow_up_required as boolean
        data['follow_up_required'] = isFollowUpRequired ? '1' : '0';
        
        // Validate Lead Quality
        if (!data['lead_quality'] || data['lead_quality'] === '') {
            showAlert('Please select Lead Quality', 'warning');
            const leadQualitySelect = document.getElementById('manager_form_lead_quality');
            if (leadQualitySelect) {
                leadQualitySelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => {
                    leadQualitySelect.focus();
                }, 100);
            }
            return;
        }
        
        // Get selected interested projects from tags (both IDs and custom names)
        const selectedTags = document.querySelectorAll('#project-tags-grid .project-tag.selected');
        data['interested_projects'] = Array.from(selectedTags).map(tag => {
            if (tag.dataset.isCustom === 'true' && tag.dataset.projectName) {
                return { name: tag.dataset.projectName, is_custom: true };
            } else if (tag.dataset.projectId) {
                return parseInt(tag.dataset.projectId);
            }
            return null;
        }).filter(p => p !== null);
        
        // Ensure interested_projects is an array
        if (!data['interested_projects'] || data['interested_projects'].length === 0) {
            showAlert('Please select at least one Interested Project', 'warning');
            return;
        }
        
        // Validate follow-up date & time if Follow Up Required is checked
        if (isFollowUpRequired) {
            if (!data['follow_up_date'] || data['follow_up_date'] === '') {
                showAlert('Please select a Follow Up Date & Time', 'warning');
                // Re-show the container and make input visible if validation fails
                if (followUpContainer) {
                    followUpContainer.style.display = 'block';
                }
                if (followUpDateInput) {
                    // Show the field but don't set required (we validate in JS)
                    followUpDateInput.style.display = 'block';
                    followUpDateInput.style.visibility = 'visible';
                    followUpDateInput.removeAttribute('required');
                    followUpDateInput.required = false;
                    // Scroll to the field
                    followUpDateInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => {
                        followUpDateInput.focus();
                    }, 100);
                }
                return;
            }
            
            // Validate that datetime is not in the past
            const selectedDateTime = new Date(data['follow_up_date']);
            const now = new Date();
            
            if (selectedDateTime <= now) {
                showAlert('Follow Up Date & Time cannot be in the past. Please select a future date and time.', 'warning');
                if (followUpDateInput) {
                    followUpDateInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => {
                        followUpDateInput.focus();
                    }, 100);
                }
                return;
            }
        } else {
            // Clear follow-up date if Follow Up Required is not checked (to avoid sending it to backend)
            data['follow_up_date'] = '';
            // Ensure required is removed and field is hidden when not Follow Up Required
            if (followUpDateInput) {
                followUpDateInput.removeAttribute('required');
                followUpDateInput.required = false;
            }
            if (followUpContainer) {
                followUpContainer.style.display = 'none';
            }
        }
        
        try {
            const response = await apiCall(`/tasks/${taskId}/verify`, {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            if (response && response.success) {
                // Remove task card immediately from DOM
                const taskCard = document.getElementById(`task-card-${taskId}`);
                if (taskCard) {
                    taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
                    taskCard.style.opacity = '0';
                    taskCard.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        taskCard.remove();
                    // Reload tasks after card removal to ensure consistency (preserve current filters)
                    const status = window.currentStatus || currentStatus || 'all';
                    const dateFilter = document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today';
                    const customDate = document.getElementById('customDatePicker')?.value || null;
                    loadTasks(status, dateFilter, customDate);
                    }, 300);
                } else {
                    // Fallback if card not found by ID
                    setTimeout(() => {
                        loadTasks();
                    }, 500);
                }
                
                const message = response.message || (isFollowUpRequired 
                    ? 'Follow-up task created successfully! Prospect will be called on the selected date and time.' 
                    : 'Prospect verified successfully!');
                showAlert(message, 'success', 3000);
                closeManagerLeadRequirementFormModal();
                setCurrentTaskId(null); // Reset after successful submission
            } else {
                showAlert(response?.message || response?.error || 'Failed to process request', 'error');
            }
        } catch (error) {
            console.error('Error verifying prospect:', error);
            showAlert('Error verifying prospect: ' + error.message, 'error');
        }
    }

    // Remove all overdue tasks
    async function removeAllOverdueTasks() {
        if (!confirm('Are you sure you want to remove all overdue tasks? This action cannot be undone.')) {
            return;
        }

        const removeBtn = document.getElementById('removeAllOverdueBtn');
        if (removeBtn) {
            removeBtn.disabled = true;
            removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';
        }

        try {
            const result = await apiCall('/sales-manager/tasks/remove-all-overdue', {
                method: 'POST',
                body: JSON.stringify({})
            });

            if (result && result.success) {
                const count = result.count || 0;
                showAlert(`Successfully removed ${count} overdue task(s)`, 'success');
                
                // Reload tasks after a short delay (preserve current filters)
                setTimeout(() => {
                    const status = window.currentStatus || currentStatus || 'all';
                    const dateFilter = document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today';
                    const customDate = document.getElementById('customDatePicker')?.value || null;
                    loadTasks(status, dateFilter, customDate);
                }, 500);
            } else {
                showAlert(result?.message || result?.error || 'Failed to remove overdue tasks', 'error');
            }
        } catch (error) {
            console.error('Error removing overdue tasks:', error);
            showAlert('Error removing overdue tasks: ' + error.message, 'error');
        } finally {
            if (removeBtn) {
                removeBtn.disabled = false;
                removeBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Remove All Overdue';
            }
        }
    }
    
    // Attach to window for global access
    window.removeAllOverdueTasks = removeAllOverdueTasks;

    // View prospect details (read-only)
    async function openProspectDetailModal(taskId, viewMode = false) {
        setCurrentTaskId(taskId);
        const modal = document.getElementById('prospectDetailModal');
        const content = document.getElementById('prospectDetailContent');
        
        modal.classList.add('active');
        content.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="display: inline-block;"></div><p style="margin-top: 15px; color: #666;">Loading...</p></div>';
        
        try {
            const result = await apiCall(`/tasks/${taskId}`);
            
            if (result && result.success && result.data) {
                const task = result.data;
                const lead = task.lead || {};
                const prospect = task.prospect || {};
                const formFields = lead.form_fields || {};
                const isProspect = prospect && prospect.id;
                const isFromGoogleSheets = lead.source === 'google_sheets' || lead.source === 'sheet';
                
                let detailsHTML = '';
                
                // Header with name and phone
                detailsHTML += `
                    <div style="background: linear-gradient(135deg, #205A44 0%, #063A1C 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 8px 0; font-size: 20px;">${lead.name || 'Lead'}</h3>
                        <p style="margin: 0; font-size: 16px; opacity: 0.9;">${lead.phone || '-'}</p>
                    </div>
                `;
                
                // Prospect Details Section
                if (isProspect) {
                    detailsHTML += `
                        <div style="margin-bottom: 20px; padding: 16px; background: #f9fafb; border-radius: 8px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600; color: #063A1C;">Prospect Details</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div><strong>Customer Name:</strong> ${prospect.customer_name || '-'}</div>
                                <div><strong>Phone:</strong> ${prospect.phone || '-'}</div>
                                <div><strong>Budget:</strong> ${prospect.budget || '-'}</div>
                                <div><strong>Preferred Location:</strong> ${prospect.preferred_location || '-'}</div>
                                <div><strong>Size:</strong> ${prospect.size || '-'}</div>
                                <div><strong>Purpose:</strong> ${prospect.purpose || '-'}</div>
                                <div><strong>Possession:</strong> ${prospect.possession || '-'}</div>
                                <div><strong>Verification Status:</strong> ${prospect.verification_status || '-'}</div>
                                ${prospect.remark ? `<div style="grid-column: 1 / -1;"><strong>Remark:</strong> ${prospect.remark}</div>` : ''}
                                ${prospect.manager_remark ? `<div style="grid-column: 1 / -1;"><strong>Manager Remark:</strong> ${prospect.manager_remark}</div>` : ''}
                            </div>
                        </div>
                    `;
                }
                
                // Note: Google Sheets Details section removed as per user request
                
                // Form Data Section (if available)
                if (formFields && Object.keys(formFields).length > 0) {
                    detailsHTML += `
                        <div style="margin-bottom: 20px; padding: 16px; background: #f9fafb; border-radius: 8px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600; color: #063A1C;">Form Data</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                ${Object.entries(formFields).map(([key, value]) => `
                                    <div>
                                        <strong>${key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}:</strong> ${value || '-'}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }
                
                // Basic Lead Details (if not prospect and not from Google Sheets)
                if (!isProspect && !isFromGoogleSheets) {
                    detailsHTML += `
                        <div style="margin-bottom: 20px; padding: 16px; background: #f9fafb; border-radius: 8px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600; color: #063A1C;">Lead Details</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                <div><strong>Email:</strong> ${lead.email || '-'}</div>
                                <div><strong>Status:</strong> ${lead.status || '-'}</div>
                            </div>
                        </div>
                    `;
                }
                
                content.innerHTML = `<div>${detailsHTML}</div>`;
            } else {
                showAlert('Failed to load task details', 'error');
                closeProspectDetailModal();
            }
        } catch (error) {
            console.error('Error loading task details:', error);
            showAlert('Error loading task details', 'error');
            closeProspectDetailModal();
        }
    }

    function closeProspectDetailModal() {
        const modal = document.getElementById('prospectDetailModal');
        modal.classList.remove('active');
        document.getElementById('prospectDetailContent').innerHTML = '';
        setCurrentTaskId(null);
    }

    function formatTaskDetailTime(totalSeconds) {
        const seconds = Math.max(0, Math.floor(Number(totalSeconds) || 0));
        const minutes = Math.floor(seconds / 60);
        const remainder = seconds % 60;
        return `${minutes}:${String(remainder).padStart(2, '0')}`;
    }

    function humanizeTaskDetailKey(key) {
        return String(key || '')
            .replace(/[_\-.]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/\b\w/g, char => char.toUpperCase());
    }

    function normalizeTaskDetailPrimitive(value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return 'Not provided';
        }

        if (typeof value === 'boolean') {
            return value ? 'Yes' : 'No';
        }

        return String(value);
    }

    function renderTaskDetailValue(value) {
        if (Array.isArray(value)) {
            const meaningfulValues = value.filter(item => item !== null && typeof item !== 'undefined' && item !== '');
            if (!meaningfulValues.length) {
                return '<div class="task-detail-qa-value is-empty">Not provided</div>';
            }

            const primitiveArray = meaningfulValues.every(item => item === null || ['string', 'number', 'boolean'].includes(typeof item));
            if (primitiveArray) {
                return `<div class="task-detail-qa-value">${escapeHtml(meaningfulValues.map(normalizeTaskDetailPrimitive).join(', '))}</div>`;
            }

            return `
                <div class="task-detail-qa-group">
                    ${meaningfulValues.map((item, index) => `
                        <div class="task-detail-qa-item">
                            <div class="task-detail-qa-label">Entry ${index + 1}</div>
                            ${renderTaskDetailValue(item)}
                        </div>
                    `).join('')}
                </div>
            `;
        }

        if (value && typeof value === 'object') {
            const entries = Object.entries(value).filter(([, itemValue]) => itemValue !== null && typeof itemValue !== 'undefined' && itemValue !== '');
            if (!entries.length) {
                return '<div class="task-detail-qa-value is-empty">Not provided</div>';
            }

            return `
                <div class="task-detail-qa-group">
                    ${entries.map(([entryKey, entryValue]) => `
                        <div class="task-detail-qa-inline">
                            <div class="task-detail-qa-inline-label">${escapeHtml(humanizeTaskDetailKey(entryKey))}</div>
                            <div class="task-detail-qa-inline-value${(entryValue === null || typeof entryValue === 'undefined' || entryValue === '') ? ' is-empty' : ''}">
                                ${renderTaskDetailValue(entryValue)}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        const normalized = normalizeTaskDetailPrimitive(value);
        const isEmpty = normalized === 'Not provided';
        return `<div class="task-detail-qa-value${isEmpty ? ' is-empty' : ''}">${escapeHtml(normalized)}</div>`;
    }

    function renderTaskDetailPayloadSection(title, payload, options = {}) {
        const hasData = payload && ((Array.isArray(payload) && payload.length > 0) || (!Array.isArray(payload) && Object.keys(payload).length > 0));
        if (!hasData) {
            return '';
        }

        const rawPayloadId = options.rawToggle ? `task-detail-raw-${Math.random().toString(36).slice(2, 10)}` : null;

        return `
            <div class="task-detail-card">
                <h4>${escapeHtml(title)}</h4>
                <div class="task-detail-qa-list">
                    <div class="task-detail-qa-item">
                        ${renderTaskDetailValue(payload)}
                    </div>
                </div>
                ${options.rawToggle ? `
                    <button type="button" class="task-detail-raw-toggle" onclick="toggleTaskDetailRawPayload('${rawPayloadId}')">
                        View raw payload
                    </button>
                    <div id="${rawPayloadId}" class="task-detail-payload">${escapeHtml(JSON.stringify(payload, null, 2))}</div>
                ` : ''}
            </div>
        `;
    }

    function toggleTaskDetailRawPayload(payloadId) {
        const payloadElement = document.getElementById(payloadId);
        if (!payloadElement) {
            return;
        }

        payloadElement.classList.toggle('active');
    }

    function initializeTaskDetailRecordingPlayer(container) {
        const player = container?.querySelector('.task-detail-player');
        if (!player || player.dataset.ready === '1') {
            return;
        }

        const audio = player.querySelector('.task-detail-audio');
        const playBtn = player.querySelector('.task-detail-player-btn');
        const playIcon = playBtn?.querySelector('i');
        const currentTimeEl = player.querySelector('.task-detail-current');
        const totalTimeEl = player.querySelector('.task-detail-total');
        const progress = player.querySelector('.task-detail-progress');
        const rateButtons = player.querySelectorAll('.task-detail-speed-btn');
        const expectedDuration = Number(player.dataset.expectedDuration || 0);

        if (!audio || !playBtn || !currentTimeEl || !totalTimeEl || !progress) {
            return;
        }

        const getDisplayDuration = () => {
            const mediaDuration = Number.isFinite(audio.duration) ? audio.duration : 0;
            return Math.max(mediaDuration, expectedDuration);
        };

        const syncUi = () => {
            const displayDuration = getDisplayDuration();
            currentTimeEl.textContent = formatTaskDetailTime(audio.currentTime);
            totalTimeEl.textContent = formatTaskDetailTime(displayDuration);

            if (displayDuration > 0) {
                progress.value = String(Math.min(1000, Math.round((audio.currentTime / displayDuration) * 1000)));
                progress.disabled = false;
            } else {
                progress.value = '0';
                progress.disabled = true;
            }
        };

        playBtn.addEventListener('click', () => {
            if (audio.paused) {
                audio.play().catch(() => {});
            } else {
                audio.pause();
            }
        });

        audio.addEventListener('play', () => {
            playIcon?.classList.remove('fa-play');
            playIcon?.classList.add('fa-pause');
        });

        audio.addEventListener('pause', () => {
            playIcon?.classList.remove('fa-pause');
            playIcon?.classList.add('fa-play');
        });

        audio.addEventListener('loadedmetadata', syncUi);
        audio.addEventListener('durationchange', syncUi);
        audio.addEventListener('timeupdate', syncUi);
        audio.addEventListener('ended', () => {
            audio.currentTime = 0;
            syncUi();
        });

        progress.addEventListener('input', () => {
            const displayDuration = getDisplayDuration();
            if (displayDuration > 0) {
                audio.currentTime = (Number(progress.value) / 1000) * displayDuration;
            }
        });

        rateButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const rate = Number(button.dataset.rate || 1);
                audio.playbackRate = rate;
                rateButtons.forEach((candidate) => candidate.classList.remove('is-active'));
                button.classList.add('is-active');
            });
        });

        syncUi();
        player.dataset.ready = '1';
    }

    function closeTaskDetailModal() {
        const modal = document.getElementById('taskDetailModal');
        const content = document.getElementById('taskDetailModalContent');
        if (content) {
            content.innerHTML = '<div style="text-align:center; padding:32px; color:#64748b;">Select a task to view detail.</div>';
        }
        modal?.classList.remove('active');
    }

    async function openTaskDetailModal(taskId) {
        const modal = document.getElementById('taskDetailModal');
        const title = document.getElementById('taskDetailModalTitle');
        const content = document.getElementById('taskDetailModalContent');

        if (!modal || !content) {
            return;
        }

        modal.classList.add('active');
        title.textContent = 'Lead Detail';
        content.innerHTML = '<div style="text-align:center; padding:32px; color:#64748b;"><div class="spinner" style="display:inline-block;"></div><p style="margin-top:12px;">Loading detail...</p></div>';

        try {
            const result = await apiCall(`/tasks/${taskId}`);
            const task = result?.data || result;
            const lead = task?.lead || {};
            const sourceDetails = lead?.source_details || {};
            const normalizedSource = String(sourceDetails.source || lead.source || 'other').toLowerCase();
            const sourceLabel = sourceDetails.label || (lead.source || 'Other');

            title.textContent = `${lead.name || task.title || 'Lead'} Detail`;

            let detailHtml = `
                <div class="task-detail-grid">
                    <div class="task-detail-card">
                        <h4>Lead Summary</h4>
                        <div class="task-detail-meta-line"><strong>Name:</strong> ${escapeHtml(lead.name || 'N/A')}</div>
                        <div class="task-detail-meta-line"><strong>Phone:</strong> ${escapeHtml(lead.phone || 'N/A')}</div>
                        <div class="task-detail-meta-line"><strong>Source:</strong> ${escapeHtml(sourceLabel)}</div>
                        <div class="task-detail-meta-line"><strong>Status:</strong> ${escapeHtml(task.status || 'N/A')}</div>
                    </div>
                </div>
            `;

            if ((normalizedSource === 'ivr' || normalizedSource === 'mcube' || normalizedSource === 'call') && sourceDetails.recording?.recording_route) {
                const recording = sourceDetails.recording;
                detailHtml += `
                    <div class="task-detail-player" data-expected-duration="${Number(recording.duration_seconds || 0)}">
                        <div class="task-detail-player-top">
                            <button type="button" class="task-detail-player-btn" aria-label="Play recording">
                                <i class="fas fa-play"></i>
                            </button>
                            <div class="task-detail-player-time">
                                <span class="task-detail-current">0:00</span>
                                <span>/</span>
                                <span class="task-detail-total">${formatTaskDetailTime(recording.duration_seconds || 0)}</span>
                            </div>
                            <div class="task-detail-speed">
                                <button type="button" class="task-detail-speed-btn is-active" data-rate="1">1x</button>
                                <button type="button" class="task-detail-speed-btn" data-rate="1.5">1.5x</button>
                                <button type="button" class="task-detail-speed-btn" data-rate="2">2x</button>
                            </div>
                            ${recording.download_route ? `<a href="${escapeHtml(recording.download_route)}" class="task-detail-speed-btn" style="text-decoration:none;">Download</a>` : ''}
                        </div>
                        <input type="range" min="0" max="1000" value="0" class="task-detail-progress" aria-label="Seek recording">
                        <audio preload="metadata" class="task-detail-audio hidden">
                            <source src="${escapeHtml(recording.recording_route)}" type="audio/mpeg">
                        </audio>
                    </div>
                `;
            } else if (normalizedSource === 'meta') {
                detailHtml += renderTaskDetailPayloadSection('Meta Field Data', sourceDetails.meta?.field_data || {});
            } else {
                detailHtml += `
                    <div class="task-detail-card">
                        <h4>Source Detail</h4>
                        <div class="task-detail-meta-line">No special source detail is available for this lead.</div>
                    </div>
                `;
            }

            content.innerHTML = detailHtml;
            initializeTaskDetailRecordingPlayer(content);
        } catch (error) {
            console.error('Failed to load task detail:', error);
            content.innerHTML = '<div style="text-align:center; padding:32px; color:#dc2626;">Failed to load task detail.</div>';
        }
    }

    function initializeSalesManagerTasksPage() {
        if (taskInitBooted) {
            console.log('Tasks page already initialized, skipping duplicate boot');
            return;
        }

        taskInitBooted = true;
        console.log('=== DOM LOADED - INITIALIZING TASKS ===');
        console.log('API_BASE_URL:', API_BASE_URL);
        console.log('API_TOKEN available:', !!API_TOKEN);
        console.log('API_TOKEN value:', API_TOKEN ? API_TOKEN.substring(0, 20) + '...' : 'null');
        initAsmMobileWeekSwipe();
        const savedTasksView = (() => {
            try { return window.innerWidth <= 768 ? 'list' : (localStorage.getItem('asm_tasks_view') || 'list'); } catch (e) { return 'list'; }
        })();
        setTasksView('list', false);
        const pageParams = new URLSearchParams(window.location.search);
        const requestedTaskId = Number(pageParams.get('task') || 0) || null;
        const requestedAction = pageParams.get('action');
        const focusMode = pageParams.get('focus');
        const requestedStatus = pageParams.get('status');
        const requestedDateFilter = pageParams.get('date_filter');
        const requestedCustomDate = pageParams.get('date');
        const requestedCategory = pageParams.get('category');
        const hasRequestedStatus = !!requestedStatus && ['all', 'pending', 'overdue', 'rescheduled', 'completed'].includes(requestedStatus);
        const hasRequestedDateFilter = !!requestedDateFilter && ['all', 'today', 'pod', 'tomorrow', 'this_week', 'this_month', 'this_year', 'custom'].includes(requestedDateFilter);
        const validTaskCategories = ['all', 'fresh_lead', 'follow_up', 'meeting', 'site_visit', 'prospect', 'closer', 'other'];
        const hasRequestedCategory = !!requestedCategory && validTaskCategories.includes(requestedCategory);
        let savedStatus = normalizeTaskStatusFilter(getStoredTaskFilter('salesManagerTasksFilter', 'all'));
        let savedDateFilter = normalizeAsmVisibleDateFilter(getStoredTaskFilter('salesManagerDateFilter', 'today'));
        let savedCustomDate = getStoredTaskFilter('salesManagerCustomDate');
        let savedCategory = getStoredTaskFilter('salesManagerTaskCategory', 'all');
        const hasExplicitTaskContext = !!requestedTaskId || !!focusMode || hasRequestedStatus || hasRequestedDateFilter || hasRequestedCategory;
        const shouldResetStandaloneTaskFilters = !hasExplicitTaskContext;

        if (shouldResetStandaloneTaskFilters) {
            savedStatus = 'all';
            savedDateFilter = 'today';
            savedCustomDate = null;
            savedCategory = 'all';
            try {
                localStorage.removeItem('salesManagerTasksFilter');
                localStorage.removeItem('salesManagerDateFilter');
                localStorage.removeItem('salesManagerTaskCategory');
                localStorage.removeItem('salesManagerCustomDate');
            } catch (e) {
                console.error('Failed to reset standalone task filters:', e);
            }
        }
        if (hasRequestedCategory) {
            savedCategory = requestedCategory;
        } else if (USES_MANAGER_TASK_POD_TOGGLE && !focusMode && !requestedTaskId) {
            savedCategory = 'all';
            try {
                localStorage.setItem('salesManagerTaskCategory', 'all');
            } catch (e) {
                console.error('Failed to reset hidden task category filter:', e);
            }
        }
        syncAsmMobileRangeButtons(savedDateFilter === 'pod' ? 'today' : (savedDateFilter || 'today'));
        
        // Set up status dropdown filters
        const filterDropdown = document.getElementById('taskFilterDropdown');
        const filterDropdownDesktop = document.getElementById('taskStatusFilterDesktop');

        function handleStatusDropdownChange(statusValue) {
            const normalizedStatus = normalizeTaskStatusFilter(statusValue);
            console.log('Status filter dropdown changed, status:', normalizedStatus);
            if (USES_MANAGER_TASK_POD_TOGGLE && asmTaskMode === 'pod' && normalizedStatus !== 'overdue') {
                syncAsmTaskModeUi('today');
                if (window.filterTasks) {
                    window.filterTasks(normalizedStatus, 'today');
                } else {
                    filterTasks(normalizedStatus, 'today');
                }
                return;
            }
            if (window.filterTasks) {
                window.filterTasks(normalizedStatus);
            } else {
                filterTasks(normalizedStatus);
            }
        }

        if (filterDropdown) {
            filterDropdown.addEventListener('change', function(e) {
                const status = this.value;
                handleStatusDropdownChange(status);
            });
        }

        if (filterDropdownDesktop) {
            filterDropdownDesktop.addEventListener('change', function() {
                handleStatusDropdownChange(this.value);
            });
        }

        Promise.resolve(requestedTaskId ? resolveRequestedTaskContext(requestedTaskId) : null).then((requestedTaskContext) => {
        if (requestedTaskContext) {
            savedStatus = requestedTaskContext.status || 'pending';
            savedDateFilter = requestedTaskContext.dateFilter || 'today';
            savedCustomDate = requestedTaskContext.customDate || null;
            savedCategory = requestedTaskContext.category || 'all';
        }

        if (hasRequestedStatus && !requestedTaskContext) {
            const normalizedRequestedStatus = normalizeTaskStatusFilter(requestedStatus);
            if (filterDropdown) filterDropdown.value = normalizedRequestedStatus;
            if (filterDropdownDesktop) filterDropdownDesktop.value = normalizedRequestedStatus;
            currentStatus = normalizedRequestedStatus;
            window.currentStatus = normalizedRequestedStatus;
        } else {
            if (filterDropdown) filterDropdown.value = savedStatus;
            if (filterDropdownDesktop) filterDropdownDesktop.value = savedStatus;
            currentStatus = savedStatus;
            window.currentStatus = savedStatus;
        }

        if (USES_MANAGER_TASK_POD_TOGGLE && savedDateFilter === 'pod') {
            currentStatus = 'overdue';
            window.currentStatus = 'overdue';
            if (filterDropdown) filterDropdown.value = 'overdue';
            if (filterDropdownDesktop) filterDropdownDesktop.value = 'overdue';
        }

        const categoryDropdownDesktop = document.getElementById('taskTypeFilterDesktop');
        const categoryDropdownMobile = document.getElementById('taskTypeFilterMobile');

        function handleCategoryChange(categoryValue) {
            const currentStatusValue = window.currentStatus || currentStatus || 'all';
            const dateFilterValue = document.getElementById('dateFilterDropdown')?.value || document.getElementById('dateFilterDropdownDesktop')?.value || 'today';
            const customDateValue = document.getElementById('customDatePicker')?.value || null;
            if (window.filterTasks) {
                window.filterTasks(currentStatusValue, dateFilterValue, customDateValue, categoryValue);
            } else {
                filterTasks(currentStatusValue, dateFilterValue, customDateValue, categoryValue);
            }
        }

        if (categoryDropdownDesktop) {
            categoryDropdownDesktop.addEventListener('change', function() {
                handleCategoryChange(this.value);
            });
        }

        if (categoryDropdownMobile) {
            categoryDropdownMobile.addEventListener('change', function() {
                handleCategoryChange(this.value);
            });
        }

        if (categoryDropdownDesktop && savedCategory) {
            categoryDropdownDesktop.value = savedCategory;
        }
        if (categoryDropdownMobile && savedCategory) {
            categoryDropdownMobile.value = savedCategory;
        }
        currentCategory = savedCategory || 'all';
        window.currentCategory = currentCategory;
        syncAsmTaskModeUi(getAsmTaskModeFromFilters(currentStatus, savedDateFilter));

        if (focusMode === 'followups' && !requestedTaskContext) {
            if (categoryDropdownDesktop) categoryDropdownDesktop.value = 'follow_up';
            if (categoryDropdownMobile) categoryDropdownMobile.value = 'follow_up';
            if (!hasRequestedStatus) {
                if (filterDropdown) filterDropdown.value = 'pending';
                if (filterDropdownDesktop) filterDropdownDesktop.value = 'pending';
                currentStatus = 'pending';
                window.currentStatus = 'pending';
            }
            currentCategory = 'follow_up';
            window.currentCategory = 'follow_up';
            if (!hasRequestedDateFilter) {
                savedDateFilter = 'today';
                savedCustomDate = null;
                try {
                    localStorage.setItem('salesManagerDateFilter', 'today');
                    localStorage.removeItem('salesManagerCustomDate');
                } catch (e) {
                    console.error('Failed to normalize follow-up focus date filter:', e);
                }
            }
        }
        
        // Set up date dropdown filters (mobile and desktop)
        const dateDropdownMobile = document.getElementById('dateFilterDropdown');
        const dateDropdownDesktop = document.getElementById('dateFilterDropdownDesktop');
        const customDatePicker = document.getElementById('customDatePicker');
        
        function handleDateFilterChange(dateFilter) {
            console.log('Date filter changed:', dateFilter);
            if (USES_MANAGER_TASK_POD_TOGGLE && dateFilter === 'pod') {
                setAsmTaskMode('pod');
                return;
            }
            
            if (dateFilter === 'custom' && customDatePicker) {
                const preparedDate = primeCustomDatePicker(customDatePicker);
                customDatePicker.showPicker?.();
                customDatePicker.focus();

                const currentStatusValue = window.currentStatus || currentStatus || 'all';
                const currentCategoryValue = window.currentCategory || currentCategory || 'all';

                if (preparedDate) {
                    if (window.filterTasks) {
                        window.filterTasks(currentStatusValue, dateFilter, preparedDate, currentCategoryValue);
                    } else {
                        filterTasks(currentStatusValue, dateFilter, preparedDate, currentCategoryValue);
                    }
                }
                return;
            }
            
            // Get current status and apply both filters
            const currentStatusValue = window.currentStatus || currentStatus || 'all';
            const currentCategoryValue = window.currentCategory || currentCategory || 'all';
            const customDate = (dateFilter === 'custom' && customDatePicker && customDatePicker.value) ? customDatePicker.value : null;
            
            if (window.filterTasks) {
                window.filterTasks(currentStatusValue, dateFilter, customDate, currentCategoryValue);
            } else {
                filterTasks(currentStatusValue, dateFilter, customDate, currentCategoryValue);
            }
        }
        
        if (dateDropdownMobile) {
                dateDropdownMobile.addEventListener('change', function(e) {
                handleDateFilterChange(normalizeAsmVisibleDateFilter(this.value));
            });
            
            if (hasRequestedDateFilter) {
                dateDropdownMobile.value = requestedDateFilter;
            } else {
                dateDropdownMobile.value = savedDateFilter;
            }
        }
        
        if (dateDropdownDesktop) {
                dateDropdownDesktop.addEventListener('change', function(e) {
                handleDateFilterChange(normalizeAsmVisibleDateFilter(this.value));
            });
            
            if (hasRequestedDateFilter) {
                dateDropdownDesktop.value = requestedDateFilter;
            } else {
                dateDropdownDesktop.value = savedDateFilter;
            }
        }
        
        // Handle custom date picker change
        if (customDatePicker) {
            customDatePicker.addEventListener('change', function(e) {
                const customDate = this.value;
                console.log('Custom date changed:', customDate);
                
                // Save to localStorage
                try {
                    localStorage.setItem('salesManagerCustomDate', customDate);
                } catch (e) {
                    console.error('Failed to save custom date:', e);
                }
                
                // Get current filters and apply
                const currentStatusValue = window.currentStatus || currentStatus || 'all';
                const dateFilter = 'custom';
                const currentCategoryValue = window.currentCategory || currentCategory || 'all';
                
                if (window.filterTasks) {
                    window.filterTasks(currentStatusValue, dateFilter, customDate, currentCategoryValue);
                } else {
                    filterTasks(currentStatusValue, dateFilter, customDate, currentCategoryValue);
                }
            });
        }
        
        const tasksGridEl = document.getElementById('tasksGrid');
        console.log('Tasks grid element found:', !!tasksGridEl);
        
        if (tasksGridEl) {
            console.log('Calling filterTasks() with current filter:', currentStatus);
            let initialDateFilter = savedDateFilter || 'today';
            let initialCustomDate = null;
            if (hasRequestedDateFilter) {
                initialDateFilter = normalizeAsmVisibleDateFilter(requestedDateFilter);
                initialCustomDate = requestedDateFilter === 'custom' ? requestedCustomDate : null;
                if (customDatePicker) {
                    if (requestedDateFilter === 'custom') {
                        if (requestedCustomDate) {
                            customDatePicker.value = requestedCustomDate;
                        }
                    }
                }
            } else if (focusMode === 'followups' && !requestedTaskContext) {
                initialDateFilter = 'today';
                initialCustomDate = null;
                if (dateDropdownMobile) {
                    dateDropdownMobile.value = 'today';
                }
                if (dateDropdownDesktop) {
                    dateDropdownDesktop.value = 'today';
                }
                if (customDatePicker) {
                    customDatePicker.value = '';
                }
            } else if (initialDateFilter === 'custom') {
                initialCustomDate = savedCustomDate;
                if (customDatePicker) {
                    if (savedCustomDate) {
                        customDatePicker.value = savedCustomDate;
                    }
                }
            }
            
            // Use filterTasks instead of loadTasks to restore UI state
            if (focusMode === 'followups' && !requestedTaskContext) {
                filterTasks('pending', initialDateFilter, initialCustomDate, 'follow_up');
            } else {
                filterTasks(currentStatus, initialDateFilter, initialCustomDate, currentCategory || 'all');
            }
            if (requestedTaskId) {
                setTimeout(() => focusRequestedTaskCard(requestedTaskId), 700);
                if (requestedAction === 'complete') {
                    const requestedCategory = requestedTaskContext?.category || currentCategory || 'other';
                    setTimeout(() => {
                        if (requestedTaskContext?.task) {
                            openManagerTaskCompletion(requestedTaskContext.task);
                            return;
                        }

                        openTaskOutcomeModal(requestedTaskId, requestedCategory);
                    }, 900);
                }
            }
            ensureTaskAutoRefreshStarted();
        } else {
            console.error('ERROR: Tasks grid element not found on DOM ready!');
            // Try again after a short delay
            setTimeout(function() {
                const retryEl = document.getElementById('tasksGrid');
                if (retryEl) {
                    console.log('Tasks grid found on retry, calling filterTasks() with current filter:', currentStatus);
                    // Use filterTasks instead of loadTasks to restore UI state
                    if (focusMode === 'followups' && !requestedTaskContext) {
                        filterTasks('pending', 'today', null, 'follow_up');
                    } else {
                        filterTasks(currentStatus, savedDateFilter || 'today', savedCustomDate, currentCategory || 'all');
                    }
                    if (requestedTaskId) {
                        setTimeout(() => focusRequestedTaskCard(requestedTaskId), 900);
                        if (requestedAction === 'complete') {
                            const requestedCategory = requestedTaskContext?.category || currentCategory || 'other';
                            setTimeout(() => {
                                if (requestedTaskContext?.task) {
                                    openManagerTaskCompletion(requestedTaskContext.task);
                                    return;
                                }

                                openTaskOutcomeModal(requestedTaskId, requestedCategory);
                            }, 1100);
                        }
                    }
                    ensureTaskAutoRefreshStarted();
                } else {
                    console.error('ERROR: Tasks grid still not found after retry!');
                }
            }, 500);
        }
        });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', initializeSalesManagerTasksPage);
    
    // Fallback: If DOMContentLoaded already fired, call immediately
    if (document.readyState === 'loading') {
        // DOM is still loading, wait for DOMContentLoaded
        console.log('DOM still loading, waiting for DOMContentLoaded...');
    } else {
        // DOM already loaded, call immediately
        console.log('DOM already loaded, initializing immediately...');
        setTimeout(initializeSalesManagerTasksPage, 100);
    }

    // Close modals on outside click (backdrop click)
    document.getElementById('verifyRejectPromptModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTaskOutcomeModal();
        }
    });
    
    document.getElementById('rejectReasonModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            cancelJunkRemarkModal();
        }
    });
    
    document.getElementById('managerLeadRequirementFormModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            cancelManagerLeadRequirementForm();
        }
    });
    
    document.getElementById('cnpTimeSelectionModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            cancelOutcomeDateTimeModal();
        }
    });
    
    document.getElementById('prospectDetailModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeProspectDetailModal();
        }
    });

    document.getElementById('taskDetailModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTaskDetailModal();
        }
    });

    document.getElementById('taskMeetingActionHubModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTaskMeetingActionHubModal();
        }
    });

    document.getElementById('taskMeetingSendCloserModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTaskMeetingSendCloserModal();
        }
    });

    document.getElementById('taskFollowUpActionHubModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTaskFollowUpActionHubModal();
        }
    });

    document.getElementById('taskVisitActionHubModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTaskVisitActionHubModal();
        }
    });

    // ASM task outcome overrides
    let selectedTaskOutcome = null;
    let currentTaskCategory = 'other';

    async function handleManagerPhoneDialOnly(taskId, phoneNumber) {
        setCurrentTaskId(taskId);

        if (!phoneNumber || phoneNumber === 'N/A') {
            showAlert('Phone number not available', 'warning');
            return;
        }

        const dialerPhone = formatPhoneForDialer(phoneNumber);
        if (!dialerPhone) {
            showAlert('Phone number not available', 'warning');
            return;
        }

        await initiateMcubeCallOrFallback(taskId, phoneNumber);
    }

    async function openTaskOutcomeModal(taskId, taskCategory = 'other') {
        const normalizedCategory = String(taskCategory || 'other').trim().toLowerCase();
        if (normalizedCategory === 'follow_up') {
            const taskContext = await resolveRequestedTaskContext(taskId);
            openTaskFollowUpActionHubModal(taskContext?.task || { id: taskId, category: 'follow_up' });
            return;
        }

        if (normalizedCategory === 'site_visit') {
            const taskContext = await resolveRequestedTaskContext(taskId);
            if (taskContext?.task) {
                openManagerTaskCompletion(taskContext.task);
                return;
            }
        }

        setCurrentTaskId(taskId);
        currentTaskCategory = normalizedCategory || 'other';
        selectedTaskOutcome = null;
        document.getElementById('taskCallLaterLabel').textContent = currentTaskCategory === 'fresh_lead' ? 'Call Later' : 'Follow Up';
        document.getElementById('verifyRejectPromptModal')?.classList.add('active');
    }

    function closeTaskOutcomeModal() {
        document.getElementById('verifyRejectPromptModal')?.classList.remove('active');
    }

    function cancelVerifyRejectPrompt() {
        closeTaskOutcomeModal();
        currentTaskCategory = 'other';
        setCurrentTaskId(null);
    }

    async function selectTaskOutcome(outcome) {
        closeTaskOutcomeModal();

        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }

        if (outcome === 'interested') {
            window.managerTaskOutcomeContext = { outcome: 'interested', taskId };
            await openManagerLeadRequirementFormModal(taskId);
            return;
        }

        if (outcome === 'not_interested') {
            openOutcomeRemarkModal('not_interested');
            return;
        }

        if (outcome === 'follow_up' || outcome === 'cnp') {
            if (outcome === 'cnp' && !managerUsesUnifiedCnpScheduling && currentTaskCategory === 'fresh_lead') {
                openOutcomeRemarkModal('cnp');
                return;
            }

            selectedTaskOutcome = outcome;
            const title = document.getElementById('outcomeDateTimeModalTitle');
            const text = document.getElementById('outcomeDateTimeModalText');
            const confirmBtn = document.getElementById('outcomeDateTimeConfirmBtn');
            const isCallLater = outcome === 'follow_up' && currentTaskCategory === 'fresh_lead';

            if (title) {
                title.textContent = isCallLater ? 'Schedule Call Later' : outcome === 'follow_up' ? 'Schedule Follow Up' : 'Select Retry Time for CNP';
            }

            if (text) {
                text.textContent = isCallLater ? 'Choose the next call date and time' : outcome === 'follow_up'
                    ? 'Choose when the next follow-up call should happen:'
                    : 'Choose when to retry this call:';
            }

            if (confirmBtn) {
                confirmBtn.textContent = isCallLater ? 'Schedule Call' : outcome === 'follow_up' ? 'Schedule Follow Up' : 'Confirm CNP';
                confirmBtn.style.background = outcome === 'follow_up' ? '#2563eb' : '#f59e0b';
            }

            openCnpTimeSelectionModal();
            if (outcome === 'follow_up') {
                showCustomTimePicker();
            }
            return;
        }

        if (outcome === 'junk') {
            openOutcomeRemarkModal('junk');
        }
    }

    function openOutcomeRemarkModal(outcome) {
        selectedTaskOutcome = outcome;

        const modal = document.getElementById('rejectReasonModal');
        const title = document.getElementById('rejectReasonModalTitle');
        const textarea = document.getElementById('rejectReasonInput');
        const submitBtn = document.getElementById('rejectReasonSubmitBtn');

        if (title) {
            title.textContent = outcome === 'junk'
                ? 'Mark Lead as Junk'
                : outcome === 'not_interested'
                    ? 'Mark Lead as Not Interested'
                    : 'Add CNP Remark';
        }

        if (textarea) {
            textarea.value = '';
            textarea.placeholder = outcome === 'junk'
                ? 'Add junk reason or context...'
                : outcome === 'not_interested'
                    ? 'Add not interested context...'
                    : 'Add CNP context...';
        }

        if (submitBtn) {
            submitBtn.textContent = outcome === 'junk'
                ? 'Mark Junk'
                : outcome === 'not_interested'
                    ? 'Continue'
                    : 'Confirm CNP';
        }

        modal?.classList.add('active');
    }

    async function confirmOutcomeDateTimeSelection() {
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return;
        }

        let nextDateTime = null;

        if (isCustomTimeSelected) {
            const date = document.getElementById('cnpCustomDate')?.value;
            const time = document.getElementById('cnpCustomTime')?.value;

            if (!date || !time) {
                showAlert('Please select both date and time', 'warning');
                return;
            }

            const selectedDateTime = new Date(`${date}T${time}`);
            if (selectedDateTime <= new Date()) {
                showAlert('Please select a future date and time', 'warning');
                return;
            }

            nextDateTime = formatDateForApi(selectedDateTime);
        } else if (selectedCnpMinutes !== null) {
            nextDateTime = formatDateForApi(new Date(Date.now() + (selectedCnpMinutes * 60 * 1000)));
        } else {
            showAlert('Please select a time option', 'warning');
            return;
        }

        const result = await submitTaskOutcome(selectedTaskOutcome, {
            next_datetime: nextDateTime,
            remark: document.getElementById('outcomeDateTimeRemark')?.value.trim() || ''
        }, false);

        if (result?.success) {
            closeCnpTimeSelectionModal();
        }
    }

    function cancelOutcomeDateTimeModal() {
        closeCnpTimeSelectionModal();
    }

    function closeJunkRemarkModal() {
        document.getElementById('rejectReasonModal')?.classList.remove('active');
        document.getElementById('rejectReasonInput').value = '';
    }

    function cancelJunkRemarkModal() {
        closeJunkRemarkModal();
    }

    async function submitOutcomeRemark() {
        const remark = document.getElementById('rejectReasonInput').value.trim();

        const outcome = selectedTaskOutcome === 'junk' || selectedTaskOutcome === 'not_interested' || selectedTaskOutcome === 'cnp'
            ? selectedTaskOutcome
            : 'junk';
        const result = await submitTaskOutcome(outcome, { remark }, false);
        if (result?.success) {
            closeJunkRemarkModal();
            if (outcome === 'cnp') {
                currentTaskCategory = 'other';
            }
        }
    }

    async function submitTaskOutcome(outcome, extraData = {}, closeOutcomeModal = true) {
        const taskId = resolveActiveTaskId();
        if (!taskId) {
            showAlert('Task ID not found', 'error');
            return null;
        }

        const result = await apiCall(`/tasks/${taskId}/outcome`, {
            method: 'POST',
            body: JSON.stringify({
                outcome,
                ...extraData
            })
        });

        if (!result || !result.success) {
            const firstValidationError = result?.errors
                ? Object.values(result.errors).flat().find(Boolean)
                : null;
            showAlert(firstValidationError || result?.message || result?.error || 'Failed to update task outcome', 'error');
            return result;
        }

            const taskCard = document.getElementById(`task-card-${taskId}`);
        if (taskCard) {
            taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
            taskCard.style.opacity = '0';
            taskCard.style.transform = 'scale(0.95)';
            setTimeout(() => {
                taskCard.remove();
                reloadCurrentTaskList();
            }, 300);
        } else {
            reloadCurrentTaskList();
        }

        if (closeOutcomeModal) {
            closeTaskOutcomeModal();
        }

        setCurrentTaskId(null);
        currentTaskCategory = 'other';
        selectedTaskOutcome = null;
        showAlert(result.message || 'Outcome submitted successfully', 'success');
        return result;
    }
    
    // Note: loadTasks() is called in DOMContentLoaded event listener above (line 1820)
</script>
<script src="{{ asset('js/manager-lead-form.js') }}?v={{ (@filemtime(public_path('js/manager-lead-form.js')) ?: time()) }}-typevaluefix4" onload="
if (typeof window.renderManagerLeadForm === 'function') { renderManagerLeadForm = window.renderManagerLeadForm; }
if (typeof window.submitManagerLeadRequirementForm === 'function') { submitManagerLeadRequirementForm = window.submitManagerLeadRequirementForm; }
if (typeof window.handleManagerCategoryChange === 'function') { handleManagerCategoryChange = window.handleManagerCategoryChange; }
if (typeof window.loadInterestedProjectsForManager === 'function') { loadInterestedProjectsForManager = window.loadInterestedProjectsForManager; }
"></script>
@endpush
