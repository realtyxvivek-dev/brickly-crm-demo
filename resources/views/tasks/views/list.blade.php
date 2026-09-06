<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    @if($tasks->count() > 0)
        <div class="overflow-x-auto">
            <!-- Desktop Table View -->
            <table class="min-w-full divide-y divide-gray-200 hidden md:table">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($tasks as $task)
                        <tr class="hover:bg-gray-50 {{ $task->isOverdue() ? 'bg-red-50 border-l-4 border-red-500' : '' }}">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $task->title }}</div>
                                @if($task->description)
                                    <div class="text-sm text-gray-500">{{ Str::limit($task->description, 50) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $task->lead?->name ?? 'Lead unavailable' }}</div>
                                <div class="text-sm text-gray-500">{{ $task->lead?->phone ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $task->type === 'phone_call' ? 'bg-blue-100 text-blue-800' : 
                                       ($task->type === 'email' ? 'bg-green-100 text-green-800' : 
                                       ($task->type === 'meeting' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800')) }}">
                                    {{ ucfirst(str_replace('_', ' ', $task->type)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $priority = $task->priority ?? 'medium';
                                    $priorityColors = [
                                        'low' => 'bg-gray-100 text-gray-800',
                                        'medium' => 'bg-yellow-100 text-yellow-800',
                                        'high' => 'bg-orange-100 text-orange-800',
                                        'urgent' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $priorityColors[$priority] ?? $priorityColors['medium'] }}">
                                    {{ ucfirst($priority) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $task->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($task->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : 
                                       ($task->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800')) }}">
                                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm {{ $task->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                {{ $task->scheduled_at ? $task->scheduled_at->format('M d, Y H:i') : '-' }}
                                @if($task->isOverdue())
                                    <span class="ml-2 text-xs text-red-600">(Overdue)</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                @if($task->status === 'pending' || $task->status === 'in_progress')
                                    <button onclick="completeTask({{ $task->id }})" 
                                            class="text-indigo-600 hover:text-indigo-900 mr-4">
                                        Complete
                                    </button>
                                @endif
                                <a href="{{ route('tasks.show', $task) }}" class="text-gray-600 hover:text-gray-900">
                                    View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            <!-- Mobile Card View -->
            <div class="md:hidden space-y-4 task-list-container">
                @foreach($tasks as $task)
                    <div class="bg-white border border-gray-200 rounded-lg p-4 {{ $task->isOverdue() ? 'border-l-4 border-l-red-500' : '' }}">
                        <div class="flex items-start justify-between mb-2">
                            <h3 class="text-base font-semibold text-gray-900 flex-1">{{ $task->title }}</h3>
                            @php
                                $priority = $task->priority ?? 'medium';
                                $priorityColors = [
                                    'low' => 'bg-gray-100 text-gray-800',
                                    'medium' => 'bg-yellow-100 text-yellow-800',
                                    'high' => 'bg-orange-100 text-orange-800',
                                    'urgent' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full ml-2 {{ $priorityColors[$priority] ?? $priorityColors['medium'] }}">
                                {{ ucfirst($priority) }}
                            </span>
                        </div>
                        
                        @if($task->description)
                            <p class="text-sm text-gray-600 mb-3">{{ Str::limit($task->description, 100) }}</p>
                        @endif
                        
                        <div class="space-y-2 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-600">Lead:</span>
                                <span class="text-sm font-medium text-gray-900">{{ $task->lead?->name ?? 'Lead unavailable' }}</span>
                                <span class="text-sm text-gray-500">({{ $task->lead?->phone ?? '-' }})</span>
                            </div>
                            
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $task->type === 'phone_call' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst(str_replace('_', ' ', $task->type)) }}
                                </span>
                                <span class="px-2 py-0.5 text-xs rounded-full {{ $task->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($task->status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                </span>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm {{ $task->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ $task->scheduled_at ? $task->scheduled_at->format('M d, Y H:i') : '-' }}
                                    @if($task->isOverdue())
                                        <span class="text-xs">(Overdue)</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex gap-2 pt-3 border-t border-gray-200">
                            @if($task->status === 'pending' || $task->status === 'in_progress')
                                <button onclick="completeTask({{ $task->id }})" 
                                        class="flex-1 px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                                    Complete
                                </button>
                            @endif
                            <a href="{{ route('tasks.show', $task) }}" 
                               class="flex-1 px-3 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 text-center">
                                View
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4">
            {{ $tasks->links() }}
        </div>
    @else
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No tasks found</h3>
            <p class="mt-1 text-sm text-gray-500">Try adjusting your filters or create a new task.</p>
        </div>
    @endif
</div>
