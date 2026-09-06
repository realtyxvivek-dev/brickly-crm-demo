<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-6">
    <div id="kanban-board" class="flex gap-3 sm:gap-4 overflow-x-auto pb-4 scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">
        <!-- Pending Column -->
        <div class="flex-shrink-0 w-72 sm:w-80 bg-gray-50 rounded-lg p-3 sm:p-4">
            <h3 class="font-semibold text-gray-700 mb-4">
                Pending 
                <span class="text-sm font-normal text-gray-500">({{ $tasks->where('status', 'pending')->count() }})</span>
            </h3>
            <div id="pending-column" class="space-y-3 min-h-[400px]">
                @foreach($tasks->where('status', 'pending') as $task)
                    @include('tasks.partials.kanban-card', ['task' => $task])
                @endforeach
            </div>
        </div>

        <!-- In Progress Column -->
        <div class="flex-shrink-0 w-72 sm:w-80 bg-blue-50 rounded-lg p-3 sm:p-4">
            <h3 class="font-semibold text-gray-700 mb-4">
                In Progress 
                <span class="text-sm font-normal text-gray-500">({{ $tasks->where('status', 'in_progress')->count() }})</span>
            </h3>
            <div id="in-progress-column" class="space-y-3 min-h-[400px]">
                @foreach($tasks->where('status', 'in_progress') as $task)
                    @include('tasks.partials.kanban-card', ['task' => $task])
                @endforeach
            </div>
        </div>

        <!-- Completed Column -->
        <div class="flex-shrink-0 w-72 sm:w-80 bg-green-50 rounded-lg p-3 sm:p-4">
            <h3 class="font-semibold text-gray-700 mb-4">
                Completed 
                <span class="text-sm font-normal text-gray-500">({{ $tasks->where('status', 'completed')->count() }})</span>
            </h3>
            <div id="completed-column" class="space-y-3 min-h-[400px]">
                @foreach($tasks->where('status', 'completed') as $task)
                    @include('tasks.partials.kanban-card', ['task' => $task])
                @endforeach
            </div>
        </div>
    </div>

    @if($tasks->count() === 0)
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No tasks found</h3>
            <p class="mt-1 text-sm text-gray-500">Try adjusting your filters.</p>
        </div>
    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize SortableJS for drag and drop
    const pendingColumn = document.getElementById('pending-column');
    const inProgressColumn = document.getElementById('in-progress-column');
    const completedColumn = document.getElementById('completed-column');

    if (pendingColumn) {
        new Sortable(pendingColumn, {
            group: 'tasks',
            animation: 150,
            onEnd: function(evt) {
                updateTaskStatus(evt.item.dataset.taskId, 'pending', evt.to.id);
            }
        });
    }

    if (inProgressColumn) {
        new Sortable(inProgressColumn, {
            group: 'tasks',
            animation: 150,
            onEnd: function(evt) {
                updateTaskStatus(evt.item.dataset.taskId, 'in_progress', evt.to.id);
            }
        });
    }

    if (completedColumn) {
        new Sortable(completedColumn, {
            group: 'tasks',
            animation: 150,
            onEnd: function(evt) {
                updateTaskStatus(evt.item.dataset.taskId, 'completed', evt.to.id);
            }
        });
    }
});

async function updateTaskStatus(taskId, newStatus, columnId) {
    try {
        const response = await axios.put(`/api/tasks/${taskId}/status`, {
            status: newStatus
        });
        
        if (!response.data.success) {
            // Revert on error
            window.location.reload();
        }
    } catch (error) {
        console.error('Error updating task status:', error);
        window.location.reload();
    }
}
</script>
@endpush
