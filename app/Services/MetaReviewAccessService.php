<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SystemSettings;
use App\Models\User;

class MetaReviewAccessService
{
    public function getOwner(): string
    {
        $owner = strtolower(trim((string) SystemSettings::get('meta_review_owner', 'crm')));

        return in_array($owner, ['crm', 'sales_team'], true) ? $owner : 'crm';
    }

    public function isMetaLead(Lead $lead): bool
    {
        return Lead::normalizeSource($lead->source) === 'meta';
    }

    public function resolveMetaLinkage(Lead $lead): array
    {
        $lead->loadMissing('latestFbLead');

        $fbLead = $lead->latestFbLead;
        $leadgenId = $fbLead?->leadgen_id ? trim((string) $fbLead->leadgen_id) : null;
        $hasFbLead = $fbLead !== null;

        return [
            'is_meta_source' => $this->isMetaLead($lead),
            'has_fb_lead' => $hasFbLead,
            'leadgen_id' => $leadgenId,
            'is_linked' => $this->isMetaLead($lead) && ($hasFbLead || !empty($leadgenId)),
        ];
    }

    public function canEdit(User $user, Lead $lead): bool
    {
        if ($user->isAdmin() || $user->isCrm()) {
            return true;
        }

        if ($this->getOwner() !== 'sales_team') {
            return false;
        }

        $isAllowedSalesRole = $user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager();

        return $isAllowedSalesRole && $lead->isAssignedToUser($user->id);
    }
}
