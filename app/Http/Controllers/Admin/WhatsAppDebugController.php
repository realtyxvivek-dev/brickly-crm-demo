<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppApiSettings;
use App\Services\WhatsAppApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppDebugController extends Controller
{
    public function testConnection(Request $request)
    {
        $debug = [];
        $settings = WhatsAppApiSettings::getSettings();
        
        // Basic Settings Info
        $debug['settings'] = [
            'api_endpoint' => $settings->api_endpoint,
            'api_token_length' => strlen($settings->api_token),
            'api_token_preview' => substr($settings->api_token, 0, 10) . '...' . substr($settings->api_token, -5),
            'is_active' => $settings->is_active,
            'is_verified' => $settings->is_verified,
        ];
        
        // Test 1: Basic HTTP Connection
        try {
            $endpoint = rtrim($settings->api_endpoint, '/');
            $debug['endpoint_cleaned'] = $endpoint;
            
            // Try different common endpoints
            $testEndpoints = [
                '/status',
                '/health',
                '/api/status',
                '/api/health',
                '/v1/status',
                '/',
            ];
            
            foreach ($testEndpoints as $testPath) {
                $testUrl = $endpoint . $testPath;
                $debug['tests'][] = $this->testEndpoint($testUrl, $settings->api_token, $testPath);
            }
            
        } catch (\Exception $e) {
            $debug['error'] = $e->getMessage();
            $debug['trace'] = $e->getTraceAsString();
        }
        
        // Test 2: CURL Info
        $debug['curl_available'] = function_exists('curl_version');
        if (function_exists('curl_version')) {
            $debug['curl_version'] = curl_version();
        }
        
        // Test 3: HTTP Client Info
        $debug['http_client'] = class_exists(\Illuminate\Support\Facades\Http::class);
        
        return view('integrations.whatsapp-debug', compact('debug', 'settings'));
    }
    
    private function testEndpoint($url, $token, $endpointName)
    {
        $result = [
            'endpoint' => $endpointName,
            'url' => $url,
            'method' => 'GET',
            'success' => false,
            'status_code' => null,
            'response' => null,
            'error' => null,
            'headers_sent' => [],
            'time_taken' => null,
        ];
        
        try {
            $startTime = microtime(true);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(10)->get($url);
            
            $endTime = microtime(true);
            $result['time_taken'] = round(($endTime - $startTime) * 1000, 2) . 'ms';
            $result['status_code'] = $response->status();
            $result['success'] = $response->successful();
            $result['response'] = $response->json() ?? $response->body();
            $result['headers_sent'] = [
                'Authorization' => 'Bearer ' . substr($token, 0, 10) . '...',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['error_class'] = get_class($e);
        }
        
        return $result;
    }
    
    public function testPostEndpoint(Request $request)
    {
        $settings = WhatsAppApiSettings::getSettings();
        $endpoint = rtrim($settings->api_endpoint, '/');
        $testPath = $request->input('path', '/send-message');
        $url = $endpoint . $testPath;
        
        $payload = $request->input('payload', [
            'to' => '919876543210',
            'message' => 'Test message from CRM',
        ]);
        
        $result = [
            'url' => $url,
            'method' => 'POST',
            'payload' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . substr($settings->api_token, 0, 10) . '...',
                'Content-Type' => 'application/json',
            ],
            'success' => false,
            'status_code' => null,
            'response' => null,
            'error' => null,
        ];
        
        try {
            $startTime = microtime(true);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $settings->api_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(30)->post($url, $payload);
            
            $endTime = microtime(true);
            $result['time_taken'] = round(($endTime - $startTime) * 1000, 2) . 'ms';
            $result['status_code'] = $response->status();
            $result['success'] = $response->successful();
            $result['response'] = $response->json() ?? $response->body();
            $result['raw_response'] = $response->body();
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['error_class'] = get_class($e);
            $result['trace'] = $e->getTraceAsString();
        }
        
        return response()->json($result);
    }
    
    public function testRawCurl(Request $request)
    {
        $settings = WhatsAppApiSettings::getSettings();
        $endpoint = rtrim($settings->api_endpoint, '/');
        $testPath = $request->input('path', '/status');
        $url = $endpoint . $testPath;
        
        $result = [
            'url' => $url,
            'method' => 'CURL',
            'success' => false,
            'response' => null,
            'error' => null,
            'curl_info' => null,
        ];
        
        if (!function_exists('curl_init')) {
            $result['error'] = 'CURL extension not available';
            return response()->json($result);
        }
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $settings->api_token,
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlInfo = curl_getinfo($ch);
            
            curl_close($ch);
            
            $result['success'] = ($httpCode >= 200 && $httpCode < 300);
            $result['status_code'] = $httpCode;
            $result['response'] = $response;
            $result['curl_info'] = $curlInfo;
            $result['curl_error'] = $curlError ?: null;
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['trace'] = $e->getTraceAsString();
        }
        
        return response()->json($result);
    }
}
