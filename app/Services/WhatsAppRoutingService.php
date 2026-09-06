<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\MetaWabaAccount;
use App\Models\WhatsAppRoutingRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WhatsAppRoutingService
{
    public function resolve(?Lead $lead = null, array $context = [], ?MetaWabaAccount $explicitAccount = null): ?MetaWabaAccount
    {
        if ($explicitAccount?->is_active) {
            return $explicitAccount;
        }

        if (!$lead || !Schema::hasTable('whatsapp_routing_rules')) {
            return MetaWabaAccount::defaultAccount();
        }

        $lead->loadMissing('activeAssignments.assignedTo.role');

        $rule = WhatsAppRoutingRule::query()
            ->with('metaWabaAccount')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->first(fn (WhatsAppRoutingRule $rule) => $rule->metaWabaAccount?->is_active && $this->matches($rule, $lead, $context));

        return $rule?->metaWabaAccount ?: MetaWabaAccount::defaultAccount();
    }

    public function matchingRule(?Lead $lead = null, array $context = []): ?WhatsAppRoutingRule
    {
        if (!$lead || !Schema::hasTable('whatsapp_routing_rules')) {
            return null;
        }

        $lead->loadMissing('activeAssignments.assignedTo.role');

        return WhatsAppRoutingRule::query()
            ->with('metaWabaAccount')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->first(fn (WhatsAppRoutingRule $rule) => $this->matches($rule, $lead, $context));
    }

    private function matches(WhatsAppRoutingRule $rule, Lead $lead, array $context): bool
    {
        $conditions = $rule->conditions ?? [];

        foreach (['source', 'city', 'status'] as $field) {
            $values = collect($conditions[$field . 's'] ?? [])
                ->map(fn ($value) => Str::lower(trim((string) $value)))
                ->filter()
                ->all();
            if ($values && !in_array(Str::lower(trim((string) $lead->{$field})), $values, true)) {
                return false;
            }
        }

        $assignedUserId = (int) ($context['assigned_user_id'] ?? optional($lead->activeAssignments->first())->assigned_to);
        $assignedUser = optional($lead->activeAssignments->first())->assignedTo;

        $userIds = array_map('intval', (array) ($conditions['assigned_user_ids'] ?? []));
        if ($userIds && !in_array($assignedUserId, $userIds, true)) {
            return false;
        }

        $roleSlugs = array_filter((array) ($conditions['assigned_role_slugs'] ?? []));
        if ($roleSlugs && !in_array((string) $assignedUser?->role?->slug, $roleSlugs, true)) {
            return false;
        }

        $projectValues = collect($conditions['projects'] ?? [])
            ->map(fn ($value) => Str::lower(trim((string) $value)))
            ->filter()
            ->all();
        $projectName = Str::lower((string) ($context['project'] ?? $lead->preferred_projects));
        if ($projectValues && !collect($projectValues)->contains(fn ($project) => Str::contains($projectName, $project))) {
            return false;
        }

        $campaignTypes = array_filter((array) ($conditions['campaign_types'] ?? []));
        if ($campaignTypes && !in_array((string) Arr::get($context, 'campaign_type'), $campaignTypes, true)) {
            return false;
        }

        return true;
    }
}
