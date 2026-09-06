<?php

namespace App\Services\Ivr;

use App\Models\BulkSmsPlansIvrSetting;
use App\Models\CallLog;
use App\Models\IvrWebhookLog;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\LeadAssignmentService;
use App\Services\SourceAutomationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IvrWebhookService
{
    private const ANSWERED_STATUSES = ['ANSWER', 'ANSWERED', 'CONNECTED', 'COMPLETED', 'SUCCESS'];
    private const BUSY_STATUSES = ['BUSY'];
    private const REJECTED_STATUSES = ['CANCEL', 'CANCELLED', 'REJECTED'];

    public function __construct(
        private readonly LeadAssignmentService $leadAssignmentService,
        private readonly SourceAutomationService $sourceAutomationService
    ) {
    }

    public function processBulkSmsPlans(array $normalized, BulkSmsPlansIvrSetting $settings, IvrWebhookLog $log): array
    {
        if ($log->call_log_id) {
            return $this->result('skipped', 'Duplicate webhook retry ignored.', $log->lead_id, $log->agent_id, $log->call_log_id);
        }

        try {
            return DB::transaction(function () use ($normalized, $settings, $log) {
                $customerPhone = (string) ($normalized['customer_phone'] ?? '');
                if ($customerPhone === '') {
                    return $this->result('failed', 'Customer phone is missing.');
                }

                $agent = $this->resolveAgent((string) ($normalized['agent_phone'] ?? ''), (string) ($normalized['agent_name'] ?? ''));
                $actor = $agent ?: $settings->fallbackUser ?: $this->defaultUser();
                if (!$actor) {
                    return $this->result('failed', 'No CRM user found for agent or fallback assignment.');
                }

                $leadCreated = false;
                $lead = $this->findLeadByPhone($customerPhone);
                if (!$lead) {
                    if (!$settings->auto_create_lead) {
                        return $this->result('skipped', 'Lead not found and auto-create is disabled.', null, $agent?->id);
                    }

                    $lead = Lead::create([
                        'name' => 'IVR Lead (' . $customerPhone . ')',
                        'phone' => $customerPhone,
                        'source' => Lead::normalizeSource($settings->default_source ?: 'ivr'),
                        'status' => 'new',
                        'created_by' => $actor->id,
                        'notes' => trim('BulkSMSPlans IVR lead' . (($normalized['dtmf_option'] ?? null) ? ' | DTMF: ' . $normalized['dtmf_option'] : '')),
                    ]);
                    $leadCreated = true;
                }

                $crmStatus = $this->crmStatus((string) ($normalized['call_status'] ?? ''));
                $callLog = CallLog::create([
                    'telecaller_id' => $actor->id,
                    'user_id' => $actor->id,
                    'lead_id' => $lead->id,
                    'phone_number' => $customerPhone,
                    'call_type' => $this->callType((string) ($normalized['direction'] ?? 'inbound')),
                    'start_time' => $normalized['start_time'] instanceof CarbonInterface ? $normalized['start_time'] : now(),
                    'end_time' => $normalized['end_time'] instanceof CarbonInterface ? $normalized['end_time'] : null,
                    'duration' => (int) ($normalized['duration'] ?? 0),
                    'status' => $crmStatus,
                    'notes' => $this->callNotes($normalized),
                    'recording_url' => $normalized['recording_url'] ?? null,
                    'call_outcome' => $crmStatus === 'completed' ? 'interested' : 'no_answer',
                    'synced_from_mobile' => false,
                ]);

                $automationAssigned = false;
                if (!$lead->activeAssignments()->exists()) {
                    $automationAssigned = $this->sourceAutomationService->assignFromSource($lead, 'ivr');
                }

                if (!$automationAssigned && $agent) {
                    $this->leadAssignmentService->assignToSpecificUser($lead, $agent->id, $actor->id, 'manual', true);
                } elseif (!$automationAssigned && $settings->fallbackUser) {
                    $this->leadAssignmentService->assignToSpecificUser($lead, $settings->fallbackUser->id, $actor->id, 'manual', true);
                }

                if ($settings->create_missed_call_task && $crmStatus !== 'completed') {
                    $this->createMissedCallTask($lead, $agent ?: $settings->fallbackUser ?: $actor, $actor, $normalized);
                }

                $message = $leadCreated ? 'New IVR lead created.' : 'Existing IVR lead updated.';
                $message .= ' Call log saved.';
                if (!$agent) {
                    $message .= ' Agent not resolved, fallback used.';
                }

                return $this->result('success', $message, $lead->id, $agent?->id, $callLog->id);
            });
        } catch (\Throwable $e) {
            Log::error('BulkSMSPlans IVR processing failed', [
                'error' => $e->getMessage(),
                'normalized' => $normalized,
            ]);

            return $this->result('failed', 'Server error: ' . $e->getMessage());
        }
    }

    private function resolveAgent(string $phone, string $name): ?User
    {
        $phone = $this->normalizePhone($phone);
        $user = null;

        if ($phone !== '') {
            $user = User::query()
                ->with('role')
                ->where('phone', $phone)
                ->orWhere('phone', 'like', '%' . substr($phone, -10))
                ->first();
        }

        if (!$user && $name !== '') {
            $user = User::query()
                ->with('role')
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->where('is_active', true)
                ->first();
        }

        return $user;
    }

    private function findLeadByPhone(string $phone): ?Lead
    {
        $phone = $this->normalizePhone($phone);

        return app(DuplicateDetectionService::class)->findExistingLeadByPhone($phone);
    }

    private function defaultUser(): ?User
    {
        $adminRole = Role::query()->where('slug', Role::ADMIN)->first();
        if ($adminRole) {
            return User::query()
                ->where('role_id', $adminRole->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        return User::query()->where('is_active', true)->orderBy('id')->first();
    }

    private function createMissedCallTask(Lead $lead, User $assignee, User $creator, array $normalized): void
    {
        Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignee->id,
            'type' => 'phone_call',
            'title' => 'Call back IVR missed lead',
            'description' => 'BulkSMSPlans IVR call was not answered. Call status: ' . (($normalized['call_status'] ?? null) ?: 'unknown'),
            'status' => 'pending',
            'priority' => 'high',
            'scheduled_at' => now()->addMinutes(10),
            'due_date' => now()->addMinutes(10),
            'created_by' => $creator->id,
            'notes' => (($normalized['dtmf_option'] ?? null) ? 'DTMF option: ' . $normalized['dtmf_option'] : null),
        ]);
    }

    private function crmStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (in_array($status, self::ANSWERED_STATUSES, true)) {
            return 'completed';
        }
        if (in_array($status, self::BUSY_STATUSES, true)) {
            return 'busy';
        }
        if (in_array($status, self::REJECTED_STATUSES, true)) {
            return 'rejected';
        }

        return 'missed';
    }

    private function callType(string $direction): string
    {
        return in_array(strtolower($direction), ['outbound', 'outgoing'], true) ? 'outgoing' : 'incoming';
    }

    private function callNotes(array $normalized): ?string
    {
        $parts = ['Provider: BulkSMSPlans'];
        if (!empty($normalized['dtmf_option'])) {
            $parts[] = 'DTMF: ' . $normalized['dtmf_option'];
        }

        return implode(' | ', $parts);
    }

    private function normalizePhone(string $phone): string
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

    private function result(string $status, string $message, ?int $leadId = null, ?int $agentId = null, ?int $callLogId = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'leadId' => $leadId,
            'agentId' => $agentId,
            'callLogId' => $callLogId,
        ];
    }
}
