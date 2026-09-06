// Mobile-specific features for tasks

(function() {
    'use strict';

    // Swipe gesture detection
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;

    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
    }, false);

    document.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        touchEndY = e.changedTouches[0].screenY;
        handleSwipe();
    }, false);

    function handleSwipe() {
        const swipeThreshold = 50;
        const deltaX = touchEndX - touchStartX;
        const deltaY = touchEndY - touchStartY;

        // Check if horizontal swipe
        if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > swipeThreshold) {
            const taskCard = document.elementFromPoint(touchStartX, touchStartY).closest('[data-task-id]');
            if (taskCard) {
                const taskId = taskCard.dataset.taskId;
                
                if (deltaX > 0) {
                    // Swipe right - mark complete
                    handleSwipeComplete(taskId);
                } else {
                    // Swipe left - show actions
                    showMobileActions(taskCard, taskId);
                }
            }
        }
    }

    function handleSwipeComplete(taskId) {
        if (confirm('Mark this task as complete?')) {
            axios.post(`/tasks/${taskId}/complete`)
                .then(response => {
                    if (response.data.success) {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error completing task:', error);
                });
        }
    }

    function showMobileActions(card, taskId) {
        // Create bottom sheet with actions
        const bottomSheet = document.createElement('div');
        bottomSheet.id = 'mobile-actions-sheet';
        bottomSheet.className = 'fixed inset-x-0 bottom-0 bg-white rounded-t-xl shadow-lg z-50 transform translate-y-full transition-transform duration-300';
        bottomSheet.innerHTML = `
            <div class="p-4">
                <div class="w-12 h-1 bg-gray-300 rounded mx-auto mb-4"></div>
                <h3 class="text-lg font-semibold mb-4">Task Actions</h3>
                <div class="space-y-2">
                    <button onclick="completeTask(${taskId})" class="w-full px-4 py-3 bg-indigo-600 text-white rounded-lg">
                        Complete Task
                    </button>
                    <button onclick="rescheduleTaskMobile(${taskId})" class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                        Reschedule
                    </button>
                    <a href="/tasks/${taskId}" class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-center">
                        View Details
                    </a>
                    <button onclick="closeMobileActions()" class="w-full px-4 py-3 text-gray-600 border border-gray-300 rounded-lg">
                        Cancel
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(bottomSheet);
        
        // Show sheet
        setTimeout(() => {
            bottomSheet.classList.remove('translate-y-full');
        }, 10);

        // Close on backdrop click
        const backdrop = document.createElement('div');
        backdrop.className = 'fixed inset-0 bg-black bg-opacity-50 z-40';
        backdrop.onclick = closeMobileActions;
        document.body.insertBefore(backdrop, bottomSheet);
    }

    window.closeMobileActions = function() {
        const sheet = document.getElementById('mobile-actions-sheet');
        const backdrop = document.querySelector('.fixed.inset-0.bg-black');
        if (sheet) {
            sheet.classList.add('translate-y-full');
            setTimeout(() => sheet.remove(), 300);
        }
        if (backdrop) backdrop.remove();
    };

    window.rescheduleTaskMobile = function(taskId) {
        closeMobileActions();
        // Simple date picker for mobile
        const dateInput = document.createElement('input');
        dateInput.type = 'datetime-local';
        dateInput.onchange = function() {
            if (this.value) {
                axios.post(`/tasks/${taskId}/reschedule`, {
                    scheduled_at: this.value
                }).then(() => {
                    window.location.reload();
                });
            }
        };
        dateInput.click();
    };

    // Pull to refresh
    let pullToRefresh = {
        startY: 0,
        currentY: 0,
        distance: 0,
        threshold: 80,
        isPulling: false
    };

    const refreshIndicator = document.createElement('div');
    refreshIndicator.className = 'fixed top-0 left-0 right-0 bg-indigo-600 text-white text-center py-2 transform -translate-y-full transition-transform';
    refreshIndicator.innerHTML = '<span>Pull to refresh</span>';
    document.body.appendChild(refreshIndicator);

    window.addEventListener('touchstart', function(e) {
        if (window.scrollY === 0) {
            pullToRefresh.startY = e.touches[0].clientY;
            pullToRefresh.isPulling = true;
        }
    });

    window.addEventListener('touchmove', function(e) {
        if (!pullToRefresh.isPulling) return;

        pullToRefresh.currentY = e.touches[0].clientY;
        pullToRefresh.distance = pullToRefresh.currentY - pullToRefresh.startY;

        if (pullToRefresh.distance > 0 && window.scrollY === 0) {
            e.preventDefault();
            const progress = Math.min(pullToRefresh.distance / pullToRefresh.threshold, 1);
            refreshIndicator.style.transform = `translateY(${(progress - 1) * 100}%)`;
        }
    });

    window.addEventListener('touchend', function() {
        if (pullToRefresh.distance >= pullToRefresh.threshold) {
            refreshIndicator.textContent = 'Refreshing...';
            window.location.reload();
        } else {
            refreshIndicator.style.transform = 'translateY(-100%)';
        }
        pullToRefresh.isPulling = false;
        pullToRefresh.distance = 0;
    });

    // Infinite scroll for mobile list view
    let isLoading = false;
    let currentPage = 1;
    const hasMorePages = document.body.dataset.hasMorePages === 'true';

    if (hasMorePages) {
        window.addEventListener('scroll', function() {
            if (isLoading) return;

            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;

            if (scrollTop + windowHeight >= documentHeight - 200) {
                loadMoreTasks();
            }
        });
    }

    function loadMoreTasks() {
        isLoading = true;
        currentPage++;
        
        const url = new URL(window.location.href);
        url.searchParams.set('page', currentPage);

        fetch(url.toString())
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTasks = doc.querySelector('.task-list-container') || doc.body;
                
                const container = document.querySelector('.task-list-container') || document.querySelector('tbody') || document.querySelector('.space-y-4');
                if (container) {
                    container.innerHTML += newTasks.innerHTML;
                }

                isLoading = false;
            })
            .catch(error => {
                console.error('Error loading more tasks:', error);
                isLoading = false;
            });
    }
})();
