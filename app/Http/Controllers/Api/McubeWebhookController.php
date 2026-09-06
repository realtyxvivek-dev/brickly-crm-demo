<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\McubeSetting;
use App\Models\McubeWebhookLog;
use App\Services\McubeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class McubeWebhookController extends Controller
{
    public function __construct(private McubeWebhookService $service) {}

    /**
     * POST /api/webhooks/mcube
     * Receives MCube call event payload.
     */
    public function receive(Request $request): JsonResponse
    {
        $payload = $request->all();

        // 1. Load settings
        $settings = McubeSetting::getSettings();

        // 2. Check if integration is enabled
        if (!$settings->is_enabled) {
            return response()->json(['success' => false, 'message' => 'MCube integration is disabled.'], 403);
        }

        // 3. Validate token (headers only — query param removed to prevent token leaking in server logs)
        $incomingToken = $request->header('X-MCube-Token')
            ?? $request->header('Authorization')
            ?? '';

        // Strip "Bearer " prefix if present
        $incomingToken = preg_replace('/^Bearer\s+/i', '', $incomingToken);

        // Use hash_equals() for constant-time comparison to prevent timing attacks
        if (!$settings->token || !hash_equals($settings->token, $incomingToken)) {
            Log::warning('MCube webhook: invalid token', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Unauthorized: invalid token.'], 401);
        }

        // 4. Store raw log entry (before processing)
        $log = McubeWebhookLog::create([
            'callid'        => $payload['callid']     ?? null,
            'emp_phone'     => $payload['emp_phone']  ?? null,
            'callto'        => $payload['callto']     ?? null,
            'dialstatus'    => $payload['dialstatus'] ?? null,
            'direction'     => $payload['direction']  ?? null,
            'recording_url' => $payload['filename']   ?? null,
            'call_starttime'=> isset($payload['starttime']) ? $payload['starttime'] : null,
            'call_endtime'  => isset($payload['endtime'])   ? $payload['endtime']   : null,
            'status'        => 'failed',
            'message'       => 'Processing...',
            'raw_payload'   => $payload,
        ]);

        // 5. Process the webhook
        $result = $this->service->process($payload, $log);

        // 6. Update log with result
        $log->update([
            'status'      => $result['status'],
            'message'     => $result['message'],
            'lead_id'     => $result['leadId']     ?? null,
            'agent_id'    => $result['agentId']    ?? null,
            'call_log_id' => $result['callLogId']  ?? null,
        ]);

        $success = $result['status'] === 'success' || $result['status'] === 'skipped';

        return response()->json([
            'success' => $success,
            'status'  => $result['status'],
            'message' => $result['message'],
        ], $success ? 200 : 422);
    }
}
