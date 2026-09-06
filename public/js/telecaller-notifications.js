// Telecaller Notifications Handler

let notificationCheckInterval = null;
let notificationPermissionGranted = false;
let shownNotificationIds = new Set(); // Track shown notifications to prevent duplicates
let isPollingPaused = false;

// Ensure API_BASE_URL is defined
if (typeof API_BASE_URL === 'undefined') {
    var API_BASE_URL = window.location.origin + '/api';
}
// Base URL for telecaller API (profile page sets API_BASE_URL to /api/telecaller, layout to /api)
function getTelecallerNotificationsBase() {
    const base = typeof API_BASE_URL !== 'undefined' ? API_BASE_URL : (window.location.origin + '/api');
    return base.endsWith('/telecaller') ? base : base + '/telecaller';
}

// Request browser notification permission
function requestNotificationPermission() {
    if (!('Notification' in window)) {
        console.log('This browser does not support notifications');
        return false;
    }

    if (Notification.permission === 'granted') {
        notificationPermissionGranted = true;
        console.log('Browser notifications: Permission already granted');
        return true;
    } else if (Notification.permission === 'denied') {
        console.log('Browser notifications: Permission denied by user');
        return false;
    } else {
        // Request permission
        Notification.requestPermission().then(permission => {
            notificationPermissionGranted = permission === 'granted';
            localStorage.setItem('notification_permission', permission);
            if (permission === 'granted') {
                console.log('Browser notifications: Permission granted');
            } else {
                console.log('Browser notifications: Permission denied');
            }
        }).catch(error => {
            console.error('Error requesting notification permission:', error);
        });
    }

    return notificationPermissionGranted;
}

// Show browser notification
function showBrowserNotification(title, body, data = {}) {
    // Check if notification already shown (prevent duplicates)
    if (data.notification_id && shownNotificationIds.has(data.notification_id)) {
        return;
    }

    if (!notificationPermissionGranted || Notification.permission !== 'granted') {
        console.log('Cannot show browser notification: Permission not granted');
        return;
    }

    try {
        const notification = new Notification(title, {
            body: body,
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            tag: data.task_id ? `task-${data.task_id}` : 'call-reminder',
            data: data,
            requireInteraction: false,
        });

        // Mark as shown
        if (data.notification_id) {
            shownNotificationIds.add(data.notification_id);
        }

        notification.onclick = function(event) {
            event.preventDefault();
            window.focus();
            
            if (data.url) {
                window.location.href = data.url;
            } else if (data.task_id) {
                window.location.href = `/telecaller/tasks?status=pending&task_id=${data.task_id}`;
            }
            
            // Mark notification as clicked
            if (data.notification_id) {
                markNotificationAsClicked(data.notification_id);
            }
            
            notification.close();
        };

        // Auto close after 5 seconds
        setTimeout(() => {
            notification.close();
        }, 5000);

        console.log('Browser notification shown:', title);
    } catch (error) {
        console.error('Error showing browser notification:', error);
    }
}

// Load notifications from API
async function loadNotifications() {
    // Don't poll if paused (tab hidden)
    if (isPollingPaused) {
        return;
    }

    try {
        const token = localStorage.getItem('telecaller_token');
        if (!token) {
            console.log('No token found, skipping notification load');
            return;
        }

        const response = await fetch(`${getTelecallerNotificationsBase()}/notifications`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            },
        });

        // Handle 401 Unauthorized
        if (response.status === 401) {
            console.log('Unauthorized - clearing token');
            localStorage.removeItem('telecaller_token');
            localStorage.removeItem('telecaller_user');
            // Don't redirect, just stop polling
            if (notificationCheckInterval) {
                clearInterval(notificationCheckInterval);
                notificationCheckInterval = null;
            }
            return;
        }

        if (!response.ok) {
            console.error('Failed to load notifications:', response.status, response.statusText);
            return;
        }

        const result = await response.json();
        if (result.success) {
            updateNotificationUI(result.data, result.unread_count);
            
            // Show browser notifications for new unread notifications (including new lead assigned)
            result.data.forEach(notification => {
                if (!notification.read_at && (
                    notification.type === 'call_reminder' ||
                    notification.type === 'new_verification' ||
                    notification.type === 'new_lead' ||
                    notification.type === 'followup_reminder' ||
                    notification.type === 'meeting_reminder' ||
                    notification.type === 'site_visit_reminder' ||
                    notification.type === 'task_overdue' ||
                    notification.type === 'followup_overdue'
                )) {
                    // Only show if not already shown
                    if (!shownNotificationIds.has(notification.id)) {
                        const data = notification.data || {};
                        // Set URL based on action_url or notification type
                        let notificationUrl = notification.action_url;
                        if (!notificationUrl && notification.action_type === 'verification') {
                            notificationUrl = '/telecaller/verification-pending';
                        } else if (!notificationUrl && notification.telecaller_task_id) {
                            notificationUrl = `/telecaller/tasks?status=pending&task_id=${notification.telecaller_task_id}`;
                        }
                        
                        showBrowserNotification(
                            notification.title,
                            notification.message,
                            {
                                ...data,
                                notification_id: notification.id,
                                url: notificationUrl,
                            }
                        );
                    }
                }
            });
        } else {
            console.error('Notification API returned error:', result.message);
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        // Don't show error to user, just log it
    }
}

