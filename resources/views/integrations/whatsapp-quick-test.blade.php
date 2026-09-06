<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WhatsApp Quick Test - {{ brand_name() }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .test-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 30px;
            color: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .success-animation {
            animation: successPulse 0.6s ease-in-out;
        }
        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-2xl w-full">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">
                    <i class="fab fa-whatsapp text-green-500 mr-3"></i>
                    WhatsApp Quick Test
                </h1>
                <p class="text-gray-600">Test message send karein ek click mein</p>
            </div>

            <!-- Test Card -->
            <div class="test-card mb-6">
                <div class="mb-6">
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-phone mr-2"></i>Phone Number
                    </label>
                    <input type="text" 
                           id="phone" 
                           value="+91 8354006519"
                           class="w-full px-4 py-3 rounded-lg text-gray-900 focus:ring-2 focus:ring-white focus:outline-none text-lg font-semibold"
                           placeholder="+91 8354006519">
                    <p class="text-white text-xs mt-2 opacity-80">Country code ke saath number enter karein</p>
                </div>

                <div class="mb-6">
                    <label class="block text-white text-sm font-medium mb-2">
                        <i class="fas fa-comment mr-2"></i>Test Message
                    </label>
                    <textarea id="message" 
                              rows="4"
                              class="w-full px-4 py-3 rounded-lg text-gray-900 focus:ring-2 focus:ring-white focus:outline-none resize-none"
                              placeholder="Test message yahan type karein...">Test message from CRM - {{ now()->format('d M Y H:i:s') }}</textarea>
                </div>

                <button onclick="sendTestMessage()" 
                        id="sendBtn"
                        class="btn-primary w-full py-4 rounded-lg text-white font-bold text-lg shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i>
                    <span id="btnText">Send Test Message</span>
                    <span id="btnLoader" class="hidden">
                        <i class="fas fa-spinner fa-spin mr-2"></i> Sending...
                    </span>
                </button>
            </div>

            <!-- Result Card -->
            <div id="resultCard" class="hidden bg-white rounded-xl shadow-lg p-6 mb-6">
                <div id="resultContent"></div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-bolt mr-2 text-yellow-500"></i>Quick Actions
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="{{ route($whatsappIndexRoute) }}" 
                       class="flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-cog mr-2"></i> Configuration
                    </a>
                    <a href="{{ route($whatsappRoutePrefix . 'debug') }}" 
                       class="flex items-center justify-center px-4 py-3 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 transition-colors">
                        <i class="fas fa-bug mr-2"></i> Debug Tool
                    </a>
                    <a href="{{ $whatsappBackRoute }}" 
                       class="flex items-center justify-center px-4 py-3 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </div>
            </div>

            <!-- API Info -->
            <div class="mt-6 bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>API Information
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Endpoint:</span>
                        <span class="text-gray-900 font-mono">{{ $settings->api_endpoint }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="px-2 py-1 rounded {{ $settings->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $settings->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Verified:</span>
                        <span class="px-2 py-1 rounded {{ $settings->is_verified ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ $settings->is_verified ? 'Yes' : 'No' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function sendTestMessage() {
            const phone = document.getElementById('phone').value.trim();
            const message = document.getElementById('message').value.trim();
            const btn = document.getElementById('sendBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            const resultCard = document.getElementById('resultCard');
            const resultContent = document.getElementById('resultContent');

            if (!phone || !message) {
                alert('Please enter phone number and message');
                return;
            }

            // Update button state
            btn.disabled = true;
            btnText.classList.add('hidden');
            btnLoader.classList.remove('hidden');
            resultCard.classList.add('hidden');

            // Send request
            fetch('{{ route($whatsappRoutePrefix . "quick-test.send") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    phone: phone,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                btn.disabled = false;
                btnText.classList.remove('hidden');
                btnLoader.classList.add('hidden');

                // Show result
                resultCard.classList.remove('hidden');
                
                if (data.success) {
                    resultContent.innerHTML = `
                        <div class="success-animation">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900">Success!</h3>
                                    <p class="text-gray-600">${data.message}</p>
                                </div>
                            </div>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                <p class="text-sm text-gray-700 mb-2">
                                    <strong>Phone:</strong> <span class="font-mono">${data.phone}</span>
                                </p>
                                <p class="text-sm text-gray-700">
                                    <strong>Message:</strong> ${data.message}
                                </p>
                            </div>
                            ${data.results && data.results.length > 0 ? `
                                <div class="mt-4">
                                    <p class="text-sm font-medium text-gray-700 mb-2">Response Details:</p>
                                    <div class="bg-gray-50 rounded-lg p-4 overflow-x-auto">
                                        <pre class="text-xs text-gray-800">${JSON.stringify(data.results[0].response || data.results[0], null, 2)}</pre>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    btn.classList.add('success-animation');
                    setTimeout(() => btn.classList.remove('success-animation'), 600);
                } else {
                    resultContent.innerHTML = `
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Failed</h3>
                                <p class="text-gray-600">${data.message}</p>
                            </div>
                        </div>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <p class="text-sm font-medium text-red-800 mb-2">Error:</p>
                            <p class="text-sm text-red-700">${typeof data.error === 'object' ? JSON.stringify(data.error, null, 2) : (data.error || 'Unknown error occurred')}</p>
                        </div>
                        ${data.results && data.results.length > 0 ? `
                            <div class="mt-4">
                                <p class="text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-list mr-2"></i>Tested Endpoints (${data.results.length}):
                                </p>
                                <div class="space-y-2 max-h-96 overflow-y-auto">
                                    ${data.results.map((result, index) => `
                                        <div class="bg-gray-50 rounded-lg p-3 border ${result.success ? 'border-green-200 bg-green-50' : 'border-red-200'}">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex-1">
                                                    <span class="text-xs font-mono text-gray-900 font-semibold">${result.endpoint || 'N/A'}</span>
                                                    ${result.payload_format ? `<p class="text-xs text-gray-500 mt-1 font-mono">Payload: ${result.payload_format}</p>` : ''}
                                                </div>
                                                <span class="px-2 py-1 rounded text-xs font-semibold ${result.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                                    ${result.success ? '✓ SUCCESS' : '✗ FAILED'}
                                                </span>
                                            </div>
                                            ${result.status_code ? `<p class="text-xs text-gray-600 mb-1"><strong>Status:</strong> ${result.status_code}</p>` : ''}
                                            ${result.url ? `<p class="text-xs text-gray-500 mb-1 break-all"><strong>URL:</strong> ${result.url}</p>` : ''}
                                            ${result.error ? `<p class="text-xs text-red-600 mt-1 bg-red-50 p-2 rounded"><strong>Error:</strong> ${typeof result.error === 'object' ? JSON.stringify(result.error, null, 2) : result.error}</p>` : ''}
                                            ${result.response && typeof result.response === 'object' ? `
                                                <details class="mt-2">
                                                    <summary class="text-xs text-gray-600 cursor-pointer hover:text-gray-800">View Response</summary>
                                                    <pre class="text-xs text-gray-700 mt-2 bg-white p-2 rounded border overflow-x-auto">${JSON.stringify(result.response, null, 2)}</pre>
                                                </details>
                                            ` : result.response ? `
                                                <details class="mt-2">
                                                    <summary class="text-xs text-gray-600 cursor-pointer hover:text-gray-800">View Response</summary>
                                                    <pre class="text-xs text-gray-700 mt-2 bg-white p-2 rounded border overflow-x-auto">${result.response}</pre>
                                                </details>
                                            ` : ''}
                                        </div>
                                    `).join('')}
                                </div>
                                ${!data.success ? `
                                    <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                        <p class="text-xs text-yellow-800 mb-2">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <strong>Note:</strong> All endpoints returned 404 (Not Found) errors.
                                        </p>
                                        ${data.suggestion ? `
                                            <p class="text-xs text-yellow-700 mb-2">
                                                <strong>Suggestion:</strong> ${data.suggestion}
                                            </p>
                                        ` : ''}
                                        <p class="text-xs text-yellow-800 mb-2">
                                            <strong>Please check:</strong>
                                        </p>
                                        <ul class="text-xs text-yellow-700 mt-1 ml-4 list-disc">
                                            <li>API endpoint URL is correct</li>
                                            <li>API token is valid</li>
                                            <li>Phone number format is correct (with country code)</li>
                                            <li>API service is active and accessible</li>
                                            <li>Check API Explorer at <a href="${data.results[0]?.url?.replace(/\/[^\/]+$/, '') || 'https://engage-api-eta.vercel.app'}" target="_blank" class="underline">${data.results[0]?.url?.replace(/\/[^\/]+$/, '') || 'https://engage-api-eta.vercel.app'}</a> for documentation</li>
                                        </ul>
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    `;
                }
            })
            .catch(error => {
                // Reset button
                btn.disabled = false;
                btnText.classList.remove('hidden');
                btnLoader.classList.add('hidden');

                // Show error
                resultCard.classList.remove('hidden');
                resultContent.innerHTML = `
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Error</h3>
                            <p class="text-gray-600">Network error occurred</p>
                        </div>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-sm text-red-700">${error.message}</p>
                    </div>
                `;
            });
        }

        // Allow Enter key to send (Ctrl+Enter or Cmd+Enter)
        document.getElementById('message').addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                sendTestMessage();
            }
        });

        // Auto-focus on message field
        document.getElementById('message').focus();
    </script>
</body>
</html>
