<!-- Meeting Post-Call Action Popup -->
<div id="postCallPopup" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[60] flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6 transform transition-all">
        <div class="text-center mb-6">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                <i class="fas fa-phone-alt text-blue-600 text-xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Customer Called</h3>
            <p class="text-sm text-gray-600">What happened with the pre-meeting call?</p>
        </div>
        
        <div class="space-y-3">
            <!-- Meeting on Time -->
            <button onclick="handlePostCallAction('meeting_on_time')" class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-gradient-to-r from-green-600 to-green-500 hover:from-green-700 hover:to-green-600 text-white rounded-lg transition font-medium shadow-md">
                <i class="fas fa-clock text-xl"></i>
                <span>Meeting on Time</span>
            </button>
            
            <!-- Reschedule Meeting -->
            <button onclick="handlePostCallAction('reschedule')" class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white rounded-lg transition font-medium shadow-md">
                <i class="fas fa-calendar-alt text-xl"></i>
                <span>Reschedule</span>
            </button>
            
            <!-- Cancel Meeting -->
            <button onclick="handlePostCallAction('cancel')" class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-700 hover:to-red-600 text-white rounded-lg transition font-medium shadow-md">
                <i class="fas fa-times-circle text-xl"></i>
                <span>Cancel Meeting</span>
            </button>
            
            <!-- Complete -->
            <button onclick="handlePostCallAction('complete')" class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-gradient-to-r from-green-600 to-emerald-500 hover:from-green-700 hover:to-emerald-600 text-white rounded-lg transition font-medium shadow-md">
                <i class="fas fa-check-circle text-xl"></i>
                <span>Complete</span>
            </button>
            
            <!-- Mark as Dead -->
            <button onclick="handlePostCallAction('mark_dead')" class="w-full flex items-center justify-center gap-3 px-4 py-3 bg-gradient-to-r from-red-700 to-red-600 hover:from-red-800 hover:to-red-700 text-white rounded-lg transition font-medium shadow-md">
                <i class="fas fa-skull text-xl"></i>
                <span>Mark as Dead</span>
            </button>
        </div>
        
        <button onclick="closePostCallPopup()" class="mt-4 w-full text-center text-sm text-gray-500 hover:text-gray-700 transition">
            Close
        </button>
    </div>
</div>

<script>
window.currentMeetingId = null;
window.currentMeetingData = null;
window.currentTaskId = null;
window.currentTaskType = null; // 'Task' or 'TelecallerTask'

function showPostCallPopup(meetingId, meetingData = null, taskId = null, taskType = null) {
    window.currentMeetingId = meetingId;
    window.currentMeetingData = meetingData;
    window.currentTaskId = taskId;
    window.currentTaskType = taskType;
    document.getElementById('postCallPopup').classList.remove('hidden');
}

function closePostCallPopup() {
    document.getElementById('postCallPopup').classList.add('hidden');
    window.currentMeetingId = null;
    window.currentMeetingData = null;
    window.currentTaskId = null;
    window.currentTaskType = null;
}

// Helper function to complete the calling task
async function completeCallingTask(taskId, taskType) {
    if (!taskId) return true; // No task to complete
    
    const apiToken = window.API_TOKEN || document.querySelector('meta[name="api-token"]')?.content;
    const apiBase = window.API_BASE_URL || '/api';
    
    if (!apiToken) {
        console.warn('API token not found, skipping task completion');
        return false;
    }
    
    try {
        let endpoint;
        if (taskType === 'Task') {
            // For manager tasks (Task model)
            endpoint = `${apiBase}/sales-manager/tasks/${taskId}/complete`;
        } else {
            // For telecaller tasks (TelecallerTask model)
            endpoint = `${apiBase}/telecaller/tasks/${taskId}/complete`;
        }
        
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${apiToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        });
        
        if (response.ok) {
            return true;
        } else {
            const result = await response.json();
            console.error('Failed to complete task:', result.message || 'Unknown error');
            return false;
        }
    } catch (error) {
        console.error('Error completing task:', error);
        return false;
    }
}

