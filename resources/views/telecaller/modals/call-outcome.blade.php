<!-- Call Outcome Modal -->
<div id="callOutcomeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Call Outcome</h3>
            <button class="close-btn" onclick="closeCallOutcomeModal()">&times;</button>
        </div>
        <div class="form-group">
            <p style="margin-bottom: 20px; color: #B3B5B4;">Please select the outcome of this call:</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button class="btn btn-success" onclick="handleCallOutcome('interested')">
                    Interested
                </button>
                <button class="btn btn-danger" onclick="handleCallOutcome('not_interested')">
                    Not Interested
                </button>
                <button class="btn btn-warning" onclick="handleCallOutcome('cnp')">
                    CNP
                </button>
                <button class="btn btn-secondary" onclick="handleCallOutcome('call_later')">
                    Call Later
                </button>
                <button class="btn btn-danger" onclick="handleCallOutcome('broker')" style="grid-column: 1 / -1;">
                    Broker
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Not Interested Modal -->
<div id="notInterestedModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark as Not Interested</h3>
            <button class="close-btn" onclick="closeNotInterestedModal()">&times;</button>
        </div>
        <form id="notInterestedForm" onsubmit="submitNotInterested(event)">
            <div class="form-group">
                <label>Remark *</label>
                <textarea name="remark" required rows="4" placeholder="Enter remark..."></textarea>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="closeNotInterestedModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- CNP Modal -->
<div id="cnpModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark as CNP (Call Not Picked)</h3>
            <button class="close-btn" onclick="closeCnpModal()">&times;</button>
        </div>
        <form id="cnpForm" onsubmit="submitCnp(event)">
            <div class="form-group">
                <label>Remark *</label>
                <textarea name="remark" required rows="4" placeholder="Enter remark..."></textarea>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="closeCnpModal()">Cancel</button>
                <button type="submit" class="btn btn-warning">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- Broker Modal -->
<div id="brokerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark as Broker</h3>
            <button class="close-btn" onclick="closeBrokerModal()">&times;</button>
        </div>
        <form id="brokerForm" onsubmit="submitBroker(event)">
            <div class="form-group">
                <label>Remark *</label>
                <textarea name="remark" required rows="4" placeholder="Enter remark..."></textarea>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="closeBrokerModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
// Use global variables from dashboard
let currentTaskId = window.currentTaskId || null;
let currentAssignmentId = window.currentAssignmentId || null;

function openCallOutcomeModal(taskId, assignmentId) {
    currentTaskId = taskId;
    currentAssignmentId = assignmentId;
    if (typeof window !== 'undefined') {
        window.currentTaskId = taskId;
        window.currentAssignmentId = assignmentId;
    }
    document.getElementById('callOutcomeModal').classList.add('show');
}

function closeCallOutcomeModal() {
    document.getElementById('callOutcomeModal').classList.remove('show');
    currentTaskId = null;
    currentAssignmentId = null;
}

function closeNotInterestedModal() {
    document.getElementById('notInterestedModal').classList.remove('show');
    document.getElementById('notInterestedForm').reset();
}

function closeCnpModal() {
    document.getElementById('cnpModal').classList.remove('show');
    document.getElementById('cnpForm').reset();
}

function closeBrokerModal() {
    document.getElementById('brokerModal').classList.remove('show');
    document.getElementById('brokerForm').reset();
}

function handleCallOutcome(outcome) {
    closeCallOutcomeModal();
    
    if (outcome === 'interested') {
        // Mark as interested and redirect to centralized form
        apiCall(`/tasks/${currentTaskId}/call-outcome`, {
            method: 'POST',
            body: JSON.stringify({ outcome: 'interested' })
        }).then(response => {
            if (response && response.success) {
                // Show success message
                if (typeof showAlert === 'function') {
                    showAlert(response.message || 'Lead marked as interested. Redirecting to form...', 'success', 2000);
                } else {
                    alert(response.message || 'Lead marked as interested. Redirecting to form...');
                }
                
                // Redirect to centralized form after a short delay
                if (response.redirect) {
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1500);
                } else if (response.lead_id) {
                    // Fallback: construct URL manually
                    setTimeout(() => {
                        window.location.href = `/leads/${response.lead_id}/edit`;
                    }, 1500);
                }
                
                // Refresh tasks list if available
                if (typeof loadTasks === 'function') {
                    setTimeout(() => loadTasks(), 1000);
                }
            } else {
                alert('Error: ' + (response?.error || 'Failed to mark as interested'));
            }
        }).catch(error => {
            console.error('Error marking as interested:', error);
            alert('Error: Failed to mark as interested. Please try again.');
        });
    } else if (outcome === 'not_interested') {
        document.getElementById('notInterestedModal').classList.add('show');
    } else if (outcome === 'cnp') {
        document.getElementById('cnpModal').classList.add('show');
    } else if (outcome === 'call_later') {
        // Use stored assignment_id from initiateCall
        if (currentAssignmentId) {
            if (document.getElementById('followUpAssignmentId')) {
                document.getElementById('followUpAssignmentId').value = currentAssignmentId;
            }
            // Store task ID
            if (document.getElementById('followUpTaskId')) {
                document.getElementById('followUpTaskId').value = currentTaskId;
            } else {
                const form = document.getElementById('followUpForm');
                if (form) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.id = 'followUpTaskId';
                    input.name = 'task_id';
                    input.value = currentTaskId;
                    form.appendChild(input);
                }
            }
            if (document.getElementById('followUpModal')) {
                document.getElementById('followUpModal').classList.add('show');
            }
        }
    } else if (outcome === 'broker') {
        document.getElementById('brokerModal').classList.add('show');
    }
}

async function submitNotInterested(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const remark = formData.get('remark');
    
    const response = await apiCall(`/tasks/${currentTaskId}/call-outcome`, {
        method: 'POST',
        body: JSON.stringify({
            outcome: 'not_interested',
            remark: remark
        })
    });
    
    if (response && response.success) {
        closeNotInterestedModal();
        alert('Marked as not interested successfully');
        loadTasks(currentFilter);
        loadStats();
    } else {
        alert('Error: ' + (response?.error || 'Failed to mark as not interested'));
    }
}

async function submitCnp(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const remark = formData.get('remark');
    
    const response = await apiCall(`/tasks/${currentTaskId}/call-outcome`, {
        method: 'POST',
        body: JSON.stringify({
            outcome: 'cnp',
            remark: remark
        })
    });
    
    if (response && response.success) {
        closeCnpModal();
        alert('Marked as CNP successfully');
        loadTasks(currentFilter);
        loadStats();
    } else {
        alert('Error: ' + (response?.error || 'Failed to mark as CNP'));
    }
}

async function submitBroker(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const remark = formData.get('remark');
    
    const response = await apiCall(`/tasks/${currentTaskId}/call-outcome`, {
        method: 'POST',
        body: JSON.stringify({
            outcome: 'broker',
            remark: remark
        })
    });
    
    if (response && response.success) {
        closeBrokerModal();
        alert('Marked as broker and blacklisted successfully');
        loadTasks(currentFilter);
        loadStats();
    } else {
        alert('Error: ' + (response?.error || 'Failed to mark as broker'));
    }
}
</script>

