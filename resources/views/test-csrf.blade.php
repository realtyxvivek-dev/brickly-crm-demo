<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CSRF Debug Test Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #205A44;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            border-left: 4px solid #205A44;
        }
        .section h2 {
            margin-top: 0;
            color: #063A1C;
        }
        button {
            background: #205A44;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #063A1C;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            background: #fff;
            border: 1px solid #ddd;
            max-height: 500px;
            overflow-y: auto;
        }
        .success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box code {
            background: white;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 CSRF Debug Test Page</h1>
        
        <div class="info-box">
            <strong>Current Token:</strong> <code id="currentToken">{{ csrf_token() }}</code><br>
            <strong>API Base URL:</strong> <code id="apiBaseUrl">{{ config('app.url') }}/api</code>
        </div>

        <div class="section">
            <h2>Test 1: Simple CSRF Check (No Auth)</h2>
            <p>Test endpoint: <code>/api/test/csrf-check</code></p>
            <button onclick="testCsrfCheck()">Test CSRF Check</button>
            <div id="result1" class="result" style="display:none;"></div>
        </div>

        <div class="section">
            <h2>Test 2: Prospect Create (With Auth Token)</h2>
            <p>Test endpoint: <code>/api/telecaller/prospects/create</code></p>
            <button onclick="testProspectCreate()">Test Prospect Create</button>
            <div id="result2" class="result" style="display:none;"></div>
        </div>

        <div class="section">
            <h2>Test 3: Check Current Auth Token</h2>
            <button onclick="checkAuthToken()">Check Token from LocalStorage</button>
            <div id="result3" class="result" style="display:none;"></div>
        </div>

        <div class="section">
            <h2>Test 4: Full Debug Request</h2>
            <button onclick="fullDebugTest()">Run Full Debug Test</button>
            <div id="result4" class="result" style="display:none;"></div>
        </div>
    </div>

    <script>
        const API_BASE_URL = '{{ config('app.url') }}/api';
        
        function getToken() {
            return localStorage.getItem('telecaller_token') || localStorage.getItem('api_token');
        }

        function showResult(elementId, data, isError = false) {
            const element = document.getElementById(elementId);
            element.style.display = 'block';
            element.className = 'result ' + (isError ? 'error' : 'success');
            element.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        }

        async function testCsrfCheck() {
            try {
                const response = await fetch(API_BASE_URL + '/test/csrf-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ test: 'data' })
                });

                const data = await response.json();
                showResult('result1', {
                    status: response.status,
                    statusText: response.statusText,
                    data: data
                }, response.status >= 400);
            } catch (error) {
                showResult('result1', {
                    error: error.message,
                    stack: error.stack
                }, true);
            }
        }

        async function testProspectCreate() {
            const token = getToken();
            if (!token) {
                showResult('result2', { error: 'No token found in localStorage' }, true);
                return;
            }

            try {
                const testData = {
                    lead_id: 1,
                    customer_name: 'Test Customer',
                    phone: '1234567890',
                    purpose: 'end_user',
                    remark: 'Test remark'
                };

                console.log('Making request with:', {
                    url: API_BASE_URL + '/telecaller/prospects/create',
                    token: token.substring(0, 20) + '...',
                    data: testData
                });

                const response = await fetch(API_BASE_URL + '/telecaller/prospects/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token,
                    },
                    body: JSON.stringify(testData)
                });

                const responseText = await response.text();
                let responseData;
                try {
                    responseData = JSON.parse(responseText);
                } catch (e) {
                    responseData = { raw_response: responseText.substring(0, 500) };
                }

                showResult('result2', {
                    status: response.status,
                    statusText: response.statusText,
                    headers: Object.fromEntries(response.headers.entries()),
                    response: responseData,
                    request_data: testData
                }, response.status >= 400);
            } catch (error) {
                showResult('result2', {
                    error: error.message,
                    stack: error.stack
                }, true);
            }
        }

        function checkAuthToken() {
            const token = getToken();
            showResult('result3', {
                has_token: !!token,
                token_preview: token ? token.substring(0, 30) + '...' : null,
                token_length: token ? token.length : 0,
                storage_keys: {
                    telecaller_token: localStorage.getItem('telecaller_token') ? 'Exists' : 'Missing',
                    api_token: localStorage.getItem('api_token') ? 'Exists' : 'Missing'
                }
            }, !token);
        }

        async function fullDebugTest() {
            const token = getToken();
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            const debugInfo = {
                timestamp: new Date().toISOString(),
                token_info: {
                    has_token: !!token,
                    token_preview: token ? token.substring(0, 30) + '...' : null,
                },
                csrf_token: {
                    has_csrf: !!csrfToken,
                    token_preview: csrfToken ? csrfToken.substring(0, 20) + '...' : null,
                },
                url_info: {
                    current_url: window.location.href,
                    api_base: API_BASE_URL,
                },
                test_results: {}
            };

            // Test 1: Simple check
            try {
                const r1 = await fetch(API_BASE_URL + '/test/csrf-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ test: 'data' })
                });
                debugInfo.test_results.simple_check = {
                    status: r1.status,
                    success: r1.ok
                };
            } catch (e) {
                debugInfo.test_results.simple_check = { error: e.message };
            }

            // Test 2: With Bearer token
            if (token) {
                try {
                    const r2 = await fetch(API_BASE_URL + '/telecaller/prospects/create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'Authorization': 'Bearer ' + token,
                        },
                        body: JSON.stringify({
                            lead_id: 1,
                            customer_name: 'Test',
                            phone: '1234567890',
                            purpose: 'end_user',
                            remark: 'Test'
                        })
                    });
                    const text = await r2.text();
                    debugInfo.test_results.with_bearer_token = {
                        status: r2.status,
                        success: r2.ok,
                        response_preview: text.substring(0, 200)
                    };
                } catch (e) {
                    debugInfo.test_results.with_bearer_token = { error: e.message };
                }
            }

            showResult('result4', debugInfo, false);
        }
    </script>
</body>
</html>

