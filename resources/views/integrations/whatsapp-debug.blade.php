<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WhatsApp API Debug - {{ brand_name() }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .test-result {
            border-left: 4px solid #e5e7eb;
            padding: 15px;
            margin-bottom: 15px;
            background: #f9fafb;
            border-radius: 5px;
        }
        .test-result.success {
            border-left-color: #10b981;
            background: #d1fae5;
        }
        .test-result.error {
            border-left-color: #ef4444;
            background: #fee2e2;
        }
        .test-result.warning {
            border-left-color: #f59e0b;
            background: #fef3c7;
        }
        .json-view {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 400px;
            overflow-y: auto;
        }
        .endpoint-test {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
    </style>
</head>
<body class="bg-gray-50 p-6">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-bold text-gray-900">
                    <i class="fab fa-whatsapp text-green-500 mr-2"></i>
                    WhatsApp API Debug Tool
                </h1>
                <div class="flex space-x-3">
                    <button onclick="downloadErrorReport()" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 font-medium">
                        <i class="fas fa-download mr-2"></i> Download Error Report
                    </button>
                    <a href="{{ route($whatsappIndexRoute) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Configuration
                    </a>
                </div>
            </div>
        </div>

        <!-- Settings Info -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Current Settings</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-600">API Endpoint</label>
                    <p class="text-gray-900 font-mono">{{ $settings->api_endpoint }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">API Token</label>
                    <p class="text-gray-900 font-mono">{{ substr($settings->api_token, 0, 20) }}...</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">Status</label>
                    <p class="text-gray-900">
                        @if($settings->is_active)
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded">Active</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded">Inactive</span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">Verified</label>
                    <p class="text-gray-900">
                        @if($settings->is_verified)
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded">Yes</span>
                        @else
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">No</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Endpoint Tests -->
        @if(isset($debug['tests']))
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Endpoint Tests</h2>
            @foreach($debug['tests'] as $test)
            <div class="endpoint-test">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-medium text-gray-900">
                        {{ $test['endpoint'] }} ({{ $test['method'] }})
                    </h3>
                    <span class="px-3 py-1 rounded text-sm font-medium {{ $test['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $test['success'] ? 'SUCCESS' : 'FAILED' }}
                    </span>
                </div>
                <p class="text-sm text-gray-600 mb-2">URL: <code class="bg-gray-100 px-2 py-1 rounded">{{ $test['url'] }}</code></p>
                @if($test['status_code'])
                <p class="text-sm text-gray-600 mb-2">Status Code: <strong>{{ $test['status_code'] }}</strong></p>
                @endif
                @if($test['time_taken'])
                <p class="text-sm text-gray-600 mb-2">Time: <strong>{{ $test['time_taken'] }}</strong></p>
                @endif
                @if($test['error'])
                <div class="mt-2 p-3 bg-red-50 border border-red-200 rounded">
                    <p class="text-sm font-medium text-red-800">Error:</p>
                    <p class="text-sm text-red-700">{{ $test['error'] }}</p>
                </div>
                @endif
                @if($test['response'])
                <div class="mt-2">
                    <p class="text-sm font-medium text-gray-700 mb-1">Response:</p>
                    <div class="json-view">{{ json_encode($test['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <!-- Manual Test Section -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Manual API Test</h2>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Test Endpoint Path</label>
                <input type="text" 
                       id="test_path" 
                       value="/status" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                       placeholder="/status, /health, /send-message, etc.">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Method</label>
                <select id="test_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                </select>
            </div>
            
            <div id="payload_section" class="mb-4" style="display: none;">
                <label class="block text-sm font-medium text-gray-700 mb-2">Payload (JSON)</label>
                <textarea id="test_payload" 
                          rows="4"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg font-mono text-sm"
                          placeholder='{"to": "919876543210", "message": "Test"}'></textarea>
            </div>
            
            <div class="flex space-x-3">
                <button onclick="testEndpoint()" 
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-play mr-2"></i> Test Endpoint
                </button>
                <button onclick="testWithCurl()" 
                        class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    <i class="fas fa-terminal mr-2"></i> Test with CURL
                </button>
            </div>
            
            <div id="test_result" class="mt-4" style="display: none;"></div>
        </div>

        <!-- System Info -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">System Information</h2>
            <div class="json-view">{{ json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const apiEndpoint = '{{ $settings->api_endpoint }}';
        
        // Store all test results for download
        let allTestResults = {
            timestamp: new Date().toISOString(),
            settings: @json($debug['settings'] ?? []),
            endpoint_cleaned: '{{ $debug['endpoint_cleaned'] ?? '' }}',
            tests: @json($debug['tests'] ?? []),
            system_info: @json($debug),
            manual_tests: []
        };
        
        document.getElementById('test_method').addEventListener('change', function() {
            document.getElementById('payload_section').style.display = this.value === 'POST' ? 'block' : 'none';
        });
        
        function downloadErrorReport() {
            // Collect all visible test results
            const manualTestResult = document.getElementById('test_result');
            if (manualTestResult && manualTestResult.style.display !== 'none') {
                const testData = {
                    timestamp: new Date().toISOString(),
                    endpoint: document.getElementById('test_path').value,
                    method: document.getElementById('test_method').value,
                    payload: document.getElementById('test_payload').value || null,
                    result_html: manualTestResult.innerHTML
                };
                allTestResults.manual_tests.push(testData);
            }
            
            // Add browser info
            allTestResults.browser_info = {
                user_agent: navigator.userAgent,
                platform: navigator.platform,
                language: navigator.language,
                cookie_enabled: navigator.cookieEnabled,
                on_line: navigator.onLine
            };
            
            // Add page URL
            allTestResults.page_url = window.location.href;
            
            // Create JSON blob
            const jsonString = JSON.stringify(allTestResults, null, 2);
            const blob = new Blob([jsonString], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            // Create download link
            const a = document.createElement('a');
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
            a.href = url;
            a.download = `whatsapp-api-debug-report-${timestamp}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            // Show success message
            alert('Error report downloaded successfully! Please share this file for troubleshooting.');
        }
        
        function testEndpoint() {
            const path = document.getElementById('test_path').value;
            const method = document.getElementById('test_method').value;
            const payload = document.getElementById('test_payload').value;
            
            const resultDiv = document.getElementById('test_result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="p-4 bg-blue-50 border border-blue-200 rounded">Testing... Please wait.</div>';
            
            let url = '{{ route($whatsappRoutePrefix . "debug.post") }}';
            let body = {
                path: path,
                payload: payload ? JSON.parse(payload) : null
            };
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(body)
            })
            .then(response => response.json())
            .then(data => {
                displayResult(data);
                // Store result for download
                allTestResults.manual_tests.push({
                    timestamp: new Date().toISOString(),
                    endpoint: path,
                    method: method,
                    payload: payload ? JSON.parse(payload) : null,
                    result: data
                });
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="p-4 bg-red-50 border border-red-200 rounded">
                        <strong>Error:</strong> ${error.message}
                    </div>
                `;
                // Store error for download
                allTestResults.manual_tests.push({
                    timestamp: new Date().toISOString(),
                    endpoint: path,
                    method: method,
                    payload: payload ? JSON.parse(payload) : null,
                    error: error.message
                });
            });
        }
        
        function testWithCurl() {
            const path = document.getElementById('test_path').value;
            
            const resultDiv = document.getElementById('test_result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="p-4 bg-blue-50 border border-blue-200 rounded">Testing with CURL... Please wait.</div>';
            
            fetch('{{ route($whatsappRoutePrefix . "debug.curl") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ path: path })
            })
            .then(response => response.json())
            .then(data => {
                displayResult(data);
                // Store result for download
                allTestResults.manual_tests.push({
                    timestamp: new Date().toISOString(),
                    endpoint: path,
                    method: 'CURL',
                    result: data
                });
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="p-4 bg-red-50 border border-red-200 rounded">
                        <strong>Error:</strong> ${error.message}
                    </div>
                `;
                // Store error for download
                allTestResults.manual_tests.push({
                    timestamp: new Date().toISOString(),
                    endpoint: path,
                    method: 'CURL',
                    error: error.message
                });
            });
        }
        
        function displayResult(data) {
            const resultDiv = document.getElementById('test_result');
            const success = data.success;
            const className = success ? 'success' : 'error';
            
            let html = `
                <div class="test-result ${className}">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-semibold">${data.method || 'Test'} Result</h4>
                        <span class="px-3 py-1 rounded text-sm font-medium ${success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${success ? 'SUCCESS' : 'FAILED'}
                        </span>
                    </div>
                    <p class="text-sm mb-2"><strong>URL:</strong> <code>${data.url || 'N/A'}</code></p>
            `;
            
            if (data.status_code) {
                html += `<p class="text-sm mb-2"><strong>Status Code:</strong> ${data.status_code}</p>`;
            }
            
            if (data.time_taken) {
                html += `<p class="text-sm mb-2"><strong>Time:</strong> ${data.time_taken}</p>`;
            }
            
            if (data.error) {
                html += `
                    <div class="mt-2 p-3 bg-red-50 border border-red-200 rounded">
                        <p class="text-sm font-medium text-red-800">Error:</p>
                        <p class="text-sm text-red-700">${data.error}</p>
                    </div>
                `;
            }
            
            if (data.response) {
                html += `
                    <div class="mt-2">
                        <p class="text-sm font-medium mb-1">Response:</p>
                        <div class="json-view">${JSON.stringify(data.response, null, 2)}</div>
                    </div>
                `;
            }
            
            if (data.curl_info) {
                html += `
                    <div class="mt-2">
                        <p class="text-sm font-medium mb-1">CURL Info:</p>
                        <div class="json-view">${JSON.stringify(data.curl_info, null, 2)}</div>
                    </div>
                `;
            }
            
            html += '</div>';
            resultDiv.innerHTML = html;
        }
    </script>
</body>
</html>
