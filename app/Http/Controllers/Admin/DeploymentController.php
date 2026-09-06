<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DeploymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class DeploymentController extends Controller
{
    protected $deploymentService;

    public function __construct(DeploymentService $deploymentService)
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
        $this->deploymentService = $deploymentService;
    }

    /**
     * Show deployment dashboard
     */
    public function index()
    {
        $gitStatus = $this->deploymentService->getGitStatus();
        $recentCommits = $this->deploymentService->getRecentCommits(10);
        $deploymentHistory = $this->deploymentService->getDeploymentHistory(10);

        return view('admin.deployment.index', compact('gitStatus', 'recentCommits', 'deploymentHistory'));
    }

    /**
     * Check Git status
     */
    public function checkGitStatus()
    {
        try {
            $status = $this->deploymentService->getGitStatus();
            return response()->json([
                'success' => true,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking git status', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error checking git status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deploy to server
     */
    public function deploy(Request $request)
    {
        // Security check: Only allow admin users
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin users can deploy.',
            ], 403);
        }

        $request->validate([
            'commit_message' => 'nullable|string|max:255',
            'auto_commit' => 'boolean',
        ]);

        // Additional security: Check if Git is properly configured
        $gitStatus = $this->deploymentService->getGitStatus();
        if (!$gitStatus['is_git_repo']) {
            return response()->json([
                'success' => false,
                'message' => 'Git repository not initialized. Please initialize Git first.',
            ], 400);
        }

        try {
            $steps = [];
            $autoCommit = $request->input('auto_commit', true);
            $commitMessage = $request->input('commit_message', 'Deployment from admin panel - ' . now()->toDateTimeString());

            // Step 1: Check for uncommitted changes
            $status = $this->deploymentService->getGitStatus();
            if ($status['has_changes'] && $autoCommit) {
                $steps[] = ['step' => 'commit', 'message' => 'Committing changes...'];
                $this->deploymentService->commitChanges($commitMessage);
            }

            // Step 2: Push to Git
            $steps[] = ['step' => 'push', 'message' => 'Pushing to Git repository...'];
            $pushResult = $this->deploymentService->pushToGit();

            if (!$pushResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to push to Git: ' . $pushResult['message'],
                    'steps' => $steps,
                ], 500);
            }

            // Step 3: Trigger server deployment (if webhook configured)
            $steps[] = ['step' => 'deploy', 'message' => 'Triggering server deployment...'];
            $deployResult = $this->deploymentService->triggerServerDeployment();

            // Step 4: Log deployment
            $this->deploymentService->logDeployment([
                'commit_message' => $commitMessage,
                'commit_hash' => $pushResult['commit_hash'] ?? null,
                'deployed_by' => auth()->id(),
                'status' => $deployResult['success'] ? 'success' : 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Deployment initiated successfully!',
                'steps' => $steps,
                'deployment' => $deployResult,
            ]);
        } catch (\Exception $e) {
            Log::error('Deployment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Deployment failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get deployment logs
     */
    public function getLogs()
    {
        try {
            $logs = $this->deploymentService->getDeploymentHistory(50);
            return response()->json([
                'success' => true,
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching logs: ' . $e->getMessage(),
            ], 500);
        }
    }
}
