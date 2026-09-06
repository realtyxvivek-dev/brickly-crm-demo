<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CRM Error Diagnostic Test Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #F7F6F3;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
        }
        .test-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #E5DED4;
        }
        .test-section h3 {
            color: #063A1C;
            margin-bottom: 20px;
            border-bottom: 2px solid #E5DED4;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        .status-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
        }
        .endpoint-test {
            background: #f8f9fa;
            border-left: 4px solid #205A44;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .endpoint-test.error {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .endpoint-test.success {
            border-left-color: #28a745;
            background: #f0fff4;
        }
        .endpoint-test.loading {
            border-left-color: #ffc107;
            background: #fffbf0;
        }
        .btn-test {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-test:hover {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .btn-test:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .info-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 14px;
            color: #063A1C;
            font-weight: 500;
            word-break: break-all;
        }
        .header-banner {
            background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #205A44;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header-banner">
            <h1><i class="bi bi-bug"></i> CRM Error Diagnostic Test Page</h1>
            <p class="mb-0">इस page से "Forbidden. CRM role required." error को diagnose कर सकते हैं</p>
        </div>

        <!-- User Information Section -->
        <div class="test-section">
            <h3><i class="bi bi-person-circle"></i> Current User Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">User ID</div>
                    <div class="info-value">{{ auth()->user()->id ?? 'N/A' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Name</div>
                    <div class="info-value">{{ auth()->user()->name ?? 'N/A' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value">{{ auth()->user()->email ?? 'N/A' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Role Name</div>
                    <div class="info-value">{{ auth()->user()->role->name ?? 'N/A' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Role Slug</div>
                    <div class="info-value">
                        {{ auth()->user()->role->slug ?? 'N/A' }}
                        @if(auth()->user()->role)
                            @if(auth()->user()->role->slug === 'crm')
                                <span class="status-badge status-success">✓ CRM Role</span>
                            @else
                                <span class="status-badge status-error">✗ Not CRM Role</span>
                            @endif
                        @endif
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Is Admin?</div>
                    <div class="info-value">
                        {{ auth()->user()->isAdmin() ? 'Yes' : 'No' }}
                        @if(auth()->user()->isAdmin())
                            <span class="status-badge status-info">Admin Access</span>
                        @endif
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Is CRM?</div>
                    <div class="info-value">
                        {{ auth()->user()->isCrm() ? 'Yes' : 'No' }}
                        @if(!auth()->user()->isCrm())
                            <span class="status-badge status-error">✗ Not CRM</span>
                        @endif
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Session Auth</div>
                    <div class="info-value">{{ auth()->check() ? 'Authenticated' : 'Not Authenticated' }}</div>
                </div>
            </div>
        </div>

        <!-- Authentication Status Section -->
        <div class="test-section">
            <h3><i class="bi bi-shield-check"></i> Authentication Status</h3>
            <div id="auth-status" class="code-block">
                Checking authentication status...
            </div>
            <button class="btn-test" onclick="checkAuthStatus()">
                <i class="bi bi-arrow-clockwise"></i> Refresh Auth Status
            </button>
        </div>

        <!-- API Endpoints Test Section -->
        <div class="test-section">
            <h3><i class="bi bi-cloud-arrow-up"></i> CRM API Endpoints Test</h3>
            <p class="text-muted">ये वही endpoints हैं जो CRM dashboard में fail हो रहे हैं:</p>
            
            <button class="btn-test mb-3" onclick="testAllEndpoints()">
                <i class="bi bi-play-circle"></i> Test All Endpoints
            </button>
            
            <div id="endpoints-results"></div>
        </div>

        <!-- Request Headers Section -->
        <div class="test-section">
            <h3><i class="bi bi-file-text"></i> Request Headers & Configuration</h3>
            <div id="headers-info" class="code-block">
                Loading headers information...
            </div>
        </div>

        <!-- Detailed Error Log Section -->
        <div class="test-section">
            <h3><i class="bi bi-exclamation-triangle"></i> Error Details</h3>
            <div id="error-log" class="code-block" style="max-height: 500px;">
                Errors will appear here...
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        const API_BASE = '/api/crm';
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;
        let authToken = localStorage.getItem('crm_token');

        // Endpoints to test (same as in crm-dashboard.js)
        const endpointsToTest = [
            { name: 'Dashboard Stats', url: '/dashboard/stats?date_range=all_time', method: 'GET' },
            { name: 'Telecaller Stats', url: '/dashboard/telecaller-stats?date_range=today', method: 'GET' },
            { name: 'Daily Prospects', url: '/dashboard/daily-prospects', method: 'GET' },
            { name: 'Users List', url: '/users', method: 'GET' },
            { name: 'Blacklist', url: '/blacklist', method: 'GET' },
            { name: 'Pending Verifications', url: '/pending-verifications', method: 'GET' },
        ];

        // Check authentication status
        async function checkAuthStatus() {
            const statusDiv = document.getElementById('auth-status');
            statusDiv.innerHTML = '<span class="spinner"></span>Checking...';
            
            try {
                const headers = {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                };
                
                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                }
                
                // Check session auth
                const sessionCheck = await fetch('/api/me', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: headers
                });

                let statusInfo = {
                    sessionAuth: sessionCheck.ok ? 'OK' : 'FAILED',
                    sessionStatus: sessionCheck.status,
                    hasToken: !!authToken,
                    csrfToken: CSRF_TOKEN ? 'Present' : 'Missing',
                };

                if (sessionCheck.ok) {
                    const sessionData = await sessionCheck.json();
                    statusInfo.sessionUser = sessionData;
                }

                // Format output
                statusDiv.innerHTML = `
                    <strong>Session Authentication:</strong> 
                    <span class="status-badge ${sessionCheck.ok ? 'status-success' : 'status-error'}">
                        ${statusInfo.sessionAuth} (${statusInfo.sessionStatus})
                    </span><br><br>
                    <strong>CSRF Token:</strong> ${statusInfo.csrfToken}<br>
                    <strong>Auth Token (localStorage):</strong> ${statusInfo.hasToken ? 'Present' : 'Missing'}<br><br>
                    <strong>Session User Data:</strong><br>
                    <pre>${JSON.stringify(statusInfo.sessionUser || {}, null, 2)}</pre>
                `;
            } catch (error) {
                statusDiv.innerHTML = `<span class="status-badge status-error">Error: ${error.message}</span>`;
            }
        }

        // Test a single endpoint
        async function testEndpoint(endpoint) {
            const resultDiv = document.createElement('div');
            resultDiv.className = 'endpoint-test loading';
            resultDiv.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>${endpoint.name}</strong><br>
                        <small style="color: #6c757d;">${endpoint.method} ${endpoint.url}</small>
                    </div>
                    <span class="spinner"></span>
                </div>
            `;
            
            document.getElementById('endpoints-results').appendChild(resultDiv);

            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };

            if (CSRF_TOKEN) {
                headers['X-CSRF-TOKEN'] = CSRF_TOKEN;
            }

            if (authToken) {
                headers['Authorization'] = `Bearer ${authToken}`;
            }

            try {
                const response = await fetch(API_BASE + endpoint.url, {
                    method: endpoint.method,
                    headers: headers,
                    credentials: 'same-origin',
                });

                const responseData = await response.json().catch(() => ({ message: 'Failed to parse JSON' }));

                if (response.ok) {
                    resultDiv.className = 'endpoint-test success';
                    resultDiv.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <strong>${endpoint.name}</strong>
                                <span class="status-badge status-success">✓ Success (${response.status})</span><br>
                                <small style="color: #6c757d;">${endpoint.method} ${endpoint.url}</small><br><br>
                                <details>
                                    <summary style="cursor: pointer; color: #205A44; font-weight: 600;">View Response</summary>
                                    <pre style="margin-top: 10px; font-size: 11px;">${JSON.stringify(responseData, null, 2).substring(0, 500)}...</pre>
                                </details>
                            </div>
                        </div>
                    `;
                } else {
                    resultDiv.className = 'endpoint-test error';
                    const errorMessage = responseData.message || 'Unknown error';
                    resultDiv.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <strong>${endpoint.name}</strong>
                                <span class="status-badge status-error">✗ Failed (${response.status})</span><br>
                                <small style="color: #6c757d;">${endpoint.method} ${endpoint.url}</small><br><br>
                                <strong style="color: #dc3545;">Error:</strong> ${errorMessage}<br><br>
                                <details>
                                    <summary style="cursor: pointer; color: #dc3545; font-weight: 600;">View Full Response</summary>
                                    <pre style="margin-top: 10px; font-size: 11px;">${JSON.stringify(responseData, null, 2)}</pre>
                                </details>
                            </div>
                        </div>
                    `;
                    
                    // Add to error log
                    addToErrorLog(endpoint.name, errorMessage, response.status, responseData);
                }
            } catch (error) {
                resultDiv.className = 'endpoint-test error';
                resultDiv.innerHTML = `
                    <div>
                        <strong>${endpoint.name}</strong>
                        <span class="status-badge status-error">✗ Exception</span><br>
                        <small style="color: #6c757d;">${endpoint.method} ${endpoint.url}</small><br><br>
                        <strong style="color: #dc3545;">Error:</strong> ${error.message}
                    </div>
                `;
                
                addToErrorLog(endpoint.name, error.message, 'EXCEPTION', { error: error.toString() });
            }
        }

        // Test all endpoints
        async function testAllEndpoints() {
            const resultsDiv = document.getElementById('endpoints-results');
            resultsDiv.innerHTML = '';
            
            for (const endpoint of endpointsToTest) {
                await testEndpoint(endpoint);
                // Small delay between requests
                await new Promise(resolve => setTimeout(resolve, 300));
            }
        }

        // Add error to error log
        function addToErrorLog(endpointName, message, status, data) {
            const errorLog = document.getElementById('error-log');
            const timestamp = new Date().toLocaleTimeString();
            const errorEntry = `
[${timestamp}] ${endpointName}
Status: ${status}
Message: ${message}
Details: ${JSON.stringify(data, null, 2)}
${'='.repeat(80)}

`;
            errorLog.textContent = errorEntry + errorLog.textContent;
        }

        // Load headers information
        function loadHeadersInfo() {
            const headersDiv = document.getElementById('headers-info');
            const headersInfo = {
                'API Base URL': API_BASE,
                'CSRF Token': CSRF_TOKEN ? 'Present (length: ' + CSRF_TOKEN.length + ')' : 'Missing',
                'Auth Token': authToken ? 'Present (from localStorage)' : 'Missing',
                'Credentials': 'same-origin',
                'User Agent': navigator.userAgent.substring(0, 100) + '...',
            };

            headersDiv.innerHTML = Object.entries(headersInfo)
                .map(([key, value]) => `<strong>${key}:</strong> ${value}<br>`)
                .join('');
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkAuthStatus();
            loadHeadersInfo();
            
            // Auto-test endpoints after 1 second
            setTimeout(() => {
                testAllEndpoints();
            }, 1000);
        });
    </script>
</body>
</html>
