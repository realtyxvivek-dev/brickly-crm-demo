<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\McubeOutboundAttempt;
use App\Services\McubeOutboundCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesManagerDialerController extends Controller
{
    public function __construct(private readonly McubeOutboundCallService $service)
    {
    }

    public function call(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->canUseDialer($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Calling mode is not enabled for this user.',
            ], 403);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $customerNumber = $this->service->normalizePhone((string) $validated['phone']);
        if (!preg_match('/^[6-9]\d{9}$/', $customerNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 10 digit Indian phone number.',
                'fallback_to_tel' => false,
            ], 422);
        }

        if ($this->hasRecentManualAttempt((int) $user->id, $customerNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Recent call already initiated for this number. Please wait before calling again.',
                'fallback_to_tel' => false,
            ], 429);
        }

        $result = $this->service->initiateManual($user, $customerNumber);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    private function canUseDialer($user): bool
    {
        if (!$user) {
            return false;
        }

        return (method_exists($user, 'isAssistantSalesManager') && $user->isAssistantSalesManager())
            || (method_exists($user, 'isSeniorManager') && $user->isSeniorManager());
    }

    private function hasRecentManualAttempt(int $userId, string $customerNumber): bool
    {
        return McubeOutboundAttempt::query()
            ->where('user_id', $userId)
            ->whereNull('lead_id')
            ->where('customer_number', $customerNumber)
            ->where('attempted_at', '>=', now()->subSeconds(60))
            ->where(function ($query) {
                $query->whereNull('refid')
                    ->orWhere('refid', 'like', 'manual:user:%');
            })
            ->exists();
    }
}
