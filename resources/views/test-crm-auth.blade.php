<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CRM Authorization Test Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .test-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-section h3 {
            color: #063A1C;
            margin-bottom: 15px;
            border-bottom: 2px solid #E5DED4;
            padding-bottom: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
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
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 10px;
        }
        .test-button {
            margin: 5px;
        }
        .result-box {
            margin-top: 15px;
            padding: 15px;
            border-radius: 4px;
            min-height: 50px;
        }
        .result-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .result-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .result-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
    </style>
</head>
<body style="background: #F7F6F3; padding: 20px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="bi bi-bug"></i> CRM Authorization Diagnostic Test Page
                </h1>
                <p class="text-muted mb-4">यह page authorization errors को find करने में help करेगा</p>

                <!-- Current User Info -->
                <div class="test-section">
                    <h3><i class="bi bi-person-circle"></i> Current User Information</h3>
                    <div id="user-info">
                        <p><strong>Loading user information...</strong></p>
                    </div>
                </div>

                <!-- Authentication Status -->
                <div class="test-section">
                    <h3><i class="bi bi-shield-check"></i> Authentication Status</h3>
                    <div id="auth-status">
                        <p><strong>Checking authentication...</strong></p>
                    </div>
                </div>

                <!-- Token Information -->
                <div class="test-section">
                    <h3><i class="bi bi-key"></i> Token/Session Information</h3>
                    <div id="token-info">
                        <p><strong>Checking tokens...</strong></p>
                    </div>
                </div>

                <!-- API Test Section -->
                <div class="test-section">
                    <h3><i class="bi bi-cloud-arrow-up"></i> API Endpoint Tests</h3>
                    <p class="text-muted">Click buttons to test different CRM API endpoints:</p>
                    
                    <div class="mb-3">
                        <button class="btn btn-primary test-button" onclick="testApiEndpoint('/api/crm/dashboard/stats')">
                            Test: /api/crm/dashboard/stats
                        </button>
                        <button class="btn btn-primary test-button" onclick="testApiEndpoint('/api/crm/dashboard/telecaller-stats')">
                            Test: /api/crm/dashboard/telecaller-stats
                        </button>
                        <button class="btn btn-primary test-button" onclick="testApiEndpoint('/api/crm/users')">
                            Test: /api/crm/users
                        </button>
                        <button class="btn btn-primary test-button" onclick="testApiEndpoint('/api/crm/blacklist')">
                            Test: /api/crm/blacklist
                        </button>
                        <button class="btn btn-primary test-button" onclick="testApiEndpoint('/api/crm/pending-verifications')">
                            Test: /api/crm/pending-verifications
                        </button>
                        <button class="btn btn-success test-button" onclick="testAllEndpoints()">
                            <i class="bi bi-arrow-repeat"></i> Test All Endpoints
                        </button>
                    </div>
                    
                    <div id="api-results"></div>
                </div>

                <!-- Role Check Section -->
                <div class="test-section">
                    <h3><i class="bi bi-person-badge"></i> Role & Permission Checks</h3>
                    <div id="role-checks">
                        <p><strong>Checking roles...</strong></p>
                    </div>
                </div>

                <!-- Middleware Test -->
                <div class="test-section">
                    <h3><i class="bi bi-code-slash"></i> Middleware Simulation Test</h3>
                    <div id="middleware-test">
                        <p><strong>Testing middleware logic...</strong></p>
                    </div>
                </div>

                <!-- Recommendations -->
                <div class="test-section">
                    <h3><i class="bi bi-lightbulb"></i> Recommendations</h3>
                    <div id="recommendations">
                        <p class="text-muted">Run tests above to see recommendations...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = '/api/crm';
        let authToken = localStorage.getItem('crm_token');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadUserInfo();
            checkAuthStatus();
            checkTokenInfo();
            checkRoleChecks();
            testMiddleware();
        });

        // Load User Information
        async function loadUserInfo() {
            try {
                const response = await fetch('/api/me', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const container = document.getElementById('user-info');
                if (response.ok) {
                    const data = await response.json();
                    container.innerHTML = `
                        <table class="table table-bordered">
                            <tr><th>User ID</th><td>${data.id || 'N/A'}</td></tr>
                            <tr><th>Name</th><td>${data.name || 'N/A'}</td></tr>
                            <tr><th>Email</th><td>${data.email || 'N/A'}</td></tr>
                            <tr><th>Role</th><td>${data.role?.name || 'N/A'} <span class="status-badge status-info">${data.role?.slug || 'N/A'}</span></td></tr>
                            <tr><th>Is Admin?</th><td>${data.is_admin || false} <span class="status-badge ${data.is_admin ? 'status-success' : 'status-error'}">${data.is_admin ? 'YES' : 'NO'}</span></td></tr>
                            <tr><th>Is CRM?</th><td>${checkIfCrm(data.role?.slug)} <span class="status-badge ${checkIfCrm(data.role?.slug) ? 'status-success' : 'status-error'}">${checkIfCrm(data.role?.slug) ? 'YES' : 'NO'}</span></td></tr>
                        </table>
                        <div class="code-block mt-3">${JSON.stringify(data, null, 2)}</div>
                    `;
                } else {
                    const error = await response.json().catch(() => ({ message: 'Failed to get user info' }));
                    container.innerHTML = `
                        <div class="result-box result-error">
                            <strong>Error:</strong> ${error.message || 'Failed to fetch user information'}
                            <br><small>Status: ${response.status}</small>
                        </div>
                    `;
                }
            } catch (error) {
                document.getElementById('user-info').innerHTML = `
                    <div class="result-box result-error">
                        <strong>Exception:</strong> ${error.message}
                    </div>
                `;
            }
        }

        // Check Authentication Status
        async function checkAuthStatus() {
            const container = document.getElementById('auth-status');
            const checks = [];

            // Check session auth
            try {
                const response = await fetch('/api/me', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                checks.push({
                    name: 'Session Authentication',
                    status: response.ok ? 'success' : 'error',
                    message: response.ok ? 'User is authenticated via session' : `Not authenticated (${response.status})`
                });
            } catch (e) {
                checks.push({
                    name: 'Session Authentication',
                    status: 'error',
                    message: `Error: ${e.message}`
                });
            }

            // Check token auth
            if (authToken) {
                try {
                    const response = await fetch('/api/me', {
                        headers: {
                            'Authorization': `Bearer ${authToken}`,
                            'Accept': 'application/json'
                        }
                    });
                    checks.push({
                        name: 'Token Authentication',
                        status: response.ok ? 'success' : 'error',
                        message: response.ok ? 'Token is valid' : `Token invalid (${response.status})`
                    });
                } catch (e) {
                    checks.push({
                        name: 'Token Authentication',
                        status: 'error',
                        message: `Error: ${e.message}`
                    });
                }
            } else {
                checks.push({
                    name: 'Token Authentication',
                    status: 'warning',
                    message: 'No token found in localStorage (crm_token)'
                });
            }

            // Render checks
            container.innerHTML = checks.map(check => `
                <div class="mb-2">
                    <strong>${check.name}:</strong>
                    <span class="status-badge status-${check.status}">${check.status.toUpperCase()}</span>
                    <br><small class="text-muted">${check.message}</small>
                </div>
            `).join('');
        }

        // Check Token Info
        function checkTokenInfo() {
            const container = document.getElementById('token-info');
            const tokenInfo = {
                'localStorage crm_token': localStorage.getItem('crm_token') || 'NOT FOUND',
                'localStorage auth_token': localStorage.getItem('auth_token') || 'NOT FOUND',
                'CSRF Token': csrfToken || 'NOT FOUND',
                'All localStorage keys': Object.keys(localStorage).join(', ') || 'None'
            };

            container.innerHTML = `
                <table class="table table-bordered table-sm">
                    ${Object.entries(tokenInfo).map(([key, value]) => `
                        <tr>
                            <th style="width: 30%;">${key}</th>
                            <td><code>${value}</code></td>
                        </tr>
                    `).join('')}
                </table>
            `;
        }

        // Check Role Checks
        async function checkRoleChecks() {
            const container = document.getElementById('role-checks');
            try {
                const response = await fetch('/api/me', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    const roleSlug = data.role?.slug || '';
                    const isCrm = checkIfCrm(roleSlug);
                    const isAdmin = data.is_admin || false;

                    container.innerHTML = `
                        <table class="table table-bordered">
                            <tr>
                                <th>Current Role Slug</th>
                                <td><code>${roleSlug || 'N/A'}</code></td>
                            </tr>
                            <tr>
                                <th>Is CRM Role?</th>
                                <td>
                                    ${isCrm ? '<span class="status-badge status-success">YES</span>' : '<span class="status-badge status-error">NO</span>'}
                                    <small class="text-muted ms-2">Required for CRM endpoints</small>
                                </td>
                            </tr>
                            <tr>
                                <th>Is Admin Role?</th>
                                <td>
                                    ${isAdmin ? '<span class="status-badge status-success">YES</span>' : '<span class="status-badge status-error">NO</span>'}
                                    <small class="text-muted ms-2">Admins also have access</small>
                                </td>
                            </tr>
                            <tr>
                                <th>Has Access?</th>
                                <td>
                                    ${(isCrm || isAdmin) ? '<span class="status-badge status-success">YES - Should have access</span>' : '<span class="status-badge status-error">NO - Missing CRM role</span>'}
                                </td>
                            </tr>
                        </table>
                    `;
                } else {
                    container.innerHTML = `<div class="result-box result-error">Could not fetch user data</div>`;
                }
            } catch (error) {
                container.innerHTML = `<div class="result-box result-error">Error: ${error.message}</div>`;
            }
        }

        // Test Middleware
        function testMiddleware() {
            const container = document.getElementById('middleware-test');
            container.innerHTML = `
                <p><strong>CheckCrmRole Middleware Logic:</strong></p>
                <ol>
                    <li>Check if user is authenticated (session or token)</li>
                    <li>Load user role relationship</li>
                    <li>Check if user is Admin → Allow</li>
                    <li>Check if user is CRM → Allow</li>
                    <li>Otherwise → Return 403 "Forbidden. CRM role required."</li>
                </ol>
                <div class="code-block mt-3">
// Middleware Code (CheckCrmRole.php):
\$user = \$request->user() ?? auth('web')->user();

if (!\$user) {
    return response()->json(['message' => 'Unauthenticated'], 401);
}

if (!\$user->isAdmin() && !\$user->isCrm()) {
    return response()->json(['message' => 'Forbidden. CRM role required.'], 403);
}
                </div>
            `;
        }

        // Test API Endpoint
        async function testApiEndpoint(endpoint) {
            const resultsContainer = document.getElementById('api-results');
            const testId = 'test-' + Date.now();
            
            // Add loading indicator
            const testDiv = document.createElement('div');
            testDiv.id = testId;
            testDiv.className = 'result-box result-info mb-3';
            testDiv.innerHTML = `<strong>Testing ${endpoint}...</strong><br><small>Please wait...</small>`;
            resultsContainer.insertBefore(testDiv, resultsContainer.firstChild);

            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                };

                if (csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }

                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                }

                const response = await fetch(endpoint, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: headers
                });

                const data = await response.json().catch(() => ({ message: 'Could not parse response' }));
                
                if (response.ok) {
                    testDiv.className = 'result-box result-success mb-3';
                    testDiv.innerHTML = `
                        <strong>✅ SUCCESS: ${endpoint}</strong>
                        <br><small>Status: ${response.status} ${response.statusText}</small>
                        <details class="mt-2">
                            <summary>View Response</summary>
                            <div class="code-block mt-2">${JSON.stringify(data, null, 2)}</div>
                        </details>
                    `;
                } else {
                    testDiv.className = 'result-box result-error mb-3';
                    testDiv.innerHTML = `
                        <strong>❌ ERROR: ${endpoint}</strong>
                        <br><small>Status: ${response.status} ${response.statusText}</small>
                        <br><strong>Message:</strong> ${data.message || 'Unknown error'}
                        <details class="mt-2">
                            <summary>View Full Response</summary>
                            <div class="code-block mt-2">${JSON.stringify(data, null, 2)}</div>
                        </details>
                    `;
                }
            } catch (error) {
                testDiv.className = 'result-box result-error mb-3';
                testDiv.innerHTML = `
                    <strong>❌ EXCEPTION: ${endpoint}</strong>
                    <br><strong>Error:</strong> ${error.message}
                    <details class="mt-2">
                        <summary>View Stack Trace</summary>
                        <div class="code-block mt-2">${error.stack || 'No stack trace available'}</div>
                    </details>
                `;
            }
        }

        // Test All Endpoints
        async function testAllEndpoints() {
            const endpoints = [
                '/api/crm/dashboard/stats',
                '/api/crm/dashboard/telecaller-stats',
                '/api/crm/dashboard/daily-prospects',
                '/api/crm/users',
                '/api/crm/roles',
                '/api/crm/blacklist',
                '/api/crm/pending-verifications'
            ];

            document.getElementById('api-results').innerHTML = '<p class="text-muted">Testing all endpoints...</p>';
            
            for (const endpoint of endpoints) {
                await testApiEndpoint(endpoint);
                await new Promise(resolve => setTimeout(resolve, 500)); // Delay between requests
            }

            updateRecommendations();
        }

        // Update Recommendations
        async function updateRecommendations() {
            const container = document.getElementById('recommendations');
            try {
                const response = await fetch('/api/me', {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    const roleSlug = data.role?.slug || '';
                    const isCrm = checkIfCrm(roleSlug);
                    const isAdmin = data.is_admin || false;

                    let recommendations = [];

                    if (!isCrm && !isAdmin) {
                        recommendations.push({
                            type: 'error',
                            title: 'Missing CRM Role',
                            message: `Your current role is "${roleSlug}". You need "crm" role to access CRM endpoints. Contact admin to assign CRM role.`
                        });
                    }

                    if (!authToken) {
                        recommendations.push({
                            type: 'warning',
                            title: 'No Token Found',
                            message: 'No crm_token found in localStorage. The page uses session-based auth, but token might be needed for API calls.'
                        });
                    }

                    if (isCrm || isAdmin) {
                        recommendations.push({
                            type: 'info',
                            title: 'Role Check Passed',
                            message: 'You have the required role. If API calls are still failing, check authentication status above.'
                        });
                    }

                    container.innerHTML = recommendations.map(rec => `
                        <div class="alert alert-${rec.type === 'error' ? 'danger' : rec.type === 'warning' ? 'warning' : 'info'} mb-2">
                            <strong>${rec.title}:</strong> ${rec.message}
                        </div>
                    `).join('');
                }
            } catch (error) {
                container.innerHTML = `<div class="alert alert-danger">Error generating recommendations: ${error.message}</div>`;
            }
        }

        // Helper function
        function checkIfCrm(roleSlug) {
            return roleSlug === 'crm';
        }
    </script>
</body>
</html>
