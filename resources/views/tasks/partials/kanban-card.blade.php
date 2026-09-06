<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-4 cursor-move hover:shadow-md transition-shadow touch-none swipeable-task" 
     data-task-id="{{ $task->id }}">
    <div class="flex items-start justify-between mb-2">
        <h4 class="text-sm font-medium text-gray-900 flex-1">{{ Str::limit($task->title, 40) }}</h4>
        @php
            $priority = $task->priority ?? 'medium';
            $priorityColors = [
                'low' => 'bg-gray-100 text-gray-800',
                'medium' => 'bg-yellow-100 text-yellow-800',
                'high' => 'bg-orange-100 text-orange-800',
                'urgent' => 'bg-red-100 text-red-800',
            ];
        @endphp
        <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $priorityColors[$priority] ?? $priorityColors['medium'] }} ml-2">
            {{ ucfirst($priority) }}
        </span>
    </div>
    
    <div class="text-xs text-gray-600 mb-2">
        <div class="font-medium">{{ $task->lead->name }}</div>
        <div>{{ $task->lead->phone }}</div>
    </div>
    
    @if($task->scheduled_at)
    <div class="text-xs text-gray-500 mb-3">
        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ $task->scheduled_at->format('M d, H:i') }}
        @if($task->isOverdue())
            <span class="text-red-600 font-semibold">(Overdue)</span>
        @endif
    </div>
    @endif
    
    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200">
        <span class="px-2 py-0.5 text-xs rounded-full 
            {{ $task->type === 'phone_call' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
            {{ ucfirst(str_replace('_', ' ', $task->type)) }}
        </span>
        <a href="{{ route('tasks.show', $task) }}" class="text-xs text-indigo-600 hover:text-indigo-900">
            View →
        </a>
    </div>
</div>
