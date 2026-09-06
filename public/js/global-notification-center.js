(function () {
    class GlobalNotificationCenter {
        constructor(root) {
            this.root = root;
            this.apiBase = root.dataset.apiBase || (window.location.origin + '/api');
            this.bell = root.querySelector('[data-notification-bell]');
            this.badge = root.querySelector('[data-notification-badge]');
            this.panel = root.querySelector('[data-notification-panel]');
            this.totalUnreadLabel = root.querySelector('[data-total-unread-label]');
            this.bannerHost = root.querySelector('[data-announcement-banners]');
            this.modal = root.querySelector('[data-announcement-modal]');
            this.modalTitle = root.querySelector('[data-announcement-modal-title]');
            this.modalMeta = root.querySelector('[data-announcement-modal-meta]');
            this.modalMessage = root.querySelector('[data-announcement-modal-message]');
            this.modalAttachment = root.querySelector('[data-announcement-modal-attachment]');
            this.modalAttachmentLink = root.querySelector('[data-announcement-modal-attachment-link]');
            this.modalCta = root.querySelector('[data-announcement-modal-cta]');
            this.modalAck = root.querySelector('[data-announcement-modal-ack]');
            this.modalClose = root.querySelector('[data-announcement-modal-close]');
            this.modalCloseFooter = root.querySelector('[data-announcement-modal-close-footer]');
            this.modalDismiss = root.querySelector('[data-announcement-modal-dismiss]');
            this.tabButtons = Array.from(root.querySelectorAll('[data-notification-tab]'));
            this.toolbarGroups = {
                notifications: root.querySelector('[data-toolbar-group="notifications"]'),
                announcements: root.querySelector('[data-toolbar-group="announcements"]'),
            };
            this.lists = {
                notifications: root.querySelector('[data-notification-list="notifications"]'),
                announcements: root.querySelector('[data-notification-list="announcements"]'),
            };
            this.countNodes = {
                notifications: root.querySelector('[data-notification-count="notifications"]'),
                announcements: root.querySelector('[data-notification-count="announcements"]'),
            };
            this.activeTab = 'notifications';
            this.state = {
                notifications: [],
                announcements: [],
                counts: {
                    notifications_unread: 0,
                    announcements_unread: 0,
                    total_unread: 0,
                },
            };
            this.interval = null;
            this.liveModalAnnouncementId = null;
            this.currentAnnouncement = null;
            this.boundHandleOutsideClick = this.handleOutsideClick.bind(this);
            this.boundHandleVisibilityChange = this.handleVisibilityChange.bind(this);
            this.shownAnnouncementIds = this.loadShownAnnouncementIds();
        }

        init() {
            if (!this.bell || !this.panel) {
                return;
            }

            this.portalFloatingLayers();

            this.bell.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.toggle();
            });

            this.tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    this.switchTab(button.dataset.notificationTab || 'notifications');
                });
            });

            this.root.querySelector('[data-notification-action="mark-all-read"]')
                ?.addEventListener('click', () => this.markAllRead());
            this.root.querySelector('[data-notification-action="clear-all"]')
                ?.addEventListener('click', () => this.clearAll());
            this.root.querySelector('[data-notification-action="mark-all-announcements-read"]')
                ?.addEventListener('click', () => this.markAllAnnouncementsRead());
            this.root.querySelector('[data-notification-action="dismiss-all-announcements"]')
                ?.addEventListener('click', () => this.dismissAllAnnouncements());

            this.modalClose?.addEventListener('click', () => this.closeAnnouncementModal());
            this.modalCloseFooter?.addEventListener('click', () => this.closeAnnouncementModal());
            this.modalDismiss?.addEventListener('click', () => this.handleAnnouncementDismiss());
            this.modalAck?.addEventListener('click', () => this.acknowledgeCurrentAnnouncement());
            this.modalCta?.addEventListener('click', () => this.clickCurrentAnnouncementCta());

            this.modal?.addEventListener('click', (event) => {
                if (event.target === this.modal) {
                    this.closeAnnouncementModal();
                }
            });

            document.addEventListener('click', this.boundHandleOutsideClick);
            document.addEventListener('visibilitychange', this.boundHandleVisibilityChange);

            this.initializeRealtime();
            this.load();
            this.interval = window.setInterval(() => {
                if (!document.hidden) {
                    this.load();
                }
            }, 30000);

            window.GlobalNotificationCenter = this;
            window.loadNotifications = () => this.load();
            window.handleIncomingFcmMessage = (payload) => this.handleIncomingFcmMessage(payload);
        }

        portalFloatingLayers() {
            if (this.panel && this.panel.parentElement !== document.body) {
                document.body.appendChild(this.panel);
            }

            if (this.bannerHost && this.bannerHost.parentElement !== document.body) {
                document.body.appendChild(this.bannerHost);
            }

            if (this.modal && this.modal.parentElement !== document.body) {
                document.body.appendChild(this.modal);
            }
        }

        destroy() {
            document.removeEventListener('click', this.boundHandleOutsideClick);
            document.removeEventListener('visibilitychange', this.boundHandleVisibilityChange);
            if (this.interval) {
                window.clearInterval(this.interval);
            }
        }

        loadShownAnnouncementIds() {
            try {
                return new Set(JSON.parse(localStorage.getItem('shown-announcement-ids') || '[]'));
            } catch (error) {
                return new Set();
            }
        }

        persistShownAnnouncementIds() {
            try {
                localStorage.setItem('shown-announcement-ids', JSON.stringify(Array.from(this.shownAnnouncementIds)));
            } catch (error) {
                // Ignore storage failures.
            }
        }

        getToken() {
            const metaToken = document.querySelector('meta[name="api-token"]')?.getAttribute('content');
            if (metaToken) {
                return metaToken;
            }

            try {
                const managerToken = typeof window.getManagerApiToken === 'function'
                    ? window.getManagerApiToken()
                    : '';

                return managerToken
                    || window.API_TOKEN
                    || sessionStorage.getItem('api_token')
                    || sessionStorage.getItem('sales_manager_token')
                    || localStorage.getItem('sales_manager_token')
                    || localStorage.getItem('telecaller_token')
                    || localStorage.getItem('auth_token')
                    || localStorage.getItem('sales_executive_api_token')
                    || '';
            } catch (error) {
                return '';
            }
        }

        headers(extra = {}) {
            const token = this.getToken();
            const headers = {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                ...extra,
            };

            if (token) {
                headers.Authorization = `Bearer ${token}`;
            }

            return headers;
        }

        async request(url, options = {}) {
            const response = await fetch(url, {
                credentials: 'same-origin',
                ...options,
                headers: this.headers(options.headers || {}),
            });

            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            return response.json();
        }

        async load() {
            try {
                const payload = await this.request(`${this.apiBase}/notifications`);
                this.state.notifications = payload.notifications || payload.data || [];
                this.state.announcements = payload.announcements || [];
                this.state.counts = payload.counts || {
                    notifications_unread: payload.personal_unread_count || 0,
                    announcements_unread: payload.announcement_unread_count || 0,
                    total_unread: payload.unread_count || 0,
                };
                this.render();
                this.processLiveAnnouncements();
            } catch (error) {
                console.error('Notification center load failed:', error);
            }
        }

        render() {
            this.renderBadge();
            this.renderTabs();
            this.renderList('notifications', this.state.notifications);
            this.renderList('announcements', this.state.announcements);
            this.renderAnnouncementBanners();
            this.updateToolbar();
        }

        renderBadge() {
            const totalUnread = this.state.counts.total_unread || 0;
            if (!this.badge || !this.totalUnreadLabel) {
                return;
            }

            if (totalUnread > 0) {
                this.badge.style.display = 'inline-flex';
                this.badge.textContent = totalUnread > 99 ? '99+' : String(totalUnread);
            } else {
                this.badge.style.display = 'none';
            }

            this.totalUnreadLabel.textContent = `${totalUnread} unread`;
        }

        renderTabs() {
            if (this.countNodes.notifications) {
                this.countNodes.notifications.textContent = String(this.state.counts.notifications_unread || 0);
            }
            if (this.countNodes.announcements) {
                this.countNodes.announcements.textContent = String(this.state.counts.announcements_unread || 0);
            }

            this.tabButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.notificationTab === this.activeTab);
            });
        }

        renderList(type, items) {
            const list = this.lists[type];
            if (!list) {
                return;
            }

            const visibleItems = type === 'announcements'
                ? items.filter((item) => !item.dismissed_at || item.sticky)
                : items;

            if (!visibleItems.length) {
                list.innerHTML = `
                    <div class="global-notification-empty">
                        <i class="fas ${type === 'announcements' ? 'fa-bullhorn' : 'fa-bell-slash'}"></i>
                        <p>${type === 'announcements' ? 'No announcements right now' : 'You are all caught up'}</p>
                    </div>
                `;
                return;
            }

            list.innerHTML = visibleItems.map((item) => this.renderItem(type, item)).join('');

            list.querySelectorAll('[data-item-action="open"]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    const itemId = Number(event.currentTarget.dataset.itemId);
                    const itemType = event.currentTarget.dataset.itemType;
                    if (itemType === 'announcements') {
                        this.openAnnouncement(itemId, { source: 'panel' });
                    } else {
                        this.openNotification(itemId);
                    }
                });
            });

            list.querySelectorAll('[data-item-action="clear"]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.clearNotification(Number(event.currentTarget.dataset.itemId));
                });
            });

            list.querySelectorAll('[data-item-action="dismiss"]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.dismissAnnouncement(Number(event.currentTarget.dataset.itemId));
                });
            });

            list.querySelectorAll('[data-outside-punch-action]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    this.handleOutsidePunchAction(
                        event.currentTarget.dataset.outsidePunchAction,
                        event.currentTarget.dataset.actionUrl,
                        Number(event.currentTarget.dataset.itemId)
                    );
                });
            });
        }

        renderItem(type, item) {
            const unread = !item.read_at || (item.requires_acknowledge && !item.acknowledged_at);
            const meta = [this.formatTime(item.created_at)];
            const itemData = item.data || {};

            if (type === 'announcements') {
                meta.push(this.escape((item.priority || 'normal').toUpperCase()));
                if (item.sender_name) {
                    meta.push(`By ${this.escape(item.sender_name)}`);
                }
                if (item.status && item.status !== 'active') {
                    meta.push(this.escape(item.status));
                }
            }

            const clickable = true;
            const statusBadges = type === 'announcements'
                ? `
                    <div class="global-announcement-inline-meta">
                        ${item.attachment_url ? '<span class="global-announcement-pill">Attachment</span>' : ''}
                        ${item.action_url ? '<span class="global-announcement-pill">Action</span>' : ''}
                        ${item.requires_acknowledge ? `<span class="global-announcement-pill ${item.acknowledged_at ? 'is-success' : 'is-warning'}">${item.acknowledged_at ? 'Acknowledged' : 'Ack required'}</span>` : ''}
                    </div>
                `
                : '';
            const outsidePunchActions = type === 'notifications' && itemData.kind === 'outside_punch_request'
                ? `
                    <div class="global-notification-inline-actions">
                        ${itemData.approve_url ? `<button type="button" class="is-approve" data-outside-punch-action="approve" data-action-url="${this.escape(itemData.approve_url)}" data-item-id="${item.id}">Approve</button>` : ''}
                        ${itemData.reject_url ? `<button type="button" class="is-reject" data-outside-punch-action="reject" data-action-url="${this.escape(itemData.reject_url)}" data-item-id="${item.id}">Reject</button>` : ''}
                    </div>
                `
                : '';

            return `
                <div class="global-notification-item ${unread ? 'is-unread' : ''} ${clickable ? 'is-clickable' : ''}" data-item-action="open" data-item-type="${type}" data-item-id="${item.id}">
                    ${unread ? '<span class="global-notification-item-dot"></span>' : '<span class="global-notification-item-dot" style="opacity:0.18;"></span>'}
                    <div class="global-notification-item-body">
                        <div class="global-notification-item-top">
                            <h4 class="global-notification-item-title">${this.escape(item.title || 'Update')}</h4>
                            <div class="global-notification-item-actions">
                                ${type === 'announcements' && item.can_dismiss
                                    ? `<button type="button" class="global-notification-action" title="Dismiss popup only" data-item-action="dismiss" data-item-id="${item.id}"><i class="fas fa-xmark"></i></button>`
                                    : ''}
                                ${type === 'notifications'
                                    ? `<button type="button" class="global-notification-action" title="Clear notification" data-item-action="clear" data-item-id="${item.id}"><i class="fas fa-xmark"></i></button>`
                                    : ''}
                            </div>
                        </div>
                        <p class="global-notification-item-message">${this.escape(item.message || '')}</p>
                        ${statusBadges}
                        ${outsidePunchActions}
                        <div class="global-notification-item-meta">${meta.join(' &middot; ')}</div>
                    </div>
                </div>
            `;
        }

        renderAnnouncementBanners() {
            if (!this.bannerHost) {
                return;
            }

            const banners = this.state.announcements.filter((announcement) => (
                announcement.is_active
                && announcement.banner_enabled
                && (!announcement.dismissed_at || announcement.sticky)
            ));

            if (!banners.length) {
                this.bannerHost.innerHTML = '';
                this.bannerHost.style.display = 'none';
                return;
            }

            this.bannerHost.style.display = 'grid';
            this.bannerHost.innerHTML = banners.map((announcement) => `
                <div class="global-announcement-banner priority-${this.escape(announcement.priority || 'normal')}" data-banner-id="${announcement.id}">
                    <div class="global-announcement-banner-copy">
                        <div class="global-announcement-banner-top">
                            <strong>${this.escape(announcement.title)}</strong>
                            <span>${this.escape((announcement.priority || 'normal').toUpperCase())}</span>
                        </div>
                        <p>${this.escape(announcement.message || '')}</p>
                    </div>
                    <div class="global-announcement-banner-actions">
                        <button type="button" data-banner-action="view" data-announcement-id="${announcement.id}">View</button>
                        ${announcement.requires_acknowledge && !announcement.acknowledged_at
                            ? `<button type="button" class="is-solid" data-banner-action="ack" data-announcement-id="${announcement.id}">Acknowledge</button>`
                            : ''}
                        ${announcement.action_url
                            ? `<button type="button" data-banner-action="cta" data-announcement-id="${announcement.id}">${this.escape(announcement.action_label || announcement.primary_cta_label || 'View Announcement')}</button>`
                            : ''}
                        ${announcement.can_dismiss
                            ? `<button type="button" data-banner-action="dismiss" data-announcement-id="${announcement.id}">Hide</button>`
                            : ''}
                    </div>
                </div>
            `).join('');

            this.bannerHost.querySelectorAll('[data-banner-action="view"]').forEach((button) => {
                button.addEventListener('click', () => this.openAnnouncement(Number(button.dataset.announcementId), { source: 'banner' }));
            });
            this.bannerHost.querySelectorAll('[data-banner-action="ack"]').forEach((button) => {
                button.addEventListener('click', () => this.acknowledgeAnnouncement(Number(button.dataset.announcementId)));
            });
            this.bannerHost.querySelectorAll('[data-banner-action="cta"]').forEach((button) => {
                button.addEventListener('click', () => this.clickAnnouncementCta(Number(button.dataset.announcementId)));
            });
            this.bannerHost.querySelectorAll('[data-banner-action="dismiss"]').forEach((button) => {
                button.addEventListener('click', () => this.dismissAnnouncement(Number(button.dataset.announcementId)));
            });
        }

        updateToolbar() {
            Object.entries(this.toolbarGroups).forEach(([key, node]) => {
                if (!node) {
                    return;
                }
                node.style.display = key === this.activeTab ? 'flex' : 'none';
            });

            Object.entries(this.lists).forEach(([key, node]) => {
                if (!node) {
                    return;
                }
                node.style.display = key === this.activeTab ? 'block' : 'none';
            });
        }

        switchTab(tab) {
            this.activeTab = tab;
            this.renderTabs();
            this.updateToolbar();
        }

        toggle() {
            const isOpen = this.panel.classList.contains('is-open');
            if (isOpen) {
                this.close();
                return;
            }

            this.panel.classList.add('is-open');
            this.bell.classList.add('is-open');
            this.load();
        }

        close() {
            this.panel.classList.remove('is-open');
            this.bell.classList.remove('is-open');
        }

        handleOutsideClick(event) {
            if (!this.root.contains(event.target) && !this.panel?.contains(event.target)) {
                this.close();
            }
        }

        handleVisibilityChange() {
            if (!document.hidden) {
                this.load();
            }
        }

        initializeRealtime() {
            const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
            const key = document.querySelector('meta[name="pusher-key"]')?.getAttribute('content');
            const cluster = document.querySelector('meta[name="pusher-cluster"]')?.getAttribute('content') || 'mt1';

            if (!userId || !key || typeof Pusher === 'undefined') {
                return;
            }

            try {
                const pusher = new Pusher(key, {
                    cluster,
                    encrypted: true,
                    authEndpoint: '/broadcasting/auth',
                });

                const channel = pusher.subscribe(`private-user.${userId}`);
                channel.bind('notification.new', (payload) => {
                    const notification = payload && payload.notification ? payload.notification : null;
                    if (!notification || !notification.data) {
                        return;
                    }

                    if (notification.data.kind === 'announcement') {
                        this.handleAnnouncementSignal(notification);
                        return;
                    }

                    this.load();
                });
            } catch (error) {
                console.warn('Notification realtime init failed:', error);
            }
        }

        handleIncomingFcmMessage(payload) {
            const data = payload?.data || payload?.notification || {};
            if (data.kind === 'outside_punch_request' || (data.tag || '').indexOf('outside-punch-request-') === 0) {
                this.load();
                return true;
            }

            if ((data.tag || '').indexOf('announcement-') !== 0) {
                return false;
            }

            this.handleAnnouncementSignal({
                title: data.title || payload?.notification?.title || 'Announcement',
                message: data.body || payload?.notification?.body || '',
                action_url: data.url || data.click_action || '',
                data,
            });
            return true;
        }

        handleAnnouncementSignal(notification) {
            const announcementId = Number(
                notification?.data?.announcement_id
                || String(notification?.data?.tag || '').replace('announcement-', '')
            );
            const fallbackAnnouncement = this.buildAnnouncementFromSignal(notification, announcementId);

            if (fallbackAnnouncement) {
                this.upsertAnnouncement(fallbackAnnouncement, { markUnread: true });
                this.render();
            }

            this.load().then(() => {
                if (announcementId) {
                    const announcement = this.findAnnouncement(announcementId);
                    if (announcement && this.shouldShowAnnouncementPopup(announcement)) {
                        this.openAnnouncement(announcement.id, { source: 'live' });
                        return;
                    }
                }

                this.processLiveAnnouncements();
            }).catch((error) => {
                console.error('Announcement signal handling failed:', error);

                if (fallbackAnnouncement && this.shouldShowAnnouncementPopup(fallbackAnnouncement)) {
                    this.openAnnouncement(fallbackAnnouncement.id, { source: 'live' });
                }
            });
        }

        processLiveAnnouncements() {
            if (document.hidden) {
                return;
            }

            const activeAnnouncements = this.state.announcements.filter((announcement) => announcement.is_active);
            const candidate = activeAnnouncements.find((announcement) => this.shouldShowAnnouncementPopup(announcement));

            if (candidate) {
                this.openAnnouncement(candidate.id, { source: 'live' });
            }
        }

        shouldShowAnnouncementPopup(announcement) {
            if (this.currentAnnouncement && this.currentAnnouncement.id === announcement.id) {
                return false;
            }

            const displayMode = announcement.display_mode
                || (announcement.sticky ? 'critical' : (announcement.priority === 'important' ? 'popup' : 'notification'));

            if (displayMode === 'notification') {
                return false;
            }

            if (displayMode === 'critical' || announcement.sticky) {
                return !announcement.acknowledged_at;
            }

            if (announcement.dismissed_at) {
                return false;
            }

            if (announcement.last_popup_shown_at) {
                return false;
            }

            return !this.shownAnnouncementIds.has(String(announcement.id));
        }

        findAnnouncement(announcementId) {
            return this.state.announcements.find((announcement) => Number(announcement.id) === Number(announcementId)) || null;
        }

        buildAnnouncementFromSignal(notification, explicitAnnouncementId = null) {
            const data = notification?.data || {};
            const announcementId = Number(
                explicitAnnouncementId
                || data.announcement_id
                || String(data.tag || '').replace('announcement-', '')
            );

            if (!announcementId) {
                return null;
            }

            const priority = data.priority || 'normal';
            const displayMode = data.display_mode || (priority === 'urgent' ? 'critical' : (priority === 'important' ? 'popup' : 'notification'));
            const sticky = Boolean(
                data.sticky
                || displayMode === 'critical'
            );
            const requiresAcknowledge = data.requires_acknowledge === undefined
                ? displayMode !== 'notification'
                : Boolean(Number(data.requires_acknowledge)) || data.requires_acknowledge === true || data.requires_acknowledge === 'true';
            const bannerEnabled = Boolean(Number(data.banner_enabled)) || data.banner_enabled === true || data.banner_enabled === 'true';

            return {
                id: announcementId,
                title: notification?.title || data.title || 'Announcement',
                message: notification?.message || data.body || data.message || '',
                priority,
                display_mode: displayMode,
                banner_enabled: bannerEnabled,
                requires_acknowledge: requiresAcknowledge,
                action_label: data.action_label || data.primary_cta_label || (data.url || data.click_action ? 'View Announcement' : null),
                action_url: data.action_url || data.url || data.click_action || notification?.action_url || '',
                primary_cta_label: data.primary_cta_label || data.action_label || 'View Announcement',
                secondary_cta_label: data.secondary_cta_label || (requiresAcknowledge ? 'Acknowledge' : 'Dismiss'),
                attachment_name: data.attachment_name || null,
                attachment_url: data.attachment_url || null,
                target_type: data.target_type || 'all_users',
                sender_name: data.sender_name || '',
                created_at: notification?.created_at || new Date().toISOString(),
                starts_at: data.starts_at || null,
                ends_at: data.ends_at || null,
                read_at: null,
                acknowledged_at: null,
                clicked_at: null,
                dismissed_at: null,
                last_popup_shown_at: null,
                sticky,
                is_active: true,
                status: 'active',
                can_dismiss: !sticky,
                type: 'announcement',
            };
        }

        upsertAnnouncement(announcement, options = {}) {
            if (!announcement || !announcement.id) {
                return;
            }

            const markUnread = options.markUnread !== false;
            const existingIndex = this.state.announcements.findIndex((item) => Number(item.id) === Number(announcement.id));
            let nextAnnouncement = announcement;

            if (existingIndex >= 0) {
                nextAnnouncement = {
                    ...this.state.announcements[existingIndex],
                    ...announcement,
                };

                this.state.announcements.splice(existingIndex, 1, nextAnnouncement);
            } else {
                this.state.announcements.unshift(nextAnnouncement);
            }

            this.state.announcements.sort((left, right) => (
                new Date(right.created_at || 0).getTime() - new Date(left.created_at || 0).getTime()
            ));

            if (markUnread && !nextAnnouncement.read_at) {
                this.syncDerivedCounts();
            }
        }

        syncDerivedCounts() {
            const notificationsUnread = Number(this.state.counts.notifications_unread || 0);
            const announcementsUnread = this.state.announcements.filter((announcement) => (
                !announcement.read_at
                || (announcement.requires_acknowledge && !announcement.acknowledged_at)
            )).length;

            this.state.counts = {
                ...this.state.counts,
                notifications_unread: notificationsUnread,
                announcements_unread: announcementsUnread,
                total_unread: notificationsUnread + announcementsUnread,
            };
        }

        async openAnnouncement(announcementId, options = {}) {
            const announcement = this.findAnnouncement(announcementId);
            if (!announcement) {
                return;
            }

            this.currentAnnouncement = announcement;
            this.liveModalAnnouncementId = options.source === 'live' ? announcement.id : null;

            this.modal?.classList.add('is-open');
            document.body.classList.add('announcement-modal-open');

            this.modalTitle.textContent = announcement.title || 'Announcement';
            this.modalMeta.textContent = [
                this.formatTime(announcement.created_at),
                (announcement.priority || 'normal').toUpperCase(),
                announcement.sender_name ? `By ${announcement.sender_name}` : '',
            ].filter(Boolean).join(' • ');
            this.modalMessage.textContent = announcement.message || '';

            const sticky = Boolean(announcement.sticky && !announcement.acknowledged_at);
            this.modal.dataset.sticky = sticky ? '1' : '0';
            this.modalClose.style.display = sticky ? 'none' : 'inline-flex';
            this.modalDismiss.style.display = sticky || !announcement.can_dismiss || options.source !== 'live' ? 'none' : 'inline-flex';
            this.modalAck.style.display = announcement.requires_acknowledge && !announcement.acknowledged_at ? 'inline-flex' : 'none';
            this.modalAck.textContent = announcement.acknowledged_at ? 'Acknowledged' : (announcement.secondary_cta_label || 'Acknowledge');
            this.modalCta.style.display = announcement.action_url ? 'inline-flex' : 'none';
            this.modalCta.textContent = announcement.action_label || announcement.primary_cta_label || 'View Announcement';

            if (announcement.attachment_url) {
                this.modalAttachment.style.display = 'block';
                this.modalAttachmentLink.href = announcement.attachment_url;
                this.modalAttachmentLink.textContent = announcement.attachment_name || 'Download attachment';
            } else {
                this.modalAttachment.style.display = 'none';
            }

            if (!announcement.read_at) {
                await this.markAnnouncementAsRead(announcement.id, false);
            }

            if (options.source === 'live') {
                this.shownAnnouncementIds.add(String(announcement.id));
                this.persistShownAnnouncementIds();
                await this.markAnnouncementPopupShown(announcement.id);
            }
        }

        closeAnnouncementModal(force = false) {
            if (!this.modal) {
                return;
            }

            const sticky = this.modal.dataset.sticky === '1';
            if (sticky && !force) {
                return;
            }

            this.modal.classList.remove('is-open');
            document.body.classList.remove('announcement-modal-open');
            this.currentAnnouncement = null;
            this.liveModalAnnouncementId = null;
        }

        async handleAnnouncementDismiss() {
            if (!this.currentAnnouncement) {
                return;
            }

            await this.dismissAnnouncement(this.currentAnnouncement.id, false);
            this.closeAnnouncementModal(true);
        }

        async acknowledgeCurrentAnnouncement() {
            if (!this.currentAnnouncement) {
                return;
            }

            await this.acknowledgeAnnouncement(this.currentAnnouncement.id);
            this.closeAnnouncementModal(true);
        }

        async clickCurrentAnnouncementCta() {
            if (!this.currentAnnouncement) {
                return;
            }

            await this.clickAnnouncementCta(this.currentAnnouncement.id);
        }

        async openNotification(notificationId) {
            try {
                const payload = await this.request(`${this.apiBase}/notifications/${notificationId}/click`, {
                    method: 'POST',
                });

                if (payload.url) {
                    window.location.href = payload.url;
                    return;
                }

                await this.load();
            } catch (error) {
                console.error('Notification open failed:', error);
            }
        }

        async markAllRead() {
            try {
                await this.request(`${this.apiBase}/notifications/mark-all-read`, { method: 'POST' });
                await this.load();
            } catch (error) {
                console.error('Mark all read failed:', error);
            }
        }

        async markAllAnnouncementsRead() {
            try {
                await this.request(`${this.apiBase}/notifications/announcements/mark-all-read`, { method: 'POST' });
                await this.load();
            } catch (error) {
                console.error('Mark all announcements read failed:', error);
            }
        }

        async clearNotification(notificationId) {
            try {
                await this.request(`${this.apiBase}/notifications/${notificationId}`, { method: 'DELETE' });
                await this.load();
            } catch (error) {
                console.error('Clear notification failed:', error);
            }
        }

        async handleOutsidePunchAction(action, actionUrl, notificationId) {
            if (!actionUrl) {
                return;
            }

            try {
                const response = await fetch(actionUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: this.headers({
                        Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    }),
                    body: new URLSearchParams({
                        remarks: action === 'approve' ? 'Approved from notification' : 'Rejected from notification',
                    }),
                });

                if (!response.ok) {
                    throw new Error(`Outside punch ${action} failed with status ${response.status}`);
                }

                if (notificationId) {
                    await this.request(`${this.apiBase}/notifications/${notificationId}`, { method: 'DELETE' });
                }

                await this.load();
            } catch (error) {
                console.error('Outside punch action failed:', error);
            }
        }

        async clearAll() {
            try {
                await this.request(`${this.apiBase}/notifications`, { method: 'DELETE' });
                await this.load();
            } catch (error) {
                console.error('Clear all notifications failed:', error);
            }
        }

        async markAnnouncementAsRead(announcementId, reload = true) {
            try {
                await this.request(`${this.apiBase}/notifications/announcements/${announcementId}/read`, { method: 'POST' });
                if (reload) {
                    await this.load();
                }
            } catch (error) {
                console.error('Mark announcement read failed:', error);
            }
        }

        async acknowledgeAnnouncement(announcementId) {
            try {
                await this.request(`${this.apiBase}/notifications/announcements/${announcementId}/acknowledge`, { method: 'POST' });
                await this.load();
            } catch (error) {
                console.error('Acknowledge announcement failed:', error);
            }
        }

        async clickAnnouncementCta(announcementId) {
            try {
                const payload = await this.request(`${this.apiBase}/notifications/announcements/${announcementId}/click`, { method: 'POST' });
                if (payload.url) {
                    window.location.href = payload.url;
                    return;
                }
                await this.load();
            } catch (error) {
                console.error('Announcement CTA failed:', error);
            }
        }

        async dismissAnnouncement(announcementId, reload = true) {
            try {
                await this.request(`${this.apiBase}/notifications/announcements/${announcementId}/dismiss`, { method: 'POST' });
                if (reload) {
                    await this.load();
                }
            } catch (error) {
                console.error('Dismiss announcement failed:', error);
            }
        }

        async dismissAllAnnouncements() {
            try {
                await this.request(`${this.apiBase}/notifications/announcements/dismiss-all`, { method: 'POST' });
                await this.load();
            } catch (error) {
                console.error('Dismiss all announcements failed:', error);
            }
        }

        async markAnnouncementPopupShown(announcementId) {
            try {
                await this.request(`${this.apiBase}/notifications/announcements/${announcementId}/popup-shown`, { method: 'POST' });
            } catch (error) {
                console.error('Popup shown tracking failed:', error);
            }
        }

        formatTime(isoString) {
            if (!isoString) {
                return 'Just now';
            }

            const date = new Date(isoString);
            const now = new Date();
            const diffMs = now - date;
            const diffMinutes = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMinutes < 1) {
                return 'Just now';
            }
            if (diffMinutes < 60) {
                return `${diffMinutes} min ago`;
            }
            if (diffHours < 24) {
                return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            }
            if (diffDays < 7) {
                return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            }

            return date.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        }

        escape(value) {
            const stringValue = String(value ?? '');
            return stringValue
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    }

    function initializeNotificationCenters() {
        document.querySelectorAll('[data-notification-center]').forEach((root) => {
            if (root.dataset.notificationCenterReady === '1') {
                return;
            }

            root.dataset.notificationCenterReady = '1';
            const center = new GlobalNotificationCenter(root);
            center.init();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeNotificationCenters);
    } else {
        initializeNotificationCenters();
    }
})();
