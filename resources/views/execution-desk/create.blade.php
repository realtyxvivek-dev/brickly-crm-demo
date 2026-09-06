@extends(auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isSalesManager()) ? 'sales-manager.layout' : 'layouts.app')

@section('title', 'Create Execution Task')

@section('content')
@php($isMarketingUser = auth()->user()?->isMarketingUser())
<div class="container mx-auto px-4 py-6">
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc pl-5 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-brand-primary">Create Execution Task</h1>
            @unless($isMarketingUser)
                <p class="text-sm text-gray-500">Assign internal work to any active user.</p>
            @endunless
        </div>
        <a href="{{ route('execution-desk.index') }}" class="btn border border-gray-300 bg-white text-gray-700">Back</a>
    </div>

    <form method="POST" action="{{ route('execution-desk.tasks.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2" required>
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('description') }}</textarea>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Assign To</label>
                @if(auth()->user()?->isMarketingExecutive() && $users->count() === 1)
                    <input type="hidden" name="assigned_to" value="{{ $users->first()->id }}">
                    <input type="text" value="{{ $users->first()->name }}" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2" disabled>
                @else
                    <select name="assigned_to" class="w-full rounded-lg border border-gray-300 px-3 py-2" required>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string) old('assigned_to') === (string) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Priority</label>
                <select name="priority" class="w-full rounded-lg border border-gray-300 px-3 py-2" required>
                    @foreach($priorities as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Due Date & Time (Optional)</label>
                <input type="datetime-local" name="due_at" value="{{ old('due_at') }}" placeholder="Select date and time" class="w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Estimated Time (Minutes)</label>
                <input type="number" min="0" name="estimated_time_minutes" value="{{ old('estimated_time_minutes') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">Context</label>
                <select name="context_label" class="w-full rounded-lg border border-gray-300 px-3 py-2" required>
                    @foreach($contexts as $key => $label)
                        <option value="{{ $key }}" @selected(old('context_label') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-3 pt-7">
                <input type="hidden" name="is_private" value="0">
                <input type="checkbox" name="is_private" value="1" id="is_private" class="h-4 w-4 rounded border-gray-300 text-green-700" @checked(old('is_private'))>
                <label for="is_private" class="text-sm text-gray-700">Private task</label>
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700">Checklist (Optional)</label>
                <div id="checklist-items" class="space-y-2">
                    @php($oldChecklist = old('checklist', ['', '', '']))
                    @foreach($oldChecklist as $index => $item)
                        <input type="text" name="checklist[]" value="{{ $item }}" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Checklist item {{ $index + 1 }}">
                    @endforeach
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700">Attachments (Optional)</label>
                <input type="file" name="attachments[]" multiple class="w-full rounded-lg border border-gray-300 px-3 py-2">
                @unless($isMarketingUser)
                    <p class="mt-2 text-xs text-gray-500">Upload one or more files. Max 10 MB per file.</p>
                @endunless
            </div>
            <div class="md:col-span-2">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <label class="block text-sm font-medium text-gray-700">Links (Optional)</label>
                    <button type="button" id="add-link-row" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700">Add Link</button>
                </div>
                @php($oldLinks = old('attachment_links', [['title' => '', 'url' => '']]))
                <div id="attachment-link-list" class="space-y-3">
                    @foreach($oldLinks as $index => $link)
                        <div class="attachment-link-row grid gap-3 md:grid-cols-[1fr,1.3fr,auto]">
                            <input type="text" name="attachment_links[{{ $index }}][title]" value="{{ $link['title'] ?? '' }}" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Link title (optional)">
                            <input type="url" name="attachment_links[{{ $index }}][url]" value="{{ $link['url'] ?? '' }}" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="https://example.com/resource">
                            <button type="button" class="remove-link-row rounded-lg border border-red-200 px-3 py-2 text-xs font-medium text-red-600">Remove</button>
                        </div>
                    @endforeach
                </div>
                @unless($isMarketingUser)
                    <p class="mt-2 text-xs text-gray-500">Add reference URLs, docs, drive links, or other helpful resources.</p>
                @endunless
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn btn-brand-primary">Create Task</button>
            <a href="{{ route('execution-desk.index') }}" class="btn border border-gray-300 bg-white text-gray-700">Cancel</a>
        </div>
    </form>
</div>

<template id="attachment-link-template">
    <div class="attachment-link-row grid gap-3 md:grid-cols-[1fr,1.3fr,auto]">
        <input type="text" data-name="title" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Link title (optional)">
        <input type="url" data-name="url" class="w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="https://example.com/resource">
        <button type="button" class="remove-link-row rounded-lg border border-red-200 px-3 py-2 text-xs font-medium text-red-600">Remove</button>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('attachment-link-list');
    const addButton = document.getElementById('add-link-row');
    const template = document.getElementById('attachment-link-template');

    if (!list || !addButton || !template) {
        return;
    }

    const wireNames = () => {
        list.querySelectorAll('.attachment-link-row').forEach((row, index) => {
            row.querySelectorAll('[data-name], input[name*="attachment_links"]').forEach((input) => {
                const key = input.dataset.name || (input.name.includes('[url]') ? 'url' : 'title');
                input.name = `attachment_links[${index}][${key}]`;
            });
        });
    };

    addButton.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        list.appendChild(fragment);
        wireNames();
    });

    list.addEventListener('click', (event) => {
        if (!event.target.classList.contains('remove-link-row')) {
            return;
        }

        const rows = list.querySelectorAll('.attachment-link-row');
        if (rows.length === 1) {
            rows[0].querySelectorAll('input').forEach((input) => input.value = '');
            return;
        }

        event.target.closest('.attachment-link-row')?.remove();
        wireNames();
    });

    wireNames();
});
</script>
@endsection
