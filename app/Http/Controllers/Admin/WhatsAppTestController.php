<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppApiSettings;
use App\Services\WhatsAppApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppTestController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppApiService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Display quick test page
     */
    public function quickTest()
    {
        $settings = WhatsAppApiSettings::getSettings();
        return view('integrations.whatsapp-quick-test', compact('settings'));
    }

    /**
     * Send quick test message
     */
    public function sendQuickTest(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $settings = WhatsAppApiSettings::getSettings();
        $baseUrl = rtrim($settings->base_url ?? $settings->api_endpoint ?? 'https://rengage.mcube.com', '/');
        $token = $settings->api_token;
        
        // MCube Rengage API - correct endpoint and payload format
        $endpointConfigs = [
            ['path' => '/api/wpbox/sendmessage', 'payload' => ['phone' => null, 'country_code' => null, 'message' => null]],
        ];

        $results = [];
        $success = false;
        $lastError = null;

        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (!str_starts_with($phone, '91') && strlen($phone) == 10) {
            $phone = '91' . $phone;
        }

        foreach ($endpointConfigs as $config) {
            $testEndpoint = $config['path'];
            $url = $baseUrl . $testEndpoint;
            
            // Build payload - MCube format
            $countryCode = '91';
            $localPhone = $phone;
            if (str_starts_with($phone, '91') && strlen($phone) == 12) {
                $localPhone = substr($phone, 2);
            }
            $payload = [
                'phone' => $localPhone,
                'country_code' => $countryCode,
                'message' => $request->message,
            ];
            
            try {
                // Try POST request
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->timeout(30)->post($url, $payload);

                $responseBody = $response->body();
                $responseJson = null;
                try {
                    $responseJson = $response->json();
                } catch (\Exception $e) {
                    // Not JSON response
                }

                $result = [
                    'endpoint' => $testEndpoint,
                    'url' => $url,
                    'method' => 'POST',
                    'payload_format' => json_encode($payload),
                    'status_code' => $response->status(),
                    'success' => $response->successful(),
                    'response' => $responseJson ?? $responseBody,
                    'error' => null,
                ];

                if ($response->successful()) {
                    $success = true;
                    $result['endpoint_used'] = $testEndpoint;
                    $result['payload_format_used'] = json_encode($payload);
                    $results[] = $result;
                    break; // Stop on first success
                } else {
                    // Extract error message
                    $errorMsg = null;
                    if ($responseJson) {
                        // Try different error formats
                        if (isset($responseJson['error'])) {
                            if (is_array($responseJson['error'])) {
                                $errorMsg = $responseJson['error']['message'] ?? $responseJson['error']['msg'] ?? json_encode($responseJson['error']);
                            } else {
                                $errorMsg = $responseJson['error'];
                            }
                        } else {
                            $errorMsg = $responseJson['message'] ?? $responseJson['msg'] ?? null;
                        }
                    }
                    if (!$errorMsg) {
                        // Try to parse HTML error
                        if (str_contains($responseBody, '<title>')) {
                            preg_match('/<title>(.*?)<\/title>/i', $responseBody, $matches);
                            $errorMsg = $matches[1] ?? null;
                        }
                        if (!$errorMsg && strlen($responseBody) < 500) {
                            $errorMsg = $responseBody;
                        }
                    }
                    $result['error'] = $errorMsg ?? "HTTP {$response->status()} - The page could not be found";
                    $lastError = $result['error'];
                }

                $results[] = $result;

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $results[] = [
                    'endpoint' => $testEndpoint,
                    'url' => $url,
                    'method' => 'POST',
                    'status_code' => null,
                    'success' => false,
                    'response' => null,
                    'error' => 'Connection failed: ' . $e->getMessage(),
                ];
                $lastError = 'Connection failed: ' . $e->getMessage();
            } catch (\Exception $e) {
                $results[] = [
                    'endpoint' => $testEndpoint,
                    'url' => $url,
                    'method' => 'POST',
                    'status_code' => null,
                    'success' => false,
                    'response' => null,
                    'error' => $e->getMessage(),
                ];
                $lastError = $e->getMessage();
            }
        }

        // If all endpoints failed, try using the service method
        if (!$success) {
            try {
                $serviceResult = $this->whatsappService->sendMessage($phone, $request->message);
                if ($serviceResult['success']) {
                    $success = true;
                    $results[] = [
                        'endpoint' => 'Service Method',
                        'url' => 'N/A',
                        'method' => 'Service',
                        'status_code' => 200,
                        'success' => true,
                        'response' => $serviceResult['data'] ?? $serviceResult,
                        'error' => null,
                    ];
                } else {
                    $lastError = $serviceResult['error'] ?? 'Service method failed';
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
            }
        }

        // Format error message properly
        $errorMessage = $lastError;
        if (is_array($lastError)) {
            $errorMessage = json_encode($lastError);
        } elseif (is_object($lastError)) {
            $errorMessage = json_encode($lastError);
        }

        return response()->json([
            'success' => $success,
            'message' => $success 
                ? 'Test message sent successfully!' 
                : 'Failed to send message. All endpoints returned 404 (Not Found). Please check the API documentation or contact API provider.',
            'error' => $errorMessage,
            'results' => $results,
            'phone' => $phone,
            'message' => $request->message,
            'suggestion' => !$success ? 'The API endpoint structure might be different. Please check the API Explorer at ' . $baseUrl . ' or contact the API provider for correct endpoint documentation.' : null,
        ]);
    }
}
