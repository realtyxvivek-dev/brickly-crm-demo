<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Services\LeadsPendingResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MobileSummaryController extends Controller
{
    private const CACHE_SECONDS = 300;

    public function __construct(
        protected LeadsPendingResponseService $pendingResponseService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

        $payload = Cache::remember(
            "mobile_summary:v2:{$user->id}:{$today->toDateString()}",
            now()->addSeconds(self::CACHE_SECONDS),
            fn () => $this->buildSummary($request)
        );

        return response()->json($payload + [
            'cache_ttl_seconds' => self::CACHE_SECONDS,
            'suggested_refresh_seconds' => self::CACHE_SECONDS,
            'generated_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'private, max-age=' . self::CACHE_SECONDS);
    }

    private function buildSummary(Request $request): array
    {
        $user = $request->user();
        $today = Carbon::today();
        $userIds = $this->visibleUserIds($user);

        return [
            'pending_leads' => $this->pendingResponseService->getCountForUser($user->id, 'today', $request),
            'today_meetings' => Meeting::query()
                ->whereDate('scheduled_at', $today)
                ->where(function ($query) use ($userIds, $user) {
                    $query->where('created_by', $user->id)
                        ->orWhere('assigned_to', $user->id);

                    if (!empty($userIds)) {
                        $query->orWhereIn('created_by', $userIds)
                            ->orWhereIn('assigned_to', $userIds);
                    }
                })
                ->count(),
            'today_visits' => SiteVisit::query()
                ->whereDate('scheduled_at', $today)
                ->where(function ($query) use ($userIds, $user) {
                    $query->where('created_by', $user->id)
                        ->orWhere('assigned_to', $user->id);

                    if (!empty($userIds)) {
                        $query->orWhereIn('created_by', $userIds)
                            ->orWhereIn('assigned_to', $userIds);
                    }
                })
                ->count(),
        ];
    }

    private function visibleUserIds($user): array
    {
        if (method_exists($user, 'isAdmin') && ($user->isAdmin() || $user->isCrm())) {
            return [];
        }

        if (method_exists($user, 'getAllTeamMemberIds')) {
            return collect($user->getAllTeamMemberIds())->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        if (method_exists($user, 'teamMembers')) {
            return $user->teamMembers()->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }

        return [];
    }
}
