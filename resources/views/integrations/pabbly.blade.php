@extends('layouts.app')

@section('title', 'Pabbly Integration - ' . brand_name())
@section('page-title', 'Pabbly Integration')

@section('header-actions')
    <a href="{{ route('integrations.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back to Integrations
    </a>
@endsection

@push('styles')
<style>
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-badge.active {
        background: #d1fae5;
        color: #065f46;
    }
    .status-badge.inactive {
        background: #fee2e2;
        color: #991b1b;
    }
    .alert {
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #86efac;
    }
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #93c5fd;
    }
    .log-entry {
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
    }
    .log-entry:last-child {
        border-bottom: none;
    }
    .log-status-success {
        color: #065f46;
    }
    .log-status-error {
        color: #991b1b;
    }
    .log-status-warning {
        color: #92400e;
    }
</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Success/Error Messages -->
    <div id="message-container" class="mb-6" style="display: none;">
        <div id="message-alert" class="alert"></div>
    </div>

    <!-- Configuration Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
            <i class="fas fa-plug text-blue-500 text-2xl mr-3"></i>
            Pabbly Webhook Configuration
        </h2>

        <form id="pabbly-config-form">
            @csrf
            
            <!-- Webhook URL (Read-only) -->
            <div class="mb-6">
                <label for="webhook_url" class="block text-sm font-medium text-gray-700 mb-2">
                    Webhook URL
                </label>
                <div class="flex items-center space-x-2">
                    <input type="text" 
                           id="webhook_url" 
                           value="{{ $webhookUrl }}" 
                           readonly
                           class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                    <button type="button" 
                            onclick="copyToClipboard('{{ $webhookUrl }}')" 
                            class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200">
                        <i class="fas fa-copy mr-2"></i> Copy
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Use this URL in your Pabbly workflow webhook action</p>
            </div>

            <!-- Webhook Secret -->
            <div class="mb-6">
                <label for="webhook_secret" class="block text-sm font-medium text-gray-700 mb-2">
                    Webhook Secret (Optional)
                </label>
                <div class="flex items-center space-x-2">
                    <input type="password" 
                           id="webhook_secret" 
                           name="webhook_secret" 
                           value="{{ $settings->webhook_secret }}"
                           class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Enter secret key for webhook authentication">
                    <button type="button" 
                            onclick="toggleSecretVisibility()" 
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-eye" id="secret-eye-icon"></i>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Optional: Add a secret key for additional webhook security</p>
            </div>

            <!-- Active Toggle -->
            <div class="mb-6">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" 
                           id="is_active" 
                           name="is_active" 
                           {{ $settings->is_active ? 'checked' : '' }}
                           class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="text-sm font-medium text-gray-700">Enable Pabbly Integration</span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ml-8">When disabled, webhook requests will be rejected</p>
            </div>

            <!-- Statistics -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Statistics</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Total Webhooks</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $settings->webhook_count ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Last Webhook</p>
                        <p class="text-lg font-semibold text-gray-900">
                            @if($settings->last_webhook_at)
                                {{ $settings->last_webhook_at->diffForHumans() }}
                            @else
                                Never
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-3">
                <button type="button" 
                        onclick="testWebhook()" 
                        class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors duration-200">
                    <i class="fas fa-vial mr-2"></i> Test Webhook
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200">
                    <i class="fas fa-save mr-2"></i> Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Webhook Logs Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-list-alt text-gray-600 text-xl mr-3"></i>
                Recent Webhook Logs
            </h2>
            <button onclick="refreshLogs()" 
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm">
                <i class="fas fa-sync-alt mr-2"></i> Refresh
            </button>
        </div>

        <div id="logs-container" class="max-h-96 overflow-y-auto">
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                <p>Loading logs...</p>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Save Settings
document.getElementById('pabbly-config-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('is_active', document.getElementById('is_active').checked ? '1' : '0');
    
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
    
    fetch('{{ route("integrations.pabbly.update") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Settings saved successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage(data.message || 'Error saving settings', 'error');
        }
    })
    .catch(error => {
        showMessage('Error: ' + error.message, 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// Test Webhook
function testWebhook() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Testing...';
    
    fetch('{{ route("integrations.pabbly.test") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Test webhook sent successfully! Lead created with ID: ' + (data.response?.lead_id || 'N/A'), 'success');
            setTimeout(() => {
                refreshLogs();
                location.reload();
            }, 2000);
        } else {
            showMessage('Test failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showMessage('Error: ' + error.message, 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// Refresh Logs
function refreshLogs() {
    const container = document.getElementById('logs-container');
    container.innerHTML = '<div class="text-center py-4 text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch('{{ route("integrations.pabbly.logs") }}', {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.logs.length > 0) {
            let html = '<div class="divide-y divide-gray-200">';
            data.logs.forEach(log => {
                const statusClass = log.status === 'success' ? 'log-status-success' : 
                                   log.status === 'error' ? 'log-status-error' : 
                                   'log-status-warning';
                const statusIcon = log.status === 'success' ? 'fa-check-circle' : 
                                  log.status === 'error' ? 'fa-times-circle' : 
                                  'fa-exclamation-circle';
                
                html += `
                    <div class="log-entry">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-1">
                                    <i class="fas ${statusIcon} ${statusClass}"></i>
                                    <span class="text-sm font-medium text-gray-900">${log.timestamp}</span>
                                    ${log.lead_id ? `<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Lead #${log.lead_id}</span>` : ''}
                                    ${log.name ? `<span class="text-xs text-gray-600">${log.name}</span>` : ''}
                                </div>
                                <p class="text-xs text-gray-500 truncate">${log.message}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="text-center py-8 text-gray-500"><i class="fas fa-inbox text-3xl mb-2"></i><p>No webhook logs found</p></div>';
        }
    })
    .catch(error => {
        container.innerHTML = '<div class="text-center py-8 text-red-500"><i class="fas fa-exclamation-circle text-3xl mb-2"></i><p>Error loading logs</p></div>';
    });
}

// Copy to Clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showMessage('Webhook URL copied to clipboard!', 'success');
    }).catch(err => {
        showMessage('Failed to copy: ' + err, 'error');
    });
}

// Toggle Secret Visibility
function toggleSecretVisibility() {
    const input = document.getElementById('webhook_secret');
    const icon = document.getElementById('secret-eye-icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Show Message
function showMessage(message, type) {
    const container = document.getElementById('message-container');
    const alert = document.getElementById('message-alert');
    
    alert.className = 'alert alert-' + type;
    alert.textContent = message;
    container.style.display = 'block';
    
    setTimeout(() => {
        container.style.display = 'none';
    }, 5000);
}

// Load logs on page load
document.addEventListener('DOMContentLoaded', function() {
    refreshLogs();
    // Auto-refresh logs every 30 seconds
    setInterval(refreshLogs, 30000);
});
</script>
@endsection
