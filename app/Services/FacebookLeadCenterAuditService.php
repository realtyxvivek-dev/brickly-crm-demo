<?php

namespace App\Services;

use App\Models\FacebookLeadCenterAudit;
use App\Models\FacebookLeadCenterAuditRow;
use App\Models\FbForm;
use App\Models\FbLead;
use App\Models\FbWebhookEvent;
use App\Models\Lead;
use App\Models\MetaOauthEvent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FacebookLeadCenterAuditService
{
    public function __construct(
        private readonly SourceAutomationService $sourceAutomationService,
        private readonly LeadDuplicateGuardService $leadDuplicateGuardService,
    ) {
    }

    public function compareAndStore(User $actor, array $payload): array
    {
        $rows = collect($payload['leads'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row, int $index) => $this->normalizeRow($row, $index))
            ->reduce(function (Collection $carry, array $row) {
                $key = $this->canonicalRowKey($row);
                $existing = $carry->get($key);
                $carry->put($key, $existing ? $this->mergeNormalizedRows($existing, $row) : $row);

                return $carry;
            }, collect())
            ->values()
            ->take(1000)
            ->values();

        $audit = FacebookLeadCenterAudit::query()->create([
            'user_id' => $actor->id,
            'source_url' => $this->trimNullable($payload['source_url'] ?? null, 2048),
            'page_title' => $this->trimNullable($payload['page_title'] ?? null),
            'browser_meta' => Arr::only($payload['browser_meta'] ?? [], [
                'user_agent',
                'extension_version',
                'date_filter',
                'scan_mode',
                'expected_rows',
                'scanned_rows',
                'scan_complete',
                'selected_form_id',
                'selected_external_form_id',
                'selected_form_name',
                'selected_page_id',
                'selected_page_name',
            ]),
        ]);

        $resultRows = $rows->map(function (array $normalized) use ($audit) {
            $match = $this->matchRow($normalized);

            $auditRow = FacebookLeadCenterAuditRow::query()->create(array_merge($normalized, $match, [
                'facebook_lead_center_audit_id' => $audit->id,
            ]));

            return $this->rowPayload($auditRow);
        });

        $summary = $this->summaryFromRows($resultRows);
        $audit->forceFill($summary)->save();

        return [
            'audit_id' => $audit->id,
            'summary' => $summary,
            'rows' => $resultRows->all(),
            'report_url' => route('admin.mobile-app-update.index') . '#facebook-lead-center-extension',
        ];
    }

    public function importSelected(User $actor, array $rowIds, array $options = []): array
    {
        $assignExistingRule = (bool) ($options['assign_existing_rule'] ?? true);
        $rows = FacebookLeadCenterAuditRow::query()
            ->whereIn('id', $rowIds)
            ->whereIn('status', ['missing', 'not_enough_data'])
            ->get();

        $imported = [];
        $skipped = [];
        $failed = [];

        foreach ($rows as $row) {
            $phone = $this->normalizePhone($row->phone);
            $email = $this->normalizeEmail($row->email);
            $existing = $this->findLeadByPhoneOrEmail($phone, $email);
            if ($existing) {
                $row->forceFill([
                    'status' => 'in_crm',
                    'match_source' => 'lead',
                    'match_reason' => 'CRM lead already exists before import.',
                    'crm_lead_id' => $existing->id,
                ])->save();

                $skipped[] = $this->rowPayload($row->fresh()) + [
                    'message' => 'Already in CRM. New lead create nahi hui.',
                ];
                continue;
            }

            if ($phone === '') {
                $failed[] = [
                    'row_id' => $row->id,
                    'message' => 'Valid phone missing hai, lead create nahi hui.',
                ];
                continue;
            }

            $selectedFormId = $row->form_id
                ? FbForm::query()->where('form_id', $row->form_id)->value('id')
                : null;
            $createdNewLead = false;
            $lead = $this->leadDuplicateGuardService->withPhoneLock($phone, function () use ($phone, $email, $row, $actor, $selectedFormId, $assignExistingRule, &$createdNewLead) {
                $existing = $this->findLeadByPhoneOrEmail($phone, $email);
                if ($existing) {
                    return $existing;
                }

                $lead = Lead::query()->create([
                    'name' => $row->lead_name ?: 'Facebook Lead ' . ($phone ?: $email),
                    'phone' => $phone,
                    'email' => $email,
                    'source' => Lead::normalizeSource('meta'),
                    'status' => 'new',
                    'created_by' => $actor->id,
                    'notes' => trim(implode("\n", array_filter([
                        'Recovered from Facebook Lead Center extension audit.',
                        $row->leadgen_id ? 'Leadgen ID: ' . $row->leadgen_id : null,
                        $row->form_id ? 'Form ID: ' . $row->form_id : null,
                        $row->page_id ? 'Page ID: ' . $row->page_id : null,
                        $row->raw_text ? 'Raw row: ' . Str::limit($row->raw_text, 800) : null,
                    ]))),
                ]);

                if ($assignExistingRule) {
                    $this->sourceAutomationService->assignFromSource($lead, 'meta', $selectedFormId ? (int) $selectedFormId : null);
                }

                $createdNewLead = true;

                return $lead;
            });

            $row->forceFill([
                'status' => $createdNewLead ? 'imported' : 'in_crm',
                'match_source' => $createdNewLead ? 'manual_import' : 'lead',
                'match_reason' => $createdNewLead
                    ? ($assignExistingRule
                        ? 'Created from confirmed Facebook Lead Center audit row and checked existing Meta assignment rule.'
                        : 'Created from confirmed Facebook Lead Center audit row without assignment.')
                    : 'CRM lead already exists before import.',
                'crm_lead_id' => $lead->id,
                'imported_at' => $createdNewLead ? now() : $row->imported_at,
                'imported_by_user_id' => $createdNewLead ? $actor->id : $row->imported_by_user_id,
            ])->save();

            if ($createdNewLead) {
                $imported[] = $this->rowPayload($row->fresh()) + [
                    'message' => $assignExistingRule
                        ? 'Imported and existing Meta assignment rule checked.'
                        : 'Imported without assignment.',
                ];
            } else {
                $skipped[] = $this->rowPayload($row->fresh()) + [
                    'message' => 'Already in CRM. New lead create nahi hui.',
                ];
            }
        }

        return [
            'imported_count' => count($imported),
            'skipped_count' => count($skipped),
            'failed_count' => count($failed),
            'rows' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'assignment_rule_checked' => $assignExistingRule,
        ];
    }

    public function importCapturedLeads(User $actor, array $leads): array
    {
        $rows = collect($leads)
            ->filter(fn ($lead) => is_array($lead))
            ->map(fn (array $lead) => $this->normalizeCapturedLeadPayload($lead))
            ->reduce(function (Collection $carry, array $lead) {
                $key = $this->capturedLeadKey($lead);
                if (!$carry->has($key)) {
                    $carry->put($key, $lead);
                }

                return $carry;
            }, collect())
            ->values();

        $imported = 0;
        $existing = 0;
        $failed = [];
        $resultRows = [];

        foreach ($rows as $leadPayload) {
            $phone = $this->normalizePhone($leadPayload['phone'] ?? null);
            $email = $this->normalizeEmail($leadPayload['email'] ?? null);
            $name = $this->trimNullable($leadPayload['name'] ?? null);
            $leadgenId = $this->trimNullable($leadPayload['facebook_lead_id'] ?? null, 120);
            $clientKey = $this->trimNullable($leadPayload['client_key'] ?? null, 255);

            if (!$name && $phone === '' && !$email && !$leadgenId) {
                $failed[] = [
                    'client_key' => $clientKey,
                    'status' => 'failed',
                    'message' => 'Name, phone, email, lead ID sab blank hain.',
                ];
                continue;
            }

            $lead = $this->findExistingCapturedLead($phone, $email, $name);
            if ($lead) {
                $existing++;
                $resultRows[] = [
                    'client_key' => $clientKey,
                    'status' => 'exists',
                    'crm_lead_id' => $lead->id,
                    'lead_url' => route('leads.show', $lead->id),
                    'message' => 'CRM lead already exists.',
                ];
                continue;
            }

            if ($phone === '') {
                $failed[] = [
                    'client_key' => $clientKey,
                    'status' => 'failed',
                    'message' => 'Valid phone missing hai. CRM lead fake 0000000000 se create nahi hogi; drawer se phone capture karke dubara send karo.',
                ];
                continue;
            }

            $lead = Lead::query()->create([
                'name' => $name ?: 'Facebook Lead ' . ($phone ?: ($email ?: $leadgenId)),
                'phone' => $phone,
                'email' => $email,
                'source' => Lead::normalizeSource('meta'),
                'status' => 'new',
                'created_by' => $actor->id,
                'notes' => $this->capturedLeadNotes($leadPayload),
            ]);

            $this->sourceAutomationService->assignFromSource($lead, 'meta');
            $imported++;
            $resultRows[] = [
                'client_key' => $clientKey,
                'status' => 'imported',
                'crm_lead_id' => $lead->id,
                'lead_url' => route('leads.show', $lead->id),
                'message' => 'Created from Facebook Lead Center capture.',
            ];
        }

        return [
            'imported_count' => $imported,
            'existing_count' => $existing,
            'failed_count' => count($failed),
            'rows' => $resultRows,
            'failed' => $failed,
        ];
    }

    public function statsPayload(): array
    {
        if (!Schema::hasTable('facebook_lead_center_audits') || !Schema::hasTable('facebook_lead_center_audit_rows')) {
            return [
                'today_scans' => 0,
                'today_rows' => 0,
                'today_missing' => 0,
                'processed_total' => 0,
                'last_scanned_at' => null,
                'recent_rows' => collect(),
            ];
        }

        $todayStart = now()->startOfDay();
        $latestAudit = FacebookLeadCenterAudit::query()->latest('id')->first();

        return [
            'today_scans' => FacebookLeadCenterAudit::query()->where('created_at', '>=', $todayStart)->count(),
            'today_rows' => FacebookLeadCenterAudit::query()->where('created_at', '>=', $todayStart)->sum('total_rows'),
            'today_missing' => FacebookLeadCenterAudit::query()->where('created_at', '>=', $todayStart)->sum('missing_rows'),
            'processed_total' => FacebookLeadCenterAudit::query()->count(),
            'last_scanned_at' => $latestAudit?->created_at,
            'recent_rows' => FacebookLeadCenterAuditRow::query()
                ->with('crmLead')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (FacebookLeadCenterAuditRow $row) => $this->rowPayload($row))
                ->values(),
            'missing_report_groups' => $this->missingReportGroups(),
            'latest_scan_quality' => $this->latestScanQuality($latestAudit),
        ];
    }

    private function normalizeRow(array $row, int $index): array
    {
        $rawText = $this->trimNullable($row['raw_text'] ?? $row['text'] ?? null, 6000);
        $leadgenId = $this->trimNullable($row['leadgen_id'] ?? $row['lead_id'] ?? null, 80)
            ?: $this->extractLabelledId($rawText, ['leadgen', 'lead id', 'lead_id']);

        $pageId = $this->trimNullable($row['page_id'] ?? null, 80)
            ?: $this->extractLabelledId($rawText, ['page id', 'page_id']);
        $formId = $this->trimNullable($row['form_id'] ?? null, 80)
            ?: $this->extractLabelledId($rawText, ['form id', 'form_id']);
        $phone = $this->normalizePhone($row['phone'] ?? $this->extractPhone($rawText));
        $email = $this->normalizeEmail($row['email'] ?? $this->extractEmail($rawText));
        $name = $this->trimNullable($row['name'] ?? $row['lead_name'] ?? null);

        if (!$name && $rawText) {
            $name = collect(preg_split('/\r\n|\r|\n/', $rawText))
                ->map(fn ($line) => trim($line))
                ->first(fn ($line) => $this->isLikelyNameLine($line));
            $name = $this->trimNullable($name);
        }

        $hashBase = implode('|', array_filter([
            $leadgenId,
            $phone,
            $email,
            $name,
            $rawText,
            (string) $index,
        ]));

        return [
            'row_hash' => hash('sha256', $hashBase !== '' ? $hashBase : (string) $index),
            'leadgen_id' => $leadgenId,
            'page_id' => $pageId,
            'form_id' => $formId,
            'lead_name' => $name,
            'phone' => $phone ?: null,
            'email' => $email,
            'submitted_at_text' => $this->trimNullable($row['submitted_at'] ?? $row['created_time'] ?? null),
            'raw_text' => $rawText,
            'raw_payload' => $row,
        ];
    }

    private function matchRow(array $row): array
    {
        if ($row['leadgen_id']) {
            $fbLead = FbLead::query()->with('crmLead')->where('leadgen_id', $row['leadgen_id'])->latest('id')->first();
            if ($fbLead?->crm_lead_id) {
                return $this->match('in_crm', 'fb_lead', 'Matched by fb_leads.leadgen_id.', [
                    'fb_lead_id' => $fbLead->id,
                    'crm_lead_id' => $fbLead->crm_lead_id,
                ]);
            }

            $oauthEvent = MetaOauthEvent::query()->where('leadgen_id', $row['leadgen_id'])->latest('id')->first();
            if ($oauthEvent?->crm_lead_id) {
                return $this->match('in_crm', 'meta_oauth_event', 'Matched by OAuth event leadgen_id.', [
                    'meta_oauth_event_id' => $oauthEvent->id,
                    'crm_lead_id' => $oauthEvent->crm_lead_id,
                ]);
            }

            $webhook = FbWebhookEvent::query()->where('leadgen_id', $row['leadgen_id'])->latest('id')->first();
            if ($webhook) {
                return $this->match('webhook_received', 'fb_webhook_event', 'Webhook exists but CRM lead was not linked.', [
                    'fb_webhook_event_id' => $webhook->id,
                ]);
            }

            if ($oauthEvent) {
                return $this->match('webhook_received', 'meta_oauth_event', 'OAuth webhook exists but CRM lead was not linked.', [
                    'meta_oauth_event_id' => $oauthEvent->id,
                ]);
            }
        }

        $lead = $this->findLeadByPhoneOrEmail($row['phone'], $row['email']);
        if ($lead) {
            return $this->match('possible_duplicate', 'lead', 'Matched by phone/email but no leadgen ID matched.', [
                'crm_lead_id' => $lead->id,
            ]);
        }

        if (!$row['leadgen_id'] && !$row['phone'] && !$row['email']) {
            $exactNameLead = $this->findLeadByExactName($row['lead_name']);
            if ($exactNameLead) {
                return $this->match('in_crm', 'lead_name_exact', 'Matched by exact CRM lead name. Open detail only if you want phone/email confirmation.', [
                    'crm_lead_id' => $exactNameLead->id,
                ]);
            }

            $nameTimeLead = $this->findLeadByNameAndSubmittedText($row['lead_name'], $row['submitted_at_text']);
            if ($nameTimeLead) {
                return $this->match('in_crm', 'lead_name_time', 'Matched by CRM lead name and time within 5 minutes.', [
                    'crm_lead_id' => $nameTimeLead->id,
                ]);
            }

            $nameLead = $this->findLeadBySimilarName($row['lead_name']);
            if ($nameLead) {
                return $this->match('name_only_review', 'lead_name', 'Possible similar CRM name found, but phone/email/lead ID did not match. Treat as suspicious until details are read.');
            }

            if ($row['lead_name']) {
                return $this->match('missing', null, 'No exact CRM name match found. Phone/email hidden; open detail to confirm before import.');
            }

            return $this->match('not_enough_data', null, 'Leadgen ID, phone, and email were not readable.');
        }

        return $this->match('missing', null, 'No matching CRM lead, webhook event, or OAuth event found.');
    }

    private function findLeadByPhoneOrEmail(?string $phone, ?string $email): ?Lead
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedEmail = $this->normalizeEmail($email);

        if ($normalizedEmail) {
            $lead = Lead::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->latest('id')->first();
            if ($lead) {
                return $lead;
            }
        }

        if ($normalizedPhone === '') {
            return null;
        }

        return Lead::query()
            ->where('phone', 'like', '%' . $normalizedPhone . '%')
            ->latest('id')
            ->limit(25)
            ->get()
            ->first(fn (Lead $lead) => $this->normalizePhone($lead->phone) === $normalizedPhone);
    }

    private function findExistingCapturedLead(?string $phone, ?string $email, ?string $name): ?Lead
    {
        $lead = $this->findLeadByPhoneOrEmail($phone, $email);
        if ($lead) {
            return $lead;
        }

        return $this->findLeadByExactName($name);
    }

    private function normalizeCapturedLeadPayload(array $lead): array
    {
        return [
            'client_key' => $this->trimNullable($lead['client_key'] ?? null, 255),
            'name' => $this->trimNullable($lead['name'] ?? null),
            'phone' => $this->normalizePhone($lead['phone'] ?? null),
            'email' => $this->normalizeEmail($lead['email'] ?? null),
            'facebook_lead_id' => $this->trimNullable($lead['facebook_lead_id'] ?? null, 120),
            'page_name' => $this->trimNullable($lead['page_name'] ?? null),
            'form_name' => $this->trimNullable($lead['form_name'] ?? null),
            'lead_time' => $this->trimNullable($lead['lead_time'] ?? null),
            'source' => $this->trimNullable($lead['source'] ?? 'facebook_lead_center'),
            'stage' => $this->trimNullable($lead['stage'] ?? null),
            'assigned_to' => $this->trimNullable($lead['assigned_to'] ?? null),
            'raw_text' => $this->trimNullable($lead['raw_text'] ?? null, 3000),
        ];
    }

    private function capturedLeadKey(array $lead): string
    {
        if (!empty($lead['facebook_lead_id'])) {
            return 'leadgen:' . $lead['facebook_lead_id'];
        }

        if (!empty($lead['phone']) && !empty($lead['email'])) {
            return 'phone-email:' . $lead['phone'] . ':' . $lead['email'];
        }

        if (!empty($lead['phone'])) {
            return 'phone:' . $lead['phone'];
        }

        if (!empty($lead['email'])) {
            return 'email:' . $lead['email'];
        }

        return 'name-time:' . $this->normalizeLeadName($lead['name'] ?? '') . ':' . strtolower((string) ($lead['lead_time'] ?? ''));
    }

    private function capturedLeadNotes(array $lead): string
    {
        return trim(implode("\n", array_filter([
            'Recovered from Facebook Lead Center extension.',
            !empty($lead['facebook_lead_id']) ? 'Leadgen ID: ' . $lead['facebook_lead_id'] : null,
            !empty($lead['page_name']) ? 'Page: ' . $lead['page_name'] : null,
            !empty($lead['form_name']) ? 'Form: ' . $lead['form_name'] : null,
            !empty($lead['lead_time']) ? 'Lead time: ' . $lead['lead_time'] : null,
            !empty($lead['stage']) ? 'Meta stage: ' . $lead['stage'] : null,
            !empty($lead['assigned_to']) ? 'Assigned in Meta: ' . $lead['assigned_to'] : null,
            !empty($lead['raw_text']) ? 'Raw detail: ' . Str::limit($lead['raw_text'], 1200) : null,
        ])));
    }

    private function canonicalRowKey(array $row): string
    {
        if ($row['leadgen_id']) {
            return 'leadgen:' . $row['leadgen_id'];
        }

        if ($row['phone'] && $row['email']) {
            return 'phone-email:' . $row['phone'] . ':' . $row['email'];
        }

        if ($row['phone']) {
            return 'phone:' . $row['phone'];
        }

        if ($row['email']) {
            return 'email:' . $row['email'];
        }

        $clientKey = $this->trimNullable(data_get($row, 'raw_payload.client_key'), 255);
        if ($clientKey) {
            return 'client:' . $clientKey;
        }

        $name = $this->normalizeLeadName($row['lead_name']);
        if ($name !== '') {
            return 'name-time:' . $name . ':' . strtolower((string) $row['submitted_at_text']);
        }

        return 'raw:' . $row['row_hash'];
    }

    private function mergeNormalizedRows(array $existing, array $next): array
    {
        $merged = $existing;
        foreach (['leadgen_id', 'page_id', 'form_id', 'lead_name', 'phone', 'email', 'submitted_at_text'] as $field) {
            if (empty($merged[$field]) && !empty($next[$field])) {
                $merged[$field] = $next[$field];
            }
        }

        $merged['raw_text'] = collect([$existing['raw_text'] ?? null, $next['raw_text'] ?? null])
            ->filter()
            ->unique()
            ->implode("\n\n--- duplicate source ---\n");
        $merged['raw_payload'] = [
            'primary' => $existing['raw_payload'] ?? null,
            'duplicate' => $next['raw_payload'] ?? null,
        ];
        $merged['row_hash'] = hash('sha256', $this->canonicalRowKey($merged) . '|' . ($merged['raw_text'] ?? ''));

        return $merged;
    }

    private function findLeadBySimilarName(?string $name): ?Lead
    {
        $name = $this->normalizeLeadName($name);
        if (mb_strlen($name) < 4) {
            return null;
        }

        $searchToken = Str::of($name)->explode(' ')
            ->map(fn ($token) => trim($token))
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->sortByDesc(fn ($token) => mb_strlen($token))
            ->first();

        if (!$searchToken) {
            return null;
        }

        return Lead::query()
            ->where('name', 'like', '%' . $searchToken . '%')
            ->latest('id')
            ->limit(25)
            ->get()
            ->first(function (Lead $lead) use ($name) {
                $leadName = $this->normalizeLeadName($lead->name);
                similar_text($name, $leadName, $percent);

                return $percent >= 78 || str_contains($leadName, $name) || str_contains($name, $leadName);
            });
    }

    private function findLeadByExactName(?string $name): ?Lead
    {
        $name = $this->normalizeLeadName($name);
        if (mb_strlen($name) < 4) {
            return null;
        }

        $searchToken = Str::of($name)->explode(' ')
            ->map(fn ($token) => trim($token))
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->sortByDesc(fn ($token) => mb_strlen($token))
            ->first();

        if (!$searchToken) {
            return null;
        }

        return Lead::query()
            ->where('name', 'like', '%' . $searchToken . '%')
            ->latest('id')
            ->limit(50)
            ->get()
            ->first(fn (Lead $lead) => $this->normalizeLeadName($lead->name) === $name);
    }

    private function findLeadByNameAndSubmittedText(?string $name, ?string $submittedText): ?Lead
    {
        $name = $this->normalizeLeadName($name);
        if (mb_strlen($name) < 4 || !$submittedText) {
            return null;
        }

        $time = $this->extractTimeFromSubmittedText($submittedText);
        if (!$time) {
            return null;
        }

        $searchToken = Str::of($name)->explode(' ')
            ->map(fn ($token) => trim($token))
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->sortByDesc(fn ($token) => mb_strlen($token))
            ->first();

        if (!$searchToken) {
            return null;
        }

        return Lead::query()
            ->where('name', 'like', '%' . $searchToken . '%')
            ->whereTime('created_at', '>=', $time['from'])
            ->whereTime('created_at', '<=', $time['to'])
            ->latest('id')
            ->limit(25)
            ->get()
            ->first(function (Lead $lead) use ($name) {
                $leadName = $this->normalizeLeadName($lead->name);
                similar_text($name, $leadName, $percent);

                return $percent >= 88 || $leadName === $name;
            });
    }

    private function extractTimeFromSubmittedText(?string $text): ?array
    {
        if (!$text || !preg_match('/\b(\d{1,2}):(\d{2})\b/', $text, $matches)) {
            return null;
        }

        $hour = max(0, min(23, (int) $matches[1]));
        $minute = max(0, min(59, (int) $matches[2]));
        $from = now()->setTime($hour, $minute)->subMinutes(5);
        $to = now()->setTime($hour, $minute)->addMinutes(5);

        return [
            'from' => $from->format('H:i:s'),
            'to' => $to->format('H:i:s'),
        ];
    }

    private function isLikelyNameLine(?string $line): bool
    {
        $line = trim((string) $line);

        return (bool) preg_match("/^[A-Za-z][A-Za-z .'-]{2,80}$/", $line)
            && !preg_match('/lead|form|page|phone|email|created|submitted|intake|paid|unassigned|address|status|stage|source|audiences|campaign|owner|labels|channel/i', $line)
            && !preg_match('/^(name|full name|city|budget|possession|location preferred)$/i', $line)
            && !preg_match('/^\d{1,2}:\d{2}/', $line)
            && !preg_match('/^[A-Z]{1,3}$/', $line);
    }

    private function normalizeLeadName(mixed $name): string
    {
        $name = strtolower(trim((string) $name));
        $name = preg_replace('/\b(dr|mr|mrs|ms|miss|shri|smt)\.?\b/i', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z\s]/', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    private function summaryFromRows(Collection $rows): array
    {
        return [
            'total_rows' => $rows->count(),
            'matched_rows' => $rows->where('status', 'in_crm')->count(),
            'webhook_rows' => $rows->where('status', 'webhook_received')->count(),
            'missing_rows' => $rows->where('status', 'missing')->count(),
            'possible_duplicate_rows' => $rows->whereIn('status', ['possible_duplicate', 'name_only_review'])->count(),
            'unreadable_rows' => $rows->where('status', 'not_enough_data')->count(),
        ];
    }

    private function rowPayload(FacebookLeadCenterAuditRow $row): array
    {
        return [
            'id' => $row->id,
            'leadgen_id' => $row->leadgen_id,
            'page_id' => $row->page_id,
            'form_id' => $row->form_id,
            'lead_name' => $row->lead_name,
            'phone' => $row->phone,
            'email' => $row->email,
            'submitted_at_text' => $row->submitted_at_text,
            'status' => $row->status,
            'match_source' => $row->match_source,
            'match_reason' => $row->match_reason,
            'lead_id' => $row->crm_lead_id,
            'lead_url' => $row->crm_lead_id ? route('leads.show', $row->crm_lead_id) : null,
            'importable' => in_array($row->status, ['missing', 'not_enough_data'], true) && ((bool) $row->phone || (bool) $row->email),
            'confidence' => $this->confidenceForRow($row),
            'issue_group' => $this->issueGroupForRow($row),
            'client_key' => data_get($row->raw_payload, 'client_key') ?: data_get($row->raw_payload, 'primary.client_key'),
            'raw_text' => $row->raw_text,
            'created_at' => $row->created_at,
        ];
    }

    private function missingReportGroups(): Collection
    {
        return FacebookLeadCenterAuditRow::query()
            ->whereIn('status', ['missing', 'name_only_review', 'not_enough_data', 'webhook_received'])
            ->latest('id')
            ->limit(300)
            ->get()
            ->groupBy(fn (FacebookLeadCenterAuditRow $row) => implode('|', [
                $row->page_id ?: 'unknown_page',
                $row->form_id ?: 'unknown_form',
                $this->issueGroupForRow($row),
            ]))
            ->map(function (Collection $rows) {
                /** @var FacebookLeadCenterAuditRow $first */
                $first = $rows->first();

                return [
                    'page_id' => $first->page_id ?: 'Unknown page',
                    'form_id' => $first->form_id ?: 'Unknown form',
                    'issue' => $this->issueGroupForRow($first),
                    'count' => $rows->count(),
                    'latest_at' => $first->created_at,
                    'sample' => $first->lead_name ?: ($first->phone ?: ($first->email ?: 'No detail lead')),
                ];
            })
            ->sortByDesc('count')
            ->take(8)
            ->values();
    }

    private function latestScanQuality(?FacebookLeadCenterAudit $audit): array
    {
        $meta = $audit?->browser_meta ?: [];
        $expected = (int) ($meta['expected_rows'] ?? 0);
        $scanned = (int) ($meta['scanned_rows'] ?? ($audit?->total_rows ?? 0));

        return [
            'scan_mode' => $meta['scan_mode'] ?? null,
            'expected_rows' => $expected,
            'scanned_rows' => $scanned,
            'scan_complete' => $expected <= 0 || $scanned >= min($expected, 50),
        ];
    }

    private function confidenceForRow(FacebookLeadCenterAuditRow $row): string
    {
        if (in_array($row->status, ['in_crm', 'imported'], true)) {
            return 'high';
        }

        if ($row->leadgen_id || ($row->phone && $row->email)) {
            return 'high';
        }

        if ($row->phone || $row->email) {
            return 'medium';
        }

        if ($row->lead_name) {
            return 'low';
        }

        return 'none';
    }

    private function issueGroupForRow(FacebookLeadCenterAuditRow $row): string
    {
        return match ($row->status) {
            'missing' => $row->phone || $row->email ? 'No CRM match' : 'Missing contact detail',
            'webhook_received' => 'Webhook received but CRM lead not linked',
            'name_only_review' => 'Name only / suspicious',
            'not_enough_data' => 'No readable details',
            default => 'Other',
        };
    }

    private function match(string $status, ?string $source, string $reason, array $ids = []): array
    {
        return array_merge([
            'status' => $status,
            'match_source' => $source,
            'match_reason' => $reason,
            'crm_lead_id' => null,
            'fb_lead_id' => null,
            'fb_webhook_event_id' => null,
            'meta_oauth_event_id' => null,
        ], $ids);
    }

    private function normalizePhone(mixed $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $digits = ltrim($digits, '0');
        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        if (strlen($digits) !== 10 || !preg_match('/^[6-9]\d{9}$/', $digits)) {
            return '';
        }

        if (preg_match('/^(\d)\1{9}$/', $digits)) {
            return '';
        }

        return $digits;
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function extractEmail(?string $text): ?string
    {
        if (!$text || !preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches)) {
            return null;
        }

        return $matches[0];
    }

    private function extractPhone(?string $text): ?string
    {
        if (!$text || !preg_match('/(?:\+?91[\s-]?)?[6-9]\d[\d\s-]{8,14}/', $text, $matches)) {
            return null;
        }

        return $matches[0];
    }

    private function extractLabelledId(?string $text, array $labels): ?string
    {
        if (!$text) {
            return null;
        }

        foreach ($labels as $label) {
            $pattern = '/\b' . preg_quote($label, '/') . '\b\s*[:#-]?\s*([0-9]{8,})/i';
            if (preg_match($pattern, $text, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function trimNullable(mixed $value, int $limit = 255): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return Str::limit($text, $limit, '');
    }
}
