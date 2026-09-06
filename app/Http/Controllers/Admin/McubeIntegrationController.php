<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\McubeSetting;
use App\Models\McubeOutboundAttempt;
use App\Models\McubeWebhookLog;
use App\Models\Task;
use App\Models\User;
use App\Services\McubeOutboundCallService;
use App\Services\McubeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class McubeIntegrationController extends Controller
{
    public function index()
    {
        $settings    = McubeSetting::getSettings();
        $webhookUrl  = url('/api/webhooks/mcube');
        $recentLogs  = McubeWebhookLog::with(['agent', 'lead'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('integrations.mcube.index', compact('settings', 'webhookUrl', 'recentLogs'));
    }

    public function outbound()
    {
        $settings = McubeSetting::getSettings();
        $outboundTokenStatus = $this->outboundTokenStatus($settings->outbound_token);
        $recentOutboundAttempts = McubeOutboundAttempt::with(['user', 'lead', 'task'])
            ->latest('attempted_at')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('integrations.mcube.outbound', compact('settings', 'outboundTokenStatus', 'recentOutboundAttempts'));
    }

    /** Save token + enabled toggle. */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'token'      => 'nullable|string|max:255',
            'is_enabled' => 'boolean',
            'outbound_enabled' => 'boolean',
            'outbound_api_url' => 'nullable|url|max:255',
            'outbound_token' => 'nullable|string|max:255',
            'outbound_auth_mode' => ['nullable', Rule::in([McubeSetting::OUTBOUND_AUTH_JSON, McubeSetting::OUTBOUND_AUTH_HEADER])],
            'default_refurl' => 'nullable|string|max:255',
            'auto_call_on_assignment' => 'boolean',
            'auto_call_cooldown_minutes' => 'nullable|integer|min:0|max:1440',
            'auto_call_quiet_start' => 'nullable|date_format:H:i',
            'auto_call_quiet_end' => 'nullable|date_format:H:i',
            'auto_call_allowed_sources' => 'nullable|string|max:1000',
            'auto_call_allowed_user_ids' => 'nullable|string|max:1000',
        ]);

        $settings = McubeSetting::getSettings();
        if ($request->has('token')) {
            $settings->token = $request->input('token', $settings->token);
        }
        if ($request->has('is_enabled')) {
            $settings->is_enabled = $request->boolean('is_enabled');
        }
        if ($request->has('outbound_enabled')) {
            $settings->outbound_enabled = $request->boolean('outbound_enabled');
        }
        if ($request->has('outbound_api_url')) {
            $settings->outbound_api_url = $request->input('outbound_api_url') ?: McubeSetting::DEFAULT_OUTBOUND_API_URL;
        }
        if (filled($request->input('outbound_token'))) {
            $settings->outbound_token = $request->input('outbound_token');
        }
        if ($request->has('outbound_auth_mode')) {
            $settings->outbound_auth_mode = $request->input('outbound_auth_mode') ?: McubeSetting::OUTBOUND_AUTH_JSON;
        }
        if ($request->has('default_refurl')) {
            $settings->default_refurl = $request->input('default_refurl') ?: '1';
        }
        if ($request->has('auto_call_on_assignment')) {
            $settings->auto_call_on_assignment = $request->boolean('auto_call_on_assignment');
        }
        if ($request->has('auto_call_cooldown_minutes')) {
            $settings->auto_call_cooldown_minutes = (int) $request->input('auto_call_cooldown_minutes', 10);
        }
        if ($request->has('auto_call_quiet_start')) {
            $settings->auto_call_quiet_start = $request->input('auto_call_quiet_start') ?: null;
        }
        if ($request->has('auto_call_quiet_end')) {
            $settings->auto_call_quiet_end = $request->input('auto_call_quiet_end') ?: null;
        }
        if ($request->has('auto_call_allowed_sources')) {
            $settings->auto_call_allowed_sources = $this->validatedAutoCallSources($request->input('auto_call_allowed_sources'));
        }
        if ($request->has('auto_call_allowed_user_ids')) {
            $settings->auto_call_allowed_user_ids = $this->validatedAutoCallUserIds($request->input('auto_call_allowed_user_ids'));
        }
        $settings->save();

        return response()->json(['success' => true, 'message' => 'Settings saved.']);
    }

    private function csvSetting(?string $value): ?array
    {
        $items = collect(explode(',', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $items ?: null;
    }

    private function validatedAutoCallSources(?string $value): ?array
    {
        $sources = $this->csvSetting($value);
        if ($sources === null) {
            return null;
        }

        $validSources = array_keys(Lead::SOURCE_OPTIONS);
        $normalized = [];
        $invalid = [];

        foreach ($sources as $source) {
            $candidate = Lead::normalizeSource($source);
            if (!in_array($candidate, $validSources, true) || ($candidate === 'other' && !in_array(strtolower(trim($source)), ['other', ''], true))) {
                $invalid[] = $source;
                continue;
            }
            $normalized[] = $candidate;
        }

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'auto_call_allowed_sources' => 'Invalid auto-call source(s): ' . implode(', ', $invalid),
            ]);
        }

        return array_values(array_unique($normalized)) ?: null;
    }

    private function validatedAutoCallUserIds(?string $value): ?array
    {
        $ids = collect($this->csvSetting($value))
            ->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->filter(fn ($id) => $id !== false && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $activeIds = User::query()
            ->whereIn('id', $ids->all())
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missing = $ids->diff($activeIds)->values()->all();
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'auto_call_allowed_user_ids' => 'Auto-call user IDs must exist and be active: ' . implode(', ', $missing),
            ]);
        }

        return $ids->all();
    }

    private function outboundTokenStatus(?string $token): array
    {
        $token = trim((string) $token);
        if ($token === '') {
            return [
                'saved' => false,
                'label' => 'No outbound token saved.',
            ];
        }

        $expiresAt = null;
        $parts = explode('.', $token);
        if (count($parts) >= 2) {
            $payload = $parts[1];
            $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
            $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')) ?: '', true);
            $timestamp = data_get($decoded, 'exp_data') ?: data_get($decoded, 'exp');
            if (is_numeric($timestamp)) {
                $expiresAt = \Carbon\CarbonImmutable::createFromTimestamp((int) $timestamp)
                    ->timezone(config('app.timezone'))
                    ->format('d M Y, h:i A');
            }
        }

        return [
            'saved' => true,
            'last' => substr($token, -6),
            'expires_at' => $expiresAt,
            'label' => 'Token saved' . ($expiresAt ? ' | expires ' . $expiresAt : '') . ' | ending ' . substr($token, -6),
        ];
    }

    /** Generate a random secure token. */
    public function generateToken()
    {
        $token = Str::random(40);
        return response()->json(['success' => true, 'token' => $token]);
    }

    /** Send a dummy payload to our own webhook for testing. */
    public function testWebhook(Request $request)
    {
        $settings = McubeSetting::getSettings();

        if (!$settings->token) {
            return response()->json(['success' => false, 'message' => 'Save a token first before testing.']);
        }
        if (!$settings->is_enabled) {
            return response()->json(['success' => false, 'message' => 'Enable the integration first.']);
        }

        // Dummy payload matching MCube format
        $dummyPayload = [
            'starttime'     => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'callid'        => 'TEST_' . time(),
            'emp_phone'     => $request->input('emp_phone', '9999999999'),
            'clicktocalldid'=> '9035053338',
            'callto'        => $request->input('callto', '8888888888'),
            'dialstatus'    => 'ANSWER',
            'filename'      => 'https://example.com/test-recording.wav',
            'direction'     => 'inbound',
            'endtime'       => now()->subMinutes(2)->format('Y-m-d H:i:s'),
            'disconnectedby'=> 'Customer',
            'answeredtime'  => '00:00:30',
            'groupname'     => 'Test',
            'agentname'     => 'Test Agent',
        ];

        // Call service directly (bypass HTTP to avoid SSL issues in dev)
        $service = app(McubeWebhookService::class);
        $result  = $service->process($dummyPayload);

        // Log it manually
        McubeWebhookLog::create([
            'callid'        => $dummyPayload['callid'],
            'emp_phone'     => $dummyPayload['emp_phone'],
            'callto'        => $dummyPayload['callto'],
            'dialstatus'    => $dummyPayload['dialstatus'],
            'direction'     => $dummyPayload['direction'],
            'recording_url' => $dummyPayload['filename'],
            'call_starttime'=> $dummyPayload['starttime'],
            'call_endtime'  => $dummyPayload['endtime'],
            'status'        => $result['status'],
            'message'       => $result['message'] . ' [TEST]',
            'lead_id'       => $result['leadId']    ?? null,
            'agent_id'      => $result['agentId']   ?? null,
            'call_log_id'   => $result['callLogId'] ?? null,
            'raw_payload'   => $dummyPayload,
        ]);

        return response()->json([
            'success' => $result['status'] !== 'failed',
            'status'  => $result['status'],
            'message' => $result['message'],
        ]);
    }

    public function testOutboundCall(Request $request, McubeOutboundCallService $service)
    {
        $validated = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $lead = Lead::query()->findOrFail($validated['lead_id']);
        $task = !empty($validated['task_id'])
            ? Task::query()->where('lead_id', $lead->id)->findOrFail($validated['task_id'])
            : null;

        $result = $service->initiate($request->user(), $lead, $task, $validated['phone'] ?? null);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
