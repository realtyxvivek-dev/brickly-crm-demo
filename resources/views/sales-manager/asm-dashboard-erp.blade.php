@extends('sales-manager.layout')

@section('title', 'ASM Dashboard - CRM Pro')
@section('page-title', 'Dashboard')

@push('styles')
<style>
    .header { display: none; }
    .content {
        background:
            radial-gradient(circle at top right, rgba(7, 111, 67, 0.08), transparent 34%),
            linear-gradient(180deg, #f8faf8 0%, #f4f6f2 100%);
    }
    @media (min-width: 768px) {
        body.asm-shell #mainContent {
            max-width: none !important;
            overflow-x: hidden !important;
        }
        body.asm-shell #mainContent > .container {
            max-width: none !important;
            width: 100% !important;
        }
    }
    .asm-erp-shell {
        display: flex;
        flex-direction: column;
        gap: 16px;
        width: 100%;
        max-width: none;
    }
    .asm-erp-hero,
    .asm-erp-card {
        background: #fff;
        border: 1px solid rgba(13, 74, 41, 0.12);
        border-radius: 8px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
    }
    .asm-erp-hero {
        position: relative;
        overflow: hidden;
        padding: 22px 24px;
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: center;
    }
    .asm-erp-hero::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 5px;
        background: #0f6b45;
    }
    .asm-erp-hero::after {
        content: "";
        position: absolute;
        right: -90px;
        top: -120px;
        width: 280px;
        height: 280px;
        border-radius: 999px;
        background: rgba(7, 111, 67, 0.08);
        pointer-events: none;
    }
    .asm-erp-hero > * {
        position: relative;
        z-index: 1;
    }
    .asm-erp-kicker {
        margin: 0 0 6px;
        color: #0f6b45;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    .asm-erp-title {
        margin: 0;
        color: #102A3A;
        font-size: 28px;
        font-weight: 800;
        line-height: 1.15;
    }
    .asm-erp-subtitle {
        margin: 7px 0 0;
        color: #5b6b62;
        font-size: 14px;
    }
    .asm-erp-subtitle,
    .asm-erp-stat small,
    .asm-erp-card-head span {
        display: none !important;
    }
    .asm-erp-hero-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .asm-erp-time {
        min-width: 150px;
        padding: 12px 16px;
        border: 1px solid rgba(13, 74, 41, 0.14);
        border-radius: 8px;
        color: #174130;
        text-align: center;
        font-weight: 800;
        background: rgba(255, 255, 255, 0.78);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.85), 0 8px 20px rgba(15,23,42,.05);
    }
    .asm-erp-time span {
        display: block;
        margin-top: 2px;
        color: #7a897f;
        font-size: 11px;
        font-weight: 600;
    }
    .asm-erp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
        padding: 0 16px;
        border: 1px solid rgba(13, 74, 41, 0.14);
        border-radius: 8px;
        color: #174130;
        background: #fff;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
    }
    .asm-erp-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(7, 111, 67, 0.32);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }
    .asm-erp-btn.primary {
        color: #fff;
        background: #0f6b45;
        border-color: #0f6b45;
    }
    .asm-erp-btn.dialer {
        color: #0f6b45;
        border-color: rgba(15, 107, 69, 0.22);
        background: #f0fdf4;
    }
    .asm-dialer-modal {
        position: fixed;
        inset: 0;
        z-index: 1400;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15, 23, 42, 0.56);
        backdrop-filter: blur(3px);
    }
    .asm-dialer-modal.active {
        display: flex;
    }
    .asm-dialer-card {
        width: min(420px, calc(100vw - 28px));
        overflow: hidden;
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
    }
    .asm-dialer-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 18px 20px;
        border-bottom: 1px solid #E2EEF3;
        background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
    }
    .asm-dialer-head h3 {
        margin: 0;
        color: #102A3A;
        font-size: 18px;
        font-weight: 800;
    }
    .asm-dialer-head p {
        margin: 4px 0 0;
        color: #5b6b62;
        font-size: 12px;
        font-weight: 600;
    }
    .asm-dialer-close {
        width: 36px;
        height: 36px;
        border: 1px solid #D8E6EC;
        border-radius: 999px;
        background: #ffffff;
        color: #102A3A;
    }
    .asm-dialer-body {
        padding: 18px;
    }
    .asm-dialer-display {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px;
        border: 1px solid #D8E6EC;
        border-radius: 14px;
        background: #F8FBFC;
    }
    .asm-dialer-prefix {
        color: #557084;
        font-weight: 700;
    }
    .asm-dialer-input {
        width: 100%;
        border: 0;
        outline: 0;
        background: transparent;
        color: #102A3A;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: 0;
    }
    .asm-dialer-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }
    .asm-dialer-key,
    .asm-dialer-action {
        border: 1px solid #D8E6EC;
        border-radius: 14px;
        background: #ffffff;
        color: #102A3A;
        font-weight: 800;
    }
    .asm-dialer-key {
        min-height: 52px;
        font-size: 20px;
    }
    .asm-dialer-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 14px;
    }
    .asm-dialer-action {
        min-height: 46px;
        font-size: 13px;
    }
    .asm-dialer-call {
        border-color: #8BE7B8;
        background: #007A4D;
        color: #ffffff;
    }
    .asm-dialer-action:disabled {
        cursor: not-allowed;
        opacity: 0.65;
    }
    .asm-dialer-status {
        min-height: 22px;
        margin-top: 12px;
        color: #557084;
        font-size: 13px;
        font-weight: 600;
    }
    .asm-dialer-status.success { color: #007A4D; }
    .asm-dialer-status.error { color: #B42318; }
    .asm-erp-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 12px;
    }
    .asm-erp-attendance {
        overflow: hidden;
        border-radius: 8px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
    }
    .asm-erp-attendance .attendance-widget {
        margin-bottom: 0;
        border-radius: 8px;
        padding: 16px;
        box-shadow: none;
        background: linear-gradient(135deg, #0b3d2a 0%, #0f6b45 100%);
    }
    .asm-erp-attendance .attendance-widget-full {
        display: none;
    }
    .asm-erp-attendance .attendance-widget-compact {
        display: block;
    }
    .asm-erp-attendance .attendance-widget-compact-top {
        margin-bottom: 12px;
    }
    .asm-erp-attendance .attendance-widget-compact-title {
        font-size: 18px;
    }
    .asm-erp-attendance .attendance-widget-compact-summary {
        margin-bottom: 12px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .asm-erp-attendance .attendance-widget-btn {
        min-height: 40px;
        border-radius: 8px;
    }
    .asm-erp-attendance .attendance-widget-status,
    .asm-erp-attendance .attendance-widget-compact-box {
        border-radius: 8px;
    }
    .asm-erp-attendance .attendance-widget-compact-links {
        margin-top: 10px;
    }
    .asm-erp-stat {
        position: relative;
        overflow: hidden;
        padding: 16px;
        background:
            linear-gradient(145deg, rgba(255, 255, 255, 0.99) 0%, rgba(248, 251, 249, 0.96) 100%);
        border: 1px solid rgba(13, 74, 41, 0.11);
        border-radius: 10px;
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.055);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .asm-erp-stat::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        right: 0;
        height: 3px;
        background: #0f6b45;
    }
    .asm-erp-stat:nth-child(2)::before { background: #0f6b45; }
    .asm-erp-stat:nth-child(3)::before { background: #dc2626; }
    .asm-erp-stat:nth-child(4)::before { background: #7c3aed; }
    .asm-erp-stat:nth-child(5)::before { background: #2f855a; }
    .asm-erp-stat:nth-child(6)::before { background: #b45309; }
    .asm-erp-stat:hover {
        transform: translateY(-2px);
        border-color: rgba(7, 111, 67, 0.22);
        box-shadow: 0 18px 32px rgba(15, 23, 42, 0.08);
    }
    .asm-erp-stat label {
        display: block;
        color: #637469;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .asm-erp-stat strong {
        display: block;
        margin-top: 10px;
        color: #071b13;
        font-size: 27px;
        line-height: 1;
    }
    .asm-erp-stat small {
        display: block;
        margin-top: 8px;
        color: #7a897f;
        font-size: 12px;
    }
    .asm-erp-main {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(340px, .9fr);
        gap: 16px;
    }
    .asm-erp-card-head {
        padding: 17px 20px;
        border-bottom: 1px solid #e5ece7;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .asm-erp-card-head h2 {
        margin: 0;
        color: #102A3A;
        font-size: 19px;
        font-weight: 800;
    }
    .asm-erp-card-head span {
        color: #6b7b72;
        font-size: 12px;
        font-weight: 700;
    }
    .asm-erp-work-head {
        align-items: flex-start;
        background:
            radial-gradient(circle at top right, rgba(7, 111, 67, 0.055), transparent 38%),
            #fff;
    }
    .asm-erp-work-summary {
        display: grid;
        grid-template-columns: repeat(2, max-content);
        align-items: stretch;
        gap: 8px;
        justify-content: flex-end;
    }
    .asm-erp-work-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 32px;
        padding: 6px 11px 6px 8px;
        border-radius: 999px;
        background: #eef5f1;
        color: #315545;
        font-size: 11px;
        font-weight: 900;
        text-decoration: none;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
    }
    .asm-erp-work-chip strong {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 20px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .74);
        color: #102A3A;
        font-size: 12px;
        line-height: 1;
    }
    .asm-erp-work-chip.danger {
        background: #fee2e2;
        color: #b91c1c;
    }
    .asm-erp-work-chip.warning {
        background: #fff5d9;
        color: #a15c07;
    }
    .asm-erp-list {
        display: flex;
        flex-direction: column;
    }
    .asm-erp-item {
        display: grid;
        grid-template-columns: 112px minmax(0, 1fr) 118px;
        gap: 14px;
        padding: 15px 20px;
        align-items: center;
        border-bottom: 1px solid #edf1ee;
        text-decoration: none;
        transition: background .18s ease;
    }
    .asm-erp-item:last-child {
        border-bottom: none;
    }
    .asm-erp-item:hover {
        background: #f7fbf8;
    }
    .asm-erp-pill {
        width: max-content;
        padding: 6px 10px;
        border-radius: 999px;
        background: #eaf8f0;
        color: #0f6b45;
        font-size: 12px;
        font-weight: 800;
    }
    .asm-erp-item h3 {
        margin: 0;
        color: #102A3A;
        font-size: 15px;
        font-weight: 800;
    }
    .asm-erp-item p {
        margin: 4px 0 0;
        color: #617268;
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .asm-erp-item time {
        color: #174130;
        font-size: 13px;
        font-weight: 800;
        text-align: right;
    }
    .asm-erp-section {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .asm-erp-side-stack {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .asm-erp-pipeline {
        padding: 18px 20px;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    .asm-erp-pipe {
        padding: 14px;
        border: 1px solid rgba(13, 74, 41, 0.11);
        border-radius: 8px;
        background: #fafcfb;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .asm-erp-pipe:hover {
        transform: translateY(-2px);
        border-color: rgba(7, 111, 67, 0.24);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.07);
    }
    .asm-erp-pipe strong {
        display: block;
        color: #071b13;
        font-size: 24px;
    }
    .asm-erp-pipe span {
        display: block;
        margin-top: 4px;
        color: #607167;
        font-size: 12px;
        font-weight: 800;
    }
    .asm-erp-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid #edf1ee;
        color: #102A3A;
        text-decoration: none;
        transition: background .18s ease;
    }
    .asm-erp-alert:last-child {
        border-bottom: none;
    }
    .asm-erp-alert strong {
        font-size: 14px;
    }
    .asm-erp-alert:hover {
        background: #f7fbf8;
    }
    .asm-erp-alert span {
        min-width: 42px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #eef3f0;
        text-align: center;
        font-weight: 900;
    }
    .asm-erp-alert.danger span {
        background: #fee2e2;
        color: #b91c1c;
    }
    .asm-erp-alert.warning span {
        background: #fff5d9;
        color: #a15c07;
    }
    .asm-erp-team-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 80px 80px;
        gap: 10px;
        padding: 13px 20px;
        border-bottom: 1px solid #edf1ee;
        align-items: center;
        transition: background .18s ease;
    }
    .asm-erp-team-row:last-child {
        border-bottom: none;
    }
    .asm-erp-team-row strong {
        color: #102A3A;
        font-size: 14px;
    }
    .asm-erp-team-row span {
        color: #5c6e64;
        font-size: 12px;
        font-weight: 800;
        text-align: right;
    }
    .asm-erp-team-row:hover {
        background: #f7fbf8;
    }
    .asm-erp-empty {
        padding: 34px 20px;
        color: #69786f;
        font-size: 14px;
        text-align: center;
    }
    .asm-erp-overdue-card {
        border-color: rgba(0, 115, 177, 0.12);
        background: linear-gradient(145deg, #ffffff 0%, #f7fbfe 100%);
    }
    .asm-erp-overdue-card .asm-erp-card-head {
        background: linear-gradient(180deg, rgba(232, 244, 251, 0.92) 0%, rgba(255, 255, 255, 0.98) 100%);
    }
    .asm-erp-overdue-card .asm-erp-list {
        padding: 12px;
        gap: 10px;
    }
    .asm-erp-overdue-card .asm-erp-item {
        position: relative;
        grid-template-columns: max-content minmax(0, 1fr) auto;
        border: 1px solid rgba(0, 115, 177, 0.12);
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(0, 59, 92, 0.06);
    }
    .asm-erp-overdue-card .asm-erp-item::before {
        content: "";
        position: absolute;
        left: 0;
        top: 12px;
        bottom: 12px;
        width: 3px;
        border-radius: 0 999px 999px 0;
        background: linear-gradient(180deg, #0073b1 0%, #7dd3fc 100%);
    }
    .asm-erp-overdue-card .asm-erp-pill {
        background: #e8f4fb;
        color: #0073b1;
    }
    .asm-erp-overdue-card .asm-erp-item h3,
    .asm-erp-overdue-card .asm-erp-item time {
        color: #003b5c;
    }
    @media (max-width: 1180px) {
        .asm-erp-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .asm-erp-main { grid-template-columns: 1fr; }
    }
    @media (max-width: 980px) {
        .asm-erp-section { grid-template-columns: 1fr; }
    }
    @media (max-width: 760px) {
        .asm-erp-hero {
            align-items: flex-start;
            flex-direction: column;
            gap: 10px;
            padding: 13px 16px;
        }
        .asm-erp-hero::after {
            right: -120px;
            top: -150px;
            width: 250px;
            height: 250px;
        }
        .asm-erp-kicker {
            margin-bottom: 4px;
            font-size: 10px;
        }
        .asm-erp-title {
            font-size: 20px;
            line-height: 1.12;
        }
        .asm-erp-subtitle,
        .asm-erp-time {
            display: none;
        }
        .asm-erp-hero-actions {
            justify-content: flex-start;
            width: 100%;
            gap: 8px;
        }
        .asm-erp-hero-actions .asm-erp-btn {
            min-height: 38px;
            padding: 0 13px;
            font-size: 12px;
        }
        .asm-erp-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }
        .asm-erp-section { grid-template-columns: 1fr; }
        .asm-erp-work-head {
            align-items: flex-start;
            flex-direction: column;
            gap: 8px;
            padding: 14px 16px 12px;
        }
        .asm-erp-work-head h2 {
            font-size: 19px;
            line-height: 1.15;
        }
        .asm-erp-work-head > div:first-child {
            width: 100%;
        }
        .asm-erp-work-head > div:first-child span {
            display: block;
            max-width: 260px;
            margin-top: 5px;
            font-size: 10.5px;
            line-height: 1.35;
            color: #65776c;
        }
        .asm-erp-work-summary {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 5px;
        }
        .asm-erp-work-chip {
            justify-content: flex-start;
            min-height: 30px;
            padding: 5px 6px 5px 5px;
            font-size: 9px;
            letter-spacing: -.01em;
            gap: 4px;
            white-space: nowrap;
            min-width: 0;
        }
        .asm-erp-work-chip strong {
            min-width: 20px;
            height: 18px;
            font-size: 10px;
            flex: 0 0 auto;
        }
        .asm-erp-pipeline {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 6px;
            padding: 10px;
        }
        .asm-erp-pipe {
            padding: 9px 6px;
            text-align: center;
        }
        .asm-erp-pipe strong {
            font-size: 18px;
        }
        .asm-erp-pipe span {
            font-size: 9px;
            line-height: 1.15;
        }
        .asm-erp-side-stack > .asm-erp-card:first-child .asm-erp-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            padding: 10px;
            background: linear-gradient(180deg, #fbfdfb 0%, #f4f8f5 100%);
        }
        .asm-erp-alert {
            min-width: 0;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            min-height: 78px;
            padding: 10px 9px;
            border: 1px solid rgba(13, 74, 41, 0.09);
            border-radius: 10px;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 251, 249, 0.96) 100%);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.055);
        }
        .asm-erp-alert.danger {
            border-color: rgba(220, 38, 38, 0.13);
            background: linear-gradient(145deg, #fff 0%, #fff7f7 100%);
        }
        .asm-erp-alert.warning {
            border-color: rgba(217, 119, 6, 0.14);
            background: linear-gradient(145deg, #fff 0%, #fffaf0 100%);
        }
        .asm-erp-alert strong {
            color: #102A3A;
            font-size: 9.5px;
            line-height: 1.18;
            letter-spacing: -.01em;
        }
        .asm-erp-alert span {
            min-width: 0;
            padding: 5px 8px;
            font-size: 12px;
            line-height: 1;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
        }
        .asm-erp-side-stack > .asm-erp-card:first-child .asm-erp-card-head {
            padding: 14px 16px 10px;
            border-bottom: 0;
        }
        .asm-erp-side-stack > .asm-erp-card:first-child .asm-erp-card-head h2 {
            font-size: 19px;
        }
        .asm-erp-side-stack > .asm-erp-card:first-child .asm-erp-card-head span {
            font-size: 11px;
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-list {
            max-height: 282px;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: linear-gradient(180deg, #fbfdfb 0%, #f5f8f6 100%);
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-list::-webkit-scrollbar {
            width: 5px;
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-list::-webkit-scrollbar-thumb {
            background: rgba(13, 74, 41, 0.26);
            border-radius: 999px;
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-item {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 7px 10px;
            min-height: 84px;
            padding: 11px 12px 11px 15px;
            border: 1px solid rgba(13, 74, 41, 0.09);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.045);
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-item::before {
            content: "";
            position: absolute;
            left: 0;
            top: 10px;
            bottom: 10px;
            width: 3px;
            border-radius: 0 999px 999px 0;
            background: linear-gradient(180deg, #0f6b45 0%, #8fd4ad 100%);
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-item:not(:last-child) {
            border-bottom: 1px solid rgba(13, 74, 41, 0.09);
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-item-copy {
            grid-column: 1 / -1;
            min-width: 0;
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-item h3 {
            font-size: 13px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-item p {
            margin-top: 3px;
            font-size: 11px;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            white-space: normal;
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-status-chip {
            display: inline-flex;
            width: max-content;
            margin-top: 6px;
            padding: 4px 7px;
            border-radius: 999px;
            background: #eef5f1;
            color: #426452;
            font-size: 10px;
            font-weight: 800;
            line-height: 1;
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-pill {
            width: max-content;
            padding: 5px 8px;
            font-size: 10px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
        }
        .asm-erp-main > .asm-erp-card:first-child .asm-erp-item time {
            font-size: 11px;
            color: #174130;
            align-self: center;
            text-align: right;
            white-space: nowrap;
        }
        .asm-erp-overdue-card .asm-erp-card-head {
            align-items: center;
            padding: 16px 18px 12px;
        }
        .asm-erp-overdue-card .asm-erp-card-head h2 {
            font-size: 20px;
            line-height: 1.1;
        }
        .asm-erp-overdue-card .asm-erp-card-head span {
            display: none;
        }
        .asm-erp-overdue-card .asm-erp-work-chip {
            width: auto;
            min-height: 34px;
            justify-content: center;
            padding: 6px 10px 6px 7px;
            font-size: 10px;
        }
        .asm-erp-overdue-card .asm-erp-list {
            padding: 12px;
            gap: 10px;
        }
        .asm-erp-overdue-card .asm-erp-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px 12px;
            min-height: 0;
            padding: 16px 14px 16px 18px;
        }
        .asm-erp-overdue-card .asm-erp-pill {
            grid-column: 1 / -1;
            padding: 6px 11px;
            font-size: 11px;
        }
        .asm-erp-overdue-card .asm-erp-item h3 {
            font-size: 16px;
            line-height: 1.2;
        }
        .asm-erp-overdue-card .asm-erp-item p {
            margin-top: 5px;
            font-size: 13px;
            line-height: 1.35;
            white-space: normal;
        }
        .asm-erp-overdue-card .asm-erp-item time {
            align-self: end;
            font-size: 13px;
            line-height: 1.2;
            white-space: nowrap;
        }
        .asm-erp-stat {
            min-width: 0;
            padding: 11px 8px 10px;
            min-height: 88px;
            border-radius: 10px;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 251, 249, 0.96) 100%);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.055);
        }
        .asm-erp-stat:nth-child(2) {
            border-color: rgba(37, 99, 235, 0.13);
            background: linear-gradient(145deg, #fff 0%, #f6f9ff 100%);
        }
        .asm-erp-stat:nth-child(3) {
            border-color: rgba(220, 38, 38, 0.13);
            background: linear-gradient(145deg, #fff 0%, #fff7f7 100%);
        }
        .asm-erp-stat:nth-child(4) {
            border-color: rgba(124, 58, 237, 0.13);
            background: linear-gradient(145deg, #fff 0%, #f8f5ff 100%);
        }
        .asm-erp-stat:nth-child(5) {
            border-color: rgba(8, 145, 178, 0.13);
            background: linear-gradient(145deg, #fff 0%, #f3fbfd 100%);
        }
        .asm-erp-stat:nth-child(6) {
            border-color: rgba(180, 83, 9, 0.14);
            background: linear-gradient(145deg, #fff 0%, #fff8ef 100%);
        }
        .asm-erp-stat label {
            min-height: 24px;
            font-size: 9px;
            line-height: 1.18;
            letter-spacing: .03em;
        }
        .asm-erp-stat strong {
            margin-top: 7px;
            font-size: 22px;
        }
        .asm-erp-stat small {
            margin-top: 6px;
            font-size: 10px;
            line-height: 1.2;
        }
        .asm-erp-item { grid-template-columns: 1fr; gap: 8px; }
        .asm-erp-item time { text-align: left; }
    }
</style>
@endpush

@section('content')
@php
    $kpis = $dashboard['kpis'];
    $pipeline = $dashboard['pipeline'];
    $canUseAsmDialer = auth()->user()?->isAssistantSalesManager() || auth()->user()?->isSeniorManager();
@endphp

<div class="asm-erp-shell">
    <section class="asm-erp-hero">
        <div>
            <p class="asm-erp-kicker">Assistant Sales Desk</p>
            <h1 class="asm-erp-title">{{ $dashboard['greeting'] }}, {{ auth()->user()->name }}</h1>
            <p class="asm-erp-subtitle">One screen for priority calls, visits, follow-ups, pipeline health, and team movement.</p>
        </div>
        <div class="asm-erp-hero-actions">
            <div class="asm-erp-time">
                {{ $dashboard['generatedAt']->format('h:i A') }}
                <span>{{ $dashboard['generatedAt']->format('d M Y') }}</span>
            </div>
            <a class="asm-erp-btn" href="{{ route('sales-manager.leads') }}"><i class="fas fa-users"></i> Leads</a>
            <a class="asm-erp-btn primary" href="{{ route('sales-manager.tasks') }}"><i class="fas fa-list-check"></i> My Work</a>
            @if($canUseAsmDialer)
                <button type="button" class="asm-erp-btn dialer" data-asm-dialer-open><i class="fas fa-phone-alt"></i> Dialer</button>
            @endif
        </div>
    </section>

    <section class="asm-erp-grid">
        <div class="asm-erp-stat"><label>Fresh Today</label><strong>{{ number_format($kpis['today_leads']) }}</strong><small>Newly received leads</small></div>
        <div class="asm-erp-stat"><label>Open Tasks</label><strong>{{ number_format($kpis['open_tasks']) }}</strong><small>Pending action queue</small></div>
        <div class="asm-erp-stat"><label>Overdue</label><strong>{{ number_format($kpis['overdue_tasks']) }}</strong><small>Needs attention first</small></div>
        <div class="asm-erp-stat"><label>Meetings Today</label><strong>{{ number_format($kpis['today_meetings']) }}</strong><small>Scheduled for today</small></div>
        <div class="asm-erp-stat"><label>Visits Today</label><strong>{{ number_format($kpis['today_visits']) }}</strong><small>Site visit plan</small></div>
        <div class="asm-erp-stat"><label>Closures</label><strong>{{ number_format($kpis['month_closures']) }}</strong><small>This month</small></div>
    </section>

    <section class="asm-erp-main">
        <div class="asm-erp-card">
            <div class="asm-erp-card-head asm-erp-work-head">
                <div>
                    <h2>Priority Work Queue</h2>
                    <span>Today only: pending tasks and follow-ups that are not overdue</span>
                </div>
                <div class="asm-erp-work-summary">
                    <a class="asm-erp-work-chip" href="{{ route('sales-manager.tasks', ['date_filter' => 'today']) }}"><strong>{{ number_format($kpis['priority_work'] ?? 0) }}</strong> All</a>
                    <a class="asm-erp-work-chip warning" href="{{ route('sales-manager.tasks', ['status' => 'pending', 'date_filter' => 'today']) }}"><strong>{{ number_format($dashboard['taskFocus']['pending']['count'] ?? 0) }}</strong> Pending</a>
                    <a class="asm-erp-work-chip" href="{{ route('sales-manager.tasks', ['focus' => 'followups', 'date_filter' => 'today']) }}"><strong>{{ number_format($dashboard['taskFocus']['followups']['count'] ?? 0) }}</strong> Follow-ups</a>
                </div>
            </div>
            <div class="asm-erp-list">
                @forelse($dashboard['queueItems'] as $item)
                    <a class="asm-erp-item" href="{{ $item['url'] }}">
                        <span class="asm-erp-pill">{{ $item['type'] }}</span>
                        <div class="asm-erp-item-copy">
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['action_title'] ?? $item['type'] }} @if(!empty($item['detail'])) - {{ $item['detail'] }} @endif</p>
                            <span class="asm-erp-status-chip">{{ $item['status'] }}</span>
                        </div>
                        <time>{{ $item['time'] ? $item['time']->format('d M, h:i A') : 'No time' }}</time>
                    </a>
                @empty
                    <div class="asm-erp-empty">No open priority work right now.</div>
                @endforelse
            </div>
        </div>

        <div class="asm-erp-card asm-erp-overdue-card">
            <div class="asm-erp-card-head">
                <div>
                    <h2>Overdue Tasks</h2>
                    <span>Needs attention first</span>
                </div>
                <a class="asm-erp-work-chip danger" href="{{ route('sales-manager.tasks', ['status' => 'overdue']) }}"><strong>{{ number_format($kpis['overdue_tasks']) }}</strong> Overdue</a>
            </div>
            <div class="asm-erp-list">
                @forelse(($dashboard['taskFocus']['overdue']['items'] ?? collect()) as $item)
                    <a class="asm-erp-item" href="{{ $item['url'] }}">
                        <span class="asm-erp-pill">Overdue</span>
                        <div>
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['action_title'] ?? 'Task' }} @if(!empty($item['detail'])) - {{ $item['detail'] }} @endif</p>
                        </div>
                        <time>{{ $item['time'] ? $item['time']->format('d M, h:i A') : 'No time' }}</time>
                    </a>
                @empty
                    <div class="asm-erp-empty">No overdue tasks. Good control.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="asm-erp-section">
        <div class="asm-erp-card">
            <div class="asm-erp-card-head">
                <div>
                    <h2>Pipeline Snapshot</h2>
                    <span>Current lead movement</span>
                </div>
            </div>
            <div class="asm-erp-pipeline">
                <a class="asm-erp-pipe" href="{{ route('sales-manager.leads') }}"><strong>{{ number_format($pipeline['fresh']) }}</strong><span>Fresh</span></a>
                <a class="asm-erp-pipe" href="{{ route('sales-manager.leads') }}"><strong>{{ number_format($pipeline['follow_up']) }}</strong><span>Follow-up</span></a>
                <a class="asm-erp-pipe" href="{{ route('sales-manager.site-visits') }}"><strong>{{ number_format($pipeline['visit_done']) }}</strong><span>Visit done</span></a>
                <a class="asm-erp-pipe" href="{{ route('sales-manager.closed') }}"><strong>{{ number_format($pipeline['closed']) }}</strong><span>Closed</span></a>
            </div>
        </div>

        <div class="asm-erp-side-stack">
            <div class="asm-erp-card">
                <div class="asm-erp-card-head">
                    <div>
                        <h2>Attention</h2>
                        <span>Exceptions to clear</span>
                    </div>
                </div>
                <div class="asm-erp-list">
                    @foreach($dashboard['alerts'] as $alert)
                        <a class="asm-erp-alert {{ $alert['tone'] }}" href="{{ $alert['url'] }}">
                            <strong>{{ $alert['label'] }}</strong>
                            <span>{{ number_format($alert['value']) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="asm-erp-attendance">
                @include('attendance._widget', ['attendanceWidgetMode' => 'sales_manager_dashboard'])
            </div>
        </div>
    </section>

    @if($dashboard['teamSnapshot']->isNotEmpty())
        <section class="asm-erp-card">
            <div class="asm-erp-card-head">
                <div>
                    <h2>Team Snapshot</h2>
                    <span>Direct team live workload</span>
                </div>
                <a class="asm-erp-btn" href="{{ route('sales-manager.team') }}">Team</a>
            </div>
            <div class="asm-erp-list">
                @foreach($dashboard['teamSnapshot'] as $member)
                    <div class="asm-erp-team-row">
                        <strong>{{ $member['name'] }}</strong>
                        <span>{{ number_format($member['fresh']) }} fresh</span>
                        <span>{{ number_format($member['tasks']) }} tasks</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

@if($canUseAsmDialer)
<div id="asmDialerModal" class="asm-dialer-modal" aria-hidden="true">
    <div class="asm-dialer-card">
        <div class="asm-dialer-head">
            <div>
                <h3>Dialer</h3>
                <p>MCube cloud call ke liye number dial karein.</p>
            </div>
            <button type="button" class="asm-dialer-close" data-asm-dialer-close aria-label="Close dialer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="asm-dialer-body">
            <div class="asm-dialer-display">
                <span class="asm-dialer-prefix">+91</span>
                <input id="asmDialerPhoneInput" class="asm-dialer-input" type="text" inputmode="none" maxlength="10" autocomplete="off" placeholder="9876543210" readonly aria-label="Dialed phone number">
            </div>
            <div class="asm-dialer-grid">
                @foreach([1,2,3,4,5,6,7,8,9] as $digit)
                    <button type="button" class="asm-dialer-key" data-asm-dialer-key="{{ $digit }}">{{ $digit }}</button>
                @endforeach
                <button type="button" class="asm-dialer-key" data-asm-dialer-clear>Clear</button>
                <button type="button" class="asm-dialer-key" data-asm-dialer-key="0">0</button>
                <button type="button" class="asm-dialer-key" data-asm-dialer-backspace><i class="fas fa-backspace"></i></button>
            </div>
            <div class="asm-dialer-actions">
                <button type="button" class="asm-dialer-action" data-asm-dialer-close>Cancel</button>
                <button type="button" class="asm-dialer-action asm-dialer-call" id="asmDialerCallButton">
                    <i class="fas fa-phone-alt"></i> <span>Call Now</span>
                </button>
            </div>
            <div class="asm-dialer-status" id="asmDialerStatus"></div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    function fitAsmErpDashboardWidth() {
        if (window.innerWidth < 768) return;

        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        if (!sidebar || !mainContent) return;

        const sidebarWidth = Math.round(sidebar.getBoundingClientRect().width || 0);
        const reservedWidth = sidebarWidth > 0 ? sidebarWidth : (document.body.classList.contains('sidebar-collapsed') ? 78 : 232);

        mainContent.style.setProperty('margin-left', reservedWidth + 'px', 'important');
        mainContent.style.setProperty('width', 'calc(100vw - ' + reservedWidth + 'px)', 'important');
        mainContent.style.setProperty('max-width', 'calc(100vw - ' + reservedWidth + 'px)', 'important');
    }

    window.addEventListener('load', fitAsmErpDashboardWidth);
    window.addEventListener('resize', fitAsmErpDashboardWidth);
    document.addEventListener('DOMContentLoaded', fitAsmErpDashboardWidth);
    setTimeout(fitAsmErpDashboardWidth, 150);
    setTimeout(fitAsmErpDashboardWidth, 500);
})();
</script>
@if($canUseAsmDialer)
<script>
(function () {
    const token = @json($api_token ?? session('api_token') ?? '');
    const endpoint = @json(url('/api/sales-manager/dialer/call'));

    function phone() {
        return (document.getElementById('asmDialerPhoneInput')?.value || '').replace(/\D+/g, '').slice(0, 10);
    }

    function setPhone(value) {
        const input = document.getElementById('asmDialerPhoneInput');
        if (!input) return;
        input.value = String(value || '').replace(/\D+/g, '').slice(0, 10);
        syncCallButton();
    }

    function setStatus(message, type) {
        const status = document.getElementById('asmDialerStatus');
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('success', type === 'success');
        status.classList.toggle('error', type === 'error');
    }

    function syncCallButton(loading) {
        const button = document.getElementById('asmDialerCallButton');
        if (!button) return;
        button.disabled = !!loading || phone().length !== 10;
        const label = button.querySelector('span');
        if (label) label.textContent = loading ? 'Calling...' : 'Call Now';
    }

    function openDialer() {
        const modal = document.getElementById('asmDialerModal');
        if (!modal) return;
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        setStatus('');
        syncCallButton();
    }

    function closeDialer() {
        const modal = document.getElementById('asmDialerModal');
        if (!modal) return;
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        setStatus('');
    }

    async function submitCall() {
        const dialed = phone();
        if (!/^[6-9]\d{9}$/.test(dialed)) {
            setStatus('Valid 10 digit Indian number enter karein.', 'error');
            return;
        }

        syncCallButton(true);
        setStatus('MCube call initiate ho rahi hai...');

        try {
            const headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (token) headers.Authorization = `Bearer ${token}`;

            const response = await fetch(endpoint, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({ phone: dialed }),
            });
            const result = await response.json().catch(() => ({}));
            syncCallButton(false);

            if (response.ok && result.success) {
                setStatus(result.message || 'Call initiated via MCube.', 'success');
                return;
            }

            const firstValidationError = result.errors ? Object.values(result.errors).flat().find(Boolean) : null;
            setStatus(firstValidationError || result.message || 'Call initiate nahi ho payi.', 'error');
        } catch (error) {
            syncCallButton(false);
            setStatus(error.message || 'Network error. Please try again.', 'error');
        }
    }

    document.querySelectorAll('[data-asm-dialer-open]').forEach((button) => button.addEventListener('click', openDialer));
    document.querySelectorAll('[data-asm-dialer-close]').forEach((button) => button.addEventListener('click', closeDialer));
    document.querySelectorAll('[data-asm-dialer-key]').forEach((button) => button.addEventListener('click', () => {
        setPhone(phone() + button.dataset.asmDialerKey);
        setStatus('');
    }));
    document.querySelector('[data-asm-dialer-clear]')?.addEventListener('click', () => {
        setPhone('');
        setStatus('');
    });
    document.querySelector('[data-asm-dialer-backspace]')?.addEventListener('click', () => {
        setPhone(phone().slice(0, -1));
        setStatus('');
    });
    document.getElementById('asmDialerPhoneInput')?.addEventListener('input', (event) => {
        setPhone(event.target.value);
        setStatus('');
    });
    document.getElementById('asmDialerCallButton')?.addEventListener('click', submitCall);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeDialer();
    });
    syncCallButton();
})();
</script>
@endif
@endpush
