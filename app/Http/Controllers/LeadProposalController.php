<?php

namespace App\Http\Controllers;

use App\Helpers\ContactHelper;
use App\Models\Lead;
use App\Models\LeadProposal;
use App\Models\Project;
use App\Models\ProjectShareLink;
use App\Models\Role;
use App\Models\User;
use App\Services\VisitorInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LeadProposalController extends Controller
{
    public function store(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->canAccessLead($user, $lead), 403);
        abort_unless($this->canManageProposal($user), 403);

        $validated = $request->validate([
            'project_ids' => ['required', 'array', 'min:1', 'max:8'],
            'project_ids.*' => ['integer', 'distinct', 'exists:projects,id'],
            'expiry_mode' => ['nullable', 'in:none,24h,3d,4d,7d,15d,custom'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'lead_capture_mode' => ['nullable', 'in:off,soft_prompt'],
            'message' => ['nullable', 'string', 'max:1200'],
        ]);

        $projectIds = collect($validated['project_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $projects = Project::query()
            ->whereIn('id', $projectIds)
            ->where('is_active', true)
            ->whereHas('publicPage', fn ($query) => $query->where('status', 'published'))
            ->get()
            ->keyBy('id');

        if ($projects->count() !== $projectIds->count()) {
            return response()->json([
                'message' => 'Only active projects with published public pages can be shared.',
            ], 422);
        }

        $expiresAt = $this->resolveExpiry($validated['expiry_mode'] ?? '7d', $validated['expires_at'] ?? null);
        $leadCaptureMode = $validated['lead_capture_mode'] ?? 'off';
        $message = trim((string) ($validated['message'] ?? ''));

        if ($projectIds->count() === 1) {
            /** @var \App\Models\Project $project */
            $project = $projects->get($projectIds->first());

            $shareLink = ProjectShareLink::query()->create([
                'project_id' => $project->id,
                'advisor_id' => $user->id,
                'lead_id' => $lead->id,
                'token' => $this->generateProjectShareToken(),
                'status' => 'active',
                'expires_at' => $expiresAt,
                'notes' => 'Lead detail tracked public share link',
            ]);

            $url = route('projects.public-share.show', $shareLink->token);
            $proposalMessage = $message
                ? $this->replaceProposalPlaceholder($message, $url)
                : $this->defaultMessage($lead, [$project->name], $url);

            $phone = filled($lead->phone) ? ContactHelper::formatPhoneForWhatsApp((string) $lead->phone) : '';

            return response()->json([
                'message' => 'Tracked public project link generated.',
                'proposal_id' => null,
                'proposal_url' => $url,
                'link_type' => 'project_share',
                'whatsapp_url' => $phone !== '' ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($proposalMessage) : null,
                'proposal_message' => $proposalMessage,
                'expires_at' => optional($shareLink->expires_at)->toIso8601String(),
            ]);
        }

        $proposal = DB::transaction(function () use ($lead, $user, $projectIds, $expiresAt, $leadCaptureMode, $message) {
            $proposal = LeadProposal::query()->create([
                'lead_id' => $lead->id,
                'created_by' => $user->id,
                'token' => $this->generateToken(),
                'status' => 'active',
                'lead_capture_mode' => $leadCaptureMode,
                'expires_at' => $expiresAt,
                'message' => $message ?: null,
            ]);

            $proposal->projects()->sync(
                $projectIds->mapWithKeys(fn ($projectId, $index) => [
                    $projectId => ['display_order' => $index + 1],
                ])->all()
            );

            return $proposal->fresh(['projects']);
        });

        $url = route('lead-proposals.public.show', $proposal->token);
        $proposalMessage = $message
            ? $this->replaceProposalPlaceholder($message, $url)
            : $this->defaultMessage($lead, $proposal->projects->pluck('name')->all(), $url);
        if ($proposal->message !== $proposalMessage) {
            $proposal->forceFill(['message' => $proposalMessage])->save();
        }

        $phone = filled($lead->phone) ? ContactHelper::formatPhoneForWhatsApp((string) $lead->phone) : '';

        return response()->json([
            'message' => 'Proposal link generated.',
            'proposal_id' => $proposal->id,
            'proposal_url' => $url,
            'whatsapp_url' => $phone !== '' ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($proposalMessage) : null,
            'proposal_message' => $proposalMessage,
            'expires_at' => optional($proposal->expires_at)->toIso8601String(),
        ]);
    }

    public function showPublic(string $token): View
    {
        $proposal = $this->resolveProposal($token);

        if (!$proposal || !$proposal->isUsable()) {
            return view('lead-proposals.unavailable', [
                'message' => 'This proposal is no longer active.',
            ]);
        }

        $proposal->loadMissing([
            'lead',
            'creator',
            'projects.builder',
            'projects.publicPage',
            'projects.publicUnitTypes.sizeVariants',
            'projects.publicAssets',
            'projects.publicLandmarks',
        ]);

        return view('lead-proposals.show', [
            'proposal' => $proposal,
            'lead' => $proposal->lead,
            'advisor' => $proposal->creator,
            'projects' => $proposal->projects,
            'eventUrl' => route('lead-proposals.public.events', $proposal->token),
        ]);
    }

    public function analytics(Request $request, Lead $lead, LeadProposal $proposal): View
    {
        $user = $request->user();
        abort_unless($user && $this->canAccessLead($user, $lead), 403);
        abort_unless($this->canManageProposal($user), 403);
        abort_unless((int) $proposal->lead_id === (int) $lead->id, 404);

        $proposal->markExpiredIfNeeded();
        $proposal->loadMissing([
            'lead',
            'creator',
            'projects.publicPage',
            'projects.publicUnitTypes.sizeVariants',
            'events.project',
        ]);

        $singleProject = $proposal->projects->count() === 1 ? $proposal->projects->first() : null;
        $preferredShareLink = null;

        if ($singleProject) {
            $preferredShareLink = ProjectShareLink::query()
                ->where('lead_id', $lead->id)
                ->where('advisor_id', $proposal->created_by)
                ->where('project_id', $singleProject->id)
                ->where('status', 'active')
                ->latest()
                ->first();
        }

        $openUrl = $preferredShareLink
            ? route('projects.public-share.show', $preferredShareLink->token)
            : route('lead-proposals.public.show', $proposal->token);

        return view('lead-proposals.analytics', [
            'lead' => $lead,
            'proposal' => $proposal,
            'projects' => $proposal->projects,
            'summary' => $proposal->analyticsSummary(),
            'proposalUrl' => $openUrl,
            'proposalUrlType' => $preferredShareLink ? 'project_share' : 'proposal',
        ]);
    }

    public function recordEvent(Request $request, string $token): JsonResponse
    {
        $proposal = $this->resolveProposal($token);
        if (!$proposal || !$proposal->isUsable()) {
            return response()->json(['ok' => false], 410);
        }

        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:80'],
            'project_id' => ['nullable', 'integer'],
            'section' => ['nullable', 'string', 'max:120'],
            'session_id' => ['nullable', 'string', 'max:120'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'meta' => ['nullable', 'array'],
        ]);

        $projectId = isset($validated['project_id']) ? (int) $validated['project_id'] : null;
        if ($projectId && !$proposal->projects()->whereKey($projectId)->exists()) {
            return response()->json(['ok' => false, 'message' => 'Project not in proposal.'], 422);
        }

        $proposal->events()->create([
            'project_id' => $projectId,
            'event_name' => Str::slug((string) $validated['event_name'], '_'),
            'section' => $validated['section'] ?? null,
            'session_id' => $validated['session_id'] ?? null,
            'duration_ms' => (int) ($validated['duration_ms'] ?? 0),
            'meta' => app(VisitorInsightService::class)->enrich(
                $request,
                Str::slug((string) $validated['event_name'], '_'),
                $validated['meta'] ?? []
            ),
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip() . '|' . config('app.key')) : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'occurred_at' => now(),
        ]);

        if (($validated['event_name'] ?? '') === 'proposal_opened') {
            $proposal->forceFill([
                'view_count' => (int) $proposal->view_count + 1,
                'first_viewed_at' => $proposal->first_viewed_at ?: now(),
                'last_viewed_at' => now(),
            ])->save();
        }

        return response()->json(['ok' => true]);
    }

    private function resolveProposal(string $token): ?LeadProposal
    {
        $proposal = LeadProposal::query()
            ->with(['projects' => fn ($query) => $query->where('is_active', true)])
            ->where('token', $token)
            ->first();

        if (!$proposal) {
            return null;
        }

        $proposal->markExpiredIfNeeded();
        $proposal->refresh();

        return $proposal;
    }

    private function resolveExpiry(string $mode, ?string $custom): ?\Carbon\Carbon
    {
        return match ($mode) {
            'none' => null,
            '24h' => now()->addDay(),
            '3d' => now()->addDays(3),
            '4d' => now()->addDays(4),
            '15d' => now()->addDays(15),
            'custom' => $custom ? \Carbon\Carbon::parse($custom) : now()->addDays(7),
            default => now()->addDays(7),
        };
    }

    private function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (LeadProposal::query()->where('token', $token)->exists());

        return $token;
    }

    private function generateProjectShareToken(): string
    {
        do {
            $token = Str::random(40);
        } while (ProjectShareLink::query()->where('token', $token)->exists());

        return $token;
    }

    private function defaultMessage(Lead $lead, array $projectNames, string $url): string
    {
        $name = trim((string) $lead->name) ?: 'there';
        $projects = collect($projectNames)->take(4)->implode(', ');

        return "Hi {$name}, as discussed, these projects match your budget and requirement: {$projects}. Please open this private proposal link: {$url}";
    }

    private function replaceProposalPlaceholder(string $message, string $url): string
    {
        $message = str_replace(
            ['{{proposal_url}}', '{{ proposal_url }}', '[proposal_url]', url('/proposal/preview')],
            $url,
            $message
        );

        return preg_replace('#https?://[^\s]+/proposal/preview#i', $url, $message)
            ?: $message;
    }

    private function canAccessLead(User $user, Lead $lead): bool
    {
        if ($user->isAdmin() || $user->isCrm()) {
            return true;
        }

        if ($user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            return !empty($teamMemberIds)
                && ($lead->isAssignedToAnyUser($teamMemberIds) || $lead->isVisibleViaProspectFallback($teamMemberIds));
        }

        if ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            return $lead->isAssignedToUser($user->id)
                || ($teamMemberIds->isNotEmpty() && $lead->isAssignedToAnyUser($teamMemberIds))
                || ($teamMemberIds->isNotEmpty() && $lead->isVisibleViaProspectFallback($teamMemberIds));
        }

        if ($user->isSalesExecutive()) {
            return $lead->isAssignedToUser($user->id) || $lead->isVisibleViaProspectFallback([$user->id]);
        }

        if ($user->role?->slug === Role::FINANCE_MANAGER) {
            return $lead->siteVisits()
                ->where('status', 'completed')
                ->whereHas('incentives', fn ($query) => $query->where('type', 'closer')->where('status', 'verified'))
                ->exists();
        }

        return false;
    }

    private function canManageProposal(User $user): bool
    {
        return $user->isAdmin()
            || $user->isCrm()
            || $user->isSalesHead()
            || $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager()
            || $user->isSalesExecutive();
    }
}
