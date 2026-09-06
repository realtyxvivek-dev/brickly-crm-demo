<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\WhatsAppAutomationJourney;
use App\Models\WhatsAppAutomationLog;
use App\Models\WhatsAppAutomationRule;
use App\Models\SystemSettings;
use App\Models\WhatsAppTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppAutomationService
{
    public const DEFAULT_QUIET_POLICY = [
        'enabled' => false,
        'start' => '09:00',
        'end' => '19:00',
    ];

    public function __construct(
        private readonly WhatsAppApiService $whatsAppApiService,
        private readonly MetaWabaApiService $metaWabaApiService,
        private readonly WhatsAppRoutingService $routingService,
        private readonly WhatsAppComplianceService $complianceService
    ) {
    }

    private function sendTemplate(string $phone, string $templateName, array $parameters = [], ?string $language = null, ?MetaWabaAccount $account = null): array
    {
        if (SystemSettings::get('whatsapp_default_sender', 'third_party') === 'meta_waba') {
            $api = $account ? $this->metaWabaApiService->forAccount($account) : $this->metaWabaApiService;
            return $api->sendTemplateMessage($phone, $templateName, $parameters, $language);
        }

        return $this->whatsAppApiService->sendTemplateMessage($phone, $templateName, $parameters, $language);
    }

    public function syncPresets(?int $userId = null): void
    {
        foreach ($this->presetDefinitions() as $preset) {
            $journey = WhatsAppAutomationJourney::query()->firstOrCreate(
                ['key' => $preset['key']],
                [
                    'name' => $preset['name'],
                    'slug' => $preset['slug'],
                    'category' => $preset['category'],
                    'status' => 'draft',
                    'is_active' => false,
                    'is_preset' => true,
                    'description' => $preset['description'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );

            if (!$journey->rules()->exists()) {
                foreach ($preset['rules'] as $ruleDefinition) {
                    $journey->rules()->create([
                        'name' => $ruleDefinition['name'],
                        'trigger' => $ruleDefinition['trigger'],
                        'status' => WhatsAppAutomationRule::STATUS_DRAFT,
                        'is_active' => false,
                        'priority' => $ruleDefinition['priority'],
                        'conditions' => $ruleDefinition['conditions'] ?? [],
                        'variable_map' => $ruleDefinition['variable_map'] ?? [],
                        'send_timing' => $ruleDefinition['send_timing'] ?? ['mode' => 'immediate'],
                        'quiet_hour_policy' => $ruleDefinition['quiet_hour_policy'] ?? self::DEFAULT_QUIET_POLICY,
                        'once_per_lead' => $ruleDefinition['once_per_lead'] ?? true,
                        'resend_cap' => $ruleDefinition['resend_cap'] ?? 1,
                        'stop_statuses' => $ruleDefinition['stop_statuses'] ?? ['dead', 'closed', 'junk', 'not_interested'],
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }
        }
    }

    public function presetDefinitions(): array
    {
        return [
            [
                'key' => 'new-lead-welcome',
                'slug' => 'new-lead-welcome',
                'name' => 'New Lead Welcome',
                'category' => 'welcome',
                'description' => 'First message for a new lead with source-wise flexibility.',
                'rules' => [[
                    'name' => 'Default Welcome',
                    'trigger' => 'lead_created',
                    'priority' => 300,
                    'variable_map' => ['1' => 'lead_name', '2' => 'project_name', '3' => 'assigned_advisor_name', '4' => 'assigned_advisor_phone'],
                ]],
            ],
            [
                'key' => 'assigned-advisor-introduction',
                'slug' => 'assigned-advisor-introduction',
                'name' => 'Assigned Advisor Introduction',
                'category' => 'welcome',
                'description' => 'Advisor introduction after lead assignment.',
                'rules' => [[
                    'name' => 'Advisor Introduction',
                    'trigger' => 'lead_assigned',
                    'priority' => 200,
                    'variable_map' => ['1' => 'lead_name', '2' => 'assigned_advisor_name', '3' => 'assigned_advisor_phone'],
                ]],
            ],
            [
                'key' => 'site-visit-confirmation',
                'slug' => 'site-visit-confirmation',
                'name' => 'Site Visit Confirmation',
                'category' => 'visit',
                'description' => 'Visit confirmation after site visit is scheduled.',
                'rules' => [[
                    'name' => 'Site Visit Scheduled',
                    'trigger' => 'site_visit_scheduled',
                    'priority' => 200,
                    'variable_map' => ['1' => 'lead_name', '2' => 'project_name', '3' => 'visit_date_time', '4' => 'office_location'],
                ]],
            ],
            [
                'key' => 'site-visit-reminder',
                'slug' => 'site-visit-reminder',
                'name' => 'Site Visit Reminder',
                'category' => 'visit',
                'description' => 'Reminder before site visit.',
                'rules' => [[
                    'name' => 'Site Visit Reminder',
                    'trigger' => 'site_visit_scheduled',
                    'priority' => 220,
                    'send_timing' => ['mode' => 'delay', 'value' => 1, 'unit' => 'days'],
                    'variable_map' => ['1' => 'lead_name', '2' => 'project_name', '3' => 'visit_date_time'],
                ]],
            ],
            [
                'key' => 'meeting-confirmation',
                'slug' => 'meeting-confirmation',
                'name' => 'Meeting Confirmation',
                'category' => 'meeting',
                'description' => 'Confirmation after meeting is scheduled.',
                'rules' => [[
                    'name' => 'Meeting Scheduled',
                    'trigger' => 'meeting_scheduled',
                    'priority' => 200,
                    'variable_map' => ['1' => 'lead_name', '2' => 'meeting_date_time', '3' => 'project_name'],
                ]],
            ],
            [
                'key' => 'post-meeting-follow-up',
                'slug' => 'post-meeting-follow-up',
                'name' => 'Post-Meeting Follow-up',
                'category' => 'meeting',
                'description' => 'Follow-up after meeting completion.',
                'rules' => [[
                    'name' => 'Meeting Completed',
                    'trigger' => 'meeting_completed',
                    'priority' => 200,
                    'variable_map' => ['1' => 'lead_name', '2' => 'project_name', '3' => 'assigned_advisor_name', '4' => 'assigned_advisor_phone'],
                ]],
            ],
            [
                'key' => 'no-response-revival',
                'slug' => 'no-response-revival',
                'name' => 'No Response Revival',
                'category' => 'nurture',
                'description' => 'Nurture lead after no activity window.',
                'rules' => [[
                    'name' => 'No Activity Revival',
                    'trigger' => 'no_activity',
                    'priority' => 200,
                    'send_timing' => ['mode' => 'delay', 'value' => 24, 'unit' => 'hours'],
                    'variable_map' => ['1' => 'lead_name', '2' => 'project_name', '3' => 'assigned_advisor_phone'],
                ]],
            ],
            [
                'key' => 'booking-closure-update',
                'slug' => 'booking-closure-update',
                'name' => 'Booking / Closure Update',
                'category' => 'closure',
                'description' => 'Update after lead is closed or booked.',
                'rules' => [[
                    'name' => 'Closed Lead Update',
                    'trigger' => 'lead_closed',
                    'priority' => 200,
                    'variable_map' => ['1' => 'lead_name', '2' => 'project_name', '3' => 'company_name'],
                ]],
            ],
            [
                'key' => 'payment-document-reminder',
                'slug' => 'payment-document-reminder',
                'name' => 'Payment / Document Reminder',
                'category' => 'closure',
                'description' => 'Reminder for payment and documents.',
                'rules' => [[
                    'name' => 'Payment Reminder',
                    'trigger' => 'follow_up_created',
                    'priority' => 250,
                    'variable_map' => ['1' => 'lead_name', '2' => 'project_name', '3' => 'crm_public_url'],
                ]],
            ],
        ];
    }

    public function availableTriggers(): array
    {
        return [
            'lead_created' => 'Lead created',
            'lead_assigned' => 'Lead assigned',
            'follow_up_created' => 'Follow-up created',
            'meeting_scheduled' => 'Meeting scheduled',
            'meeting_completed' => 'Meeting completed',
            'site_visit_scheduled' => 'Site visit scheduled',
            'site_visit_verified' => 'Site visit verified',
            'no_activity' => 'No activity / no response',
            'lead_stage_changed' => 'Lead stage changed',
            'lead_closed' => 'Lead closed / booked / lost',
            'inbound_keyword' => 'Inbound keyword received',
            'campaign_delivered' => 'Campaign delivered',
            'campaign_read' => 'Campaign read',
            'campaign_replied' => 'Campaign replied',
        ];
    }

    public function variableCatalog(): array
    {
        return [
            'lead_name' => 'Lead name',
            'project_name' => 'Project name',
            'source' => 'Lead source',
            'assigned_advisor_name' => 'Advisor name',
            'assigned_advisor_phone' => 'Advisor phone',
            'company_name' => 'Company name',
            'visit_date_time' => 'Visit date and time',
            'meeting_date_time' => 'Meeting date and time',
            'office_location' => 'Office or project location',
            'crm_public_url' => 'Callback / CRM public URL',
        ];
    }

    public function templatesCatalog(): Collection
    {
        return WhatsAppTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(function (WhatsAppTemplate $template) {
                $content = (string) $template->content;
                preg_match_all('/\{\{(\d+)\}\}/', $content, $matches);

                return collect([
                    'id' => $template->id,
                    'template_id' => $template->template_id,
                    'name' => $template->name,
                    'language' => $template->language,
                    'category' => $template->category,
                    'is_active' => $template->is_active,
                    'content' => $content,
                    'placeholder_count' => collect($matches[1] ?? [])->unique()->count(),
                    'slots' => collect($matches[1] ?? [])->unique()->sort()->values()->all(),
                ]);
            });
    }

    public function availableSources(): array
    {
        $dbSources = Lead::query()
            ->select('source')
            ->whereNotNull('source')
            ->distinct()
            ->pluck('source')
            ->map(fn ($value) => Lead::normalizeSource($value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return array_values(array_unique(array_merge(array_keys(Lead::sourceOptions()), $dbSources)));
    }

    public function availableProjects(): EloquentCollection
    {
        return Project::query()->orderBy('name')->get(['id', 'name']);
    }

    public function overviewMetrics(): array
    {
        $today = now()->startOfDay();
        $journeyIds = WhatsAppAutomationJourney::query()->where('is_active', true)->pluck('id');

        return [
            'active_automations' => $journeyIds->count(),
            'sent_today' => WhatsAppAutomationLog::query()->where('status', WhatsAppAutomationLog::STATUS_SENT)->where('sent_at', '>=', $today)->count(),
            'delivered' => WhatsAppAutomationLog::query()->whereNotNull('delivered_at')->count(),
            'read' => WhatsAppAutomationLog::query()->whereNotNull('read_at')->count(),
            'replied' => WhatsAppAutomationLog::query()->whereNotNull('replied_at')->count(),
            'failed' => WhatsAppAutomationLog::query()->where('status', WhatsAppAutomationLog::STATUS_FAILED)->count(),
            'blocked' => WhatsAppAutomationLog::query()->whereIn('status', [WhatsAppAutomationLog::STATUS_BLOCKED, WhatsAppAutomationLog::STATUS_SKIPPED])->count(),
            'top_journeys' => WhatsAppAutomationLog::query()
                ->select('journey_id', DB::raw('count(*) as total'))
                ->whereNotNull('journey_id')
                ->groupBy('journey_id')
                ->orderByDesc('total')
                ->with('journey:id,name')
                ->limit(5)
                ->get(),
        ];
    }

    public function analytics(): array
    {
        return [
            'journey_performance' => WhatsAppAutomationLog::query()
                ->select('journey_id', DB::raw('count(*) as total'))
                ->with('journey:id,name')
                ->groupBy('journey_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'template_performance' => WhatsAppAutomationLog::query()
                ->select('template_name', DB::raw('count(*) as total'))
                ->whereNotNull('template_name')
                ->groupBy('template_name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'source_performance' => WhatsAppAutomationLog::query()
                ->selectRaw("json_unquote(json_extract(context_snapshot, '$.lead.source')) as source, count(*) as total")
                ->groupBy('source')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'project_performance' => WhatsAppAutomationLog::query()
                ->selectRaw("json_unquote(json_extract(context_snapshot, '$.project.name')) as project_name, count(*) as total")
                ->groupBy('project_name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'trigger_failures' => WhatsAppAutomationLog::query()
                ->select('trigger', DB::raw('count(*) as total'))
                ->where('status', WhatsAppAutomationLog::STATUS_FAILED)
                ->groupBy('trigger')
                ->orderByDesc('total')
                ->get(),
        ];
    }

    public function previewRule(WhatsAppAutomationRule $rule, array $entityContext = []): array
    {
        $context = $this->buildContextSnapshot($entityContext);
        $template = $rule->template ?: $rule->journey?->template;
        $resolved = $this->resolveRuleVariables($rule, $template, $context);

        return [
            'template' => $template?->name,
            'content' => $this->applyPreviewContent($template?->content ?? '', $resolved['ordered']),
            'variables' => $resolved['resolved'],
            'errors' => $resolved['errors'],
        ];
    }

    public function handleTrigger(string $eventName, array $entityContext = []): array
    {
        $context = $this->buildContextSnapshot($entityContext);
        $lead = $context['lead'];
        if (!$lead instanceof Lead) {
            return ['processed' => 0, 'matched' => 0, 'logs' => []];
        }

        $matched = [];
        $logs = [];
        $processedJourneyIds = [];
        $rules = WhatsAppAutomationRule::query()
            ->with(['journey', 'template', 'journey.template'])
            ->where('trigger', $eventName)
            ->where('is_active', true)
            ->where('status', WhatsAppAutomationRule::STATUS_ACTIVE)
            ->whereHas('journey', fn ($query) => $query->where('is_active', true))
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if (in_array($rule->journey_id, $processedJourneyIds, true)) {
                continue;
            }

            if (!$this->matchesRule($rule, $context)) {
                continue;
            }

            $matched[] = $rule->id;
            $executionKey = $this->buildExecutionKey($rule, $lead, $eventName, $context);
            if ($this->shouldSkipExecution($rule, $lead, $executionKey)) {
                $logs[] = $this->createSkippedLog($rule, $lead, $eventName, $context, $executionKey, 'Duplicate execution prevented by idempotency rules.')->id;
                $processedJourneyIds[] = $rule->journey_id;
                continue;
            }

            $scheduledFor = $this->resolveScheduledFor($rule, $context);
            $log = WhatsAppAutomationLog::query()->create([
                'journey_id' => $rule->journey_id,
                'rule_id' => $rule->id,
                'lead_id' => $lead->id,
                'trigger' => $eventName,
                'related_type' => $context['related_type'],
                'related_id' => $context['related_id'],
                'template_id' => $rule->template_id ?: $rule->journey?->template_id,
                'template_name' => $rule->template?->name ?: $rule->journey?->template?->name,
                'meta_waba_account_id' => $rule->meta_waba_account_id,
                'recipient_phone' => $lead->phone,
                'execution_key' => $executionKey,
                'status' => WhatsAppAutomationLog::STATUS_PENDING,
                'actor_type' => Arr::get($context, 'actor.type'),
                'actor_id' => Arr::get($context, 'actor.id'),
                'scheduled_for' => $scheduledFor,
                'context_snapshot' => $this->serializeContext($context),
            ]);
            $logs[] = $log->id;

            if ($scheduledFor->lessThanOrEqualTo(now())) {
                $this->processLog($log->fresh(['rule', 'journey', 'rule.template', 'journey.template']));
            }

            $processedJourneyIds[] = $rule->journey_id;
        }

        return [
            'processed' => count($logs),
            'matched' => count($matched),
            'logs' => $logs,
        ];
    }

    public function processPending(int $limit = 50): int
    {
        $processed = 0;
        $logs = WhatsAppAutomationLog::query()
            ->with(['rule', 'journey', 'rule.template', 'journey.template'])
            ->where('status', WhatsAppAutomationLog::STATUS_PENDING)
            ->where(function ($query) {
                $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now());
            })
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();

        foreach ($logs as $log) {
            $this->processLog($log);
            $processed++;
        }

        return $processed;
    }

    public function scheduleNoActivityRules(int $limit = 100): int
    {
        $created = 0;
        $rules = WhatsAppAutomationRule::query()
            ->with(['journey', 'template', 'journey.template'])
            ->where('trigger', 'no_activity')
            ->where('is_active', true)
            ->where('status', WhatsAppAutomationRule::STATUS_ACTIVE)
            ->whereHas('journey', fn ($query) => $query->where('is_active', true))
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            $timing = $rule->send_timing ?? ['mode' => 'delay', 'value' => 24, 'unit' => 'hours'];
            $value = max(1, (int) ($timing['value'] ?? 24));
            $unit = (string) ($timing['unit'] ?? 'hours');
            $cutoff = match ($unit) {
                'days' => now()->subDays($value),
                'minutes' => now()->subMinutes($value),
                default => now()->subHours($value),
            };

            $leads = Lead::query()
                ->with('activeAssignments.assignedTo.role')
                ->where(function ($query) use ($cutoff) {
                    $query->where('last_contacted_at', '<=', $cutoff)
                        ->orWhere(function ($fallbackQuery) use ($cutoff) {
                            $fallbackQuery->whereNull('last_contacted_at')
                                ->where('updated_at', '<=', $cutoff);
                        });
                })
                ->where(function ($query) use ($rule) {
                    foreach (($rule->stop_statuses ?? []) as $status) {
                        $query->where('status', '!=', $status);
                    }
                })
                ->limit($limit)
                ->get();

            foreach ($leads as $lead) {
                $context = $this->buildContextSnapshot([
                    'lead' => $lead,
                    'related_type' => 'lead',
                    'related_id' => $lead->id,
                    'actor_type' => 'system',
                    'actor_id' => null,
                ]);

                if (!$this->matchesRule($rule, $context)) {
                    continue;
                }

                $executionKey = $this->buildExecutionKey($rule, $lead, 'no_activity', $context);
                if ($this->shouldSkipExecution($rule, $lead, $executionKey)) {
                    continue;
                }

                $log = WhatsAppAutomationLog::query()->create([
                    'journey_id' => $rule->journey_id,
                    'rule_id' => $rule->id,
                    'lead_id' => $lead->id,
                    'trigger' => 'no_activity',
                    'related_type' => 'lead',
                    'related_id' => $lead->id,
                    'template_id' => $rule->template_id ?: $rule->journey?->template_id,
                    'template_name' => $rule->template?->name ?: $rule->journey?->template?->name,
                    'meta_waba_account_id' => $rule->meta_waba_account_id,
                    'recipient_phone' => $lead->phone,
                    'execution_key' => $executionKey,
                    'status' => WhatsAppAutomationLog::STATUS_PENDING,
                    'scheduled_for' => $this->applyQuietHours(now(), $rule->quiet_hour_policy ?? self::DEFAULT_QUIET_POLICY, $context),
                    'context_snapshot' => $this->serializeContext($context),
                ]);
                $created++;

                if (($log->scheduled_for ?? now())->lessThanOrEqualTo(now())) {
                    $this->processLog($log->fresh(['rule', 'journey', 'rule.template', 'journey.template']));
                }
            }
        }

        return $created;
    }

    public function processLog(WhatsAppAutomationLog $log): void
    {
        $rule = $log->rule;
        if (!$rule || !$rule->journey || !$rule->journey->is_active || !$rule->is_active) {
            $log->update([
                'status' => WhatsAppAutomationLog::STATUS_SKIPPED,
                'failure_reason' => 'Journey or rule is no longer active.',
                'processed_at' => now(),
            ]);
            return;
        }

        $context = $log->context_snapshot ?? [];
        $lead = Lead::find($log->lead_id);
        if (!$lead) {
            $log->update([
                'status' => WhatsAppAutomationLog::STATUS_FAILED,
                'failure_reason' => 'Lead no longer exists.',
                'processed_at' => now(),
            ]);
            return;
        }

        $context = $this->rebuildContextFromSnapshot($context, $lead);
        $template = $rule->template ?: $rule->journey?->template;
        if (!$template) {
            $log->update([
                'status' => WhatsAppAutomationLog::STATUS_FAILED,
                'failure_reason' => 'No approved template selected.',
                'processed_at' => now(),
            ]);
            return;
        }

        $compliance = $this->complianceService->checkLead($lead, [
            'daily_send_cap' => $rule->daily_send_cap ?? 3,
            'cooldown_minutes' => $rule->cooldown_minutes ?? 0,
            'requires_session_window' => $rule->requires_session_window ?? false,
        ]);
        if (!($compliance['allowed'] ?? false)) {
            $log->update([
                'status' => WhatsAppAutomationLog::STATUS_BLOCKED,
                'failure_reason' => $compliance['reason'] ?? 'Blocked by WhatsApp compliance rules.',
                'processed_at' => now(),
            ]);
            return;
        }

        $resolved = $this->resolveRuleVariables($rule, $template, $context);
        if (!empty($resolved['errors'])) {
            $log->update([
                'status' => WhatsAppAutomationLog::STATUS_FAILED,
                'failure_reason' => implode(' ', $resolved['errors']),
                'resolved_variables' => $resolved['resolved'],
                'processed_at' => now(),
            ]);
            return;
        }

        try {
            $explicitAccount = $rule->metaWabaAccount ?: $template->metaWabaAccount;
            $account = $this->routingService->resolve($lead, [
                'project' => Arr::get($context, 'project.name') ?: $lead->preferred_projects,
                'assigned_user_id' => Arr::get($context, 'assigned_user.id'),
                'automation_trigger' => $log->trigger,
            ], $explicitAccount);

            $result = $this->sendTemplate(
                $lead->phone ?? '',
                $template->name ?: $template->template_id,
                $resolved['ordered'],
                $template->language,
                $account
            );

            $sendSucceeded = (bool) Arr::get($result, 'success');
            $log->update([
                'status' => $sendSucceeded ? WhatsAppAutomationLog::STATUS_SENT : WhatsAppAutomationLog::STATUS_FAILED,
                'provider_message_id' => Arr::get($result, 'data.id') ?? Arr::get($result, 'data.message_id'),
                'meta_waba_account_id' => $account?->id,
                'failure_reason' => $sendSucceeded ? null : (Arr::get($result, 'error') ?: 'Provider send failed'),
                'resolved_variables' => $resolved['resolved'],
                'payload_snapshot' => [
                    'template_name' => $template->name,
                    'language' => $template->language,
                    'parameters' => $resolved['ordered'],
                ],
                'provider_response' => $result,
                'processed_at' => now(),
                'sent_at' => $sendSucceeded ? now() : null,
            ]);

            $rule->forceFill(['last_executed_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('WhatsApp automation send failed', [
                'rule_id' => $rule->id,
                'log_id' => $log->id,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => WhatsAppAutomationLog::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
                'resolved_variables' => $resolved['resolved'],
                'processed_at' => now(),
            ]);
        }
    }

    protected function buildExecutionKey(WhatsAppAutomationRule $rule, Lead $lead, string $trigger, array $context): string
    {
        $base = $rule->once_per_lead
            ? implode(':', [$rule->id, $lead->id, $trigger, 'once'])
            : implode(':', [$rule->id, $lead->id, $trigger, $context['related_type'] ?: 'lead', $context['related_id'] ?: '0']);

        return Str::limit(hash('sha256', $base), 64, '');
    }

    protected function shouldSkipExecution(WhatsAppAutomationRule $rule, Lead $lead, string $executionKey): bool
    {
        if (WhatsAppAutomationLog::query()->where('execution_key', $executionKey)->exists()) {
            return true;
        }

        if ($rule->once_per_lead) {
            return WhatsAppAutomationLog::query()
                ->where('rule_id', $rule->id)
                ->where('lead_id', $lead->id)
                ->whereIn('status', [
                    WhatsAppAutomationLog::STATUS_PENDING,
                    WhatsAppAutomationLog::STATUS_SENT,
                    WhatsAppAutomationLog::STATUS_BLOCKED,
                ])
                ->exists();
        }

        $resendCap = max(1, (int) $rule->resend_cap);
        return WhatsAppAutomationLog::query()
            ->where('rule_id', $rule->id)
            ->where('lead_id', $lead->id)
            ->count() >= $resendCap;
    }

    protected function matchesRule(WhatsAppAutomationRule $rule, array $context): bool
    {
        $lead = $context['lead'];
        if (!$lead instanceof Lead) {
            return false;
        }

        if ($lead->is_dead || in_array($lead->status, $rule->stop_statuses ?? [], true)) {
            return false;
        }

        $conditions = $rule->conditions ?? [];
        $source = Lead::normalizeSource($lead->source);

        if (!empty($conditions['source_mode'])) {
            $sourceValues = collect($conditions['source_values'] ?? [])
                ->map(fn ($value) => Lead::normalizeSource((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($conditions['source_mode'] === 'exact' && !in_array($source, $sourceValues, true)) {
                return false;
            }
            if ($conditions['source_mode'] === 'in_list' && !in_array($source, $sourceValues, true)) {
                return false;
            }
            if ($conditions['source_mode'] === 'except' && in_array($source, $sourceValues, true)) {
                return false;
            }
        }

        if (!empty($conditions['project_ids'])) {
            $projectId = (int) Arr::get($context, 'project.id');
            if (!$projectId || !in_array($projectId, array_map('intval', (array) $conditions['project_ids']), true)) {
                return false;
            }
        }

        if (!empty($conditions['cities']) && !in_array(Str::lower((string) $lead->city), collect($conditions['cities'])->map(fn ($value) => Str::lower((string) $value))->all(), true)) {
            return false;
        }

        if (!empty($conditions['lead_statuses']) && !in_array($lead->status, (array) $conditions['lead_statuses'], true)) {
            return false;
        }

        if (!empty($conditions['assigned_user_ids'])) {
            $assignedUserId = (int) Arr::get($context, 'assigned_user.id');
            if (!$assignedUserId || !in_array($assignedUserId, array_map('intval', (array) $conditions['assigned_user_ids']), true)) {
                return false;
            }
        }

        if (!empty($conditions['assigned_role_slugs'])) {
            $assignedRole = (string) Arr::get($context, 'assigned_user.role_slug');
            if ($assignedRole === '' || !in_array($assignedRole, (array) $conditions['assigned_role_slugs'], true)) {
                return false;
            }
        }

        if (($conditions['first_time_only'] ?? false) && $lead->is_reenquiry) {
            return false;
        }

        if (($conditions['reenquiry_only'] ?? false) && !$lead->is_reenquiry) {
            return false;
        }

        if (($conditions['require_assigned_advisor'] ?? false) && !Arr::get($context, 'assigned_user.id')) {
            return false;
        }

        return true;
    }

    protected function resolveScheduledFor(WhatsAppAutomationRule $rule, array $context): Carbon
    {
        $baseTime = now();
        $timing = $rule->send_timing ?? ['mode' => 'immediate'];
        if (($timing['mode'] ?? 'immediate') === 'delay') {
            $value = max(0, (int) ($timing['value'] ?? 0));
            $unit = (string) ($timing['unit'] ?? 'minutes');
            $baseTime = match ($unit) {
                'days' => $baseTime->copy()->addDays($value),
                'hours' => $baseTime->copy()->addHours($value),
                default => $baseTime->copy()->addMinutes($value),
            };
        }

        return $this->applyQuietHours($baseTime, $rule->quiet_hour_policy ?? self::DEFAULT_QUIET_POLICY, $context);
    }

    protected function applyQuietHours(Carbon $candidate, array $policy, array $context): Carbon
    {
        if (!(bool) ($policy['enabled'] ?? false)) {
            return $candidate;
        }

        $timezone = config('app.timezone');
        $start = Carbon::parse(($policy['start'] ?? '09:00'), $timezone);
        $end = Carbon::parse(($policy['end'] ?? '19:00'), $timezone);
        $candidate = $candidate->copy()->setTimezone($timezone);

        $windowStart = $candidate->copy()->setTimeFrom($start);
        $windowEnd = $candidate->copy()->setTimeFrom($end);

        if ($candidate->betweenIncluded($windowStart, $windowEnd)) {
            return $candidate;
        }

        if ($candidate->lt($windowStart)) {
            return $windowStart;
        }

        return $candidate->copy()->addDay()->setTimeFrom($start);
    }

    protected function resolveRuleVariables(WhatsAppAutomationRule $rule, ?WhatsAppTemplate $template, array $context): array
    {
        $errors = [];
        $resolved = [];
        $ordered = [];
        $variableMap = $rule->variable_map ?? [];
        $slots = $this->extractTemplateSlots($template?->content ?? '');

        if (empty($slots) && !empty($variableMap)) {
            $slots = collect(array_keys($variableMap))->map(fn ($slot) => (int) $slot)->sort()->values()->all();
        }

        foreach ($slots as $slot) {
            $mappedKey = $variableMap[(string) $slot] ?? null;
            if (!$mappedKey) {
                $errors[] = "Template field {$slot} is not mapped.";
                continue;
            }

            $value = $this->resolveVariableValue((string) $mappedKey, $context);
            $resolved[(string) $slot] = [
                'field' => $mappedKey,
                'value' => $value,
            ];

            if ($value === null || trim((string) $value) === '') {
                $label = $this->variableCatalog()[$mappedKey] ?? $mappedKey;
                $errors[] = "Required value missing for {$label}.";
                continue;
            }

            $ordered[] = ['type' => 'text', 'text' => (string) $value];
        }

        return compact('errors', 'resolved', 'ordered');
    }

    protected function resolveVariableValue(string $key, array $context): ?string
    {
        $lead = $context['lead'];
        $assignedUser = $context['assigned_user'];

        return match ($key) {
            'lead_name' => $lead?->name,
            'project_name' => Arr::get($context, 'project.name') ?: $lead?->preferred_projects,
            'source' => $lead ? Lead::displaySourceLabel($lead->source) : null,
            'assigned_advisor_name' => $assignedUser?->name,
            'assigned_advisor_phone' => $assignedUser?->phone,
            'company_name' => (string) CompanySetting::get('company_name', config('app.name', 'Company')),
            'visit_date_time' => optional(Arr::get($context, 'site_visit.scheduled_at'))->format('d M Y h:i A'),
            'meeting_date_time' => optional(Arr::get($context, 'meeting.scheduled_at'))->format('d M Y h:i A'),
            'office_location' => Arr::get($context, 'site_visit.property_address')
                ?: Arr::get($context, 'site_visit.property_name')
                ?: Arr::get($context, 'meeting.location'),
            'crm_public_url' => url('/leads/' . ($lead?->id ?? '')),
            default => null,
        };
    }

    protected function buildContextSnapshot(array $entityContext): array
    {
        $lead = $entityContext['lead'] ?? null;
        if (!$lead && isset($entityContext['lead_id'])) {
            $lead = Lead::query()->find($entityContext['lead_id']);
        }

        if ($lead instanceof Lead) {
            $lead->loadMissing('activeAssignments.assignedTo.role');
        }

        $meeting = $entityContext['meeting'] ?? null;
        if (!$meeting && isset($entityContext['meeting_id'])) {
            $meeting = Meeting::find($entityContext['meeting_id']);
        }

        $siteVisit = $entityContext['site_visit'] ?? null;
        if (!$siteVisit && isset($entityContext['site_visit_id'])) {
            $siteVisit = SiteVisit::find($entityContext['site_visit_id']);
        }

        $assignment = $entityContext['assignment'] ?? null;
        if (!$assignment && isset($entityContext['assignment_id'])) {
            $assignment = LeadAssignment::find($entityContext['assignment_id']);
        }

        $assignedUser = $entityContext['assigned_user'] ?? optional($assignment)->assignedTo ?? optional(optional($lead)->activeAssignments->first())->assignedTo;
        $project = $this->resolveProjectFromContext($entityContext, $lead, $meeting, $siteVisit);

        return [
            'lead' => $lead,
            'meeting' => $meeting,
            'site_visit' => $siteVisit,
            'assignment' => $assignment,
            'assigned_user' => $assignedUser,
            'project' => $project,
            'actor' => [
                'type' => $entityContext['actor_type'] ?? 'system',
                'id' => $entityContext['actor_id'] ?? auth()->id(),
            ],
            'related_type' => $entityContext['related_type']
                ?? ($siteVisit ? 'site_visit' : ($meeting ? 'meeting' : ($assignment ? 'lead_assignment' : 'lead'))),
            'related_id' => $entityContext['related_id']
                ?? ($siteVisit?->id ?: ($meeting?->id ?: ($assignment?->id ?: $lead?->id))),
        ];
    }

    protected function serializeContext(array $context): array
    {
        $lead = $context['lead'];
        $assignedUser = $context['assigned_user'];
        $meeting = $context['meeting'];
        $siteVisit = $context['site_visit'];

        return [
            'lead' => $lead ? [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'source' => Lead::normalizeSource($lead->source),
                'status' => $lead->status,
                'city' => $lead->city,
                'preferred_projects' => $lead->preferred_projects,
                'is_reenquiry' => (bool) $lead->is_reenquiry,
            ] : null,
            'assigned_user' => $assignedUser ? [
                'id' => $assignedUser->id,
                'name' => $assignedUser->name,
                'phone' => $assignedUser->phone,
                'role_slug' => $assignedUser->role?->slug,
            ] : null,
            'project' => $context['project'],
            'meeting' => $meeting ? [
                'id' => $meeting->id,
                'scheduled_at' => optional($meeting->scheduled_at)?->toIso8601String(),
                'project' => $meeting->project,
                'location' => $meeting->location,
            ] : null,
            'site_visit' => $siteVisit ? [
                'id' => $siteVisit->id,
                'scheduled_at' => optional($siteVisit->scheduled_at)?->toIso8601String(),
                'project' => $siteVisit->project,
                'property_name' => $siteVisit->property_name,
                'property_address' => $siteVisit->property_address,
            ] : null,
            'actor' => $context['actor'],
            'related_type' => $context['related_type'],
            'related_id' => $context['related_id'],
        ];
    }

    protected function rebuildContextFromSnapshot(array $snapshot, Lead $lead): array
    {
        $meetingId = Arr::get($snapshot, 'meeting.id');
        $siteVisitId = Arr::get($snapshot, 'site_visit.id');
        $assignedUserId = Arr::get($snapshot, 'assigned_user.id');

        return [
            'lead' => $lead->loadMissing('activeAssignments.assignedTo.role'),
            'meeting' => $meetingId ? Meeting::find($meetingId) : null,
            'site_visit' => $siteVisitId ? SiteVisit::find($siteVisitId) : null,
            'assignment' => null,
            'assigned_user' => $assignedUserId ? User::with('role')->find($assignedUserId) : optional($lead->activeAssignments->first())->assignedTo,
            'project' => Arr::get($snapshot, 'project'),
            'actor' => Arr::get($snapshot, 'actor', ['type' => 'system', 'id' => null]),
            'related_type' => Arr::get($snapshot, 'related_type', 'lead'),
            'related_id' => Arr::get($snapshot, 'related_id', $lead->id),
        ];
    }

    protected function resolveProjectFromContext(array $entityContext, ?Lead $lead, ?Meeting $meeting, ?SiteVisit $siteVisit): ?array
    {
        $projectName = $entityContext['project_name']
            ?? $siteVisit?->project
            ?? $meeting?->project
            ?? $lead?->preferred_projects;

        if (!$projectName) {
            return null;
        }

        $project = Project::query()->where('name', $projectName)->first();

        return [
            'id' => $project?->id,
            'name' => $project?->name ?: $projectName,
        ];
    }

    protected function extractTemplateSlots(string $content): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function applyPreviewContent(string $content, array $ordered): string
    {
        foreach ($ordered as $index => $parameter) {
            $content = str_replace('{{' . ($index + 1) . '}}', (string) ($parameter['text'] ?? ''), $content);
        }

        return $content;
    }

    protected function createSkippedLog(WhatsAppAutomationRule $rule, Lead $lead, string $trigger, array $context, string $executionKey, string $reason): WhatsAppAutomationLog
    {
        return WhatsAppAutomationLog::query()->create([
            'journey_id' => $rule->journey_id,
            'rule_id' => $rule->id,
            'lead_id' => $lead->id,
            'trigger' => $trigger,
            'related_type' => $context['related_type'],
            'related_id' => $context['related_id'],
            'template_id' => $rule->template_id ?: $rule->journey?->template_id,
            'template_name' => $rule->template?->name ?: $rule->journey?->template?->name,
            'recipient_phone' => $lead->phone,
            'execution_key' => hash('sha256', $executionKey . ':skip:' . now()->format('YmdHisv') . ':' . Str::random(6)),
            'status' => WhatsAppAutomationLog::STATUS_SKIPPED,
            'failure_reason' => $reason,
            'actor_type' => Arr::get($context, 'actor.type'),
            'actor_id' => Arr::get($context, 'actor.id'),
            'processed_at' => now(),
            'context_snapshot' => $this->serializeContext($context),
        ]);
    }
}
