// Telecaller Dashboard JavaScript

(function() {
    'use strict';

    let leadDistributionChart = null;
    let pusher = null;
    let channel = null;

    // Initialize dashboard when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeCharts();
        initializePusher();
        initializeAutoRefresh();
        initializeCountdownTimers();
    });

    /**
     * Initialize Chart.js charts
     */
    function initializeCharts() {
        if (typeof window.dashboardData === 'undefined' || !window.dashboardData.lead_breakdown) {
            return;
        }

        const ctx = document.getElementById('leadDistributionChart');
        if (!ctx) return;

        const breakdown = window.dashboardData.lead_breakdown;
        
        // Prepare chart data
        const labels = [];
        const data = [];
        const colors = [
            '#3B82F6', // Blue - New
            '#10B981', // Green - Contacted
            '#F59E0B', // Yellow - Qualified
            '#8B5CF6', // Purple - Site Visit
            '#EF4444', // Red - Others
        ];

        // Add non-zero statuses
        const statusMap = {
            'new': 'New',
            'contacted': 'Contacted',
            'qualified': 'Qualified',
            'site_visit_scheduled': 'Site Visit Scheduled',
            'site_visit_completed': 'Site Visit Completed',
            'negotiation': 'Negotiation',
            'closed_won': 'Closed Won',
            'closed_lost': 'Closed Lost',
            'on_hold': 'On Hold',
        };

        Object.keys(statusMap).forEach((status, index) => {
            if (breakdown[status] && breakdown[status] > 0) {
                labels.push(statusMap[status]);
                data.push(breakdown[status]);
            }
        });

        if (data.length === 0) {
            ctx.parentElement.innerHTML = '<p class="text-center text-gray-500">No data to display</p>';
            return;
        }

        leadDistributionChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Pusher for real-time updates
     */
    function initializePusher() {
        // Check if Pusher is available (should be loaded from layout or config)
        if (typeof Pusher === 'undefined') {
            console.warn('Pusher is not loaded. Real-time updates will not work.');
            return;
        }

        // Get Pusher config from window or meta tags
        const pusherKey = document.querySelector('meta[name="pusher-key"]')?.content || null;
        const pusherCluster = document.querySelector('meta[name="pusher-cluster"]')?.content || 'mt1';

        if (!pusherKey) {
            console.warn('Pusher key not found. Real-time updates will not work.');
            return;
        }

        // Get user ID (should be available from auth)
        const userId = getUserId();
        if (!userId) {
            console.warn('User ID not found. Real-time updates will not work.');
            return;
        }

        try {
            pusher = new Pusher(pusherKey, {
                cluster: pusherCluster,
                encrypted: true
            });

            channel = pusher.subscribe('telecaller.' + userId);

            channel.bind('dashboard.update', function(data) {
                handleDashboardUpdate(data);
            });

            console.log('Pusher initialized and listening for dashboard updates');
        } catch (error) {
            console.error('Error initializing Pusher:', error);
        }
    }

    /**
     * Handle dashboard update from Pusher
     */
    function handleDashboardUpdate(data) {
        if (!data || !data.type) return;

        // Show notification
        showNotification('Dashboard updated: ' + data.type);

        // Refresh specific sections based on update type
        switch (data.type) {
            case 'lead_assigned':
            case 'task_created':
                // Refresh stats and urgent tasks
                refreshStats();
                break;
            case 'stats':
                refreshStats();
                break;
            default:
                // Full refresh for other types
                refreshDashboard();
        }
    }

    /**
     * Initialize auto-refresh for time-sensitive data
     */
    function initializeAutoRefresh() {
        // Refresh stats every 2 minutes
        setInterval(function() {
            refreshStats();
        }, 120000); // 2 minutes

        // Refresh urgent tasks every minute
        setInterval(function() {
            refreshUrgentTasks();
        }, 60000); // 1 minute
    }

    /**
     * Initialize SLA countdown timers
     */
    function initializeCountdownTimers() {
        const slaRiskElements = document.querySelectorAll('[data-sla-deadline]');
        
        slaRiskElements.forEach(function(element) {
            const deadline = new Date(element.getAttribute('data-sla-deadline'));
            
            setInterval(function() {
                const now = new Date();
                const diff = deadline - now;
                
                if (diff <= 0) {
                    element.textContent = 'Overdue';
                    element.classList.add('text-danger');
                } else {
                    const minutes = Math.floor(diff / 60000);
                    const hours = Math.floor(minutes / 60);
                    
                    if (hours > 0) {
                        element.textContent = hours + 'h ' + (minutes % 60) + 'm remaining';
                    } else {
                        element.textContent = minutes + 'm remaining';
                    }
                }
            }, 60000); // Update every minute
        });
    }

    /**
     * Refresh dashboard stats
     */
    function refreshStats() {
        const token = getToken();
        if (!token) return;

        fetch(API_BASE_URL + '/telecaller/dashboard/stats', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                updateStatsDisplay(data.data);
            }
        })
        .catch(error => {
            console.error('Error refreshing stats:', error);
        });
    }

    /**
     * Refresh urgent tasks
     */
    function refreshUrgentTasks() {
        const token = getToken();
        if (!token) return;

        fetch(API_BASE_URL + '/telecaller/dashboard/urgent-tasks', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                updateUrgentTasksDisplay(data.data);
            }
        })
        .catch(error => {
            console.error('Error refreshing urgent tasks:', error);
        });
    }

    /**
     * Update stats display
     */
    function updateStatsDisplay(data) {
        // Update KPI cards with new data
        // This is a simplified version - you can enhance it to update specific elements
        console.log('Stats updated:', data);
        // Trigger a visual update animation
        document.querySelectorAll('.kpi-card').forEach(card => {
            card.classList.add('updated');
            setTimeout(() => card.classList.remove('updated'), 500);
        });
    }

    /**
     * Update urgent tasks display
     */
    function updateUrgentTasksDisplay(data) {
        console.log('Urgent tasks updated:', data);
        // Update urgent tasks section if needed
    }

    /**
     * Full dashboard refresh
     */
    function refreshDashboard() {
        window.location.reload();
    }

    /**
     * Show notification
     */
    function showNotification(message) {
        // Simple notification - can be enhanced with a toast library
        const notification = document.createElement('div');
        notification.className = 'dashboard-notification';
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Get user ID from localStorage or page
     */
    function getUserId() {
        const userStr = localStorage.getItem('telecaller_user');
        if (userStr) {
            try {
                const user = JSON.parse(userStr);
                return user.id;
            } catch (e) {
                console.error('Error parsing user data:', e);
            }
        }
        return null;
    }

    /**
     * Get token from localStorage
     */
    function getToken() {
        return localStorage.getItem('telecaller_token');
    }

    // Make refreshDashboard available globally
    window.refreshDashboard = refreshDashboard;
})();

