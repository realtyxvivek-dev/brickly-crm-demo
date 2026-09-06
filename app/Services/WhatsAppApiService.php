<?php

namespace App\Services;

use App\Models\WhatsAppApiSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppApiService
{
    protected $baseUrl;
    protected $endpoint;
    protected $token;
    protected $settings;
    protected $resolvedAuthMode = null;

    public function __construct()
    {
        $this->settings = WhatsAppApiSettings::getSettings();
        
        // Normalize base URL - remove trailing slashes and fix common issues
        $rawBaseUrl = $this->settings->base_url ?? $this->settings->api_endpoint ?? 'https://rengage.mcube.com';
        $this->baseUrl = $this->normalizeUrl($rawBaseUrl);
        
        $rawEndpoint = $this->settings->api_endpoint ?? $this->baseUrl;
        $this->endpoint = $this->normalizeUrl($rawEndpoint);
        
        $this->token = is_string($this->settings->api_token ?? null) ? trim($this->settings->api_token) : '';
    }

    public function activeProviderName(): string
    {
        return 'third_party';
    }
    
    /**
     * Normalize URL - fix common formatting issues
     */
    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        
        if (empty($url)) {
            return $url;
        }
        
        // Remove trailing slashes first
        $url = rtrim($url, '/');
        
        // Fix cases where https/http is appended incorrectly (e.g., "domain.comhttps" or "https://domain.comhttps")
        // Pattern: remove trailing "https" or "http" if it appears after domain
        $url = preg_replace('/(https?:\/\/)?([^\/]+?)(https?)$/i', '$1$2', $url);
        
        // If URL doesn't start with http:// or https://, add https://
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }
        
        // Remove trailing slashes again after normalization
        return rtrim($url, '/');
    }

    /**
     * Parse stored token and infer auth mode.
     *
     * Supported stored formats:
     * - "Bearer <token>" (Authorization)
     * - "Token <token>"  (Authorization)
     * - "Basic <token>"  (Authorization)
     * - "X-API-KEY <token>" / "ApiKey <token>" / "api-key:<token>" (X-API-KEY header)
     * - "<token>" (default: Bearer)
     */
    protected function parseToken(): array
    {
        $raw = is_string($this->token ?? null) ? trim($this->token) : '';

        if ($raw === '') {
            return ['mode' => null, 'token' => ''];
        }

        if (preg_match('/^(Bearer|Token|Basic)\s+(.+)$/i', $raw, $m)) {
            return ['mode' => strtolower($m[1]), 'token' => trim($m[2])];
        }

        if (preg_match('/^(X-API-KEY|XAPIKEY|APIKEY|ApiKey|api-key)\s+(.+)$/i', $raw, $m)) {
            return ['mode' => 'x-api-key', 'token' => trim($m[2])];
        }

        if (preg_match('/^(x-api-key|api-key)\s*:\s*(.+)$/i', $raw, $m)) {
            return ['mode' => 'x-api-key', 'token' => trim($m[2])];
        }

        // Default for raw tokens
        return ['mode' => 'bearer', 'token' => $raw];
    }

    protected function formatTokenForStorage(string $mode, string $token): string
    {
        $token = trim($token);
        return match ($mode) {
            'bearer' => "Bearer {$token}",
            'token' => "Token {$token}",
            'basic' => "Basic {$token}",
            'raw' => $token,
            'x-api-key' => "X-API-KEY {$token}",
            default => $token,
        };
    }

    protected function buildAuthHeaders(string $mode, string $token): array
    {
        $token = trim($token);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        return match ($mode) {
            'bearer' => $headers + ['Authorization' => 'Bearer ' . $token],
            'token' => $headers + ['Authorization' => 'Token ' . $token],
            'basic' => $headers + ['Authorization' => 'Basic ' . $token],
            'raw' => $headers + ['Authorization' => $token],
            'x-api-key' => $headers + ['X-API-KEY' => $token, 'x-api-key' => $token],
            default => $headers,
        };
    }

    protected function authModeCandidates(string $token): array
    {
        // Try the most common header styles across providers.
        // Order matters: prefer Bearer, then Token, then raw Authorization, then X-API-KEY.
        return [
            ['mode' => 'bearer', 'token' => $token],
            ['mode' => 'token', 'token' => $token],
            ['mode' => 'raw', 'token' => $token],
            ['mode' => 'x-api-key', 'token' => $token],
        ];
    }

    /**
     * Build full URL from endpoint path
     */
    protected function buildUrl($endpointPath, $replacements = [])
    {
        // Normalize endpoint path - remove any full URLs, ensure it starts with /
        $endpointPath = $this->normalizeEndpointPath($endpointPath);
        
        $url = $this->baseUrl . $endpointPath;
        
        // Replace placeholders like {id}, {contact}, {templateID}
        foreach ($replacements as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }
        
        return $url;
    }
    
    /**
     * Normalize endpoint path - ensure it's a relative path starting with /
     */
    protected function normalizeEndpointPath(string $endpointPath): string
    {
        $endpointPath = trim($endpointPath);
        
        // If endpoint path is a full URL, extract just the path part
        if (preg_match('/^https?:\/\/[^\/]+(\/.*)$/i', $endpointPath, $matches)) {
            $endpointPath = $matches[1];
        }
        
        // Ensure it starts with /
        if (!str_starts_with($endpointPath, '/')) {
            $endpointPath = '/' . $endpointPath;
        }
        
        return $endpointPath;
    }

    /**
     * Make API request
     */
    protected function makeRequest($method, $endpointPath, $data = [], $replacements = [])
    {
        $url = $this->buildUrl($endpointPath, $replacements);

        $parsed = $this->parseToken();
        $primaryMode = $this->resolvedAuthMode ?? ($parsed['mode'] ?? 'bearer');
        $primaryToken = $parsed['token'] ?? '';
        $headers = $this->buildAuthHeaders($primaryMode, $primaryToken);

        try {
            $doRequest = function(array $hdrs) use ($method, $url, $data) {
                $req = Http::withHeaders($hdrs)->timeout(30);
                return match ($method) {
                    'GET' => $req->get($url, $data),
                    'POST' => $req->post($url, $data),
                    'PUT' => $req->put($url, $data),
                    'DELETE' => $req->delete($url, $data),
                    default => $req->send($method, $url, ['json' => $data]),
                };
            };

            $response = $doRequest($headers);

            // If auth failed, try other auth modes automatically (helps when token is valid but scheme differs).
            if (in_array($response->status(), [401, 403], true) && !empty($primaryToken)) {
                foreach ($this->authModeCandidates($primaryToken) as $candidate) {
                    if (($candidate['mode'] ?? null) === $primaryMode) {
                        continue;
                    }
                    $candidateHeaders = $this->buildAuthHeaders($candidate['mode'], $candidate['token']);
                    $candidateResponse = $doRequest($candidateHeaders);
                    if ($candidateResponse->successful()) {
                        // Cache resolved mode for this request lifecycle to avoid repeated retries.
                        $this->resolvedAuthMode = $candidate['mode'];
                        $response = $candidateResponse;
                        break;
                    }
                }
            }

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json() ?? $response->body(),
                    'endpoint_used' => $endpointPath,
                ];
            }

            $errorMsg = null;
            $responseJson = $response->json();
            $responseBody = $response->body();
            
            if ($responseJson) {
                $errorMsg = $responseJson['message'] ?? $responseJson['error'] ?? $responseJson['msg'] ?? null;
            }
            
            // Handle 405 Method Not Allowed errors specifically
            if ($response->status() === 405) {
                // If error mentions wrong method, provide helpful message
                if (str_contains($errorMsg ?? $responseBody, 'POST method is not supported') || 
                    str_contains($errorMsg ?? $responseBody, 'GET, HEAD')) {
                    $errorMsg = 'Invalid endpoint configuration: The endpoint does not support ' . $method . ' method. Please check your WhatsApp API endpoint settings.';
                } else {
                    $errorMsg = $errorMsg ?? 'HTTP method not allowed for this endpoint. Please check your WhatsApp API endpoint configuration.';
                }
            }
            
            return [
                'success' => false,
                'error' => $errorMsg ?? "HTTP {$response->status()}",
                'status' => $response->status(),
                'endpoint_used' => $endpointPath,
                'method_used' => $method,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('WhatsApp API Connection Error: ' . $e->getMessage(), [
                'url' => $url,
                'method' => $method,
                'endpoint' => $endpointPath,
            ]);
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage() . '. Please check the Base URL in settings.',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp API Request Error: ' . $e->getMessage(), [
                'url' => $url,
                'method' => $method,
                'endpoint' => $endpointPath,
                'trace' => $e->getTraceAsString(),
            ]);
            $errorMsg = $e->getMessage();
            if (str_contains($errorMsg, 'Could not resolve host') || str_contains($errorMsg, 'cURL error 6')) {
                $errorMsg = 'Cannot connect to API server. Please check the Base URL: ' . $this->baseUrl;
            }
            return [
                'success' => false,
                'error' => $errorMsg,
            ];
        }
    }

    /**
     * Send WhatsApp message
     */
    public function sendMessage(string $to, string $message, ?string $templateId = null)
    {
        if (!$this->isConfigured()) {
            throw new \Exception('WhatsApp API is not configured');
        }

        try {
            // Format phone number and extract country code
            $phone = preg_replace('/[^0-9]/', '', $to);
            
            // Extract country code (default to 91 for India if 10 digits)
            $countryCode = '91'; // Default to India
            if (strlen($phone) == 10) {
                // 10 digit number, add country code
                $phone = $countryCode . $phone;
            } elseif (strlen($phone) > 10) {
                // Number already has country code, extract it
                if (str_starts_with($phone, '91') && strlen($phone) == 12) {
                    $countryCode = '91';
                } elseif (strlen($phone) >= 12) {
                    // Extract first 1-3 digits as country code
                    $countryCode = substr($phone, 0, strlen($phone) - 10);
                    $phone = $phone; // Keep full number
                }
            }
            
            // Use configured endpoint or fallback to default
            $endpointPath = $this->settings->send_message_endpoint ?? '/api/wpbox/sendmessage';
            
            // MCube Rengage API - correct payload format
            $localPhone = $phone;
            if (str_starts_with($phone, '91') && strlen($phone) == 12) {
                $localPhone = substr($phone, 2);
            }
            $payloadFormats = [
                ['phone' => $localPhone, 'country_code' => $countryCode, 'message' => $message],
            ];

            if ($templateId) {
                // If template ID is provided, use template endpoint instead
                return $this->sendTemplateMessage($to, $templateId);
            }

            $lastError = null;

            // Try different payload formats with the configured endpoint
            foreach ($payloadFormats as $payload) {
                try {
                    $result = $this->makeRequest('POST', $endpointPath, $payload);
                    
                    if ($result['success']) {
                        Log::info('WhatsApp API Success', [
                            'endpoint' => $endpointPath,
                            'phone' => $phone,
                            'country_code' => $countryCode,
                            'payload_format' => json_encode($payload),
                        ]);
                        return [
                            'success' => true,
                            'data' => $result['data'],
                            'endpoint_used' => $endpointPath,
                            'payload_format_used' => json_encode($payload),
                        ];
                    } else {
                        // If error mentions country code, try next format
                        $errorMsg = $result['error'] ?? 'Request failed';
                        if (str_contains(strtolower($errorMsg), 'country code')) {
                            $lastError = $errorMsg;
                            continue; // Try next format
                        }
                        $lastError = $errorMsg;
                    }
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    continue;
                }
            }

            // If all payload formats failed
            Log::error('WhatsApp API Error: All payload formats failed', [
                'endpoint' => $endpointPath,
                'base_url' => $this->baseUrl,
                'phone' => $to,
                'last_error' => $lastError,
            ]);
            return [
                'success' => false,
                'error' => $lastError ?? 'Failed to send message. Please check the API endpoint configuration.',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp API Error: ' . $e->getMessage(), [
                'endpoint' => $endpointPath ?? 'N/A',
                'base_url' => $this->baseUrl,
                'phone' => $to,
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify API connection
     */
    public function verifyConnection()
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        try {
            // Try multiple common endpoints
            $testEndpoints = [
                '/api/wpbox/getTemplates',
                '/api/wpbox/getConversations',
                '/status',
                '/health',
                '/api/status',
                '/api/health',
                '/v1/status',
                '/',
            ];

            $lastError = null;
            $lastStatus = null;
            $lastResponse = null;
            $lastAuthError = null;

            $parsed = $this->parseToken();
            $rawToken = $parsed['token'] ?? '';
            if ($rawToken === '') {
                return [
                    'success' => false,
                    'error' => 'API token is missing',
                ];
            }

            foreach ($testEndpoints as $endpoint) {
                try {
                    $url = $this->baseUrl . $endpoint;

                    // Try multiple auth header formats (different providers use different schemes)
                    $candidates = $this->authModeCandidates($rawToken);
                    // Prefer stored explicit scheme first (if token was saved like "Token xxx" / "X-API-KEY xxx")
                    if (!empty($parsed['mode']) && $parsed['mode'] !== 'bearer') {
                        array_unshift($candidates, ['mode' => $parsed['mode'], 'token' => $rawToken]);
                    }

                    $response = null;
                    foreach ($candidates as $candidate) {
                        $headers = $this->buildAuthHeaders($candidate['mode'], $candidate['token']);
                        $response = Http::withHeaders($headers)->timeout(10)->get($url);

                        $lastStatus = $response->status();
                        $lastResponse = $response->body();

                        if ($response->successful()) {
                            $this->resolvedAuthMode = $candidate['mode'];

                            // Persist working auth mode by normalizing token format in DB
                            try {
                                $normalized = $this->formatTokenForStorage($candidate['mode'], $rawToken);
                                if (trim((string) $this->settings->api_token) !== $normalized) {
                                    WhatsAppApiSettings::updateSettings(['api_token' => $normalized]);
                                    $this->token = $normalized;
                                }
                            } catch (\Exception $e) {
                                // Non-fatal; verification still succeeded.
                                Log::warning('WhatsApp token normalization failed: ' . $e->getMessage());
                            }

                            return [
                                'success' => true,
                                'data' => $response->json() ?? $response->body(),
                                'message' => 'Connection verified successfully',
                                'endpoint' => $endpoint,
                                'auth_mode' => $candidate['mode'],
                            ];
                        }

                        // 404 means endpoint likely doesn't exist; try next endpoint (no need to try other auth modes)
                        if ($response->status() === 404) {
                            $lastError = "Endpoint {$endpoint} not found (404)";
                            break;
                        }

                        // 401/403: auth failed for this auth mode, try next candidate
                        if (in_array($response->status(), [401, 403], true)) {
                            $lastAuthError = [
                                'status' => $response->status(),
                                'endpoint' => $endpoint,
                                'auth_mode' => $candidate['mode'],
                                'response' => $response->json() ?? $response->body(),
                            ];
                            continue;
                        }

                        // Other errors: keep last error and try next endpoint
                        $lastError = $response->json()['message'] ?? $response->body() ?? "HTTP {$response->status()}";
                        break;
                    }
                    
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    $lastError = "Connection failed: " . $e->getMessage();
                    continue;
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    continue;
                }
            }

            // If all endpoints failed, return the last error
            return [
                'success' => false,
                'error' => $lastError
                    ?? ($lastAuthError ? 'Authentication failed. Please check your API token.' : 'All endpoints failed. Please check the API endpoint URL.'),
                'status' => $lastStatus,
                'response' => $lastResponse,
                'auth_error' => $lastAuthError,
                'suggestion' => 'Please check the debug page for detailed error information.',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp API Verification Error: ' . $e->getMessage(), [
                'endpoint' => $this->endpoint,
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ];
        }
    }

    /**
     * Check if API is configured
     */
    public function isConfigured(): bool
    {
        return !empty(trim((string) $this->token)) && (!empty($this->baseUrl) || !empty($this->endpoint));
    }

    /**
     * Get API status
     */
    public function getStatus()
    {
        $settings = WhatsAppApiSettings::getSettings();
        return [
            'configured' => $this->isConfigured(),
            'active' => $settings->is_active,
            'verified' => $settings->is_verified,
            'endpoint' => $this->endpoint,
        ];
    }

    /**
     * Send template message
     */
    public function sendTemplateMessage(string $to, string $templateId, array $parameters = [], ?string $language = null)
    {
        if (!$this->isConfigured()) {
            throw new \Exception('WhatsApp API is not configured');
        }

        // Format phone number and extract country code
        $phone = preg_replace('/[^0-9]/', '', $to);
        
        // Extract country code (default to 91 for India if 10 digits)
        $countryCode = '91'; // Default to India
        if (strlen($phone) == 10) {
            // 10 digit number, add country code
            $phone = $countryCode . $phone;
        } elseif (strlen($phone) > 10) {
            // Number already has country code, extract it
            if (str_starts_with($phone, '91') && strlen($phone) == 12) {
                $countryCode = '91';
            } elseif (strlen($phone) >= 12) {
                // Extract first 1-3 digits as country code
                $countryCode = substr($phone, 0, strlen($phone) - 10);
                $phone = $phone; // Keep full number
            }
        }
        
        // Use configured endpoint or fallback to default
        $endpointPath = $this->settings->send_template_endpoint ?? '/api/wpbox/sendtemplatemessage';
        
        // MCube Rengage API - correct template payload format
        $localPhone = $phone;
        if (str_starts_with($phone, '91') && strlen($phone) == 12) {
            $localPhone = substr($phone, 2);
        }
        $payload = [
            'phone' => $localPhone,
            'country_code' => $countryCode,
            'template_name' => $templateId,
            'template_language' => $language ?: 'en_US',
            'components' => !empty($parameters) ? [['type' => 'body', 'parameters' => $parameters]] : [['type' => 'body']],
        ];

        $result = $this->makeRequest('POST', $endpointPath, $payload);
        return $result;
    }

    /**
     * Send normal text message (alias for sendMessage)
     */
    public function sendTextMessage(string $to, string $message)
    {
        return $this->sendMessage($to, $message);
    }

    /**
     * Get conversation history from API
     */
    public function getConversations(string $phone = null)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->get_conversations_endpoint ?? '/api/wpbox/getConversations';
        
        $queryParams = [];
        if ($phone) {
            $queryParams['filter'] = 'all'; // Default filter
            $queryParams['phone'] = preg_replace('/[^0-9]/', '', $phone);
        } else {
            $queryParams['filter'] = 'all';
        }

        return $this->makeRequest('GET', $endpointPath, $queryParams);
    }

    /**
     * Get messages for a specific contact/phone number
     */
    public function getMessages(string $phone)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->get_messages_endpoint ?? '/api/wpbox/getMessages/{contact}';
        
        // Format phone number
        $formattedPhone = preg_replace('/[^0-9]/', '', $phone);
        
        return $this->makeRequest('GET', $endpointPath, [], ['contact' => $formattedPhone]);
    }

    /**
     * Get available templates from API
     */
    public function getTemplates()
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->get_templates_endpoint ?? '/api/wpbox/getTemplates';
        
        $result = $this->makeRequest('GET', $endpointPath);
        
        if ($result['success'] && isset($result['data'])) {
            // Handle different response formats
            $data = $result['data'];
            if (isset($data['templates'])) {
                $result['data'] = $data['templates'];
            } elseif (isset($data['data'])) {
                $result['data'] = $data['data'];
            } elseif (is_array($data)) {
                $result['data'] = $data;
            } else {
                $result['data'] = [];
            }
        }
        
        return $result;
    }

    /**
     * Get specific template by ID
     */
    public function getTemplate(string $templateId)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->get_template_endpoint ?? '/api/wpbox/get-template/{templateID}';
        
        return $this->makeRequest('GET', $endpointPath, [], ['templateID' => $templateId]);
    }

    /**
     * Create a new template
     */
    public function createTemplate(array $data)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->create_template_endpoint ?? '/api/wpbox/createTemplate';
        
        return $this->makeRequest('POST', $endpointPath, $data);
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(string $templateId)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->delete_template_endpoint ?? '/api/wpbox/deleteTemplate';
        
        return $this->makeRequest('POST', $endpointPath, ['template_id' => $templateId]);
    }

    /**
     * Get all groups
     */
    public function getGroups()
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->get_groups_endpoint ?? '/api/wpbox/getGroups';
        
        return $this->makeRequest('GET', $endpointPath);
    }

    /**
     * Create a new group
     */
    public function makeGroup(array $data)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->make_group_endpoint ?? '/api/wpbox/makeGroups';
        
        return $this->makeRequest('POST', $endpointPath, $data);
    }

    /**
     * Update a group
     */
    public function updateGroup(string $id, array $data)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->update_group_endpoint ?? '/api/wpbox/updateGroups/{id}';
        
        return $this->makeRequest('PUT', $endpointPath, $data, ['id' => $id]);
    }

    /**
     * Remove a group
     */
    public function removeGroup(string $id)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->remove_group_endpoint ?? '/api/wpbox/removeGroups/{id}';
        
        return $this->makeRequest('DELETE', $endpointPath, [], ['id' => $id]);
    }

    /**
     * Import a contact
     */
    public function importContact(array $data)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->import_contact_endpoint ?? '/api/wpbox/importContact';
        
        return $this->makeRequest('POST', $endpointPath, $data);
    }

    /**
     * Update a contact
     */
    public function updateContact(string $id, array $data)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->update_contact_endpoint ?? '/api/wpbox/updateContact/{id}';
        
        return $this->makeRequest('PUT', $endpointPath, $data, ['id' => $id]);
    }

    /**
     * Remove a contact
     */
    public function removeContact(string $id)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->remove_contact_endpoint ?? '/api/wpbox/removeContact/{id}';
        
        return $this->makeRequest('DELETE', $endpointPath, [], ['id' => $id]);
    }

    /**
     * Add bulk contacts
     */
    public function addContacts(array $contacts)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->add_contacts_endpoint ?? '/api/wpbox/addContacts';
        
        return $this->makeRequest('POST', $endpointPath, ['contacts' => $contacts]);
    }

    /**
     * Get media files
     */
    public function getMedia(array $data)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->get_media_endpoint ?? '/api/wpbox/getMedia';
        
        return $this->makeRequest('POST', $endpointPath, $data);
    }

    /**
     * Get campaigns
     */
    public function getCampaigns()
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->get_campaigns_endpoint ?? '/api/wpbox/getCampaigns';
        
        return $this->makeRequest('GET', $endpointPath);
    }

    /**
     * Send campaign
     */
    public function sendCampaign(array $data)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        $endpointPath = $this->settings->send_campaign_endpoint ?? '/api/wpbox/sendwpcampaigns';
        
        return $this->makeRequest('POST', $endpointPath, $data);
    }

    /**
     * Get message delivery status
     */
    public function getMessageStatus(string $messageId)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'API not configured',
            ];
        }

        try {
            $endpoints = [
                "/api/messages/{$messageId}/status",
                "/messages/{$messageId}/status",
                "/api/v1/messages/{$messageId}/status",
                "/api/message/{$messageId}",
            ];

            foreach ($endpoints as $endpoint) {
                try {
                    $url = $this->endpoint . $endpoint;
                    
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])->timeout(10)->get($url);

                    if ($response->successful()) {
                        return [
                            'success' => true,
                            'data' => $response->json() ?? $response->body(),
                            'endpoint_used' => $endpoint,
                        ];
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            return [
                'success' => false,
                'error' => 'Could not fetch message status from API',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Get Message Status Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
