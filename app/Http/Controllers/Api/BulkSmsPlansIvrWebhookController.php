<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BulkSmsPlansIvrSetting;
use App\Models\IvrWebhookLog;
use App\Services\Ivr\BulkSmsPlansIvrAdapter;
use App\Services\Ivr\IvrWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BulkSmsPlansIvrWebhookController extends Controller
{
    public function __construct(
        private readonly BulkSmsPlansIvrAdapter $adapter,
        private readonly IvrWebhookService $service
    ) {
    }

    public function receive(Request $request): JsonResponse
    {
        $settings = BulkSmsPlansIvrSetting::getSettings();
        if (!$settings->is_enabled) {
            return response()->json(['success' => false, 'message' => 'BulkSMSPlans IVR integration is disabled.'], 403);
        }

        $payload = $request->all();
        $normalized = $this->adapter->normalize($payload);

        $incomingToken = $request->header('X-IVR-Token')
            ?? $request->header('X-BulkSMSPlans-Token')
            ?? $request->header('Authorization')
            ?? '';
        $incomingToken = preg_replace('/^Bearer\s+/i', '', (string) $incomingToken);

        $hasValidToken = $settings->token && hash_equals((string) $settings->token, $incomingToken);
        $requestIp = $request->ip();
        $hasAllowedIp = $settings->isAllowedWebhookIp($requestIp);
        $usingIpWhitelist = !$hasValidToken && $hasAllowedIp;
        $usingTokenlessTest = !$hasValidToken
            && !$usingIpWhitelist
            && $settings->acceptsTokenlessTest()
            && (!$settings->hasWebhookIpWhitelist() || $hasAllowedIp);

        if (!$hasValidToken && !$usingIpWhitelist && !$usingTokenlessTest) {
            $message = $settings->hasWebhookIpWhitelist()
                ? 'Unauthorized: request IP is not whitelisted.'
                : 'Unauthorized: missing or invalid webhook token.';
            Log::warning('BulkSMSPlans IVR webhook rejected', ['ip' => $requestIp, 'reason' => $message]);
            $this->logRejectedAttempt($normalized, $payload, $message);
            return response()->json(['success' => false, 'message' => $message], 401);
        }

        $log = IvrWebhookLog::firstOrNew([
            'provider' => BulkSmsPlansIvrAdapter::PROVIDER,
            'external_call_id' => $normalized['call_id'],
        ]);

        if (!$log->exists) {
            $log->fill([
                'customer_phone' => $normalized['customer_phone'],
                'agent_phone' => $normalized['agent_phone'],
                'agent_name' => $normalized['agent_name'],
                'call_status' => $normalized['call_status'],
                'direction' => $normalized['direction'],
                'recording_url' => $normalized['recording_url'],
                'dtmf_option' => $normalized['dtmf_option'],
                'call_starttime' => $normalized['start_time'],
                'call_endtime' => $normalized['end_time'],
                'duration' => $normalized['duration'],
                'status' => 'failed',
                'message' => 'Processing...',
                'normalized_payload' => $this->serializableNormalizedPayload($normalized),
                'raw_payload' => $payload,
            ])->save();
        }

        $result = $this->service->processBulkSmsPlans($normalized, $settings->fresh('fallbackUser'), $log);
        $authMode = $usingIpWhitelist ? ' [IP WHITELIST]' : ($usingTokenlessTest ? ' [TOKENLESS TEST MODE]' : '');

        $log->fill([
            'status' => $result['status'],
            'message' => $result['message'] . $authMode,
            'lead_id' => $result['leadId'] ?? $log->lead_id,
            'agent_id' => $result['agentId'] ?? $log->agent_id,
            'call_log_id' => $result['callLogId'] ?? $log->call_log_id,
            'normalized_payload' => $this->serializableNormalizedPayload($normalized),
            'raw_payload' => $payload,
        ])->save();

        $success = in_array($result['status'], ['success', 'skipped'], true);

        return response()->json([
            'success' => $success,
            'status' => $result['status'],
            'message' => $result['message'] . $authMode,
        ], $success ? 200 : 422);
    }

    private function serializableNormalizedPayload(array $normalized): array
    {
        foreach (['start_time', 'end_time'] as $key) {
            if (isset($normalized[$key]) && $normalized[$key] instanceof \DateTimeInterface) {
                $normalized[$key] = $normalized[$key]->format('Y-m-d H:i:s');
            }
        }

        return $normalized;
    }

    private function logRejectedAttempt(array $normalized, array $payload, string $message): void
    {
        $log = IvrWebhookLog::firstOrNew([
            'provider' => BulkSmsPlansIvrAdapter::PROVIDER,
            'external_call_id' => $normalized['call_id'],
        ]);

        $log->fill([
            'customer_phone' => $normalized['customer_phone'] ?? null,
            'agent_phone' => $normalized['agent_phone'] ?? null,
            'agent_name' => $normalized['agent_name'] ?? null,
            'call_status' => $normalized['call_status'] ?? null,
            'direction' => $normalized['direction'] ?? null,
            'recording_url' => $normalized['recording_url'] ?? null,
            'dtmf_option' => $normalized['dtmf_option'] ?? null,
            'call_starttime' => $normalized['start_time'] ?? null,
            'call_endtime' => $normalized['end_time'] ?? null,
            'duration' => $normalized['duration'] ?? null,
            'status' => 'failed',
            'message' => $message,
            'normalized_payload' => $this->serializableNormalizedPayload($normalized),
            'raw_payload' => $payload,
        ])->save();
    }
}
