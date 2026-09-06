<div class="mt-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Activity Log</h3>
    
    @if(isset($activities) && $activities->count() > 0)
        <div class="space-y-4">
            @foreach($activities as $activity)
                <div class="flex items-start gap-3 pb-4 border-b border-gray-200 last:border-b-0">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                            <span class="text-xs font-semibold text-indigo-600">
                                {{ $activity->user ? strtoupper(substr($activity->user->name, 0, 1)) : 'S' }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-900">
                                {{ $activity->user ? $activity->user->name : 'System' }}
                            </span>
                            <span class="text-xs text-gray-500">
                                {{ $activity->created_at->diffForHumans() }}
                            </span>
                        </div>
                        
                        <p class="text-sm text-gray-700 mt-1">
                            {{ $activity->description ?? $this->getActivityDescription($activity) }}
                        </p>
                        
                        @if($activity->old_value && $activity->new_value)
                            <div class="mt-2 text-xs text-gray-600 bg-gray-50 p-2 rounded">
                                <span class="line-through text-red-600">{{ $activity->old_value }}</span>
                                <span class="mx-2">→</span>
                                <span class="text-green-600 font-medium">{{ $activity->new_value }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8 text-gray-500">
            <p>No activity recorded yet.</p>
        </div>
    @endif
</div>

@php
function getActivityDescription($activity) {
    switch($activity->activity_type) {
        case 'created':
            return 'Task created';
        case 'status_changed':
            return "Status changed from '{$activity->old_value}' to '{$activity->new_value}'";
        case 'priority_changed':
            return "Priority changed from '{$activity->old_value}' to '{$activity->new_value}'";
        case 'rescheduled':
            return 'Task rescheduled';
        case 'assigned':
            return 'Task assigned';
        case 'deleted':
            return 'Task deleted';
        default:
            return 'Task updated';
    }
}
@endphp