// Update notification UI
function updateNotificationUI(notifications, unreadCount) {
    const badge = document.getElementById('notificationBadge');
    const list = document.getElementById('notificationList');

    // Update badge
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // Update list
    if (list) {
        if (notifications.length === 0) {
            list.innerHTML = `
                <div style="text-align: center; padding: 40px 20px; color: #B3B5B4;">
                    <i class="fas fa-bell-slash" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5;"></i>
                    <p>No notifications</p>
                </div>
            `;
        } else {
            list.innerHTML = notifications.map(notification => {
                const isUnread = !notification.read_at;
                const timeAgo = getTimeAgo(notification.created_at);
                
                return `
                    <div class="notification-item" onclick="handleNotificationClick(${notification.id}, ${notification.telecaller_task_id || 'null'})" 
                         style="padding: 12px 16px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: all 0.2s; ${isUnread ? 'background: #f8f9fa;' : ''}"
                         onmouseover="this.style.background='#f0f0f0'" 
                         onmouseout="this.style.background='${isUnread ? '#f8f9fa' : 'white'}'">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <div style="flex: 1;">
                                <div style="font-weight: ${isUnread ? '600' : '500'}; color: #063A1C; margin-bottom: 4px;">${notification.title}</div>
                                <div style="font-size: 14px; color: #666; margin-bottom: 4px;">${notification.message}</div>
                                <div style="font-size: 12px; color: #B3B5B4;">${timeAgo}</div>
                            </div>
                            ${isUnread ? '<div style="width: 8px; height: 8px; background: #205A44; border-radius: 50%; margin-top: 6px;"></div>' : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }
    }
}

// Handle notification click
async function handleNotificationClick(notificationId, taskId) {
    try {
        const token = localStorage.getItem('telecaller_token');
        if (!token) return;

        // Mark as clicked
        const response = await fetch(`${getTelecallerNotificationsBase()}/notifications/${notificationId}/click`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            },
        });

        if (response.ok) {
            const result = await response.json();
            if (result.success && result.url) {
                window.location.href = result.url;
            } else if (taskId) {
                window.location.href = `/telecaller/tasks?status=pending&task_id=${taskId}`;
            }
        }
    } catch (error) {
        console.error('Error handling notification click:', error);
        if (taskId) {
            window.location.href = `/telecaller/tasks?status=pending&task_id=${taskId}`;
        }
    }
}

// Mark notification as read
async function markNotificationAsRead(notificationId) {
    try {
        const token = localStorage.getItem('telecaller_token');
        if (!token) return;

        await fetch(`${getTelecallerNotificationsBase()}/notifications/${notificationId}/read`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            },
        });
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark notification as clicked
async function markNotificationAsClicked(notificationId) {
    try {
        const token = localStorage.getItem('telecaller_token');
        if (!token) return;

        await fetch(`${getTelecallerNotificationsBase()}/notifications/${notificationId}/click`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            },
        });
    } catch (error) {
        console.error('Error marking notification as clicked:', error);
    }
}

// Mark all notifications as read
async function markAllNotificationsRead() {
    try {
        const token = localStorage.getItem('telecaller_token');
        if (!token) return;

        const response = await fetch(`${getTelecallerNotificationsBase()}/notifications/mark-all-read`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            },
        });

        if (response.ok) {
            loadNotifications();
        }
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
    }
}

// Toggle notification dropdown
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        const isVisible = dropdown.style.display !== 'none';
        dropdown.style.display = isVisible ? 'none' : 'block';
        
        if (!isVisible) {
            // Refresh notifications when opening dropdown
            loadNotifications();
        }
    }
}

// Make all functions globally accessible for onclick handlers
window.toggleNotificationDropdown = toggleNotificationDropdown;
window.markAllNotificationsRead = markAllNotificationsRead;
window.handleNotificationClick = handleNotificationClick;
window.loadNotifications = loadNotifications;
window.requestNotificationPermission = requestNotificationPermission;
window.showBrowserNotification = showBrowserNotification;

// Also expose via NotificationSystem object
window.NotificationSystem = {
    loadNotifications,
    toggleNotificationDropdown,
    markAllNotificationsRead,
    handleNotificationClick,
    requestNotificationPermission,
    showBrowserNotification
};

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (bell && dropdown && !bell.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});

// Get time ago string
function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    
    return date.toLocaleDateString();
}

// Initialize notifications on page load
function initializeNotifications() {
    console.log('Initializing notification system...');
    
    // Request notification permission
    requestNotificationPermission();
    
    // Load notifications immediately (with small delay to ensure DOM is ready)
    setTimeout(() => {
        loadNotifications();
    }, 1000);
    
    // Poll for new notifications every 30 seconds
    notificationCheckInterval = setInterval(() => {
        if (!isPollingPaused) {
            loadNotifications();
        }
    }, 30000);
    
    console.log('Notification system initialized');
}

// Handle page visibility changes (pause polling when tab is hidden)
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        isPollingPaused = true;
        console.log('Tab hidden - pausing notification polling');
    } else {
        isPollingPaused = false;
        console.log('Tab visible - resuming notification polling');
        // Load notifications immediately when tab becomes visible
        loadNotifications();
    }
});

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeNotifications);
} else {
    // DOM already loaded
    initializeNotifications();
}

// Clean up interval on page unload
window.addEventListener('beforeunload', function() {
    if (notificationCheckInterval) {
        clearInterval(notificationCheckInterval);
        notificationCheckInterval = null;
    }
});
