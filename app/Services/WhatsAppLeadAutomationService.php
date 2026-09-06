<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Role;
use App\Models\SystemSettings;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppLeadAutomationService
{
    private const SETTING_ENABLED = 'whatsapp_auto_assign_enabled';
    private const SETTING_USER_IDS = 'whatsapp_auto_assign_user_ids';
    private const SETTING_MODE = 'whatsapp_auto_assign_mode';
    private const SETTING_SINGLE_USER = 'whatsapp_auto_assign_single_user_id';
    private const SETTING_PERCENTAGES = 'whatsapp_auto_assign_user_percentages';
    private const SETTING_CREATE_TASK = 'whatsapp_auto_create_calling_task';
    private const SETTING_NOTIFY = 'whatsapp_auto_notify_assigned_user';
    private const SETTING_LAST_USER = 'whatsapp_last_assigned_user_id';

    public function __construct(
        private readonly LeadAssignmentService $leadAssignmentService,
        private readonly NotificationService $notificationService,
        private readonly LeadDuplicateGuardService $leadDuplicateGuardService
    ) {
    }

    public function settings(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'user_ids' => $this->configuredUserIds(),
            'mode' => $this->configuredAssignmentMethod(),
            'assignment_method' => $this->configuredAssignmentMethod(),
            'single_user_id' => $this->configuredSingleUserId(),
            'user_percentages' => $this->configuredUserPercentages(),
            'create_calling_task' => $this->shouldCreateCallingTask(),
            'notify_assigned_user' => $this->shouldNotifyAssignedUser(),
        ];
    }

    public function eligibleUsers(): Collection
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', [
                    Role::SALES_EXECUTIVE,
                    Role::SALES_MANAGER,
                    Role::SENIOR_MANAGER,
                    Role::ASSISTANT_SALES_MANAGER,
                ]);
            })
            ->orderBy('name')
            ->get();
    }

    public function resolveInboundConversation(string $phone, ?string $contactName = null): array
    {
        return $this->leadDuplicateGuardService->withPhoneLock($phone, function (string $normalizedPhone) use ($phone, $contactName) {
            $phone = $normalizedPhone !== '' ? $normalizedPhone : $phone;

        $lead = $this->findLeadByPhone($phone);
        $createdLead = false;
        $assignedUser = null;

        if (!$lead) {
            $lead = $this->createLeadFromWhatsApp($phone, $contactName);
            $createdLead = true;

            if ($lead) {
                $assignedUser = $this->attemptAutoAssignment($lead, $phone, true);
            }
        } elseif ($this->isEnabled() && !$lead->activeAssignments()->exists()) {
            $assignedUser = $this->attemptAutoAssignment($lead, $phone, false);
        }

        if ($lead && !$assignedUser) {
            $assignedUser = optional($lead->loadMissing('activeAssignments.assignedTo')->activeAssignments->first())->assignedTo;
        }

        $conversationOwner = $assignedUser ?: $this->fallbackConversationOwner();

        if (!$conversationOwner) {
            throw new \RuntimeException('No fallback WhatsApp conversation owner is available.');
        }

        $conversation = WhatsAppConversation::firstOrNew([
            'phone_number' => $phone,
        ]);

        $updates = [
            'user_id' => $conversationOwner->id,
            'lead_id' => $lead?->id,
        ];

        if (!$conversation->exists || !$conversation->contact_name) {
            $updates['contact_name'] = $contactName ?: ($lead?->name ?: $phone);
        }

        $conversation->fill($updates);
        $conversation->save();

        return [
            'conversation' => $conversation,
            'lead' => $lead,
            'assigned_user' => $assignedUser,
            'created_lead' => $createdLead,
            'created_lead_assigned' => $createdLead && (bool) $assignedUser,
        ];
        });
    }

    public function notifyInboundMessage(WhatsAppConversation $conversation, WhatsAppMessage $message, array $context): void
    {
        if (!$this->shouldNotifyAssignedUser()) {
            return;
        }

        $lead = $context['lead'] ?? null;
        $assignedUser = $context['assigned_user'] ?? null;

        if (!$lead || !$assignedUser instanceof User) {
            return;
        }

        if (!empty($context['created_lead_assigned'])) {
            return;
        }

        try {
            $this->notificationService->notifyWhatsAppMessage(
                $assignedUser,
                $lead,
                $conversation,
                route('chat.index', ['conversation' => $conversation->id]),
                [
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'phone_number' => $conversation->phone_number,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('WhatsApp inbound notification failed', [
                'conversation_id' => $conversation->id,
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function configuredUserIds(): array
    {
        $raw = SystemSettings::get(self::SETTING_USER_IDS, '[]');
        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isEnabled(): bool
    {
        return SystemSettings::get(self::SETTING_ENABLED, '1') === '1';
    }

    public function shouldCreateCallingTask(): bool
    {
        return SystemSettings::get(self::SETTING_CREATE_TASK, '1') === '1';
    }

    public function shouldNotifyAssignedUser(): bool
    {
        return SystemSettings::get(self::SETTING_NOTIFY, '1') === '1';
    }

    public function configuredAssignmentMethod(): string
    {
        $method = SystemSettings::get(self::SETTING_MODE, 'round_robin') ?: 'round_robin';

        return in_array($method, ['round_robin', 'first_available', 'percentage', 'single_user', 'random'], true)
            ? $method
            : 'round_robin';
    }

    public function configuredSingleUserId(): ?int
    {
        $userId = (int) SystemSettings::get(self::SETTING_SINGLE_USER, '0');

        return $userId > 0 ? $userId : null;
    }

    public function configuredUserPercentages(): array
    {
        $raw = SystemSettings::get(self::SETTING_PERCENTAGES, '{}');
        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->mapWithKeys(function ($percentage, $userId) {
                $normalizedUserId = (int) $userId;
                $normalizedPercentage = max(0, (float) $percentage);

                return $normalizedUserId > 0 ? [$normalizedUserId => $normalizedPercentage] : [];
            })
            ->all();
    }

    private function findLeadByPhone(string $phone): ?Lead
    {
        $lastTenDigits = strlen($phone) > 10 ? substr($phone, -10) : $phone;

        return Lead::query()
            ->where(function ($query) use ($phone, $lastTenDigits) {
                $query->whereRaw('REPLACE(REPLACE(phone, "+", ""), " ", "") LIKE ?', ['%' . $phone . '%'])
                    ->orWhereRaw('REPLACE(REPLACE(phone, "+", ""), " ", "") LIKE ?', ['%' . $lastTenDigits . '%']);
            })
            ->first();
    }

    private function createLeadFromWhatsApp(string $phone, ?string $contactName = null): Lead
    {
        return Lead::create([
            'name' => $contactName ?: ('WhatsApp Lead ' . substr($phone, -10)),
            'phone' => $phone,
            'source' => Lead::normalizeSource('whatsapp'),
            'status' => 'new',
            'notes' => 'Auto-created from inbound WhatsApp chat.',
            'created_by' => $this->systemActorId(),
        ]);
    }

    private function pickAutoAssignUser(): ?User
    {
        $pool = $this->eligibleUsers()
            ->whereIn('id', $this->configuredUserIds())
            ->values();

        if ($pool->isEmpty()) {
            return null;
        }

        $mode = $this->configuredAssignmentMethod();

        if ($mode === 'random') {
            return $pool->shuffle()->first();
        }

        if ($mode === 'first_available') {
            return $pool->first();
        }

        if ($mode === 'single_user') {
            $singleUserId = $this->configuredSingleUserId();
            return $singleUserId ? $pool->firstWhere('id', $singleUserId) : null;
        }

        if ($mode === 'percentage') {
            return $this->pickByPercentage($pool);
        }

        $lastUserId = (int) SystemSettings::get(self::SETTING_LAST_USER, '0');
        $index = $pool->search(fn (User $user) => $user->id === $lastUserId);
        $nextIndex = $index === false ? 0 : (($index + 1) % $pool->count());
        $selected = $pool->get($nextIndex);

        if ($selected) {
            SystemSettings::set(self::SETTING_LAST_USER, (string) $selected->id);
        }

        return $selected;
    }

    private function pickByPercentage(Collection $pool): ?User
    {
        $percentages = $this->configuredUserPercentages();

        $weightedPool = $pool
            ->map(function (User $user) use ($percentages) {
                return [
                    'user' => $user,
                    'weight' => (float) ($percentages[$user->id] ?? 0),
                ];
            })
            ->filter(fn (array $item) => $item['weight'] > 0)
            ->values();

        if ($weightedPool->isEmpty()) {
            return $pool->first();
        }

        $totalWeight = $weightedPool->sum('weight');
        if ($totalWeight <= 0) {
            return $pool->first();
        }

        $target = mt_rand(1, (int) round($totalWeight * 100)) / 100;
        $running = 0.0;

        foreach ($weightedPool as $item) {
            $running += $item['weight'];
            if ($target <= $running) {
                return $item['user'];
            }
        }

        return $weightedPool->last()['user'] ?? $pool->first();
    }

    private function attemptAutoAssignment(Lead $lead, string $phone, bool $wasJustCreated): ?User
    {
        if (!$this->isEnabled() || $lead->activeAssignments()->exists()) {
            return null;
        }

        $assignedUser = $this->pickAutoAssignUser();

        if (!$assignedUser) {
            Log::warning('WhatsApp auto-assignment skipped because no eligible user was resolved', [
                'lead_id' => $lead->id,
                'phone' => $phone,
                'created_lead' => $wasJustCreated,
                'configured_user_ids' => $this->configuredUserIds(),
                'mode' => $this->configuredAssignmentMethod(),
            ]);

            return null;
        }

        $assignment = $this->leadAssignmentService->assignToSpecificUser(
            $lead,
            $assignedUser->id,
            $this->systemActorId(),
            $this->assignmentRecordMethod(),
            true
        );

        if ($assignment) {
            return $assignedUser;
        }

        Log::warning('WhatsApp auto-assignment failed for inbound lead', [
            'lead_id' => $lead->id,
            'phone' => $phone,
            'assigned_to' => $assignedUser->id,
            'created_lead' => $wasJustCreated,
        ]);

        return null;
    }

    private function assignmentRecordMethod(): string
    {
        $mode = $this->configuredAssignmentMethod();

        return in_array($mode, ['round_robin', 'first_available', 'percentage'], true)
            ? $mode
            : 'manual';
    }

    private function fallbackConversationOwner(): ?User
    {
        $query = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', [Role::ADMIN, Role::CRM]);
            });

        if (DB::connection()->getDriverName() === 'mysql') {
            $query->orderByRaw("FIELD((SELECT slug FROM roles WHERE roles.id = users.role_id), 'admin', 'crm')");
        }

        return $query->orderBy('id')->first();
    }

    private function systemActorId(): int
    {
        return $this->fallbackConversationOwner()?->id
            ?? (int) User::query()->where('is_active', true)->min('id')
            ?? 1;
    }
}
