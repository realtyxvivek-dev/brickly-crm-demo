<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FlowTestService;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FlowTestController extends Controller
{
    protected $flowTestService;

    public function __construct(FlowTestService $flowTestService)
    {
        $this->middleware('auth');
        $this->flowTestService = $flowTestService;
    }

    /**
     * Display flow testing page
     */
    public function index()
    {
        $user = auth()->user();
        
        // Only Admin and CRM can access flow testing
        if (!$user->isAdmin() && !$user->isCrm()) {
            abort(403, 'Only Admin and CRM can access flow testing');
        }

        return view('admin.flow-test');
    }

    /**
     * Get all flow stages with current status
     */
    public function getFlowStages(Request $request)
    {
        try {
            $stages = $this->flowTestService->getAllStages();
            $currentUser = auth()->user();
            
            // Get status for each stage
            foreach ($stages as &$stage) {
                $stage['status'] = $this->flowTestService->getStageStatus($stage['id']);
                $stage['canTest'] = $this->flowTestService->canTestStage($stage['id'], $currentUser);
            }

            return response()->json([
                'success' => true,
                'stages' => $stages,
                'currentUser' => [
                    'id' => $currentUser->id,
                    'name' => $currentUser->name,
                    'role' => $currentUser->role->name ?? 'N/A',
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Flow test get stages error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login as specific user for testing
     */
    public function loginAsUser(Request $request, $userId)
    {
        try {
            $targetUser = User::with('role')->findOrFail($userId);
            $currentUser = auth()->user();

            // Only Admin and CRM can login as other users
            if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Admin and CRM can login as other users'
                ], 403);
            }

            // Store original user ID in session
            session(['flow_test_original_user_id' => $currentUser->id]);
            
            // Login as target user
            Auth::login($targetUser);

            return response()->json([
                'success' => true,
                'message' => 'Logged in as ' . $targetUser->name,
                'user' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                    'role' => $targetUser->role->name ?? 'N/A',
                    'roleSlug' => $targetUser->role->slug ?? 'N/A',
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Flow test login as user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error logging in: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore original user session
     */
    public function restoreOriginalUser(Request $request)
    {
        try {
            $originalUserId = session('flow_test_original_user_id');
            
            if ($originalUserId) {
                $originalUser = User::findOrFail($originalUserId);
                Auth::login($originalUser);
                session()->forget('flow_test_original_user_id');
                
                return response()->json([
                    'success' => true,
                    'message' => 'Restored original user session',
                    'user' => [
                        'id' => $originalUser->id,
                        'name' => $originalUser->name,
                        'role' => $originalUser->role->name ?? 'N/A',
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No original user session found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Flow test restore user error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error restoring user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test a specific stage
     */
    public function testStage(Request $request, $stageId)
    {
        try {
            $user = auth()->user();
            $result = $this->flowTestService->testStage($stageId, $user);

            return response()->json([
                'success' => true,
                'stageId' => $stageId,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Flow test stage error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error testing stage: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Validate stage conditions
     */
    public function validateStage(Request $request, $stageId)
    {
        try {
            $user = auth()->user();
            $validation = $this->flowTestService->validateStage($stageId, $user);

            return response()->json([
                'success' => true,
                'stageId' => $stageId,
                'validation' => $validation
            ]);
        } catch (\Exception $e) {
            Log::error('Flow test validate stage error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error validating stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stage data
     */
    public function getStageData(Request $request, $stageId)
    {
        try {
            $user = auth()->user();
            $data = $this->flowTestService->getStageData($stageId, $user);

            return response()->json([
                'success' => true,
                'stageId' => $stageId,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Flow test get stage data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stage data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fix errors for a stage
     */
    public function fixErrors(Request $request, $stageId)
    {
        try {
            $user = auth()->user();
            $errors = $request->input('errors', []);
            $fixResults = $this->flowTestService->fixErrors($stageId, $errors, $user);

            return response()->json([
                'success' => true,
                'stageId' => $stageId,
                'fixResults' => $fixResults
            ]);
        } catch (\Exception $e) {
            Log::error('Flow test fix errors error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fixing errors: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users grouped by role
     */
    public function getUsersByRole(Request $request)
    {
        try {
            $users = User::with('role')
                ->where('is_active', true)
                ->get()
                ->groupBy(function($user) {
                    return $user->role->slug ?? 'unknown';
                });

            $grouped = [];
            foreach ($users as $roleSlug => $userList) {
                $grouped[$roleSlug] = $userList->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role->name ?? 'N/A',
                    ];
                })->values();
            }

            return response()->json([
                'success' => true,
                'users' => $grouped
            ]);
        } catch (\Exception $e) {
            Log::error('Flow test get users error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset flow test (optional - clears test data)
     */
    public function resetFlow(Request $request)
    {
        try {
            // This is optional - can be used to clear test data if needed
            // For now, just return success
            return response()->json([
                'success' => true,
                'message' => 'Flow test reset (no data cleared - use with caution)'
            ]);
        } catch (\Exception $e) {
            Log::error('Flow test reset error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error resetting flow: ' . $e->getMessage()
            ], 500);
        }
    }
}
