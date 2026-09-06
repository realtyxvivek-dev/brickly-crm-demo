@extends(auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isSalesManager()) ? 'sales-manager.layout' : 'layouts.app')

@section('title', 'Execution Task Detail')
@section('hide-app-header', '1')
@section('page-title', '')
@section('page-subtitle', '')

@section('content')
<div class="container mx-auto px-4 py-6">
    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc pl-5 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold text-brand-primary">{{ $task->title }}</h1>
                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">{{ $task->task_code }}</span>
                <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">{{ ucwords(str_replace('_', ' ', $task->status)) }}</span>
            </div>
            <p class="text-sm text-gray-500">Assigned by {{ $task->creator->name ?? 'Unknown' }} to {{ $task->assignee->name ?? 'Unknown' }}</p>
        </div>
        <a href="{{ route('execution-desk.index') }}" class="btn border border-gray-300 bg-white text-gray-700">Back to Desk</a>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.6fr,1fr]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-brand-primary">Task Details</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div><span class="text-sm text-gray-500">Priority</span><div class="mt-1 font-medium">{{ ucfirst($task->priority) }}</div></div>
                    <div><span class="text-sm text-gray-500">Context</span><div class="mt-1 font-medium">{{ config('execution-desk.contexts.' . $task->context_label, ucfirst($task->context_label)) }}</div></div>
                    <div><span class="text-sm text-gray-500">Due At</span><div class="mt-1 font-medium">{{ $task->due_at ? $task->due_at->format('d M Y h:i A') : 'Not set' }}</div></div>
                    <div><span class="text-sm text-gray-500">Estimated Time</span><div class="mt-1 font-medium">{{ $task->estimated_time_minutes ? $task->estimated_time_minutes . ' min' : 'Not set' }}</div></div>
                    <div class="md:col-span-2"><span class="text-sm text-gray-500">Description</span><div class="mt-1 whitespace-pre-line text-gray-700">{{ $task->description ?: 'No description added.' }}</div></div>
                    @if($task->status === 'waiting')
                        <div><span class="text-sm text-gray-500">Waiting On</span><div class="mt-1 font-medium">{{ $task->waitingOnUser->name ?? 'Unknown' }}</div></div>
                        <div class="md:col-span-2"><span class="text-sm text-gray-500">Waiting Reason</span><div class="mt-1 whitespace-pre-line text-gray-700">{{ $task->waiting_reason }}</div></div>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-brand-primary">Checklist</h2>
                </div>
                <div class="space-y-3">
                    @forelse($task->checklists as $item)
                        <div class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2">
                            <form method="POST" action="{{ route('execution-desk.tasks.checklists.update', [$task, $item]) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="is_completed" value="{{ $item->is_completed ? 0 : 1 }}">
                                <button type="submit" class="h-5 w-5 rounded border {{ $item->is_completed ? 'bg-green-600 border-green-600' : 'border-gray-400' }}"></button>
                            </form>
                            <div class="flex-1">
                                <div class="{{ $item->is_completed ? 'line-through text-gray-400' : 'text-gray-800' }}">{{ $item->title }}</div>
                                @if($item->completed_at)
                                    <div class="text-xs text-gray-500">Completed {{ $item->completed_at->diffForHumans() }}</div>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('execution-desk.tasks.checklists.destroy', [$task, $item]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600">Delete</button>
                            </form>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500">No checklist items yet.</div>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('execution-desk.tasks.checklists.store', $task) }}" class="mt-4 flex gap-2">
                    @csrf
                    <input type="text" name="title" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Add checklist item">
                    <button type="submit" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700">Add</button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-brand-primary">Attachments</h2>
                <div class="space-y-3">
                    @forelse($task->attachments as $attachment)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <div class="font-medium text-gray-800">{{ $attachment->display_name }}</div>
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $attachment->isLink() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $attachment->isLink() ? 'Link' : 'File' }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    @if($attachment->isLink())
                                        {{ $attachment->link_url }} by {{ $attachment->uploadedBy->name ?? 'Unknown' }}
                                    @else
                                        {{ $attachment->file_size_human }} by {{ $attachment->uploadedBy->name ?? 'Unknown' }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-3">
                                @if($attachment->isLink())
                                    <a href="{{ $attachment->link_url }}" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-600">Open</a>
                                @else
                                    <a href="{{ route('execution-desk.tasks.attachments.download', [$task, $attachment]) }}" class="text-sm text-blue-600">Download</a>
                                @endif
                                <form method="POST" action="{{ route('execution-desk.tasks.attachments.destroy', [$task, $attachment]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600">Delete</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500">No attachments uploaded.</div>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('execution-desk.tasks.attachments.store', $task) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <input type="file" name="file" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <div class="grid gap-2 md:grid-cols-[1fr,1.2fr]">
                        <input type="text" name="link_title" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Link title (optional)">
                        <input type="url" name="link_url" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="https://example.com/resource">
                    </div>
                    <button type="submit" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700">Save Attachment</button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-brand-primary">Activity Timeline</h2>
                <div class="space-y-4">
                    @forelse($task->activities->sortByDesc('created_at') as $activity)
                        <div class="border-l-2 border-gray-200 pl-4">
                            <div class="text-sm font-medium text-gray-800">{{ $activity->message ?: ucwords(str_replace('_', ' ', $activity->type)) }}</div>
                            <div class="text-xs text-gray-500">{{ $activity->user->name ?? 'System' }} • {{ $activity->created_at?->format('d M Y h:i A') }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No activity logged yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-brand-primary">Quick Actions</h2>
                <form method="POST" action="{{ route('execution-desk.tasks.status', $task) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="{{ $task->status }}" selected>{{ ucwords(str_replace('_', ' ', $task->status)) }} (Current)</option>
                        @foreach($allowedNextStatuses as $status)
                            <option value="{{ $status }}" @selected(old('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                    <textarea name="reason" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Reason for reopen/reject"></textarea>
                    <textarea name="waiting_reason" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Waiting reason"></textarea>
                    <select name="waiting_on_user" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="">Waiting on user</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-brand-primary w-full">Update Status</button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-brand-primary">Reassign</h2>
                <form method="POST" action="{{ route('execution-desk.tasks.reassign', $task) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <select name="assigned_to" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected($task->assigned_to === $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn border border-gray-300 bg-white text-gray-700 w-full">Reassign Task</button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-brand-primary">Add Note</h2>
                <form method="POST" action="{{ route('execution-desk.tasks.notes.store', $task) }}" class="space-y-3">
                    @csrf
                    <textarea name="note" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Write a note or update"></textarea>
                    <button type="submit" class="btn border border-gray-300 bg-white text-gray-700 w-full">Add Note</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
