<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification API Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #063A1C;
            margin-bottom: 20px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .test-section h2 {
            color: #205A44;
            margin-top: 0;
        }
        button {
            background: #205A44;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        button:hover {
            background: #063A1C;
        }
        .result-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-top: 10px;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        .error {
            color: #ef4444;
            background: #fee2e2;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .success {
            color: #10b981;
            background: #d1fae5;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .info {
            color: #3b82f6;
            background: #dbeafe;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 5px 0;
        }
        label {
            display: block;
            margin: 10px 0 5px 0;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verification API Test Page</h1>
        
        <div class="test-section">
            <h2>1. Authentication Test</h2>
            <label>API Token (Bearer Token):</label>
            <input type="text" id="apiToken" placeholder="Enter your API token here" value="{{ session('api_token') ?? '' }}">
            <button onclick="testAuth()">Test Authentication</button>
            <div id="authResult"></div>
        </div>

        <div class="test-section">
            <h2>2. Test Pending Verifications Endpoint</h2>
            <button onclick="testPendingVerifications()">Test /api/admin/verifications/pending</button>
            <div id="pendingResult"></div>
        </div>

        <div class="test-section">
            <h2>3. Test Pending Closers Endpoint</h2>
            <button onclick="testPendingClosers()">Test /api/admin/verifications/pending-closers</button>
            <div id="closersResult"></div>
        </div>

        <div class="test-section">
            <h2>4. Test Pending Prospects Endpoint</h2>
            <button onclick="testPendingProspects()">Test /api/crm/verifications/pending-prospects</button>
            <div id="prospectsResult"></div>
        </div>

        <div class="test-section">
            <h2>5. Direct Database Query Test</h2>
            <button onclick="testDatabaseQuery()">Test Database Query (via API)</button>
            <div id="dbResult"></div>
        </div>

        <div class="test-section">
            <h2>6. Full Debug Test</h2>
            <button onclick="runFullDebug()">Run All Tests</button>
            <div id="fullDebugResult"></div>
        </div>
    </div>

    <script>
        function getToken() {
            // Try input field first
            const inputToken = document.getElementById('apiToken').value;
            if (inputToken) {
                return inputToken;
            }
            
            // Try localStorage
            const localToken = localStorage.getItem('crm_token');
            if (localToken) {
                return localToken;
            }
            
            // Try session
            const sessionToken = '{{ session("api_token") ?? "" }}';
            if (sessionToken) {
                return sessionToken;
            }
            
            // Try to get from authenticated user
            // If user is logged in, we can make a request to generate token
            return null;
        }
        
        // Auto-fill token on page load if available
        window.addEventListener('DOMContentLoaded', function() {
            const tokenInput = document.getElementById('apiToken');
            if (!tokenInput.value) {
                const existingToken = localStorage.getItem('crm_token') || '{{ session("api_token") ?? "" }}';
                if (existingToken) {
                    tokenInput.value = existingToken;
                } else {
                    // Try to generate token via backend (web route with session auth)
                    fetch('/test/generate-token', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.token) {
                            tokenInput.value = data.token;
                            localStorage.setItem('crm_token', data.token);
                            console.log('✅ Token auto-generated and stored');
                        } else {
                            console.log('⚠️ Token generation failed:', data);
                        }
                    })
                    .catch(err => console.log('Could not auto-generate token:', err));
                }
            }
        });

        function displayResult(elementId, data, isError = false) {
            const element = document.getElementById(elementId);
            const className = isError ? 'error' : 'success';
            element.innerHTML = `<div class="${className}"><strong>${isError ? '❌ Error' : '✅ Success'}:</strong><div class="result-box">${JSON.stringify(data, null, 2)}</div></div>`;
        }

        async function testAuth() {
            const token = getToken();
            if (!token) {
                displayResult('authResult', { error: 'No token provided' }, true);
                return;
            }

            try {
                const response = await fetch('/api/me', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();
                displayResult('authResult', {
                    status: response.status,
                    statusText: response.statusText,
                    data: data
                }, !response.ok);
            } catch (error) {
                displayResult('authResult', {
                    error: error.message,
                    stack: error.stack
                }, true);
            }
        }

        async function testPendingVerifications() {
            const token = getToken();
            const resultDiv = document.getElementById('pendingResult');
            resultDiv.innerHTML = '<div class="info">⏳ Testing...</div>';

            try {
                const startTime = Date.now();
                const response = await fetch('/api/admin/verifications/pending', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                });

                const endTime = Date.now();
                const responseTime = endTime - startTime;

                let responseData;
                const responseText = await response.text();
                
                try {
                    responseData = JSON.parse(responseText);
                } catch (e) {
                    responseData = { raw_response: responseText, parse_error: e.message };
                }

                const result = {
                    status: response.status,
                    statusText: response.statusText,
                    responseTime: `${responseTime}ms`,
                    headers: Object.fromEntries(response.headers.entries()),
                    url: response.url,
                    data: responseData,
                    meetings_count: responseData?.meetings?.length || 0,
                    site_visits_count: responseData?.site_visits?.length || 0,
                };

                displayResult('pendingResult', result, !response.ok);
            } catch (error) {
                displayResult('pendingResult', {
                    error: error.message,
                    stack: error.stack,
                    name: error.name
                }, true);
            }
        }

        async function testPendingClosers() {
            const token = getToken();
            const resultDiv = document.getElementById('closersResult');
            resultDiv.innerHTML = '<div class="info">⏳ Testing...</div>';

            try {
                const response = await fetch('/api/admin/verifications/pending-closers', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                });

                const responseText = await response.text();
                let responseData;
                try {
                    responseData = JSON.parse(responseText);
                } catch (e) {
                    responseData = { raw_response: responseText, parse_error: e.message };
                }

                displayResult('closersResult', {
                    status: response.status,
                    statusText: response.statusText,
                    data: responseData,
                    closers_count: responseData?.data?.length || 0,
                }, !response.ok);
            } catch (error) {
                displayResult('closersResult', {
                    error: error.message,
                    stack: error.stack
                }, true);
            }
        }

        async function testPendingProspects() {
            const token = getToken();
            const resultDiv = document.getElementById('prospectsResult');
            resultDiv.innerHTML = '<div class="info">⏳ Testing...</div>';

            try {
                const response = await fetch('/api/crm/verifications/pending-prospects?verification_status=pending_verification', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                });

                const responseText = await response.text();
                let responseData;
                try {
                    responseData = JSON.parse(responseText);
                } catch (e) {
                    responseData = { raw_response: responseText, parse_error: e.message };
                }

                displayResult('prospectsResult', {
                    status: response.status,
                    statusText: response.statusText,
                    data: responseData,
                    prospects_count: responseData?.data?.length || 0,
                }, !response.ok);
            } catch (error) {
                displayResult('prospectsResult', {
                    error: error.message,
                    stack: error.stack
                }, true);
            }
        }

        async function testDatabaseQuery() {
            const token = getToken();
            const resultDiv = document.getElementById('dbResult');
            resultDiv.innerHTML = '<div class="info">⏳ Testing...</div>';

            try {
                // Create a test endpoint to check database directly
                const response = await fetch('/api/test/db-query', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({})
                });

                const responseText = await response.text();
                let responseData;
                try {
                    responseData = JSON.parse(responseText);
                } catch (e) {
                    responseData = { raw_response: responseText, parse_error: e.message };
                }

                displayResult('dbResult', {
                    status: response.status,
                    data: responseData,
                }, !response.ok);
            } catch (error) {
                displayResult('dbResult', {
                    error: error.message,
                    stack: error.stack
                }, true);
            }
        }

        async function runFullDebug() {
            const resultDiv = document.getElementById('fullDebugResult');
            resultDiv.innerHTML = '<div class="info">⏳ Running full debug test...</div>';

            const results = {
                timestamp: new Date().toISOString(),
                token: getToken() ? 'Present' : 'Missing',
                tests: {}
            };

            // Test 1: Auth
            try {
                const authRes = await fetch('/api/me', {
                    headers: { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' }
                });
                results.tests.auth = {
                    status: authRes.status,
                    ok: authRes.ok,
                    data: await authRes.json().catch(() => ({ error: 'Failed to parse' }))
                };
            } catch (e) {
                results.tests.auth = { error: e.message };
            }

            // Test 2: Pending Verifications
            try {
                const pendingRes = await fetch('/api/admin/verifications/pending', {
                    headers: { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' }
                });
                const pendingText = await pendingRes.text();
                results.tests.pending_verifications = {
                    status: pendingRes.status,
                    ok: pendingRes.ok,
                    headers: Object.fromEntries(pendingRes.headers.entries()),
                    response: pendingText.length > 1000 ? pendingText.substring(0, 1000) + '... (truncated)' : pendingText,
                    parsed: (() => {
                        try {
                            return JSON.parse(pendingText);
                        } catch (e) {
                            return { parse_error: e.message };
                        }
                    })()
                };
            } catch (e) {
                results.tests.pending_verifications = { error: e.message, stack: e.stack };
            }

            // Test 3: Check browser info
            results.browser = {
                userAgent: navigator.userAgent,
                url: window.location.href,
                cookies: document.cookie
            };

            displayResult('fullDebugResult', results, false);
        }
    </script>
</body>
</html>

