<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PabblyIntegrationSettings;
use App\Support\TestLeadNameGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PabblyIntegrationController extends Controller
{
    /**
     * Display Pabbly integration settings page
     */
    public function index()
    {
        $settings = PabblyIntegrationSettings::getSettings();
        $webhookUrl = url('/api/pabbly/webhook');
        
        return view('integrations.pabbly', compact('settings', 'webhookUrl'));
    }

    /**
     * Update Pabbly integration settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'webhook_secret' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        try {
            $updateData = [
                'is_active' => $request->has('is_active'),
            ];

            if ($request->filled('webhook_secret')) {
                $updateData['webhook_secret'] = $request->webhook_secret;
            } else {
                $updateData['webhook_secret'] = null;
            }

            $settings = PabblyIntegrationSettings::updateSettings($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Pabbly integration settings updated successfully',
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Pabbly Settings Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test webhook by sending a test request
     */
    public function testWebhook(Request $request)
    {
        try {
            $webhookUrl = url('/api/pabbly/webhook');
            $settings = PabblyIntegrationSettings::getSettings();

            // Prepare test payload
            $testPayload = [
                'name' => TestLeadNameGenerator::make(),
                'phone' => '9876543210',
                'email' => 'test@pabbly.com',
                'city' => 'Mumbai',
                'property_type' => 'apartment',
                'requirements' => 'This is a test webhook request from the admin panel',
            ];

            // Add webhook secret to headers if configured
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($settings->webhook_secret) {
                $headers['X-Pabbly-Secret'] = $settings->webhook_secret;
            }

            // Send test request
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post($webhookUrl, $testPayload);

            if ($response->successful()) {
                $responseData = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Test webhook sent successfully',
                    'response' => $responseData,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook test failed',
                    'status' => $response->status(),
                    'response' => $response->body(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Pabbly Webhook Test Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error testing webhook: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent webhook logs
     */
    public function getWebhookLogs(Request $request)
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            $logs = [];
            $limit = $request->get('limit', 20);

            if (file_exists($logFile)) {
                $lines = file($logFile);
                $lines = array_reverse($lines); // Start from end of file
                
                $webhookLogs = [];
                foreach ($lines as $line) {
                    if (strpos($line, 'Pabbly Webhook') !== false) {
                        $webhookLogs[] = $line;
                        if (count($webhookLogs) >= $limit) {
                            break;
                        }
                    }
                }

                // Parse logs
                foreach ($webhookLogs as $logLine) {
                    // Extract timestamp
                    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $logLine, $matches)) {
                        $timestamp = $matches[1];
                    } else {
                        $timestamp = 'Unknown';
                    }

                    // Extract log level and message
                    $isError = strpos($logLine, 'ERROR') !== false || strpos($logLine, 'error') !== false;
                    $isWarning = strpos($logLine, 'WARNING') !== false || strpos($logLine, 'warning') !== false;
                    $isSuccess = strpos($logLine, 'Successfully') !== false || strpos($logLine, 'successfully') !== false;

                    // Extract lead ID if present
                    $leadId = null;
                    if (preg_match('/lead_id["\']?\s*[:=]\s*(\d+)/', $logLine, $matches)) {
                        $leadId = $matches[1];
                    }

                    // Extract name if present
                    $name = null;
                    if (preg_match('/"name"["\']?\s*[:=]\s*["\']?([^"\']+)/', $logLine, $matches)) {
                        $name = $matches[1];
                    }

                    $status = 'info';
                    if ($isError) {
                        $status = 'error';
                    } elseif ($isWarning) {
                        $status = 'warning';
                    } elseif ($isSuccess) {
                        $status = 'success';
                    }

                    $logs[] = [
                        'timestamp' => $timestamp,
                        'status' => $status,
                        'lead_id' => $leadId,
                        'name' => $name,
                        'message' => substr($logLine, 0, 200), // Truncate long lines
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
            ]);
        } catch (\Exception $e) {
            Log::error('Pabbly Webhook Logs Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching logs: ' . $e->getMessage(),
                'logs' => [],
            ], 500);
        }
    }
}