async function handlePostCallAction(action) {
    const meetingId = window.currentMeetingId;
    const taskId = window.currentTaskId;
    const taskType = window.currentTaskType;
    
    if (!meetingId) {
        alert('Meeting ID not found');
        return;
    }
    
    // Get API token and base URL (should be defined globally in the page)
    const apiToken = window.API_TOKEN || document.querySelector('meta[name="api-token"]')?.content;
    const apiBase = window.API_BASE_URL || '/api';
    
    if (!apiToken) {
        alert('Authentication token not found');
        return;
    }
    
    try {
        if (action === 'meeting_on_time') {
            // Complete the calling task and close popup
            await completeCallingTask(taskId, taskType);
            closePostCallPopup();
            if (typeof showNotification === 'function') {
                showNotification('Calling task completed. Meeting is on time.', 'success', 3000);
            } else {
                alert('Calling task completed. Meeting is on time.');
            }
            // Reload to refresh task list
            if (typeof loadTasks === 'function') {
                loadTasks();
            } else {
                location.reload();
            }
            return;
        }
        
        if (action === 'reschedule') {
            // Close popup and open reschedule modal from meeting section
            closePostCallPopup();
            
            // Check if reschedule modal functions exist (from meeting section)
            if (typeof showRescheduleMeetingModal === 'function') {
                showRescheduleMeetingModal(meetingId);
                // Complete task after reschedule modal is opened
                // Task will be completed when reschedule is submitted
            } else {
                // Fallback: use API directly
                if (!confirm('This will cancel the current meeting. Do you want to reschedule?')) {
                    return;
                }
                
                const cancelResponse = await fetch(`${apiBase}/meetings/${meetingId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${apiToken}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ reason: 'Rescheduled by customer request' })
                });
                
                if (cancelResponse.ok) {
                    await completeCallingTask(taskId, taskType);
                    alert('Meeting cancelled. Please reschedule from the meeting section.');
                    location.reload();
                } else {
                    alert('Failed to cancel meeting');
                }
            }
            return;
        }
        
        if (action === 'cancel') {
            // Call cancelMeeting function from meeting section or use API
            if (typeof cancelMeeting === 'function') {
                // Use meeting section's cancelMeeting function
                closePostCallPopup();
                // Store task info for completion after cancel
                window.pendingTaskCompletion = { taskId, taskType };
                cancelMeeting(meetingId);
            } else {
                // Fallback: use API directly
                if (!confirm('Are you sure you want to cancel this meeting?')) {
                    return;
                }
                
                const response = await fetch(`${apiBase}/meetings/${meetingId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${apiToken}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ reason: 'Cancelled via pre-meeting call' })
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    await completeCallingTask(taskId, taskType);
                    closePostCallPopup();
                    if (typeof showNotification === 'function') {
                        showNotification('Meeting cancelled successfully', 'success', 3000);
                    } else {
                        alert('Meeting cancelled successfully');
                    }
                    if (typeof loadMeetings === 'function') {
                        loadMeetings();
                    } else {
                        location.reload();
                    }
                } else {
                    alert(result.message || 'Failed to cancel meeting');
                }
            }
            return;
        }
        
        if (action === 'complete') {
            // Close popup and open complete modal from meeting section
            closePostCallPopup();
            
            // Check if complete modal functions exist (from meeting section)
            if (typeof showCompleteMeetingModal === 'function') {
                showCompleteMeetingModal(meetingId);
                // Store task info for completion after meeting is completed
                window.pendingTaskCompletion = { taskId, taskType };
            } else {
                alert('Complete meeting modal not available. Please complete from the meeting section.');
            }
            return;
        }
        
        if (action === 'mark_dead') {
            // Close popup and open mark dead modal from meeting section
            closePostCallPopup();
            
            // Check if mark dead modal functions exist (from meeting section)
            if (typeof showMarkDeadModal === 'function') {
                showMarkDeadModal('meeting', meetingId);
                // Store task info for completion after meeting is marked dead
                window.pendingTaskCompletion = { taskId, taskType };
            } else {
                // Fallback: use API directly
                const reason = prompt('Enter reason for marking as dead:');
                if (!reason || reason.trim() === '') {
                    alert('Reason is required');
                    return;
                }
                
                const response = await fetch(`${apiBase}/meetings/${meetingId}/mark-dead`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${apiToken}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ reason: reason.trim() })
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    await completeCallingTask(taskId, taskType);
                    if (typeof showNotification === 'function') {
                        showNotification('Meeting marked as dead successfully', 'success', 3000);
                    } else {
                        alert('Meeting marked as dead successfully');
                    }
                    if (typeof loadMeetings === 'function') {
                        loadMeetings();
                    } else {
                        location.reload();
                    }
                } else {
                    alert(result.message || 'Failed to mark as dead');
                }
            }
            return;
        }
        
        // Legacy actions (for backward compatibility)
        if (action === 'confirm') {
            const response = await fetch(`${apiBase}/meetings/${meetingId}/complete-pre-call`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ action: 'confirm' })
            });
            
            const result = await response.json();
            
            if (response.ok) {
                await completeCallingTask(taskId, taskType);
                closePostCallPopup();
                if (typeof showNotification === 'function') {
                    showNotification('Meeting confirmed! Customer will join.', 'success', 3000);
                } else {
                    alert('Meeting confirmed! Customer will join.');
                }
                location.reload();
            } else {
                alert(result.message || 'Failed to confirm meeting');
            }
        }
        
    } catch (error) {
        console.error('Error handling post-call action:', error);
        alert('An error occurred. Please try again.');
    }
}
</script>
