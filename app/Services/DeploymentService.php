<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class DeploymentService
{
    /**
     * Run a shell command in the project base path (avoids shell_exec which may be disabled on server).
     */
    private function runCommand(string $command): ?string
    {
        try {
            $result = Process::path(base_path())->timeout(30)->run($command);
            $output = trim($result->output() . "\n" . $result->errorOutput());
            return $output !== '' ? $output : null;
        } catch (\Throwable $e) {
            Log::warning('DeploymentService runCommand failed', ['command' => $command, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get Git status
     */
    public function getGitStatus()
    {
        try {
            $gitDir = base_path('.git');
            
            if (!File::exists($gitDir)) {
                return [
                    'is_git_repo' => false,
                    'has_changes' => false,
                    'branch' => null,
                    'last_commit' => null,
                    'uncommitted_files' => [],
                ];
            }

            // Get current branch
            $branch = trim($this->runCommand('git rev-parse --abbrev-ref HEAD 2>&1') ?? '');
            
            // Check for uncommitted changes
            $statusOutput = $this->runCommand('git status --porcelain 2>&1');
            $hasChanges = !empty(trim($statusOutput ?? ''));
            
            // Get uncommitted files
            $uncommittedFiles = [];
            if ($hasChanges) {
                $files = explode("\n", trim($statusOutput ?? ''));
                foreach ($files as $file) {
                    if (!empty(trim($file))) {
                        $status = substr($file, 0, 2);
                        $filename = trim(substr($file, 3));
                        $uncommittedFiles[] = [
                            'status' => $status,
                            'file' => $filename,
                        ];
                    }
                }
            }

            // Get last commit
            $lastCommit = null;
            $commitHash = trim($this->runCommand('git rev-parse HEAD 2>&1') ?? '');
            $commitMessage = trim($this->runCommand('git log -1 --pretty=format:"%s" 2>&1') ?? '');
            $commitDate = trim($this->runCommand('git log -1 --pretty=format:"%ci" 2>&1') ?? '');
            
            if ($commitHash && !str_contains($commitHash, 'fatal')) {
                $lastCommit = [
                    'hash' => substr($commitHash, 0, 7),
                    'full_hash' => $commitHash,
                    'message' => $commitMessage,
                    'date' => $commitDate,
                ];
            }

            return [
                'is_git_repo' => true,
                'has_changes' => $hasChanges,
                'branch' => $branch ?: 'unknown',
                'last_commit' => $lastCommit,
                'uncommitted_files' => $uncommittedFiles,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting git status', ['error' => $e->getMessage()]);
            return [
                'is_git_repo' => false,
                'has_changes' => false,
                'branch' => null,
                'last_commit' => null,
                'uncommitted_files' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get recent commits
     */
    public function getRecentCommits($limit = 10)
    {
        try {
            $gitDir = base_path('.git');
            if (!File::exists($gitDir)) {
                return [];
            }

            $output = $this->runCommand('git log -' . (int) $limit . ' --pretty=format:"%h|%s|%ci|%an" 2>&1');
            
            if (empty($output) || str_contains($output ?? '', 'fatal')) {
                return [];
            }

            $commits = [];
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $parts = explode('|', $line);
                if (count($parts) >= 4) {
                    $commits[] = [
                        'hash' => $parts[0],
                        'message' => $parts[1],
                        'date' => $parts[2],
                        'author' => $parts[3],
                    ];
                }
            }

            return $commits;
        } catch (\Exception $e) {
            Log::error('Error getting recent commits', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Commit changes
     */
    public function commitChanges($message)
    {
        // Security: Sanitize commit message
        $message = strip_tags($message);
        $message = preg_replace('/[^a-zA-Z0-9\s\-_.,!?()]/', '', $message);
        $message = substr($message, 0, 255); // Limit length

        try {
            // Add all changes
            $this->runCommand('git add -A 2>&1');
            
            // Commit
            $commitOutput = $this->runCommand('git commit -m ' . escapeshellarg($message) . ' 2>&1') ?? '';
            
            if (str_contains($commitOutput, 'fatal') || str_contains($commitOutput, 'error')) {
                throw new \Exception('Git commit failed: ' . $commitOutput);
            }

            return [
                'success' => true,
                'message' => 'Changes committed successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Error committing changes', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Push to Git
     */
    public function pushToGit()
    {
        try {
            $branch = $this->getGitStatus()['branch'] ?? 'main';
            
            $output = $this->runCommand('git push origin ' . $branch . ' 2>&1') ?? '';
            
            if (str_contains($output, 'fatal') || str_contains($output, 'error')) {
                throw new \Exception('Git push failed: ' . $output);
            }

            // Get commit hash
            $commitHash = trim($this->runCommand('git rev-parse HEAD 2>&1') ?? '');

            return [
                'success' => true,
                'message' => 'Pushed to Git successfully',
                'commit_hash' => $commitHash,
                'branch' => $branch,
            ];
        } catch (\Exception $e) {
            Log::error('Error pushing to git', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Trigger server deployment
     */
    public function triggerServerDeployment()
    {
        try {
            // Option 1: Webhook URL (if configured)
            $webhookUrl = config('deployment.webhook_url');
            if ($webhookUrl) {
                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300) {
                    return [
                        'success' => true,
                        'message' => 'Deployment webhook triggered successfully',
                        'method' => 'webhook',
                    ];
                }
            }

            // Option 2: SSH deployment (if configured)
            $sshConfig = config('deployment.ssh');
            if ($sshConfig && isset($sshConfig['host'])) {
                // SSH deployment would go here
                // For now, return pending status
                return [
                    'success' => true,
                    'message' => 'Deployment will be processed via SSH',
                    'method' => 'ssh',
                    'status' => 'pending',
                ];
            }

            // If no deployment method configured, return info message
            return [
                'success' => true,
                'message' => 'Code pushed to Git. Please configure webhook or SSH for automatic server deployment.',
                'method' => 'manual',
                'status' => 'pending',
            ];
        } catch (\Exception $e) {
            Log::error('Error triggering server deployment', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log deployment
     */
    public function logDeployment($data)
    {
        try {
            // Store in database if deployment_logs table exists
            if (DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                DB::table('deployment_logs')->insert([
                    'commit_message' => $data['commit_message'] ?? null,
                    'commit_hash' => $data['commit_hash'] ?? null,
                    'deployed_by' => $data['deployed_by'] ?? null,
                    'status' => $data['status'] ?? 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Store in file as fallback
                $logFile = storage_path('logs/deployments.log');
                $logEntry = now()->toDateTimeString() . ' | ' . json_encode($data) . "\n";
                File::append($logFile, $logEntry);
            }
        } catch (\Exception $e) {
            Log::error('Error logging deployment', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get deployment history
     */
    public function getDeploymentHistory($limit = 10)
    {
        try {
            // Try database first
            if (DB::getSchemaBuilder()->hasTable('deployment_logs')) {
                return DB::table('deployment_logs')
                    ->leftJoin('users', 'deployment_logs.deployed_by', '=', 'users.id')
                    ->select('deployment_logs.*', 'users.name as deployed_by_name')
                    ->orderBy('deployment_logs.created_at', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'commit_message' => $log->commit_message,
                            'commit_hash' => $log->commit_hash,
                            'deployed_by' => $log->deployed_by,
                            'deployed_by_name' => $log->deployed_by_name ?? 'System',
                            'status' => $log->status,
                            'created_at' => $log->created_at,
                        ];
                    })
                    ->toArray();
            }

            // Fallback to file
            $logFile = storage_path('logs/deployments.log');
            if (File::exists($logFile)) {
                $lines = array_slice(array_reverse(File::lines($logFile)->toArray()), 0, $limit);
                $history = [];
                foreach ($lines as $line) {
                    $parts = explode(' | ', $line);
                    if (count($parts) >= 2) {
                        $data = json_decode($parts[1], true);
                        if ($data) {
                            $history[] = $data;
                        }
                    }
                }
                return $history;
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Error getting deployment history', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
