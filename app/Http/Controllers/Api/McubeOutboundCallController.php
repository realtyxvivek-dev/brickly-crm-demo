<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\McubeOutboundAttempt;
use App\Models\Task;
use App\Services\McubeOutboundCallService;
use App\Services\PhonePrivacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McubeOutboundCallController extends Controller
{
    public function __construct(
        private readonly McubeOutboundCallService $service,
        private readonly PhonePrivacyService $privacy
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
            'task_id' => ['nullable'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_slot' => ['nullable', 'in:primary,alternate'],
        ]);

        $user = $request->user();
        $lead = Lead::query()->findOrFail($validated['lead_id']);
        $task = $this->resolveManagerTask($validated['task_id'] ?? null, $lead);
        $masked = $this->privacy->shouldMask($user);

        if (!$this->canCallLead($user, $lead, $task)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to call this lead through MCube.',
                'fallback_to_tel' => false,
            ], 403);
        }

        if ($masked && filled($validated['phone'] ?? null)) {
            $this->privacy->audit('blocked_raw_phone_override', $user, $user, $lead, null, null, null, $request);
            return response()->json([
                'success' => false,
                'message' => 'Protected calls must use the CRM customer number.',
                'fallback_to_tel' => false,
            ], 422);
        }

        if ($this->hasRecentManualAttempt((int) $user->id, (int) $lead->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Recent call already initiated for this lead. Please wait before calling again.',
                'fallback_to_tel' => false,
            ], 429);
        }

        $phoneSlot = $validated['phone_slot'] ?? 'primary';
        $serverPhone = $this->privacy->rawLeadPhone($lead, $phoneSlot);
        $result = $this->service->initiate($user, $lead, $task, $serverPhone);

        if ($masked) {
            unset($result['dialer_phone']);
            $result['fallback_to_tel'] = false;
            $result['fallback_available'] = !$result['success'] && !$this->privacy->isCloudOnly($user);
            $result['fallback_endpoint'] = $result['fallback_available']
                ? route('api.mcube.outbound-call.fallback', $result['attempt_id'])
                : null;
            $result = $this->privacy->maskPhoneFields($result, $user);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function fallback(Request $request, McubeOutboundAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (int) $attempt->user_id === (int) $user->id, 403);
        abort_unless($attempt->status === 'failed', 422, 'Fallback is available only after a failed cloud call.');

        if ($this->privacy->isCloudOnly($user)) {
            $this->privacy->audit('blocked_dialer_fallback', $user, $user, $attempt->lead, null, null, ['attempt_id' => $attempt->id], $request);
            return response()->json(['success' => false, 'message' => 'Cloud Only mode blocks normal dialer fallback.'], 403);
        }

        $this->privacy->audit('dialer_fallback_revealed', $user, $user, $attempt->lead, null, null, ['attempt_id' => $attempt->id], $request);

        return response()->json([
            'success' => true,
            'dialer_phone' => $this->service->formatDialerPhone((string) $attempt->customer_number),
        ]);
    }

    private function canCallLead($user, Lead $lead, ?Task $task): bool
    {
        if (!$user) {
            return false;
        }

        if ((method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'isCrm') && $user->isCrm())) {
            return true;
        }

        if ($task && (int) $task->assigned_to === (int) $user->id) {
            return true;
        }

        return $lead->activeAssignments()
            ->where('assigned_to', $user->id)
            ->exists();
    }

    private function resolveManagerTask(mixed $taskId, Lead $lead): ?Task
    {
        if ($taskId === null || $taskId === '') {
            return null;
        }

        if (is_string($taskId) && str_starts_with($taskId, 'mt_')) {
            $taskId = substr($taskId, 3);
        }

        if (!is_numeric($taskId) || (int) $taskId <= 0) {
            return null;
        }

        return Task::query()
            ->where('lead_id', $lead->id)
            ->where('id', (int) $taskId)
            ->first();
    }

    private function hasRecentManualAttempt(int $userId, int $leadId): bool
    {
        return McubeOutboundAttempt::query()
            ->where('user_id', $userId)
            ->where('lead_id', $leadId)
            ->where('attempted_at', '>=', now()->subSeconds(60))
            ->where(function ($query) {
                $query->whereNull('refid')
                    ->orWhere(function ($refQuery) {
                        $refQuery->where('refid', 'not like', 'calling_campaign:%')
                            ->where('refid', 'not like', 'auto_assignment:%');
                    });
            })
            ->exists();
    }
}
