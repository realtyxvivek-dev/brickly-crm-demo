<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\MailDeliveryLog;
use App\Models\SiteVisit;
use App\Models\SystemSettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HighBudgetLeadAlertService
{
    private const THRESHOLD_RUPEES = 20000000;
    private const RECIPIENT_SETTING = 'high_budget_alert_recipient_user_ids';

    public function checkLead(Lead $lead): void
    {
        $lead->loadMissing('formFieldValues');

        $budget = $this->resolveLeadBudget($lead);
        if (!$this->isAboveTwoCrore($budget, $lead->budget_min, $lead->budget_max)) {
            return;
        }

        $this->dispatchAfterCommit($lead, $budget ?: 'Above 2 Cr', 'lead');
    }

    public function checkSiteVisit(SiteVisit $siteVisit): void
    {
        $budget = (string) ($siteVisit->budget_range ?? '');
        if (!$this->isAboveTwoCrore($budget)) {
            return;
        }

        $siteVisit->loadMissing('lead');
        $this->dispatchAfterCommit($siteVisit, $budget, 'site_visit');
    }

    public function sendLatestTestAlert(): Collection
    {
        $candidate = $this->latestHighBudgetCandidate();

        if (!$candidate) {
            return collect();
        }

        return $this->sendAlerts($candidate['related'], $candidate['budget'], $candidate['source'], true);
    }

    private function dispatchAfterCommit(Model $related, string $budget, string $source): void
    {
        try {
            DB::afterCommit(function () use ($related, $budget, $source) {
                $fresh = $related->fresh();
                if (!$fresh) {
                    return;
                }

                $this->sendAlerts($fresh, $budget, $source);
            });
        } catch (\Throwable) {
            $this->sendAlerts($related, $budget, $source);
        }
    }

    private function sendAlerts(Model $related, string $budget, string $source, bool $force = false): Collection
    {
        if (!Schema::hasTable('mail_delivery_logs') || !Schema::hasTable('users')) {
            return collect();
        }

        if (!$force && $this->alreadySent($related)) {
            return collect();
        }

        $lead = $related instanceof SiteVisit ? $related->lead : $related;
        if (!$lead instanceof Lead) {
            return collect();
        }

        $lead->loadMissing('activeAssignments.assignedTo.role');

        $owners = $this->assignedUsersFor($lead);
        $leadUrl = route('leads.show', $lead);
        $subject = ($force ? 'TEST - ' : '') . 'High Budget Lead Alert: ' . ($lead->name ?: 'Customer') . ' - ' . $budget;
        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            Log::warning('High budget lead alert skipped: no recipients configured.', [
                'related_type' => $related::class,
                'related_id' => $related->getKey(),
                'lead_id' => $lead->id,
            ]);
            return collect();
        }

        return $recipients->map(function (User $recipient) use ($subject, $lead, $related, $budget, $source, $owners, $leadUrl, $force) {
            return app(MailDeliveryLogger::class)->sendView(
                MailDeliveryLog::TYPE_HIGH_BUDGET_LEAD_ALERT,
                $subject,
                $recipient,
                'emails.high-budget-lead-alert',
                [
                    'lead' => $lead,
                    'related' => $related,
                    'budget' => $budget,
                    'source' => $source,
                    'owners' => $owners,
                    'leadUrl' => $leadUrl,
                    'isTest' => $force,
                ],
                [
                    'lead_id' => $lead->id,
                    'budget' => $budget,
                    'source' => $source,
                    'assigned_user_names' => $owners->pluck('name')->filter()->values()->all(),
                    'assigned_user_emails' => $owners->pluck('email')->filter()->values()->all(),
                    'lead_url' => $leadUrl,
                    'test_send' => $force,
                ],
                $related
            );
        });
    }

    private function alreadySent(Model $related): bool
    {
        return MailDeliveryLog::query()
            ->where('mail_type', MailDeliveryLog::TYPE_HIGH_BUDGET_LEAD_ALERT)
            ->where('related_type', $related::class)
            ->where('related_id', $related->getKey())
            ->whereIn('status', [MailDeliveryLog::STATUS_QUEUED, MailDeliveryLog::STATUS_SENT])
            ->exists();
    }

    private function recipients(): Collection
    {
        $ids = collect($this->decodeRecipientIds(SystemSettings::get(self::RECIPIENT_SETTING)))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $query = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($ids->isNotEmpty()) {
            return $query->whereIn('id', $ids)->get(['id', 'name', 'email']);
        }

        return $query->whereHas('role', fn ($role) => $role->where('slug', 'admin'))
            ->get(['id', 'name', 'email']);
    }

    private function decodeRecipientIds(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function assignedUsersFor(Lead $lead): Collection
    {
        return $lead->activeAssignments
            ->map(function (LeadAssignment $assignment) {
                $user = $assignment->assignedTo;

                if (!$user) {
                    return null;
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name ?: 'User #' . $user->id,
                    'email' => $user->email,
                    'role' => $user->role?->name ?: str((string) $user->role?->slug)->replace('_', ' ')->title()->toString(),
                    'assigned_at' => optional($assignment->assigned_at)->format('d M Y h:i A'),
                ];
            })
            ->filter()
            ->values();
    }

    private function latestHighBudgetCandidate(): ?array
    {
        $lead = Lead::query()
            ->with(['formFieldValues', 'activeAssignments.assignedTo.role'])
            ->latest()
            ->limit(1000)
            ->get()
            ->first(function (Lead $lead) {
                $budget = $this->resolveLeadBudget($lead);

                return $this->isAboveTwoCrore($budget, $lead->budget_min, $lead->budget_max);
            });

        $visit = SiteVisit::query()
            ->with(['lead.formFieldValues', 'lead.activeAssignments.assignedTo.role'])
            ->latest()
            ->limit(1000)
            ->get()
            ->first(fn (SiteVisit $siteVisit) => $this->isAboveTwoCrore((string) ($siteVisit->budget_range ?? '')));

        $leadTime = $lead?->created_at;
        $visitTime = $visit?->created_at;

        if ($visit && (!$lead || ($visitTime && $leadTime && $visitTime->greaterThan($leadTime)))) {
            return [
                'related' => $visit,
                'budget' => (string) ($visit->budget_range ?: 'Above 2 Cr'),
                'source' => 'site_visit',
            ];
        }

        if ($lead) {
            return [
                'related' => $lead,
                'budget' => $this->resolveLeadBudget($lead) ?: 'Above 2 Cr',
                'source' => 'lead',
            ];
        }

        return null;
    }

    private function resolveLeadBudget(Lead $lead): string
    {
        $direct = trim((string) ($lead->budget ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        if ($lead->relationLoaded('formFieldValues')) {
            $field = $lead->formFieldValues->firstWhere('field_key', 'budget');
            if ($field && trim((string) $field->field_value) !== '') {
                return trim((string) $field->field_value);
            }
        }

        return '';
    }

    private function isAboveTwoCrore(?string $budget, mixed $min = null, mixed $max = null): bool
    {
        if ($this->numericAmount($max) >= self::THRESHOLD_RUPEES || $this->numericAmount($min) >= self::THRESHOLD_RUPEES) {
            return true;
        }

        $normalized = strtolower(trim((string) $budget));
        if ($normalized === '') {
            return false;
        }

        $normalized = str_replace(['–', '—', 'â€“', 'Ã¢â‚¬â€œ'], '-', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        if (preg_match('/above\s*([0-9]+(?:\.[0-9]+)?)\s*(cr|crore)/', $normalized, $matches)) {
            return (float) $matches[1] >= 2.0;
        }

        if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(cr|crore)\s*-\s*([0-9]+(?:\.[0-9]+)?)\s*(cr|crore)/', $normalized, $matches)) {
            return (float) $matches[1] >= 2.0 || (float) $matches[3] > 2.0;
        }

        if (str_contains($normalized, 'above 2 cr') || str_contains($normalized, 'above 2 crore') || str_contains($normalized, '2 cr - 3 cr') || str_contains($normalized, '2 crore - 3 crore') || str_contains($normalized, 'above 3 cr')) {
            return true;
        }

        $amounts = $this->textAmounts($budget);
        if ($amounts->count() >= 2) {
            return (float) $amounts->first() >= self::THRESHOLD_RUPEES
                || (float) $amounts->last() > self::THRESHOLD_RUPEES;
        }

        if ($amounts->count() === 1) {
            return (float) $amounts->first() >= self::THRESHOLD_RUPEES;
        }

        return $this->numericAmount($budget) >= self::THRESHOLD_RUPEES;
    }

    private function numericAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = strtolower((string) $value);
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(cr|crore|lac|lakh)/', $text, $matches)) {
            $amount = (float) $matches[1];
            $unit = $matches[2];

            if ($unit === 'cr' || $unit === 'crore') {
                return $amount * 10000000;
            }

            return $amount * 100000;
        }

        $clean = preg_replace('/[^0-9.]/', '', $text);

        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    private function textAmounts(mixed $value): Collection
    {
        $text = strtolower((string) $value);
        if ($text === '') {
            return collect();
        }

        preg_match_all('/([0-9]+(?:\.[0-9]+)?)\s*(cr|crore|lac|lakh)/', $text, $matches, PREG_SET_ORDER);

        return collect($matches)->map(function (array $match) {
            $amount = (float) $match[1];
            $unit = $match[2];

            return ($unit === 'cr' || $unit === 'crore')
                ? $amount * 10000000
                : $amount * 100000;
        });
    }
}
