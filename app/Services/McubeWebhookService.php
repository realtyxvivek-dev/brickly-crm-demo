<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\McubeWebhookLog;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class McubeWebhookService
{
    private const STATUS_ANSWER = 'ANSWER';

    private const NON_ANSWER_STATUS_MAP = [
        'CANCEL' => 'rejected',
        'NOANSWER' => 'missed',
        'NO_ANSWER' => 'missed',
        'BUSY' => 'busy',
        'EXECUTIVE_BUSY' => 'busy',
        'FAILED' => 'missed',
    ];

    public function __construct(
        private readonly IvrLeadAutomationService $ivrLeadAutomationService,
        private readonly LeadAssignmentService $leadAssignmentService,
        private readonly CallingCenterService $callingCenterService,
        private readonly SourceAutomationService $sourceAutomationService
    ) {
    }

    public function process(array $payload, ?McubeWebhookLog $webhookLog = null): array
    {
        try {
            $dialStatus = $this->normalizeDialStatus((string) ($payload['dialstatus'] ?? ''));

            if ($duplicate = $this->findDuplicateCallLog($payload)) {
                $this->hydrateDuplicateCallLog($duplicate, $payload);
                $callingItem = $this->callingCenterService->linkInboundWebhook($payload);
                if ($callingItem && blank($callingItem->call_log_id)) {
                    $callingItem->update(['call_log_id' => $duplicate->id]);
                }

                return $this->result(
                    'skipped',
                    'Skipped: duplicate MCube callid already processed.',
                    $duplicate->lead_id,
                    $duplicate->user_id ?: $duplicate->telecaller_id,
                    $duplicate->id
                );
            }

            if ($dialStatus === self::STATUS_ANSWER) {
                return $this->processAnsweredCall($payload, $webhookLog);
            }

            if (isset(self::NON_ANSWER_STATUS_MAP[$dialStatus])) {
                return $this->processNonAnsweredCall($payload, $dialStatus);
            }

            return $this->result('skipped', 'Skipped: unsupported dialstatus (' . ($payload['dialstatus'] ?? 'null') . ')');
        } catch (\Throwable $e) {
            Log::error('McubeWebhookService::process failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return $this->result('failed', 'Server error: ' . $e->getMessage());
        }
    }

    private function processAnsweredCall(array $payload, ?McubeWebhookLog $webhookLog = null): array
    {
        $empPhone = $this->normalizePhone($payload['emp_phone'] ?? '');
        $agentName = trim($payload['agentname'] ?? '');
        $receiver = $this->resolveAgent($empPhone, $agentName);

        if (!$receiver) {
            return $this->result('failed', "Agent not found for phone: {$empPhone} or name: {$agentName}");
        }

        $customerPhone = $this->normalizePhone($payload['callto'] ?? '');
        if ($customerPhone === '') {
            return $this->result('failed', 'Customer phone (callto) is missing.');
        }

        $leadCreated = false;
        $lead = $this->findLeadByPhone($customerPhone);
        if (!$lead) {
            $lead = Lead::create([
                'name' => 'MCube Lead (' . $customerPhone . ')',
                'phone' => $customerPhone,
                'source' => Lead::normalizeSource('mcube'),
                'status' => 'new',
                'created_by' => $receiver->id,
            ]);
            $leadCreated = true;
        }

        $callLog = $this->createCallLog($lead, $receiver, $payload, 'completed');

        $automationAssigned = false;
        if (!$lead->activeAssignments()->exists()) {
            $automationAssigned = $this->sourceAutomationService->assignFromSource($lead, 'ivr');
            $lead->load('activeAssignments.assignedTo');
        }

        $routing = ['assigned_user' => null];
        $finalAssignee = $lead->activeAssignments->first()?->assignedTo;

        if (!$automationAssigned) {
            $routing = $this->ivrLeadAutomationService->routeLead($lead, $receiver, $webhookLog);
            $finalAssignee = $routing['assigned_user'];
        }

        if (!$automationAssigned && $finalAssignee) {
            $assignment = $this->leadAssignmentService->assignToSpecificUser(
                $lead,
                $finalAssignee->id,
                $receiver->id,
                'manual',
                true
            );

            if (!$assignment) {
                return $this->result('failed', 'IVR routing resolved an assignee but assignment creation failed.');
            }
        }

        $message = $leadCreated
            ? "New IVR lead created. Receiver: {$receiver->name}"
            : "Existing IVR lead updated. Receiver: {$receiver->name}";

        if ($finalAssignee && (int) $finalAssignee->id !== (int) $receiver->id) {
            $message .= " Assigned to {$finalAssignee->name}.";
        } elseif ($finalAssignee) {
            $message .= ' Assigned to receiver.';
        } else {
            $message .= ' Lead left unassigned by IVR fallback.';
        }

        return $this->result('success', $message, $lead->id, $receiver->id, $callLog->id);
    }

    private function processNonAnsweredCall(array $payload, string $dialStatus): array
    {
        $empPhone = $this->normalizePhone($payload['emp_phone'] ?? '');
        $agentName = trim($payload['agentname'] ?? '');
        $receiver = $this->resolveAgent($empPhone, $agentName);

        $customerPhone = $this->normalizePhone($payload['callto'] ?? '');
        if ($customerPhone === '') {
            return $this->result('failed', 'Customer phone (callto) is missing.');
        }

        $leadCreated = false;
        $lead = $this->findLeadByPhone($customerPhone);
        if (!$lead) {
            $lead = Lead::create([
                'name' => 'MCube ' . $dialStatus . ' Lead (' . $customerPhone . ')',
                'phone' => $customerPhone,
                'source' => Lead::normalizeSource('mcube'),
                'status' => 'new',
                'created_by' => $receiver?->id ?? $this->getDefaultCreatorId(),
                'notes' => 'MCube non-answered call captured with dialstatus: ' . $dialStatus,
            ]);
            $leadCreated = true;
        }

        $callLog = $this->createCallLog($lead, $receiver, $payload, self::NON_ANSWER_STATUS_MAP[$dialStatus]);

        $automationAssigned = false;
        if (!$lead->activeAssignments()->exists()) {
            $automationAssigned = $this->sourceAutomationService->assignFromSource($lead, 'ivr');
        }

        if (!$automationAssigned && $receiver) {
            $this->leadAssignmentService->assignToSpecificUser(
                $lead,
                $receiver->id,
                $receiver->id,
                'manual',
                true
            );
        }

        $message = $leadCreated
            ? "New MCube {$dialStatus} lead captured."
            : "Existing lead updated for MCube {$dialStatus} call.";

        if ($receiver) {
            $message .= " Agent: {$receiver->name}.";
        } else {
            $message .= ' Agent not resolved.';
        }

        return $this->result('success', $message, $lead->id, $receiver?->id, $callLog->id);
    }

    private function createCallLog(Lead $lead, ?User $receiver, array $payload, string $status): CallLog
    {
        $startTime = $this->parseDateTime($payload['starttime'] ?? null);
        $endTime = $this->parseDateTime($payload['endtime'] ?? null);
        $startTime = $startTime ?? $endTime ?? now();
        $duration = ($startTime && $endTime) ? $endTime->diffInSeconds($startTime) : 0;
        $direction = strtolower($payload['direction'] ?? 'inbound');
        $callType = $direction === 'inbound' ? 'incoming' : 'outgoing';
        $customerPhone = $this->normalizePhone($payload['callto'] ?? '');
        $empPhone = $this->normalizePhone($payload['emp_phone'] ?? '');
        $actorId = $receiver?->id ?? $this->getDefaultCreatorId();

        $callLog = CallLog::create([
            'telecaller_id' => $actorId,
            'user_id' => $actorId,
            'lead_id' => $lead->id,
            'phone_number' => $customerPhone,
            'call_type' => $callType,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration' => $duration,
            'status' => $status,
            'notes' => $this->buildCallNotes($payload),
            'recording_url' => $payload['filename'] ?? null,
            'mcube_call_id' => $payload['callid'] ?? null,
            'mcube_agent_phone' => $empPhone,
            'synced_from_mobile' => false,
        ]);

        $callingItem = $this->callingCenterService->linkInboundWebhook($payload);
        if ($callingItem && (int) $callingItem->lead_id === (int) $lead->id) {
            $callingItem->update(['call_log_id' => $callLog->id]);
        }

        return $callLog;
    }

    private function normalizeDialStatus(string $status): string
    {
        $normalized = strtoupper(trim($status));
        $normalized = preg_replace('/[\s-]+/', '_', $normalized);

        return match ($normalized) {
            'NOANSWER', 'NO_ANSWER' => 'NOANSWER',
            'EXECUTIVE_BUSY', 'EXECUTIVEBUSY' => 'EXECUTIVE_BUSY',
            default => $normalized,
        };
    }

    private function findDuplicateCallLog(array $payload): ?CallLog
    {
        $callId = trim((string) ($payload['callid'] ?? ''));
        if ($callId === '') {
            return null;
        }

        return CallLog::query()
            ->where('mcube_call_id', $callId)
            ->whereNotNull('mcube_call_id')
            ->first();
    }

    private function hydrateDuplicateCallLog(CallLog $callLog, array $payload): void
    {
        $updates = [];

        if (blank($callLog->recording_url) && filled($payload['filename'] ?? null)) {
            $updates['recording_url'] = $payload['filename'];
        }

        $notes = $this->buildCallNotes($payload);
        if (filled($notes) && !str_contains((string) $callLog->notes, $notes)) {
            $updates['notes'] = trim((string) $callLog->notes . "\n" . $notes);
        }

        if ($updates !== []) {
            $callLog->fill($updates)->save();
        }
    }

    private function buildCallNotes(array $payload): ?string
    {
        $parts = [];
        $map = [
            'clicktocalldid' => 'DID',
            'disconnectedby' => 'Disconnected by',
            'answeredtime' => 'Answered time',
            'groupname' => 'Group',
            'agentname' => 'Agent',
        ];

        foreach ($map as $key => $label) {
            if (filled($payload[$key] ?? null)) {
                $parts[] = $label . ': ' . $payload[$key];
            }
        }

        return $parts === [] ? null : 'MCube metadata - ' . implode(' | ', $parts);
    }

    private function resolveAgent(string $empPhone, string $agentName): ?User
    {
        $receiver = $this->findUserByPhone($empPhone);
        if (!$receiver && $agentName !== '') {
            $receiver = $this->findUserByName($agentName);
        }

        return $receiver;
    }

    private function getDefaultCreatorId(): int
    {
        $adminRole = Role::query()->where('slug', Role::ADMIN)->first();
        if ($adminRole) {
            $adminUser = User::query()
                ->where('role_id', $adminRole->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($adminUser) {
                return $adminUser->id;
            }
        }

        return 1;
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        return $phone;
    }

    private function findUserByPhone(string $phone): ?User
    {
        if ($phone === '') {
            return null;
        }

        return User::query()
            ->with('role')
            ->where('phone', $phone)
            ->orWhere('phone', 'like', '%' . substr($phone, -10))
            ->first();
    }

    private function findUserByName(string $name): ?User
    {
        if ($name === '') {
            return null;
        }

        $user = User::query()
            ->with('role')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->where('is_active', true)
            ->first();

        if ($user) {
            return $user;
        }

        $firstName = explode(' ', $name)[0];

        return User::query()
            ->with('role')
            ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($firstName) . '%'])
            ->where('is_active', true)
            ->first();
    }

    private function findLeadByPhone(string $phone): ?Lead
    {
        if ($phone === '') {
            return null;
        }

        return app(DuplicateDetectionService::class)->findExistingLeadByPhone($phone);
    }

    private function parseDateTime(?string $dateTime): ?Carbon
    {
        if (!$dateTime) {
            return null;
        }

        try {
            return Carbon::parse($dateTime);
        } catch (\Throwable) {
            return null;
        }
    }

    private function result(string $status, string $message, ?int $leadId = null, ?int $agentId = null, ?int $callLogId = null): array
    {
        return compact('status', 'message', 'leadId', 'agentId', 'callLogId');
    }
}
