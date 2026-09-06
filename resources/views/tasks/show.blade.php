@extends('layouts.app')

@section('title', 'Task Details - ' . brand_name())
@section('page-title', 'Task Details')
@section('page-subtitle', 'View task information')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">{{ $task->title }}</h2>
            <p class="text-gray-600 mt-1">{{ $task->description }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full 
                    {{ $task->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                       ($task->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : 
                       ($task->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800')) }}">
                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                </span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Priority</label>
                @php
                    $priority = $task->priority ?? 'medium';
                    $priorityColors = [
                        'low' => 'bg-gray-100 text-gray-800',
                        'medium' => 'bg-yellow-100 text-yellow-800',
                        'high' => 'bg-orange-100 text-orange-800',
                        'urgent' => 'bg-red-100 text-red-800',
                    ];
                @endphp
                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full {{ $priorityColors[$priority] ?? $priorityColors['medium'] }}">
                    {{ ucfirst($priority) }}
                </span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Type</label>
                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                    {{ ucfirst(str_replace('_', ' ', $task->type)) }}
                </span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Scheduled At</label>
                <p class="text-gray-900 {{ $task->isOverdue() ? 'text-red-600 font-semibold' : '' }}">
                    {{ $task->scheduled_at ? $task->scheduled_at->format('M d, Y H:i') : '-' }}
                    @if($task->isOverdue())
                        <span class="ml-1 text-xs text-red-600">(Overdue)</span>
                    @endif
                </p>
            </div>
            @if($task->due_date)
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Due Date</label>
                <p class="text-gray-900">
                    {{ $task->due_date->format('M d, Y H:i') }}
                </p>
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Completed At</label>
                <p class="text-gray-900">{{ $task->completed_at ? $task->completed_at->format('M d, Y H:i') : '-' }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Assigned To</label>
                <p class="text-gray-900">{{ $task->assignedTo->name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Created By</label>
                <p class="text-gray-900">{{ $task->creator->name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Created At</label>
                <p class="text-gray-900">{{ $task->created_at->format('M d, Y H:i') }}</p>
            </div>
        </div>

        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-500">Notes</label>
                @if($task->assigned_to === auth()->id() || auth()->user()->isAdmin() || auth()->user()->isCrm())
                    <button onclick="toggleNotesEdit()" id="edit-notes-btn" class="text-sm text-indigo-600 hover:text-indigo-900">
                        {{ $task->notes ? 'Edit' : 'Add Notes' }}
                    </button>
                @endif
            </div>
            
            <div id="notes-display" class="bg-gray-50 rounded-lg p-4 {{ $task->notes ? '' : 'hidden' }}">
                <p class="text-gray-900 whitespace-pre-wrap">{{ $task->notes }}</p>
            </div>
            
            <div id="notes-edit" class="hidden">
                <textarea id="notes-input" rows="4" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="Add notes about this task...">{{ $task->notes }}</textarea>
                <div class="flex justify-end gap-2 mt-2">
                    <button onclick="cancelNotesEdit()" class="px-3 py-1 text-sm border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button onclick="saveNotes({{ $task->id }})" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Save
                    </button>
                </div>
            </div>
        </div>

        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Lead Information</h3>
            <div class="bg-gray-50 rounded-lg p-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Name</label>
                        <p class="text-gray-900">{{ $task->lead->name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Phone</label>
                        <p class="text-gray-900">{{ $task->lead->phone }}</p>
                    </div>
                    @if($task->lead->email)
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Email</label>
                        <p class="text-gray-900">{{ $task->lead->email }}</p>
                    </div>
                    @endif
                    @if($task->lead->city)
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">City</label>
                        <p class="text-gray-900">{{ $task->lead->city }}</p>
                    </div>
                    @endif
                </div>
                <div class="mt-4">
                    <a href="{{ route('leads.show', $task->lead) }}" 
                       class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                        View Full Lead Details →
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        @if($task->assigned_to === auth()->id())
        <div class="flex flex-col sm:flex-row flex-wrap justify-end gap-2 sm:gap-3 mb-6">
            @if($task->status === 'pending' || $task->status === 'in_progress')
                <button onclick="completeTask({{ $task->id }})" 
                        class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                    Complete Task
                </button>
                <button onclick="rescheduleTask({{ $task->id }})" 
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Reschedule
                </button>
            @endif
            @if($task->type === 'phone_call' && $task->lead->phone)
                @include('components.mcube-click-call', [
                    'leadId' => $task->lead_id,
                    'taskId' => $task->id,
                    'phone' => $task->lead->phone,
                    'label' => 'MCUBE Call',
                    'class' => 'px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center gap-2',
                ])
            @endif
        </div>
        @endif
    </div>

    <!-- Attachments -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
        @include('tasks.partials.task-attachments', ['task' => $task])
    </div>

    <!-- Activity Log -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
        @include('tasks.partials.activity-log', ['activities' => $task->activities])
    </div>
</div>

<!-- Complete Call Modal -->
@include('tasks.complete-call-modal')

@push('scripts')
<script>
    // Setup axios CSRF token
    axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

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
                field.value = leadData[key] || '';
            }
        });

        document.getElementById('task_id').value = taskId;
        document.getElementById('complete-call-modal').classList.remove('hidden');
    }

    function closeCompleteCallModal() {
        document.getElementById('complete-call-modal').classList.add('hidden');
    }

    function rescheduleTask(taskId) {
        const newDate = prompt('Enter new date and time (YYYY-MM-DD HH:MM):');
        if (!newDate) return;

        axios.post(`/tasks/${taskId}/reschedule`, {
            scheduled_at: newDate
        }).then(response => {
            if (response.data.success) {
                alert('Task rescheduled successfully!');
                window.location.reload();
            } else {
                alert('Failed to reschedule: ' + response.data.message);
            }
        }).catch(error => {
            console.error('Error rescheduling task:', error);
            alert('Error: ' + (error.response?.data?.message || 'Failed to reschedule task'));
        });
    }

    function toggleNotesEdit() {
        document.getElementById('notes-display').classList.add('hidden');
        document.getElementById('notes-edit').classList.remove('hidden');
        document.getElementById('edit-notes-btn').classList.add('hidden');
    }

    function cancelNotesEdit() {
        document.getElementById('notes-display').classList.remove('hidden');
        document.getElementById('notes-edit').classList.add('hidden');
        document.getElementById('edit-notes-btn').classList.remove('hidden');
        // Restore original notes
        document.getElementById('notes-input').value = `{{ $task->notes ?? '' }}`;
    }

    function saveNotes(taskId) {
        const notes = document.getElementById('notes-input').value;

        axios.put(`/tasks/${taskId}`, {
            notes: notes
        }).then(response => {
            if (response.data.success) {
                // Update display
                const display = document.getElementById('notes-display');
                if (notes) {
                    display.querySelector('p').textContent = notes;
                    display.classList.remove('hidden');
                } else {
                    display.classList.add('hidden');
                }
                cancelNotesEdit();
                if (notes) {
                    document.getElementById('edit-notes-btn').textContent = 'Edit';
                } else {
                    document.getElementById('edit-notes-btn').textContent = 'Add Notes';
                }
            } else {
                alert('Failed to save notes: ' + response.data.message);
            }
        }).catch(error => {
            console.error('Error saving notes:', error);
            alert('Error: ' + (error.response?.data?.message || 'Failed to save notes'));
        });
    }

    document.getElementById('complete-call-form').addEventListener('submit', async function(e) {
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
