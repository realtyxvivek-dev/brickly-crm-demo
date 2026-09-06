@once
    <style>
        .global-notification-center {
            position: relative;
            flex-shrink: 0;
        }

        .global-announcement-banners {
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            width: min(960px, calc(100vw - 32px));
            display: none;
            gap: 10px;
            z-index: 2800;
        }

        .global-announcement-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 16px;
            border-radius: 18px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(16px);
            color: #fff;
        }

        .global-announcement-banner.priority-normal { background: linear-gradient(135deg, #0f5b42, #1b7f5d); }
        .global-announcement-banner.priority-important { background: linear-gradient(135deg, #8a5a00, #f59e0b); }
        .global-announcement-banner.priority-urgent { background: linear-gradient(135deg, #991b1b, #ef4444); }

        .global-announcement-banner-copy {
            min-width: 0;
            flex: 1;
        }

        .global-announcement-banner-top {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .global-announcement-banner-top span {
            border-radius: 999px;
            padding: 3px 8px;
            background: rgba(255, 255, 255, 0.18);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
        }

        .global-announcement-banner-copy p {
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
            opacity: 0.94;
        }

        .global-announcement-banner-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .global-announcement-banner-actions button {
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.26);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 12px;
            cursor: pointer;
        }

        .global-announcement-banner-actions button.is-solid {
            background: #fff;
            color: #0f5b42;
            border-color: #fff;
        }

        .global-notification-bell {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid rgba(15, 91, 66, 0.12);
            background: #f7f6f3;
            color: #063A1C;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s ease;
            box-shadow: 0 4px 12px rgba(6, 58, 28, 0.08);
        }

        .global-notification-bell:hover,
        .global-notification-bell.is-open {
            background: #ecf7f1;
            border-color: rgba(15, 91, 66, 0.24);
            transform: translateY(-1px);
        }

        .global-notification-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.28);
        }

        .global-notification-panel {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: min(420px, 92vw);
            max-height: min(72vh, 620px);
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(15, 91, 66, 0.12);
            box-shadow: 0 24px 60px rgba(6, 58, 28, 0.18);
            overflow: hidden;
            display: none;
            z-index: 1400;
        }

        .global-notification-panel.is-open {
            display: flex;
            flex-direction: column;
        }

        .global-notification-panel-header {
            padding: 18px 18px 12px;
            border-bottom: 1px solid #edf2ef;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdfa 100%);
        }

        .global-notification-panel-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .global-notification-panel-title h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #063A1C;
        }

        .global-notification-meta {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
        }

        .global-notification-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }

        .global-notification-tab {
            border: 1px solid #d7e5de;
            background: #f8fbf9;
            color: #355246;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .global-notification-tab.is-active {
            background: #0f5b42;
            color: #fff;
            border-color: #0f5b42;
        }

        .global-notification-tab-count {
            min-width: 20px;
            height: 20px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.2);
            color: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            padding: 0 6px;
        }

        .global-notification-toolbar,
        .global-notification-toolbar-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .global-notification-toolbar {
            justify-content: space-between;
        }

        .global-notification-toolbar button {
            background: none;
            border: none;
            color: #0f5b42;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
        }

        .global-notification-list {
            overflow-y: auto;
            padding: 8px;
            background: #fff;
            min-height: 0;
        }

        .global-notification-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            border-radius: 14px;
            transition: 0.2s ease;
            border: 1px solid transparent;
        }

        .global-notification-item + .global-notification-item {
            margin-top: 8px;
        }

        .global-notification-item.is-clickable {
            cursor: pointer;
        }

        .global-notification-item.is-clickable:hover {
            background: #f7fbf8;
            border-color: #d9e8e0;
        }

        .global-notification-item.is-unread {
            background: #f5faf7;
            border-color: #dceddf;
        }

        .global-notification-item-dot {
            width: 10px;
            height: 10px;
            margin-top: 6px;
            border-radius: 999px;
            background: #0f5b42;
            flex-shrink: 0;
        }

        .global-notification-item-body {
            min-width: 0;
            flex: 1;
        }

        .global-notification-item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 4px;
        }

        .global-notification-item-title {
            font-size: 14px;
            font-weight: 800;
            color: #063A1C;
            margin: 0;
            line-height: 1.35;
        }

        .global-notification-item-message {
            font-size: 13px;
            line-height: 1.5;
            color: #4b5563;
            margin: 0 0 8px;
            white-space: pre-wrap;
        }

        .global-notification-item-meta,
        .global-announcement-inline-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .global-notification-item-meta {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .global-notification-inline-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 10px 0 8px;
        }

        .global-notification-inline-actions button {
            border: 1px solid transparent;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .global-notification-inline-actions .is-approve {
            background: #0f5b42;
            color: #fff;
        }

        .global-notification-inline-actions .is-reject {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #be123c;
        }

        .global-announcement-pill {
            padding: 3px 8px;
            border-radius: 999px;
            background: #edf8f2;
            color: #0f5b42;
            font-size: 11px;
            font-weight: 700;
        }

        .global-announcement-pill.is-warning {
            background: #fff4d6;
            color: #a16207;
        }

        .global-announcement-pill.is-success {
            background: #dcfce7;
            color: #166534;
        }

        .global-notification-item-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .global-notification-action {
            border: none;
            background: #f3f4f6;
            color: #4b5563;
            border-radius: 999px;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .global-notification-empty {
            padding: 36px 18px;
            text-align: center;
            color: #94a3b8;
        }

        .global-notification-empty i {
            font-size: 28px;
            margin-bottom: 10px;
            opacity: 0.6;
        }

        .global-announcement-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.62);
            display: none;
            align-items: center;
            justify-content: center;
            padding: max(20px, env(safe-area-inset-top)) 20px max(20px, env(safe-area-inset-bottom));
            z-index: 3200;
        }

        .global-announcement-modal.is-open {
            display: flex;
        }

        .global-announcement-modal-card {
            width: min(640px, 100%);
            max-height: min(78vh, 760px);
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .global-announcement-modal-header {
            padding: 22px 24px 14px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }

        .global-announcement-modal-header h3 {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
        }

        .global-announcement-modal-meta {
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .global-announcement-modal-close {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            border: none;
            background: #f8fafc;
            color: #475569;
            cursor: pointer;
        }

        .global-announcement-modal-body {
            padding: 24px;
            overflow-y: auto;
        }

        .global-announcement-modal-body p {
            margin: 0;
            font-size: 15px;
            line-height: 1.7;
            color: #334155;
            white-space: pre-wrap;
        }

        .global-announcement-modal-attachment {
            margin-top: 18px;
            padding: 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .global-announcement-modal-attachment a {
            color: #0f5b42;
            font-weight: 700;
            text-decoration: none;
        }

        .global-announcement-modal-footer {
            padding: 18px 24px 24px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .global-announcement-modal-footer button,
        .global-announcement-modal-footer a {
            border-radius: 999px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .global-announcement-modal-footer .is-primary {
            border: none;
            background: linear-gradient(135deg, #0f5b42, #15803d);
            color: #fff;
        }

        .global-announcement-modal-footer .is-secondary {
            border: 1px solid #dbe3eb;
            background: #fff;
            color: #334155;
        }

        .announcement-modal-open {
            overflow: hidden;
        }

        @media (max-width: 767px) {
            .global-announcement-banners {
                top: 10px;
                width: calc(100vw - 20px);
            }

            .global-announcement-banner {
                flex-direction: column;
                align-items: flex-start;
            }

            .global-announcement-banner-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .global-notification-panel {
                position: fixed;
                top: max(74px, calc(env(safe-area-inset-top, 0px) + 66px));
                left: max(10px, env(safe-area-inset-left, 0px));
                right: max(10px, env(safe-area-inset-right, 0px));
                width: auto;
                min-width: 0;
                max-height: calc(100dvh - max(96px, calc(env(safe-area-inset-top, 0px) + 88px)) - env(safe-area-inset-bottom, 0px));
                border-radius: 18px;
                z-index: 5000;
            }

            .global-notification-list {
                max-height: calc(100dvh - 292px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px));
            }

            .global-notification-panel-header {
                padding: 14px 14px 10px;
            }

            .global-notification-panel-title h3 {
                font-size: 17px;
            }

            .global-notification-tabs {
                gap: 8px;
            }

            .global-notification-tab {
                flex: 1 1 0;
                justify-content: center;
                padding: 8px 9px;
                font-size: 12px;
            }

            .global-announcement-modal {
                padding: max(18px, env(safe-area-inset-top)) 14px max(18px, env(safe-area-inset-bottom));
                align-items: center;
                justify-content: center;
            }

            .global-announcement-modal-card {
                width: min(100%, 560px);
                max-height: min(82dvh, 720px);
                border-radius: 28px;
            }

            .global-announcement-modal-header {
                padding: 18px 18px 12px;
            }

            .global-announcement-modal-header h3 {
                font-size: 20px;
                line-height: 1.15;
            }

            .global-announcement-modal-body {
                padding: 18px;
            }

            .global-announcement-modal-body p {
                font-size: 16px;
                line-height: 1.75;
            }

            .global-announcement-modal-footer {
                padding: 14px 18px 18px;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .global-announcement-modal-footer button,
            .global-announcement-modal-footer a {
                width: 100%;
                min-height: 46px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
                padding: 11px 14px;
            }

            .global-announcement-modal-footer .is-primary {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 480px) {
            .global-announcement-modal {
                padding: max(16px, env(safe-area-inset-top)) 10px max(16px, env(safe-area-inset-bottom));
            }

            .global-announcement-modal-card {
                width: calc(100vw - 20px);
                max-height: min(84dvh, 760px);
                border-radius: 24px;
            }

            .global-announcement-modal-header {
                padding: 16px 16px 10px;
            }

            .global-announcement-modal-meta {
                font-size: 11px;
                line-height: 1.5;
            }

            .global-announcement-modal-body {
                padding: 16px;
            }

            .global-announcement-modal-body p {
                font-size: 15px;
                line-height: 1.7;
            }

            .global-announcement-modal-footer {
                grid-template-columns: 1fr;
                padding: 12px 16px 16px;
            }
        }
    </style>

    @push('scripts')
        <script src="{{ asset('js/global-notification-center.js') }}?v={{ @filemtime(public_path('js/global-notification-center.js')) ?: time() }}"></script>
    @endpush
@endonce

<div class="global-notification-center" data-notification-center data-api-base="{{ url('/api') }}">
    <div class="global-announcement-banners" data-announcement-banners></div>

    <button type="button" class="global-notification-bell" data-notification-bell aria-label="Open notifications">
        <i class="fas fa-bell"></i>
        <span class="global-notification-badge" data-notification-badge>0</span>
    </button>

    <div class="global-notification-panel" data-notification-panel>
        <div class="global-notification-panel-header">
            <div class="global-notification-panel-title">
                <h3>Updates</h3>
                <span class="global-notification-meta" data-total-unread-label>0 unread</span>
            </div>

            <div class="global-notification-tabs">
                <button type="button" class="global-notification-tab is-active" data-notification-tab="notifications">
                    Notifications
                    <span class="global-notification-tab-count" data-notification-count="notifications">0</span>
                </button>
                <button type="button" class="global-notification-tab" data-notification-tab="announcements">
                    Announcements
                    <span class="global-notification-tab-count" data-notification-count="announcements">0</span>
                </button>
            </div>

            <div class="global-notification-toolbar">
                <div class="global-notification-toolbar-group" data-toolbar-group="notifications">
                    <button type="button" data-notification-action="mark-all-read">Mark all as read</button>
                    <button type="button" data-notification-action="clear-all">Clear all</button>
                </div>
                <div class="global-notification-toolbar-group" data-toolbar-group="announcements" style="display: none;">
                    <button type="button" data-notification-action="mark-all-announcements-read">Mark all as read</button>
                    <button type="button" data-notification-action="dismiss-all-announcements">Clear all</button>
                </div>
            </div>
        </div>

        <div class="global-notification-list" data-notification-list="notifications"></div>
        <div class="global-notification-list" data-notification-list="announcements" style="display: none;"></div>
    </div>

    <div class="global-announcement-modal" data-announcement-modal>
        <div class="global-announcement-modal-card">
            <div class="global-announcement-modal-header">
                <div>
                    <h3 data-announcement-modal-title>Announcement</h3>
                    <div class="global-announcement-modal-meta" data-announcement-modal-meta></div>
                </div>
                <button type="button" class="global-announcement-modal-close" data-announcement-modal-close>
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div class="global-announcement-modal-body">
                <p data-announcement-modal-message></p>
                <div class="global-announcement-modal-attachment" data-announcement-modal-attachment style="display: none;">
                    <a href="#" target="_blank" rel="noopener" data-announcement-modal-attachment-link></a>
                </div>
            </div>
            <div class="global-announcement-modal-footer">
                <button type="button" class="is-secondary" data-announcement-modal-dismiss>Hide popup</button>
                <button type="button" class="is-secondary" data-announcement-modal-close-footer>Close</button>
                <button type="button" class="is-secondary" data-announcement-modal-cta>Open</button>
                <button type="button" class="is-primary" data-announcement-modal-ack>Acknowledge</button>
            </div>
        </div>
    </div>
</div>
