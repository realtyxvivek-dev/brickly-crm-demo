if (window._chatbotLoaded) { console.log('Chatbot already loaded, skipping'); } else { window._chatbotLoaded = true;
/**
 * CRM Chatbot Assistant
 * Handles real-time notifications via Pusher with polling fallback
 */

class ChatbotAssistant {
    constructor() {
        this.pusher = null;
        this.userChannel = null;
        this.adminChannel = null;
        this.pollingInterval = null;
        this.notifications = [];
        this.unreadCount = 0;
        this.apiToken = this.getApiToken();
        this.userId = this.getUserId();
        this.pollingEnabled = true;
        this.pollInterval = 30000; // 30 seconds
        
        this.init();
    }

    init() {
        this.initializePusher();
        this.startPolling();
        this.loadNotifications();
    }

    getApiToken() {
        // Try to get token from various sources (telecaller token has priority)
        const tokenFromMeta = document.querySelector('meta[name="api-token"]')?.content;
        const telecallerToken = localStorage.getItem('telecaller_token');
        const tokenFromLocalStorage = localStorage.getItem('api_token');
        const tokenFromSessionStorage = sessionStorage.getItem('api_token') || sessionStorage.getItem('telecaller_token');
        
        // For web routes, we can use session-based auth or token
        return tokenFromMeta || telecallerToken || tokenFromLocalStorage || tokenFromSessionStorage;
    }
    
    isSalesExecutive() {
        // Check if user is sales executive by checking for sales executive token or user role
        const salesExecutiveToken = localStorage.getItem('telecaller_token'); // Keep token name for backward compatibility
        const userData = localStorage.getItem('telecaller_user'); // Keep key name for backward compatibility
        if (salesExecutiveToken || userData) {
            try {
                if (userData) {
                    const user = JSON.parse(userData);
                    return user.role?.slug === 'sales_executive' || user.role?.name === 'Sales Executive';
                }
                return true; // If token exists, assume sales executive
            } catch (e) {
                return false;
            }
        }
        return false;
    }
    
    // Deprecated: Use isSalesExecutive() instead
    isTelecaller() {
        return this.isSalesExecutive();
    }
    
    getNotificationsEndpoint() {
        // Check if sales executive, use sales executive endpoint
        if (this.isSalesExecutive()) {
            return '/api/telecaller/notifications/unread'; // Keep endpoint name for backward compatibility
        }
        // Otherwise use general endpoint (if exists)
        return '/api/notifications/unread';
    }
    
    getNotificationReadEndpoint(notificationId) {
        // Check if sales executive, use sales executive endpoint
        if (this.isSalesExecutive()) {
            return `/api/telecaller/notifications/${notificationId}/read`;
        }
        // Otherwise use general endpoint (if exists)
        return `/api/notifications/${notificationId}/read`;
    }

    getMarkAllReadEndpoint() {
        if (this.isTelecaller()) {
            return '/api/telecaller/notifications/mark-all-read';
        }
        return '/api/notifications/mark-all-read';
    }

    getUserId() {
        const userIdMeta = document.querySelector('meta[name="user-id"]')?.content;
        return userIdMeta ? parseInt(userIdMeta) : null;
    }

    initializePusher() {
        if (typeof Pusher === 'undefined') {
            console.warn('Pusher not loaded, using polling only');
            this.pollingEnabled = true;
            return;
        }

        const pusherKey = document.querySelector('meta[name="pusher-key"]')?.content;
        const pusherCluster = document.querySelector('meta[name="pusher-cluster"]')?.content || 'mt1';

        if (!pusherKey) {
            console.warn('Pusher key not found, using polling only');
            this.pollingEnabled = true;
            return;
        }

        try {
            this.pusher = new Pusher(pusherKey, {
                cluster: pusherCluster,
                encrypted: true,
            });

            // Subscribe to user-specific channel
            if (this.userId) {
                this.userChannel = this.pusher.subscribe('private-user.' + this.userId);
                this.userChannel.bind('notification.new', (data) => {
                    this.handleNewNotification(data.notification);
                });
            }

            // Subscribe to admin broadcast channel
            this.adminChannel = this.pusher.subscribe('admin-broadcast');
            this.adminChannel.bind('broadcast.new', (data) => {
                this.handleAdminBroadcast(data);
            });

            console.log('Chatbot Assistant: Pusher initialized');
        } catch (error) {
            console.error('Chatbot Assistant: Pusher initialization failed', error);
            this.pollingEnabled = true;
        }
    }

    startPolling() {
        if (!this.pollingEnabled) return;

        // Poll immediately
        this.loadNotifications();

        // Then poll at intervals
        this.pollingInterval = setInterval(() => {
            this.loadNotifications();
        }, this.pollInterval);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    async loadNotifications() {
        try {
            const headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
            
            // Add Authorization header only if token is available
            if (this.apiToken) {
                headers['Authorization'] = `Bearer ${this.apiToken}`;
            }
            
            const endpoint = this.getNotificationsEndpoint();
            const response = await fetch(endpoint, {
                headers: headers,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load notifications');
            }

            const data = await response.json();
            if (data.success) {
                this.updateNotifications(data.data);
                this.updateUnreadCount(data.unread_count);
            }
        } catch (error) {
            console.error('Chatbot Assistant: Error loading notifications', error);
        }
    }

    handleNewNotification(notification) {
        // Add to notifications array
        this.notifications.unshift(notification);
        
        // Update UI
        this.renderNotification(notification);
        this.updateUnreadCount(this.unreadCount + 1);
        
        // Show browser notification if permission granted
        this.showBrowserNotification(notification);
        
        // Play sound (optional)
        this.playNotificationSound();
    }

    handleAdminBroadcast(data) {
        // Check if this broadcast is for current user
        const userNotification = data.notifications.find(n => n.user_id === this.userId);
        if (userNotification) {
            this.handleNewNotification(userNotification);
        }
    }

    updateNotifications(notifications) {
        this.notifications = notifications;
        this.renderNotifications();
    }

    updateUnreadCount(count) {
        this.unreadCount = count;
        const badge = document.getElementById('chatbotBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    renderNotifications() {
        const container = document.getElementById('chatbotNotifications');
        if (!container) return;

        if (this.notifications.length === 0) {
            container.innerHTML = '<div class="chatbot-empty">No new notifications</div>';
            return;
        }

        container.innerHTML = this.notifications.map(notification => 
            this.createNotificationHTML(notification)
        ).join('');
    }

    renderNotification(notification) {
        const container = document.getElementById('chatbotNotifications');
        if (!container) return;

        // Remove empty message if exists
        const emptyMsg = container.querySelector('.chatbot-empty');
        if (emptyMsg) {
            emptyMsg.remove();
        }

        // Add new notification at top
        const notificationHTML = this.createNotificationHTML(notification);
        container.insertAdjacentHTML('afterbegin', notificationHTML);
    }

    createNotificationHTML(notification) {
        const icon = this.getNotificationIcon(notification.type);
        const timeAgo = this.getTimeAgo(notification.created_at);
        const isUnread = !notification.read_at;
        const actions = this.getNotificationActions(notification);
        const actionHtml = actions.length ? `
                    <div class="chatbot-notification-action">
                        ${actions.map((action) => `
                            <button onclick="chatbotAssistant.handleNotificationAction(${notification.id}, '${this.escapeJs(action.url)}', '${this.escapeJs(action.method)}')" 
                                    class="chatbot-view-lead-btn">
                                ${this.escapeHtml(action.label)}
                            </button>
                        `).join('')}
                    </div>
                ` : '';
        
        return `
            <div class="chatbot-notification ${isUnread ? 'unread' : ''}">
                <div class="chatbot-notification-header">
                    <div class="chatbot-notification-title">
                        <span class="chatbot-notification-icon">${icon}</span>
                        ${this.escapeHtml(notification.title)}
                    </div>
                    <span class="chatbot-notification-time">${timeAgo}</span>
                </div>
                <div class="chatbot-notification-message">
                    ${this.escapeHtml(notification.message)}
                </div>
                ${actionHtml}
            </div>
        `;
    }

    getNotificationActions(notification) {
        const data = notification.data || {};
        const actions = [];
        const primaryUrl = data.primary_action_url || notification.action_url || '';
        const primaryLabel = data.primary_action_label || (primaryUrl ? 'Open CRM' : '');
        const primaryMethod = (data.primary_action_method || 'GET').toUpperCase();
        const secondaryUrl = data.secondary_action_url || '';
        const secondaryLabel = data.secondary_action_label || '';
        const secondaryMethod = (data.secondary_action_method || 'GET').toUpperCase();

        if (primaryUrl && primaryLabel) {
            actions.push({ label: primaryLabel, url: primaryUrl, method: primaryMethod });
        }
        if (secondaryUrl && secondaryLabel && secondaryMethod !== 'DISMISS') {
            actions.push({ label: secondaryLabel, url: secondaryUrl, method: secondaryMethod });
        }

        return actions.slice(0, 2);
    }

    getNotificationIcon(type) {
        const icons = {
            'new_lead': '📋',
            'new_verification': '✅',
            'followup_reminder': '📅',
            'admin_broadcast': '📢',
            'call_reminder': '📞',
            'site_visit': '📍',
            'meeting': '🤝',
        };
        return icons[type] || '🔔';
    }

    getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString();
    }

    async handleNotificationClick(notificationId, actionUrl) {
        // Mark as read
        try {
            const headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
            
            if (this.apiToken) {
                headers['Authorization'] = `Bearer ${this.apiToken}`;
            }
            
            const readEndpoint = this.getNotificationReadEndpoint(notificationId);
            await fetch(readEndpoint, {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
            });
        } catch (error) {
            console.error('Error marking notification as read', error);
        }

        // Navigate to action URL if available
        if (actionUrl) {
            window.location.href = actionUrl;
        }

        // Reload notifications
        this.loadNotifications();
    }

    async handleNotificationAction(notificationId, actionUrl, method = 'GET') {
        method = (method || 'GET').toUpperCase();
        if (method === 'GET' || actionUrl.indexOf('tel:') === 0) {
            return this.handleNotificationClick(notificationId, actionUrl);
        }

        try {
            const headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };

            if (this.apiToken) {
                headers['Authorization'] = `Bearer ${this.apiToken}`;
            }

            const response = await fetch(actionUrl, {
                method,
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({ outcome: 'completed_from_notification_center' }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'Action failed');
            }
            await this.handleNotificationClick(notificationId, '');
        } catch (error) {
            console.error('Notification action failed', error);
        }
    }

    async cleanAllNotifications() {
        try {
            const headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };

            if (this.apiToken) {
                headers['Authorization'] = `Bearer ${this.apiToken}`;
            }

            const endpoint = this.getMarkAllReadEndpoint();
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to mark all as read');
            }

            // Refresh list + badge
            await this.loadNotifications();
        } catch (error) {
            console.error('Chatbot Assistant: Error cleaning notifications', error);
        }
    }

    showBrowserNotification(notification) {
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;

        const browserNotification = new Notification(notification.title, {
            body: notification.message,
            icon: '/favicon.ico',
            tag: `notification-${notification.id}`,
        });

        browserNotification.onclick = () => {
            window.focus();
            browserNotification.close();
            if (notification.action_url) {
                this.handleNotificationClick(notification.id, notification.action_url);
            }
        };
    }

    playNotificationSound() {
        // Optional: Play a subtle sound
        // You can add an audio file and play it here
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    escapeJs(text) {
        return String(text || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    destroy() {
        this.stopPolling();
        if (this.userChannel) {
            this.pusher?.unsubscribe('private-user.' + this.userId);
        }
        if (this.adminChannel) {
            this.pusher?.unsubscribe('admin-broadcast');
        }
        if (this.pusher) {
            this.pusher.disconnect();
        }
    }
}

// Initialize chatbot assistant when DOM is ready
let chatbotAssistant;
document.addEventListener('DOMContentLoaded', function() {
    chatbotAssistant = new ChatbotAssistant();
    window.chatbotAssistant = chatbotAssistant;
    
    // Expose loadChatbotNotifications for toggle function
    window.loadChatbotNotifications = function() {
        if (chatbotAssistant) {
            chatbotAssistant.loadNotifications();
        }
    };
});

// Request notification permission on page load
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

}
