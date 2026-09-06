<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Services\TelecallerLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TelecallerLimitController extends Controller
{
    protected $limitService;

    public function __construct(TelecallerLimitService $limitService)
    {
        $this->limitService = $limitService;
    }

    /**
     * Index - show all telecaller daily limits
     */
    public function index()
    {
        $salesExecutiveRoleId = Role::where('slug', Role::SALES_EXECUTIVE)->value('id');

        $telecallers = User::where('role_id', $salesExecutiveRoleId)
            ->where('is_active', true)
            ->with(['telecallerDailyLimit', 'telecallerProfile'])
            ->get()
            ->map(function ($telecaller) {
                $dailyLimit = $telecaller->telecallerDailyLimit;
                return [
                    'id' => $telecaller->id,
                    'name' => $telecaller->name,
                    'email' => $telecaller->email,
                    'overall_daily_limit' => $dailyLimit?->overall_daily_limit ?? 0,
                    'assigned_count_today' => $dailyLimit?->assigned_count_today ?? 0,
                    'last_reset_date' => $dailyLimit?->last_reset_date,
                    'max_pending_leads' => $telecaller->telecallerProfile?->max_pending_leads ?? 50,
                ];
            });

        return view('lead-assignment.telecaller-limits', compact('telecallers'));
    }

    /**
     * Save daily limit
     */
    public function saveDailyLimit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'overall_daily_limit' => 'required|integer|min:0',
            'max_pending_leads' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        
        if (!$user->isSalesExecutive()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a sales executive.'
            ], 422);
        }

        // Update or create daily limit
        $dailyLimit = $this->limitService->getOverallDailyLimit($user->id);
        $dailyLimit->update([
            'overall_daily_limit' => $request->overall_daily_limit,
        ]);

        // Update max pending leads in profile
        if ($request->has('max_pending_leads')) {
            $statusService = app(\App\Services\TelecallerStatusService::class);
            $profile = $statusService->getOrCreateProfile($user->id);
            $profile->update([
                'max_pending_leads' => $request->max_pending_leads,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Daily limit updated successfully.',
            'daily_limit' => $dailyLimit->fresh(),
        ]);
    }

    /**
     * Get daily limits (API)
     */
    public function getDailyLimits(Request $request)
    {
        $telecallerId = $request->input('telecaller_id');
        
        if (!$telecallerId) {
            $telecallerRoleId = Role::where('slug', Role::SALES_EXECUTIVE)->value('id');
            $telecallers = User::where('role_id', $telecallerRoleId)
                ->where('is_active', true)
                ->with(['telecallerDailyLimit', 'telecallerProfile'])
                ->get();
        } else {
            $telecallers = collect([User::with(['telecallerDailyLimit', 'telecallerProfile'])->findOrFail($telecallerId)]);
        }

        $data = $telecallers->map(function ($telecaller) {
            $dailyLimit = $telecaller->telecallerDailyLimit;
            return [
                'user_id' => $telecaller->id,
                'name' => $telecaller->name,
                'overall_daily_limit' => $dailyLimit?->overall_daily_limit ?? 0,
                'assigned_count_today' => $dailyLimit?->assigned_count_today ?? 0,
                'max_pending_leads' => $telecaller->telecallerProfile?->max_pending_leads ?? 50,
            ];
        });

        return response()->json($data);
    }
}
