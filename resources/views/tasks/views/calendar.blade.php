<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div id="calendar-container"></div>
</div>

@push('styles')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css' rel='stylesheet' />
@endpush

@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar-container');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: [
            @foreach($tasks as $task)
                @if($task->scheduled_at)
                {
                    id: '{{ $task->id }}',
                    title: '{{ $task->title }}',
                    start: '{{ $task->scheduled_at->format('Y-m-d\TH:i:s') }}',
                    @if($task->due_date)
                    end: '{{ $task->due_date->format('Y-m-d\TH:i:s') }}',
                    @endif
                    backgroundColor: getTaskColor('{{ $task->priority ?? 'medium' }}', '{{ $task->status }}'),
                    borderColor: getTaskColor('{{ $task->priority ?? 'medium' }}', '{{ $task->status }}'),
                    extendedProps: {
                        taskId: {{ $task->id }},
                        leadName: '{{ $task->lead->name }}',
                        status: '{{ $task->status }}',
                        priority: '{{ $task->priority ?? 'medium' }}'
                    }
                },
                @endif
            @endforeach
        ],
        eventClick: function(info) {
            window.location.href = '/tasks/' + info.event.extendedProps.taskId;
        },
        eventDrop: function(info) {
            // Handle reschedule on drag
            const taskId = info.event.extendedProps.taskId;
            const newDate = info.event.start;
            
            axios.put(`/api/tasks/${taskId}/reschedule`, {
                scheduled_at: newDate.toISOString()
            }).then(response => {
                if (!response.data.success) {
                    info.revert();
                }
            }).catch(error => {
                console.error('Error rescheduling task:', error);
                info.revert();
            });
        }
    });
    
    calendar.render();

    function getTaskColor(priority, status) {
        if (status === 'completed') return '#10b981'; // green
        if (status === 'cancelled') return '#ef4444'; // red
        
        const colors = {
            'urgent': '#ef4444', // red
            'high': '#f97316', // orange
            'medium': '#eab308', // yellow
            'low': '#6b7280' // gray
        };
        
        return colors[priority] || colors['medium'];
    }
});
</script>
@endpush
