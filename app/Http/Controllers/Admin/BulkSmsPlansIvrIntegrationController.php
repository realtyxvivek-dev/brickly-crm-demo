<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulkSmsPlansIvrSetting;
use App\Models\IvrWebhookLog;
use App\Models\User;
use App\Services\Ivr\BulkSmsPlansIvrAdapter;
use App\Services\Ivr\IvrWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BulkSmsPlansIvrIntegrationController extends Controller
{
    public function __construct(
        private readonly BulkSmsPlansIvrAdapter $adapter,
        private readonly IvrWebhookService $service
    ) {
    }

    public function index()
    {
        $settings = BulkSmsPlansIvrSetting::getSettings()->load('fallbackUser');
        $webhookUrl = url('/api/webhooks/bulksmsplans-ivr');
        $recentLogs = IvrWebhookLog::with(['agent', 'lead', 'callLog'])
            ->where('provider', BulkSmsPlansIvrAdapter::PROVIDER)
            ->latest()
            ->limit(10)
            ->get();
        $fallbackUsers = User::query()
            ->with('role')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role_id']);

        return view('integrations.bulksmsplans-ivr.index', compact('settings', 'webhookUrl', 'recentLogs', 'fallbackUsers'));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'nullable|string|max:255',
            'is_enabled' => 'boolean',
            'default_source' => 'required|string|max:50',
            'auto_create_lead' => 'boolean',
            'create_missed_call_task' => 'boolean',
            'fallback_user_id' => 'nullable|exists:users,id',
            'allow_tokenless_testing' => 'boolean',
            'tokenless_testing_minutes' => 'nullable|integer|min:5|max:1440',
            'allowed_webhook_ips' => 'nullable|string|max:1000',
        ]);

        $allowTokenlessTesting = $request->boolean('allow_tokenless_testing');
        $tokenlessExpiresAt = $allowTokenlessTesting
            ? now()->addMinutes((int) ($validated['tokenless_testing_minutes'] ?? 60))
            : null;

        $settings = BulkSmsPlansIvrSetting::getSettings();
        $settings->fill([
            'token' => $validated['token'] ?? $settings->token,
            'is_enabled' => $request->boolean('is_enabled'),
            'default_source' => $validated['default_source'] ?: 'ivr',
            'auto_create_lead' => $request->boolean('auto_create_lead'),
            'create_missed_call_task' => $request->boolean('create_missed_call_task'),
            'fallback_user_id' => $validated['fallback_user_id'] ?? null,
            'allow_tokenless_testing' => $allowTokenlessTesting,
            'tokenless_testing_expires_at' => $tokenlessExpiresAt,
            'allowed_webhook_ips' => $this->parseAllowedIps($validated['allowed_webhook_ips'] ?? ''),
        ])->save();

        return response()->json(['success' => true, 'message' => 'BulkSMSPlans IVR settings saved.']);
    }

    public function generateToken(): JsonResponse
    {
        return response()->json(['success' => true, 'token' => Str::random(48)]);
    }

    public function testWebhook(Request $request): JsonResponse
    {
        $settings = BulkSmsPlansIvrSetting::getSettings()->load('fallbackUser');
        if (!$settings->is_enabled || !$settings->token) {
            return response()->json(['success' => false, 'message' => 'Enable integration and save a token before testing.'], 422);
        }

        $payload = [
            'call_id' => 'BSP_TEST_' . time(),
            'caller_number' => $request->input('customer_phone', '8888888888'),
            'agent_phone' => $request->input('agent_phone', '9999999999'),
            'agent_name' => $request->input('agent_name', 'Test Agent'),
            'call_status' => $request->input('call_status', 'ANSWERED'),
            'direction' => 'inbound',
            'start_time' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'end_time' => now()->subMinutes(2)->format('Y-m-d H:i:s'),
            'duration' => 180,
            'recording_url' => 'https://example.com/bulksmsplans-test-recording.wav',
            'dtmf_option' => $request->input('dtmf_option', '1'),
        ];

        $normalized = $this->adapter->normalize($payload);
        $log = IvrWebhookLog::create([
            'provider' => BulkSmsPlansIvrAdapter::PROVIDER,
            'external_call_id' => $normalized['call_id'],
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
            'message' => 'Processing test...',
            'normalized_payload' => $this->serializableNormalizedPayload($normalized),
            'raw_payload' => $payload,
        ]);

        $result = $this->service->processBulkSmsPlans($normalized, $settings, $log);
        $log->update([
            'status' => $result['status'],
            'message' => $result['message'] . ' [TEST]',
            'lead_id' => $result['leadId'] ?? null,
            'agent_id' => $result['agentId'] ?? null,
            'call_log_id' => $result['callLogId'] ?? null,
        ]);

        return response()->json([
            'success' => $result['status'] !== 'failed',
            'status' => $result['status'],
            'message' => $result['message'],
        ], $result['status'] === 'failed' ? 422 : 200);
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

    private function parseAllowedIps(?string $value): array
    {
        return collect(preg_split('/[\s,]+/', (string) $value))
            ->map(fn ($ip) => trim($ip))
            ->filter(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP))
            ->unique()
            ->values()
            ->all();
    }
}
