<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Services\TargetService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TargetController extends Controller
{
    protected $targetService;

    public function __construct(TargetService $targetService)
    {
        $this->targetService = $targetService;
    }

    /**
     * Get current user's targets with progress
     */
    public function myTargets(Request $request)
    {
        $user = $request->user();
        $month = $request->get('month', now()->format('Y-m'));

        $progress = $this->targetService->getTargetProgress($user->id, $month);

        if (!$progress) {
            return response()->json([
                'message' => 'No targets set for this month',
                'targets' => null,
                'progress' => null,
            ]);
        }

        return response()->json([
            'targets' => [
                'prospects_extract' => $progress['target']->target_prospects_extract,
                'prospects_verified' => $progress['target']->target_prospects_verified,
                'calls' => $progress['target']->target_calls,
            ],
            'progress' => $progress['progress'],
        ]);
    }

    /**
     * Get team progress for managers
     */
    public function teamProgress(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isSalesManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $month = $request->get('month', now()->format('Y-m'));
        $teamProgress = $this->targetService->getTeamTargetsProgress($user->id, $month);

        return response()->json([
            'month' => $month,
            'team' => $teamProgress,
        ]);
    }

    /**
     * Get system-wide overview (Admin/CRM only)
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $month = $request->get('month', now()->format('Y-m'));
        $overview = $this->targetService->getSystemOverview($month);

        return response()->json($overview);
    }
}

