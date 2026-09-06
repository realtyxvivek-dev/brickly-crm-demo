<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NinetyNineAcresRequestLog;
use App\Models\NinetyNineAcresSetting;
use App\Services\NinetyNineAcresLeadIntakeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NinetyNineAcresWebhookController extends Controller
{
    public function __construct(
        private readonly NinetyNineAcresLeadIntakeService $leadIntakeService,
    ) {
    }

    public function receive(Request $request)
    {
        $settings = NinetyNineAcresSetting::getSettings();
        $providedKey = trim((string) $request->header('X-API-Key', ''));

        if ($providedKey === '' || !hash_equals($settings->api_key, $providedKey)) {
            $requestId = (string) Str::uuid();

            NinetyNineAcresRequestLog::create([
                'ninety_nine_acres_setting_id' => $settings->id,
                'request_id' => $requestId,
                'request_ip' => $request->ip(),
                'raw_payload' => $request->all(),
                'status' => 'auth_failed',
                'error_message' => 'Invalid API key.',
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid API key.',
                'request_id' => $requestId,
            ], 401);
        }

        $result = $this->leadIntakeService->handle($settings, $request->all(), false, $request->ip());

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'lead_id' => $result['lead_id'],
            'duplicate' => $result['duplicate'],
            'request_id' => $result['request_id'],
        ], $result['http_status']);
    }
}
