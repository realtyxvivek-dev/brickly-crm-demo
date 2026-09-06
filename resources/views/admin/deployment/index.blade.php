@extends('layouts.app')

@section('title', 'Deployment Dashboard - ' . brand_name())
@section('page-title', 'Deployment Dashboard')
@section('page-subtitle', 'One-Click Deployment to Production Server')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Git Status Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-900">
                <i class="fab fa-git-alt text-[#063A1C] mr-2"></i>
                Git Repository Status
            </h2>
            <button onclick="refreshGitStatus()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i> Refresh
            </button>
        </div>

        <div id="git-status-content">
            @if($gitStatus['is_git_repo'])
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Current Branch</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $gitStatus['branch'] }}</p>
                        </div>
                        @if($gitStatus['last_commit'])
                        <div>
                            <p class="text-sm text-gray-600">Last Commit</p>
                            <p class="text-sm font-medium text-gray-900">{{ $gitStatus['last_commit']['hash'] }}</p>
                            <p class="text-xs text-gray-500">{{ $gitStatus['last_commit']['message'] }}</p>
                        </div>
                        @endif
                    </div>

                    @if($gitStatus['has_changes'])
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                            <span class="font-semibold text-yellow-900">Uncommitted Changes Detected</span>
                        </div>
                        <div class="mt-2">
                            <p class="text-sm text-yellow-800 mb-2">Modified Files:</p>
                            <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                                @foreach(array_slice($gitStatus['uncommitted_files'], 0, 5) as $file)
                                <li>{{ $file['file'] }} <span class="text-xs">({{ $file['status'] }})</span></li>
                                @endforeach
                                @if(count($gitStatus['uncommitted_files']) > 5)
                                <li class="text-xs">... and {{ count($gitStatus['uncommitted_files']) - 5 }} more</li>
                                @endif
                            </ul>
                        </div>
                    </div>
                    @else
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 mr-2"></i>
                            <span class="font-semibold text-green-900">Working directory is clean</span>
                        </div>
                    </div>
                    @endif
                </div>
            @else
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-times-circle text-red-600 mr-2"></i>
                        <span class="font-semibold text-red-900">Not a Git repository</span>
                    </div>
                    <p class="text-sm text-red-700 mt-2">Please initialize Git repository first.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Deployment Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">
            <i class="fas fa-rocket text-blue-600 mr-2"></i>
            Deploy to Production Server
        </h2>

        <form id="deploy-form" class="space-y-4">
            @if($gitStatus['has_changes'] ?? false)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <label class="flex items-center">
                    <input type="checkbox" name="auto_commit" value="1" checked class="mr-2">
                    <span class="text-sm text-blue-900">Automatically commit changes before deploying</span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Commit Message</label>
                <input type="text" name="commit_message" 
                    value="Deployment from admin panel - {{ now()->toDateTimeString() }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
            </div>
            @endif

            <button type="button" onclick="deploy()" id="deploy-btn" 
                class="w-full px-6 py-3 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] font-semibold text-lg">
                <i class="fas fa-paper-plane mr-2"></i>
                Deploy to Server
            </button>
        </form>

        <!-- Deployment Progress -->
        <div id="deployment-progress" class="hidden mt-6">
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 mb-3">Deployment Progress</h3>
                <div id="deployment-steps" class="space-y-2"></div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div id="deployment-progress-bar" class="bg-gradient-to-r from-[#063A1C] to-[#205A44] h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Commits -->
    @if(count($recentCommits) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">
            <i class="fas fa-history text-purple-600 mr-2"></i>
            Recent Commits
        </h2>
        <div class="space-y-3">
            @foreach($recentCommits as $commit)
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $commit['hash'] }}</code>
                            <span class="text-sm text-gray-600">{{ $commit['date'] }}</span>
                        </div>
                        <p class="text-sm font-medium text-gray-900">{{ $commit['message'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">by {{ $commit['author'] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Deployment History -->
    @if(count($deploymentHistory) > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">
            <i class="fas fa-list-alt text-green-600 mr-2"></i>
            Deployment History
        </h2>
        <div class="space-y-3">
            @foreach($deploymentHistory as $deployment)
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            @if(isset($deployment['status']))
                            <span class="px-2 py-1 text-xs rounded {{ $deployment['status'] === 'success' ? 'bg-green-100 text-green-800' : ($deployment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ ucfirst($deployment['status']) }}
                            </span>
                            @endif
                            @if(isset($deployment['commit_hash']))
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ substr($deployment['commit_hash'], 0, 7) }}</code>
                            @endif
                            <span class="text-sm text-gray-600">{{ isset($deployment['created_at']) ? $deployment['created_at'] : 'N/A' }}</span>
                        </div>
                        <p class="text-sm font-medium text-gray-900">{{ $deployment['commit_message'] ?? 'Deployment' }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
    async function refreshGitStatus() {
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';

        try {
            const response = await fetch('{{ route("admin.deploy.status") }}');
            const data = await response.json();
            
            if (data.success) {
                location.reload();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error refreshing status: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async function deploy() {
        const form = document.getElementById('deploy-form');
        const formData = new FormData(form);
        const deployBtn = document.getElementById('deploy-btn');
        const progressDiv = document.getElementById('deployment-progress');
        const stepsDiv = document.getElementById('deployment-steps');
        const progressBar = document.getElementById('deployment-progress-bar');

        // Show progress
        progressDiv.classList.remove('hidden');
        deployBtn.disabled = true;
        deployBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deploying...';
        stepsDiv.innerHTML = '';
        progressBar.style.width = '0%';

        function addStep(message, status = 'info') {
            const icon = status === 'success' ? 'fa-check' : status === 'error' ? 'fa-times' : 'fa-spinner fa-spin';
            const color = status === 'success' ? 'text-green-600' : status === 'error' ? 'text-red-600' : 'text-blue-600';
            stepsDiv.innerHTML += `<div class="flex items-center text-sm ${color}"><i class="fas ${icon} mr-2"></i>${message}</div>`;
        }

        try {
            addStep('Starting deployment...', 'info');
            progressBar.style.width = '10%';

            const response = await fetch('{{ route("admin.deploy.deploy") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    commit_message: formData.get('commit_message'),
                    auto_commit: formData.get('auto_commit') === '1',
                }),
            });

            const data = await response.json();
            progressBar.style.width = '100%';

            if (data.success) {
                if (data.steps) {
                    data.steps.forEach(step => {
                        addStep(step.message, 'success');
                    });
                }
                addStep('Deployment initiated successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                addStep('Deployment failed: ' + data.message, 'error');
                deployBtn.disabled = false;
                deployBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Deploy to Server';
            }
        } catch (error) {
            addStep('Error: ' + error.message, 'error');
            deployBtn.disabled = false;
            deployBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Deploy to Server';
        }
    }
</script>
@endpush
@endsection
