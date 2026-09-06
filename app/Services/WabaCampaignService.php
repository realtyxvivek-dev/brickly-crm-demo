<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\MetaWabaAccount;
use App\Models\WabaCampaign;
use App\Models\WabaCampaignRecipient;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WabaCampaignService
{
    public function __construct(
        private readonly MetaWabaApiService $metaWabaApiService,
        private readonly WhatsAppRoutingService $routingService,
        private readonly WhatsAppComplianceService $complianceService
    )
    {
    }

    public function previewAudience(array $criteria, int $limit = 5000): array
    {
        $leads = $this->candidateQuery($criteria)
            ->select(['id', 'name', 'phone', 'city', 'source', 'status'])
            ->limit($limit)
            ->get();

        $valid = collect();
        $seen = [];
        $invalid = 0;
        $optedOut = 0;
        $duplicates = 0;

        foreach ($leads as $lead) {
            if ($lead->whatsapp_opted_out_at) {
                $optedOut++;
                continue;
            }

            $phone = $this->normalizePhone($lead->phone);
            if (!$phone) {
                $invalid++;
                continue;
            }
            if (isset($seen[$phone])) {
                $duplicates++;
                continue;
            }

            $seen[$phone] = true;
            $valid->push($lead);
        }

        return [
            'matched' => $leads->count(),
            'valid' => $valid->count(),
            'invalid' => $invalid,
            'opted_out' => $optedOut,
            'duplicates' => $duplicates,
            'sample' => $valid->take(10)->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $this->normalizePhone($lead->phone),
                'city' => $lead->city,
                'source' => $lead->source,
                'status' => $lead->status,
            ])->values()->all(),
        ];
    }

    public function createCampaign(array $data, int $createdBy): WabaCampaign
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $account = filled($data['meta_waba_account_id'] ?? null)
                ? MetaWabaAccount::query()->where('is_active', true)->findOrFail((int) $data['meta_waba_account_id'])
                : null;

            $template = WhatsAppTemplate::query()
                ->where('provider', 'meta_waba')
                ->where('is_active', true)
                ->when($account, function ($query) use ($account) {
                    $query->where(function ($templateQuery) use ($account) {
                        $templateQuery->where('meta_waba_account_id', $account->id)
                            ->orWhereNull('meta_waba_account_id');
                    });
                })
                ->findOrFail((int) $data['template_id']);

            $criteria = $data['audience_criteria'] ?? [];
            $mapping = $data['variable_mapping'] ?? [];
            $rateLimit = max(1, min(1000, (int) ($data['rate_limit_per_minute'] ?? 30)));

            $campaign = WabaCampaign::create([
                'name' => $data['name'],
                'template_id' => $template->id,
                'meta_waba_account_id' => $account?->id ?: $template->meta_waba_account_id,
                'created_by' => $createdBy,
                'status' => WabaCampaign::STATUS_QUEUED,
                'audience_criteria' => $criteria,
                'variable_mapping' => $mapping,
                'rate_limit_per_minute' => $rateLimit,
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);

            $recipients = $this->buildRecipients($criteria, $mapping);
            foreach ($recipients as $recipient) {
                WabaCampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'lead_id' => $recipient['lead_id'],
                    'phone' => $recipient['phone'],
                    'name' => $recipient['name'],
                    'status' => WabaCampaignRecipient::STATUS_QUEUED,
                    'variables' => $recipient['variables'],
                ]);
            }

            $campaign->update([
                'total_recipients' => $recipients->count(),
                'queued_count' => $recipients->count(),
            ]);

            return $campaign->fresh(['template', 'recipients']);
        });
    }

    public function processDueCampaigns(int $limit = 50): array
    {
        $processed = 0;
        $sent = 0;
        $failed = 0;

        $campaigns = WabaCampaign::query()
            ->with(['template', 'metaWabaAccount'])
            ->whereIn('status', [WabaCampaign::STATUS_QUEUED, WabaCampaign::STATUS_SENDING])
            ->where(function ($query) {
                $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($campaigns as $campaign) {
            if ($processed >= $limit) {
                break;
            }

            $campaign->update([
                'status' => WabaCampaign::STATUS_SENDING,
                'started_at' => $campaign->started_at ?: now(),
            ]);

            $take = min($limit - $processed, max(1, (int) $campaign->rate_limit_per_minute));
            $recipients = $campaign->recipients()
                ->where('status', WabaCampaignRecipient::STATUS_QUEUED)
                ->orderBy('id')
                ->limit($take)
                ->get();

            foreach ($recipients as $recipient) {
                $processed++;
                $result = $this->sendRecipient($campaign, $recipient);
                $result ? $sent++ : $failed++;
            }

            $this->refreshCampaignCounters($campaign);
        }

        return compact('processed', 'sent', 'failed');
    }

    protected function sendRecipient(WabaCampaign $campaign, WabaCampaignRecipient $recipient): bool
    {
        $template = $campaign->template;
        $lead = $recipient->lead;
        $compliance = $this->complianceService->checkLead($lead, [
            'daily_send_cap' => 5,
            'cooldown_minutes' => 10,
        ]);
        if (!($compliance['allowed'] ?? false)) {
            $recipient->update([
                'status' => WabaCampaignRecipient::STATUS_SKIPPED,
                'error_message' => $compliance['reason'] ?? 'Blocked by WhatsApp compliance rules.',
            ]);

            return false;
        }

        $parameters = collect($recipient->variables ?? [])
            ->values()
            ->map(fn ($value) => ['type' => 'text', 'text' => (string) $value])
            ->all();

        $account = $this->routingService->resolve($lead, [
            'campaign_id' => $campaign->id,
            'campaign_type' => data_get($campaign->audience_criteria, 'audience_type'),
            'source' => $lead?->source,
            'city' => $lead?->city,
            'project' => $lead?->preferred_projects,
        ], $campaign->metaWabaAccount);

        $api = $account
            ? $this->metaWabaApiService->forAccount($account)
            : $this->metaWabaApiService;

        $result = $api->sendTemplateMessage(
            $recipient->phone,
            $template->name,
            $parameters,
            $template->language ?: 'en_US'
        );

        $messageId = data_get($result, 'data.message_id') ?: data_get($result, 'data.messages.0.id') ?: data_get($result, 'data.id');
        $success = (bool) ($result['success'] ?? false);

        $recipient->update([
            'status' => $success ? WabaCampaignRecipient::STATUS_SENT : WabaCampaignRecipient::STATUS_FAILED,
            'provider_message_id' => $messageId,
            'error_message' => $success ? null : ($result['error'] ?? 'Meta WABA send failed.'),
            'sent_at' => $success ? now() : null,
            'failed_at' => $success ? null : now(),
        ]);

        $this->storeChatMessage($campaign, $recipient, $success, $result, $messageId, $account);

        return $success;
    }

    protected function buildRecipients(array $criteria, array $mapping): Collection
    {
        $seen = [];

        return $this->candidateQuery($criteria)
            ->select(['id', 'name', 'phone', 'email', 'city', 'source', 'status'])
            ->orderBy('id')
            ->limit(10000)
            ->get()
            ->filter(fn (Lead $lead) => !$lead->whatsapp_opted_out_at)
            ->map(function (Lead $lead) use ($mapping, &$seen) {
                $phone = $this->normalizePhone($lead->phone);
                if (!$phone || isset($seen[$phone])) {
                    return null;
                }

                $seen[$phone] = true;

                return [
                    'lead_id' => $lead->id,
                    'phone' => $phone,
                    'name' => $lead->name,
                    'variables' => $this->resolveVariables($lead, $mapping),
                ];
            })
            ->filter()
            ->values();
    }

    protected function candidateQuery(array $criteria): Builder
    {
        $query = Lead::query()->visibleInAllLeadsInventory();

        if (($criteria['audience_type'] ?? '') === 'tag' && filled($criteria['tag_id'] ?? null)) {
            $tagId = (int) $criteria['tag_id'];
            $query->whereHas('leadTags', fn ($tagQuery) => $tagQuery->where('lead_tags.id', $tagId));
        }

        if (($criteria['audience_type'] ?? '') === 'assigned' && filled($criteria['assigned_to'] ?? null)) {
            $query->whereAssignedToUsers([(int) $criteria['assigned_to']]);
        }

        foreach (['city', 'source', 'status'] as $field) {
            if (filled($criteria[$field] ?? null)) {
                $query->where($field, $criteria[$field]);
            }
        }

        if (filled($criteria['search'] ?? null)) {
            $search = trim((string) $criteria['search']);
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    protected function resolveVariables(Lead $lead, array $mapping): array
    {
        $variables = [];
        foreach ($mapping as $position => $field) {
            $field = trim((string) $field);
            $value = match ($field) {
                'name' => $lead->name,
                'phone' => $lead->phone,
                'city' => $lead->city,
                'source' => $lead->source,
                'status' => $lead->status,
                default => $field,
            };
            $variables[(int) $position] = $value ?: '-';
        }

        ksort($variables);

        return $variables;
    }

    protected function storeChatMessage(WabaCampaign $campaign, WabaCampaignRecipient $recipient, bool $success, array $result, ?string $messageId, ?MetaWabaAccount $account = null): void
    {
        $conversation = WhatsAppConversation::firstOrCreate(
            [
                'user_id' => $campaign->created_by,
                'phone_number' => $recipient->phone,
            ],
            [
                'contact_name' => $recipient->name,
                'lead_id' => $recipient->lead_id,
                'meta_waba_account_id' => $account?->id ?: $campaign->meta_waba_account_id,
            ]
        );

        if (!$conversation->lead_id && $recipient->lead_id) {
            $conversation->update(['lead_id' => $recipient->lead_id]);
        }
        if (!$conversation->meta_waba_account_id && ($account?->id || $campaign->meta_waba_account_id)) {
            $conversation->update(['meta_waba_account_id' => $account?->id ?: $campaign->meta_waba_account_id]);
        }

        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $campaign->created_by,
            'direction' => 'sent',
            'message' => 'Template: ' . $campaign->template->name,
            'message_id' => $messageId,
            'template_id' => $campaign->template->template_id,
            'provider' => 'meta_waba',
            'meta_waba_account_id' => $account?->id ?: $campaign->meta_waba_account_id,
            'external_message_id' => $messageId,
            'provider_status' => $success ? 'sent' : 'failed',
            'status' => $success ? 'sent' : 'failed',
            'error_message' => $success ? null : ($result['error'] ?? null),
            'api_response' => [
                'campaign_id' => $campaign->id,
                'campaign_recipient_id' => $recipient->id,
                'result' => $result,
            ],
            'sent_at' => now(),
        ]);

        $conversation->touch();
    }

    public function refreshCampaignCounters(WabaCampaign $campaign): void
    {
        $counts = $campaign->recipients()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $queued = (int) ($counts[WabaCampaignRecipient::STATUS_QUEUED] ?? 0);
        $sent = (int) ($counts[WabaCampaignRecipient::STATUS_SENT] ?? 0)
            + (int) ($counts[WabaCampaignRecipient::STATUS_DELIVERED] ?? 0)
            + (int) ($counts[WabaCampaignRecipient::STATUS_READ] ?? 0);
        $failed = (int) ($counts[WabaCampaignRecipient::STATUS_FAILED] ?? 0);
        $skipped = (int) ($counts[WabaCampaignRecipient::STATUS_SKIPPED] ?? 0);

        $campaign->update([
            'queued_count' => $queued,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'status' => $queued > 0 ? WabaCampaign::STATUS_SENDING : WabaCampaign::STATUS_COMPLETED,
            'completed_at' => $queued > 0 ? null : now(),
        ]);
    }

    public function retryRecipient(WabaCampaignRecipient $recipient): bool
    {
        $campaign = $recipient->campaign()->with(['template', 'metaWabaAccount'])->firstOrFail();

        $recipient->forceFill([
            'status' => WabaCampaignRecipient::STATUS_QUEUED,
            'error_message' => null,
            'failed_at' => null,
            'retry_count' => (int) $recipient->retry_count + 1,
            'last_retry_at' => now(),
        ])->save();

        $success = $this->sendRecipient($campaign, $recipient->fresh());
        $this->refreshCampaignCounters($campaign->fresh());

        return $success;
    }

    protected function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $phone);
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        return strlen($phone) >= 11 && strlen($phone) <= 15 ? $phone : null;
    }
}
