<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeadsPendingResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadsPendingResponseController extends Controller
{
    public function __construct(
        protected LeadsPendingResponseService $service
    ) {}

    /**
     * Get current user's leads allocated but not yet responded.
     * Used by Sales Executive (telecaller) and Sales Manager / ASM dashboards.
     */
    public function forCurrentUser(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $data = $this->service->getForUser($userId, $request);

        return response()->json($data);
    }
}
