<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebsiteIntegration;
use App\Models\WebsiteIntegrationRequestLog;
use App\Services\WebsiteLeadIntakeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebsiteLeadWebhookController extends Controller
{
    public function __construct(
        private readonly WebsiteLeadIntakeService $websiteLeadIntakeService,
    ) {
    }

    public function receive(Request $request, string $slug)
    {
        $integration = WebsiteIntegration::with('fieldMappings')->where('slug', $slug)->firstOrFail();

        $providedKey = trim((string) $request->header('X-API-Key', ''));
        if ($providedKey === '' || !hash_equals($integration->api_key, $providedKey)) {
            WebsiteIntegrationRequestLog::create([
                'website_integration_id' => $integration->id,
                'request_id' => (string) Str::uuid(),
                'request_ip' => $request->ip(),
                'raw_payload' => $request->all(),
                'status' => 'auth_failed',
                'is_test' => false,
                'error_message' => 'Invalid API key.',
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid API key.',
            ], 401);
        }

        $result = $this->websiteLeadIntakeService->handle(
            $integration,
            $request->all(),
            false,
            $request->ip()
        );

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'lead_id' => $result['lead_id'],
            'duplicate' => $result['duplicate'],
            'request_id' => $result['request_id'],
        ], $result['http_status']);
    }
}
