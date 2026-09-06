<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppConversation;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppConversationScopeService
{
    public function canViewAll(User $user): bool
    {
        return $user->isAdmin() || $user->isCrm();
    }

    public function accessibleUserIds(User $user): array
    {
        if ($this->canViewAll($user)) {
            return [];
        }

        if ($user->isAssistantSalesManager()) {
            return [$user->id];
        }

        if ($user->isSalesManager() || $user->isSeniorManager()) {
            return collect([$user->id])
                ->merge($user->getAllTeamMemberIds())
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [$user->id];
    }

    public function conversationsFor(User $user): Builder
    {
        $query = WhatsAppConversation::query();

        if ($this->canViewAll($user)) {
            return $query;
        }

        $accessibleUserIds = $this->accessibleUserIds($user);

        return $query->where(function (Builder $conversationQuery) use ($accessibleUserIds) {
            $conversationQuery->whereIn('user_id', $accessibleUserIds)
                ->orWhereIn('assigned_to', $accessibleUserIds)
                ->orWhereHas('lead.activeAssignments', function (Builder $assignmentQuery) use ($accessibleUserIds) {
                    $assignmentQuery->where('is_active', true)
                        ->whereIn('assigned_to', $accessibleUserIds);
                });
        });
    }

    public function resolveConversation(User $user, int|string $conversationId): ?WhatsAppConversation
    {
        return $this->conversationsFor($user)
            ->where('id', $conversationId)
            ->first();
    }

    public function visibleLeadsFor(User $user): Builder
    {
        $query = Lead::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if ($this->canViewAll($user)) {
            return $query;
        }

        return $query->whereAssignedToUsers($this->accessibleUserIds($user));
    }
}
