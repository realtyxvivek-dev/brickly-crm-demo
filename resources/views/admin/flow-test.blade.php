@extends('layouts.app')

@section('title', 'Flow Testing & Validation - ' . brand_name())
@section('page-title', 'Complete Flow Testing & Validation')
@section('page-subtitle', 'Test all CRM flows from Sales Executive to Closer')

@push('styles')
<style>
    .flow-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .user-panel {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #E5DED4;
    }
    
    .user-selector {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .user-selector select {
        padding: 10px 15px;
        border: 2px solid #E5DED4;
        border-radius: 8px;
        font-size: 14px;
        min-width: 200px;
    }
    
    .current-user-badge {
        padding: 8px 16px;
        background: #063A1C;
        color: white;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .flow-progress {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #E5DED4;
    }
    
    .progress-bar-container {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding: 20px 0;
    }
    
    .stage-item {
        min-width: 150px;
        text-align: center;
        padding: 15px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        border: 2px solid #E5DED4;
        background: white;
    }
    
    .stage-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .stage-item.active {
        border-color: #063A1C;
        background: #F0F9F5;
    }
    
    .stage-item.completed {
        border-color: #10B981;
        background: #D1FAE5;
    }
    
    .stage-item.error {
        border-color: #EF4444;
        background: #FEE2E2;
    }
    
    .stage-number {
        font-size: 24px;
        font-weight: 700;
        color: #063A1C;
        margin-bottom: 5px;
    }
    
    .stage-name {
        font-size: 12px;
        color: #6B7280;
        font-weight: 500;
    }
    
    .stage-details {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #E5DED4;
    }
    
    .validation-result {
        margin-top: 20px;
    }
    
    .error-item {
        padding: 12px;
        margin: 8px 0;
        border-radius: 8px;
        border-left: 4px solid #EF4444;
        background: #FEE2E2;
    }
    
    .warning-item {
        padding: 12px;
        margin: 8px 0;
        border-radius: 8px;
        border-left: 4px solid #F59E0B;
        background: #FEF3C7;
    }
    
    .info-item {
        padding: 12px;
        margin: 8px 0;
        border-radius: 8px;
        border-left: 4px solid #3B82F6;
        background: #DBEAFE;
    }
    
    .success-item {
        padding: 12px;
        margin: 8px 0;
        border-radius: 8px;
        border-left: 4px solid #10B981;
        background: #D1FAE5;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background: #063A1C;
        color: white;
    }
    
    .btn-primary:hover {
        background: #052816;
    }
    
    .btn-secondary {
        background: #6B7280;
        color: white;
    }
    
    .btn-success {
        background: #10B981;
        color: white;
    }
    
    .btn-danger {
        background: #EF4444;
        color: white;
    }
    
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #063A1C;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .data-display {
        background: #F9FAFB;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
    }
    
    .data-item {
        padding: 8px;
        border-bottom: 1px solid #E5E7EB;
    }
    
    .data-item:last-child {
        border-bottom: none;
    }
</style>
@endpush

@section('content')
<div class="flow-container">
    <!-- User Login Panel -->
    <div class="user-panel">
        <h3 style="margin-bottom: 15px; color: #063A1C;">User Selection</h3>
        <div class="user-selector">
            <label style="font-weight: 500;">Select Role:</label>
            <select id="roleSelect" onchange="loadUsersByRole()">
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="crm">CRM</option>
                <option value="hr_manager">HR Manager</option>
                <option value="finance_manager">Finance Manager</option>
                <option value="sales_manager">Senior Manager</option>
                <option value="sales_executive">Sales Executive</option>
            </select>
            
            <label style="font-weight: 500;">Select User:</label>
            <select id="userSelect" onchange="loginAsUser()">
                <option value="">Select User</option>
            </select>
            
            <div class="current-user-badge" id="currentUserBadge">
                Current: {{ auth()->user()->name }} ({{ auth()->user()->role->name ?? 'N/A' }})
            </div>
            
            <button class="btn btn-secondary" onclick="restoreOriginalUser()" id="restoreBtn" style="display: none;">
                Restore Original User
            </button>
        </div>
    </div>

    <!-- Flow Progress -->
    <div class="flow-progress">
        <h3 style="margin-bottom: 15px; color: #063A1C;">Flow Stages</h3>
        <div class="progress-bar-container" id="progressBar">
            <!-- Stages will be loaded here -->
        </div>
    </div>

    <!-- Stage Details -->
    <div class="stage-details" id="stageDetails" style="display: none;">
        <h3 id="stageTitle" style="margin-bottom: 15px; color: #063A1C;"></h3>
        <p id="stageDescription" style="color: #6B7280; margin-bottom: 20px;"></p>
        
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="testCurrentStage()">
                <span id="testBtnText">Test Stage</span>
                <span id="testBtnLoader" class="loading" style="display: none;"></span>
            </button>
            <button class="btn btn-success" onclick="validateCurrentStage()">
                Validate Stage
            </button>
            <button class="btn btn-secondary" onclick="getStageData()">
                Get Stage Data
            </button>
        </div>
        
        <div class="validation-result" id="validationResult"></div>
    </div>
</div>

<script>
let currentStageId = null;
let stages = [];
let originalUserId = {{ auth()->user()->id }};

// Load stages on page load
document.addEventListener('DOMContentLoaded', function() {
    loadStages();
    loadUsersByRole();
});

// Load all stages
async function loadStages() {
    try {
        const response = await fetch('/api/admin/flow-test/stages');
        const data = await response.json();
        
        if (data.success) {
            stages = data.stages;
            renderProgressBar();
        }
    } catch (error) {
        console.error('Error loading stages:', error);
        alert('Error loading stages: ' + error.message);
    }
}

// Render progress bar
function renderProgressBar() {
    const container = document.getElementById('progressBar');
    container.innerHTML = '';
    
    stages.forEach((stage, index) => {
        const stageDiv = document.createElement('div');
        stageDiv.className = 'stage-item';
        stageDiv.id = `stage-${stage.id}`;
        stageDiv.onclick = () => selectStage(stage.id);
        
        stageDiv.innerHTML = `
            <div class="stage-number">${stage.order}</div>
            <div class="stage-name">${stage.name}</div>
        `;
        
        container.appendChild(stageDiv);
    });
}

// Select a stage
function selectStage(stageId) {
    currentStageId = stageId;
    const stage = stages.find(s => s.id === stageId);
    
    if (!stage) return;
    
    // Update active stage
    document.querySelectorAll('.stage-item').forEach(item => {
        item.classList.remove('active');
    });
    document.getElementById(`stage-${stageId}`).classList.add('active');
    
    // Show stage details
    document.getElementById('stageDetails').style.display = 'block';
    document.getElementById('stageTitle').textContent = stage.name;
    document.getElementById('stageDescription').textContent = stage.description;
    
    // Clear validation result
    document.getElementById('validationResult').innerHTML = '';
}

// Load users by role
async function loadUsersByRole() {
    const roleSelect = document.getElementById('roleSelect');
    const userSelect = document.getElementById('userSelect');
    const role = roleSelect.value;
    
    if (!role) {
        userSelect.innerHTML = '<option value="">Select User</option>';
        return;
    }
    
    try {
        const response = await fetch('/api/admin/flow-test/users-by-role');
        const data = await response.json();
        
        if (data.success) {
            userSelect.innerHTML = '<option value="">Select User</option>';
            
            if (data.users[role]) {
                data.users[role].forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = `${user.name} (${user.email})`;
                    userSelect.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Login as user
async function loginAsUser() {
    const userSelect = document.getElementById('userSelect');
    const userId = userSelect.value;
    
    if (!userId) return;
    
    try {
        const response = await fetch(`/api/admin/flow-test/login-as/${userId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('currentUserBadge').textContent = 
                `Current: ${data.user.name} (${data.user.role})`;
            document.getElementById('restoreBtn').style.display = 'block';
            alert('Logged in as ' + data.user.name);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error logging in:', error);
        alert('Error logging in: ' + error.message);
    }
}

// Restore original user
async function restoreOriginalUser() {
    try {
        const response = await fetch('/api/admin/flow-test/restore-original-user', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('currentUserBadge').textContent = 
                `Current: ${data.user.name} (${data.user.role})`;
            document.getElementById('restoreBtn').style.display = 'none';
            alert('Restored original user session');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error restoring user:', error);
        alert('Error restoring user: ' + error.message);
    }
}

// Test current stage
async function testCurrentStage() {
    if (!currentStageId) {
        alert('Please select a stage first');
        return;
    }
    
    const btn = document.getElementById('testBtnText');
    const loader = document.getElementById('testBtnLoader');
    btn.style.display = 'none';
    loader.style.display = 'inline-block';
    
    try {
        const response = await fetch(`/api/admin/flow-test/stages/${currentStageId}/test`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayValidationResult(data.result);
            updateStageStatus(currentStageId, data.result.valid ? 'completed' : 'error');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error testing stage:', error);
        alert('Error testing stage: ' + error.message);
    } finally {
        btn.style.display = 'inline';
        loader.style.display = 'none';
    }
}

// Validate current stage
async function validateCurrentStage() {
    if (!currentStageId) {
        alert('Please select a stage first');
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/flow-test/stages/${currentStageId}/validate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayValidationResult(data.validation);
            updateStageStatus(currentStageId, data.validation.valid ? 'completed' : 'error');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error validating stage:', error);
        alert('Error validating stage: ' + error.message);
    }
}

// Get stage data
async function getStageData() {
    if (!currentStageId) {
        alert('Please select a stage first');
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/flow-test/stages/${currentStageId}/data`);
        const data = await response.json();
        
        if (data.success) {
            displayStageData(data.data);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error getting stage data:', error);
        alert('Error getting stage data: ' + error.message);
    }
}

// Display validation result
function displayValidationResult(result) {
    const container = document.getElementById('validationResult');
    let html = '';
    
    if (result.valid) {
        html += '<div class="success-item"><strong>✓ Stage is Valid</strong></div>';
    } else {
        html += '<div class="error-item"><strong>✗ Stage has Errors</strong></div>';
    }
    
    if (result.errors && result.errors.length > 0) {
        html += '<h4 style="margin-top: 15px; color: #EF4444;">Errors:</h4>';
        result.errors.forEach(error => {
            html += `<div class="error-item">${error}</div>`;
        });
    }
    
    if (result.warnings && result.warnings.length > 0) {
        html += '<h4 style="margin-top: 15px; color: #F59E0B;">Warnings:</h4>';
        result.warnings.forEach(warning => {
            html += `<div class="warning-item">${warning}</div>`;
        });
    }
    
    if (result.info && result.info.length > 0) {
        html += '<h4 style="margin-top: 15px; color: #3B82F6;">Info:</h4>';
        result.info.forEach(info => {
            html += `<div class="info-item">${info}</div>`;
        });
    }
    
    if (result.data && Object.keys(result.data).length > 0) {
        html += '<h4 style="margin-top: 15px; color: #063A1C;">Data:</h4>';
        html += '<div class="data-display">';
        for (const [key, value] of Object.entries(result.data)) {
            html += `<div class="data-item"><strong>${key}:</strong> ${value}</div>`;
        }
        html += '</div>';
    }
    
    container.innerHTML = html;
}

// Display stage data
function displayStageData(data) {
    const container = document.getElementById('validationResult');
    let html = '<h4 style="margin-top: 15px; color: #063A1C;">Stage Data:</h4>';
    html += '<div class="data-display">';
    
    if (data && Object.keys(data).length > 0) {
        for (const [key, value] of Object.entries(data)) {
            if (Array.isArray(value)) {
                html += `<div class="data-item"><strong>${key}:</strong> ${value.length} items</div>`;
                value.forEach((item, index) => {
                    html += `<div class="data-item" style="padding-left: 20px;">${index + 1}. ${JSON.stringify(item)}</div>`;
                });
            } else {
                html += `<div class="data-item"><strong>${key}:</strong> ${JSON.stringify(value)}</div>`;
            }
        }
    } else {
        html += '<div class="data-item">No data available</div>';
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// Update stage status
function updateStageStatus(stageId, status) {
    const stageElement = document.getElementById(`stage-${stageId}`);
    if (stageElement) {
        stageElement.classList.remove('active', 'completed', 'error');
        stageElement.classList.add(status);
    }
}
</script>
@endsection
