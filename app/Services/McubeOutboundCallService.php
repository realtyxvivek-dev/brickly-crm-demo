<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\McubeOutboundAttempt;
use App\Models\McubeSetting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class McubeOutboundCallService
{
    public function initiateManual(User $user, string $phone): array
    {
        $settings = McubeSetting::getSettings();
        $agentNumber = $this->normalizePhone((string) ($user->phone ?? ''));
        $customerNumber = $this->normalizePhone($phone);

        $attempt = McubeOutboundAttempt::create([
            'user_id' => $user->id,
            'lead_id' => null,
            'task_id' => null,
            'agent_number' => $agentNumber,
            'customer_number' => $customerNumber,
            'refurl' => (string) ($settings->default_refurl ?: '1'),
            'status' => 'pending',
            'attempted_at' => now(),
        ]);

        $attempt->refid = sprintf('manual:user:%d:attempt:%d', $user->id, $attempt->id);
        $attempt->save();

        return $this->sendOutboundRequest($settings, $attempt, $agentNumber, $customerNumber, $attempt->refid);
    }

    public function initiate(
        User $user,
        Lead $lead,
        ?Task $task = null,
        ?string $overridePhone = null,
        ?string $customRefid = null,
        ?int $callingCenterCampaignItemId = null
    ): array {
        $settings = McubeSetting::getSettings();
        $agentNumber = $this->normalizePhone((string) ($user->phone ?? ''));
        $customerNumber = $this->normalizePhone((string) ($overridePhone ?: $lead->getRawOriginal('phone')));

        $attempt = McubeOutboundAttempt::create([
            'user_id' => $user->id,
            'lead_id' => $lead->id,
            'task_id' => $task?->id,
            'calling_center_campaign_item_id' => $callingCenterCampaignItemId,
            'agent_number' => $agentNumber,
            'customer_number' => $customerNumber,
            'refurl' => (string) ($settings->default_refurl ?: '1'),
            'status' => 'pending',
            'attempted_at' => now(),
        ]);

        $refid = $customRefid ?: sprintf(
            'lead:%d:%s:attempt:%d',
            $lead->id,
            $task ? 'task:' . $task->id : 'task:none',
            $attempt->id
        );
        $attempt->refid = $refid;
        $attempt->save();

        return $this->sendOutboundRequest($settings, $attempt, $agentNumber, $customerNumber, $refid);
    }

    private function sendOutboundRequest(McubeSetting $settings, McubeOutboundAttempt $attempt, string $agentNumber, string $customerNumber, string $refid): array
    {
        if (!$settings->outbound_enabled) {
            return $this->fail($attempt, 'MCube outbound calling is disabled.');
        }

        if ($agentNumber === '') {
            return $this->fail($attempt, 'Current user phone number is missing.');
        }

        if ($customerNumber === '') {
            return $this->fail($attempt, 'Customer phone number is missing.');
        }

        if (!$this->isIndianCustomerNumber($customerNumber)) {
            return $this->fail($attempt, 'Cloud calling is currently unavailable for this country. Use Phone Dialer for this customer.');
        }

        if (!filled($settings->outbound_token)) {
            return $this->fail($attempt, 'MCube outbound token is not configured.');
        }

        $payload = [
            'exenumber' => $agentNumber,
            'custnumber' => $customerNumber,
            'refurl' => (string) ($settings->default_refurl ?: '1'),
            'refid' => $refid,
        ];

        $logPayload = $payload;
        $headers = ['Accept' => 'application/json'];

        if ($settings->outboundAuthMode() === McubeSetting::OUTBOUND_AUTH_HEADER) {
            $headers['Authorization'] = 'Bearer ' . $settings->outbound_token;
        } else {
            $payload['HTTP_AUTHORIZATION'] = $settings->outbound_token;
            $logPayload['HTTP_AUTHORIZATION'] = '[masked]';
        }

        $attempt->request_payload = $logPayload;
        $attempt->save();

        try {
            $response = Http::withHeaders($headers)
                ->timeout(20)
                ->asJson()
                ->post($settings->outboundApiUrl(), $payload);

            $body = $this->responseBody($response->body());
            $successful = $this->isSuccessfulResponse($response->successful(), $body);

            $attempt->update([
                'status' => $successful ? 'success' : 'failed',
                'http_status' => $response->status(),
                'response_payload' => $body,
                'error_message' => $successful ? null : $this->responseMessage($body, 'MCube outbound call failed.'),
            ]);

            return [
                'success' => $successful,
                'message' => $successful
                    ? $this->responseMessage($body, 'Call initiated via MCube.')
                    : ($attempt->error_message ?: 'MCube outbound call failed.'),
                'fallback_to_tel' => !$successful,
                'attempt_id' => $attempt->id,
                'refid' => $refid,
                'dialer_phone' => $this->formatDialerPhone($customerNumber),
                'http_status' => $response->status(),
                'api_response' => $body,
            ];
        } catch (\Throwable $e) {
            return $this->fail($attempt, 'MCube outbound request error: ' . $e->getMessage());
        }
    }

    private function fail(McubeOutboundAttempt $attempt, string $message): array
    {
        $attempt->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);

        return [
            'success' => false,
            'message' => $message,
            'fallback_to_tel' => true,
            'attempt_id' => $attempt->id,
            'refid' => $attempt->refid,
            'dialer_phone' => $this->formatDialerPhone((string) $attempt->customer_number),
        ];
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return substr($digits, 2);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    private function isIndianCustomerNumber(string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            return true;
        }

        return strlen($digits) === 12 && str_starts_with($digits, '91');
    }

    public function formatDialerPhone(string $phone): string
    {
        $digits = $this->normalizePhone($phone);
        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }

        return $digits !== '' ? '+' . ltrim($digits, '+') : '';
    }

    private function responseBody(string $body): array
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return ['raw' => Str::limit($body, 2000, '')];
    }

    private function responseMessage(array $body, string $fallback): string
    {
        foreach (['message', 'error', 'msg', 'status'] as $key) {
            if (!empty($body[$key]) && is_string($body[$key])) {
                return $body[$key];
            }
        }

        return $fallback;
    }

    private function isSuccessfulResponse(bool $httpSuccessful, array $body): bool
    {
        if (!$httpSuccessful) {
            return false;
        }

        if (array_key_exists('success', $body) && $body['success'] === false) {
            return false;
        }

        foreach (['error', 'errors'] as $key) {
            if (!empty($body[$key])) {
                return false;
            }
        }

        $status = strtolower((string) ($body['status'] ?? ''));
        if (in_array($status, ['failed', 'fail', 'error', 'rejected'], true)) {
            return false;
        }

        return true;
    }
}
