@extends('layouts.app')

@section('title', 'My Tasks - ' . brand_name())
@section('page-title', 'My Tasks')
@section('page-subtitle', 'View and manage your assigned tasks')

@section('content')
@php
    $isPaginated = $tasks instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $taskCount = $isPaginated ? $tasks->total() : $tasks->count();
    $hasMoreTaskPages = $isPaginated && $tasks->hasMorePages();
@endphp
<div class="max-w-7xl mx-auto">
    <!-- View Switcher and Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-gray-900">Tasks</h2>
                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                    {{ $taskCount }} {{ Str::plural('task', $taskCount) }}
                </span>
            </div>
            
            <!-- View Switcher -->
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-600 mr-2">View:</span>
                <div class="inline-flex rounded-lg border border-gray-300 bg-white">
                    <button onclick="switchView('list')" 
                            class="px-4 py-2 text-sm font-medium rounded-l-lg {{ ($view ?? 'list') == 'list' ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-50' }}">
                        List
                    </button>
                    <button onclick="switchView('kanban')" 
                            class="px-4 py-2 text-sm font-medium border-l border-gray-300 {{ ($view ?? 'list') == 'kanban' ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-50' }}">
                        Kanban
                    </button>
                    <button onclick="switchView('calendar')" 
                            class="px-4 py-2 text-sm font-medium rounded-r-lg border-l border-gray-300 {{ ($view ?? 'list') == 'calendar' ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-50' }}">
                        Calendar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    @include('tasks.partials.filters')

    <!-- Task Views -->
    @if(($view ?? 'list') == 'kanban')
        @include('tasks.views.kanban')
    @elseif(($view ?? 'list') == 'calendar')
        @include('tasks.views.calendar')
    @else
        @include('tasks.views.list')
    @endif
</div>

<!-- Complete Call Modal -->
@include('tasks.complete-call-modal')

@push('styles')
<style>
@media (max-width: 640px) {
    .task-list-container {
        padding-bottom: 20px;
    }
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/tasks-mobile.js') }}?v={{ @filemtime(public_path('js/tasks-mobile.js')) ?: time() }}" defer></script>
<script>
    // Setup axios CSRF token
    axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Add data attribute for infinite scroll
    document.body.dataset.hasMorePages = '{{ $hasMoreTaskPages ? 'true' : 'false' }}';

    // View switching
    function switchView(view) {
        const url = new URL(window.location.href);
        url.searchParams.set('view', view);
        // Save to localStorage
        localStorage.setItem('task_view_preference', view);
        window.location.href = url.toString();
    }

    // Load view preference from localStorage on page load
    window.addEventListener('DOMContentLoaded', function() {
        const savedView = localStorage.getItem('task_view_preference');
        const currentView = '{{ $view ?? 'list' }}';
        if (savedView && savedView !== currentView && !window.location.search.includes('view=')) {
            switchView(savedView);
        }
    });

    async function completeTask(taskId) {
        try {
            const response = await axios.post(`/tasks/${taskId}/complete`);
            
            if (response.data.success) {
                openCompleteCallModal(response.data.lead_data, taskId);
            } else {
                alert('Failed to complete task: ' + (response.data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error completing task:', error);
            alert('Error: ' + (error.response?.data?.message || 'Failed to complete task'));
        }
    }

    function openCompleteCallModal(leadData, taskId) {
        Object.keys(leadData).forEach(key => {
            const field = document.getElementById(`lead_${key}`);
            if (field) {
                if (field.tagName === 'SELECT') {
                    field.value = leadData[key] || '';
                } else {
                    field.value = leadData[key] || '';
                }
            }
        });

        document.getElementById('task_id').value = taskId;
        document.getElementById('complete-call-modal').classList.remove('hidden');
    }

    function closeCompleteCallModal() {
        document.getElementById('complete-call-modal').classList.add('hidden');
    }

    document.getElementById('complete-call-form')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const taskId = document.getElementById('task_id').value;
        const formData = new FormData(this);

        try {
            const response = await axios.post(`/tasks/${taskId}/update-lead`, formData);
            
            if (response.data.success) {
                alert('Lead updated successfully!');
                closeCompleteCallModal();
                window.location.reload();
            } else {
                alert('Failed to update lead: ' + (response.data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error updating lead:', error);
            if (error.response?.data?.errors) {
                const errors = Object.values(error.response.data.errors).flat().join('\n');
                alert('Validation errors:\n' + errors);
            } else {
                alert('Error: ' + (error.response?.data?.message || 'Failed to update lead'));
            }
        }
    });
</script>
@endpush
@endsection
