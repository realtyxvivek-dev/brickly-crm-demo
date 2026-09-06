@extends('layouts.app')

@section('title', ($rule ? 'Edit' : 'Create') . ' Meta Automation - ' . brand_name())
@section('page-title', $rule ? 'Edit Meta Automation' : 'Create Meta Automation')

@section('content')
@php
    $selectedSource = old('source_type', $rule->source_type ?? $prefill['source_type'] ?? 'facebook_lead_ads');
    $selectedFbFormIds = collect(old('fb_form_ids', null) ?? [])
        ->merge($rule && $rule->relationLoaded('fbForms') ? $rule->fbForms->pluck('id')->all() : [])
        ->merge($rule?->fb_form_id ? [$rule->fb_form_id] : [])
        ->merge($selectedSource === 'facebook_lead_ads' && $rule?->source_id && is_numeric($rule->source_id) ? [$rule->source_id] : [])
        ->merge($selectedSource === 'facebook_lead_ads' && !empty($prefill['source_id']) ? [$prefill['source_id']] : [])
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $method = old('assignment_method', $rule->assignment_method ?? 'round_robin');
    $ruleUsers = $rule ? $rule->users->keyBy('user_id') : collect();
@endphp
<div class="max-w-5xl mx-auto">
    <form method="POST" action="{{ $rule ? route('ad-manager.automation.update', $rule) : route('ad-manager.automation.store') }}" class="space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if($rule)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
            <div class="mb-3 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-xs font-black text-white">1</span>
                <div>
                    <h2 class="text-sm font-black text-slate-900">Automation Name</h2>
                    <p class="text-xs text-slate-500">Is rule ko team/campaign ke naam se identify karo.</p>
                </div>
            </div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Rule Name</label>
            <input type="text" name="name" value="{{ old('name', $rule->name ?? '') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Residential Lead Distribution">
        </section>

        <section class="rounded-lg border border-slate-200 p-4">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-xs font-black text-white">2</span>
                <div>
                    <h2 class="text-sm font-black text-slate-900">Lead Distribution Type</h2>
                    <p class="text-xs text-slate-500">Lead users me kis method se jayegi.</p>
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Assignment Method</label>
                    <select name="assignment_method" id="assignmentMethod" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="round_robin" @selected($method === 'round_robin')>Round Robin</option>
                        <option value="first_available" @selected($method === 'first_available')>First Available</option>
                        <option value="percentage" @selected($method === 'percentage')>Percentage</option>
                        <option value="single_user" @selected($method === 'single_user')>Single User</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Source</label>
                    <select name="source_type" id="sourceType" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="facebook_lead_ads" @selected($selectedSource === 'facebook_lead_ads')>Facebook Lead Ads Form</option>
                        <option value="meta" @selected($selectedSource === 'meta')>Meta Leads</option>
                        <option value="meta_awareness" @selected($selectedSource === 'meta_awareness')>Meta Awareness</option>
                        <option value="whatsapp" @selected($selectedSource === 'whatsapp')>WhatsApp</option>
                        <option value="instagram" @selected($selectedSource === 'instagram')>Instagram</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 p-4">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-xs font-black text-white">3</span>
                <div>
                    <h2 class="text-sm font-black text-slate-900">Distribution Users</h2>
                    <p class="text-xs text-slate-500">Lead kin users me distribute hogi.</p>
                </div>
            </div>

            <div id="singleUserBlock">
                <label class="mb-1 block text-sm font-semibold text-slate-700">Single User</label>
                <select name="single_user_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Select user</option>
                    @foreach($assignableUsers as $user)
                        <option value="{{ $user->id }}" @selected((int) old('single_user_id', $rule->single_user_id ?? 0) === $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div id="multiUserBlock">
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach($assignableUsers as $index => $user)
                        @php($existing = $ruleUsers->get($user->id))
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <input type="checkbox" name="users[{{ $index }}][user_id]" value="{{ $user->id }}" @checked(old("users.$index.user_id", $existing?->user_id)) class="rounded border-slate-300">
                            <span class="min-w-0 flex-1 text-sm font-semibold text-slate-700">{{ $user->name }}</span>
                            <input type="number" step="0.01" name="users[{{ $index }}][percentage]" value="{{ old("users.$index.percentage", $existing?->percentage) }}" class="w-20 rounded-lg border-slate-300 text-xs" placeholder="%">
                            <input type="number" name="users[{{ $index }}][daily_limit]" value="{{ old("users.$index.daily_limit", $existing?->daily_limit) }}" class="w-24 rounded-lg border-slate-300 text-xs" placeholder="Limit">
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="fbFormBlock" class="rounded-lg border border-slate-200 p-4">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-xs font-black text-white">4</span>
                <div>
                    <h2 class="text-sm font-black text-slate-900">Facebook Forms</h2>
                    <p class="text-xs text-slate-500">Kin forms se aane wali lead is automation me jayegi.</p>
                </div>
            </div>
            <select name="fb_form_ids[]" id="fbFormSelect" multiple size="8" class="w-full rounded-lg border border-slate-300 bg-white p-2 text-sm leading-6 text-slate-800 focus:border-slate-500 focus:ring-slate-500">
                @foreach($fbForms as $form)
                    <option value="{{ $form->id }}" @selected(in_array((string) $form->id, $selectedFbFormIds, true))>
                        {{ $form->form_name ?: $form->form_id }} @if($form->page) - {{ $form->page->page_name }} @endif
                    </option>
                @endforeach
            </select>
            <p class="mt-2 text-xs text-slate-500">Ctrl/Cmd hold karke multiple forms select karo. Selected forms ki new leads isi rule se assign hongi.</p>
            <div id="selectedFormsList" class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-600"></div>
        </section>

        <section class="rounded-lg border border-slate-200 p-4">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-xs font-black text-white">5</span>
                <div>
                    <h2 class="text-sm font-black text-slate-900">Rules & Limits</h2>
                    <p class="text-xs text-slate-500">Fallback, limits, task aur notification behavior.</p>
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Fallback User</label>
                    <select name="fallback_user_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">None</option>
                        @foreach($assignableUsers as $user)
                            <option value="{{ $user->id }}" @selected((int) old('fallback_user_id', $rule->fallback_user_id ?? 0) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Daily Limit</label>
                    <input type="number" name="daily_limit" value="{{ old('daily_limit', $rule->daily_limit ?? '') }}" class="w-full rounded-lg border-slate-300 text-sm" min="1">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Source Label</label>
                    <input type="text" name="source_label" value="{{ old('source_label', $rule->source_label ?? $prefill['source_label'] ?? '') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Campaign or form label">
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-4">
                <label class="flex items-center gap-2 rounded-lg bg-slate-50 p-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="task_enabled" value="1" @checked(old('task_enabled', $rule?->taskEnabled() ?? true)) class="rounded border-slate-300"> Create task</label>
                <label class="flex items-center gap-2 rounded-lg bg-slate-50 p-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="notification_enabled" value="1" @checked(old('notification_enabled', $rule?->notificationEnabled() ?? true)) class="rounded border-slate-300"> Notify user</label>
                <label class="flex items-center gap-2 rounded-lg bg-slate-50 p-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="skip_lead_off_users" value="1" @checked(old('skip_lead_off_users', $rule->skip_lead_off_users ?? true)) class="rounded border-slate-300"> Skip off users</label>
                <label class="flex items-center gap-2 rounded-lg bg-slate-50 p-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->is_active ?? true)) class="rounded border-slate-300"> Active</label>
            </div>
        </section>

        <input type="hidden" name="source" id="sourceValue" value="{{ old('source', $rule->source ?? $selectedSource) }}">
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
            <a href="{{ route('ad-manager.automation.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save Rule</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
const sourceType = document.getElementById('sourceType');
const sourceValue = document.getElementById('sourceValue');
const fbFormBlock = document.getElementById('fbFormBlock');
const fbFormSelect = document.getElementById('fbFormSelect');
const selectedFormsList = document.getElementById('selectedFormsList');
const assignmentMethod = document.getElementById('assignmentMethod');
const singleUserBlock = document.getElementById('singleUserBlock');
const multiUserBlock = document.getElementById('multiUserBlock');

function syncSourceUi() {
    sourceValue.value = sourceType.value;
    fbFormBlock.style.display = sourceType.value === 'facebook_lead_ads' ? 'block' : 'none';
}

function syncAssignmentUi() {
    const single = assignmentMethod.value === 'single_user';
    singleUserBlock.style.display = single ? 'block' : 'none';
    multiUserBlock.style.display = single ? 'none' : 'block';
}

function syncSelectedForms() {
    if (!selectedFormsList || !fbFormSelect) return;

    const selected = Array.from(fbFormSelect.selectedOptions);
    selectedFormsList.innerHTML = '';

    if (selected.length === 0) {
        selectedFormsList.innerHTML = '<span class="rounded-full bg-slate-100 px-3 py-1 text-slate-500">No forms selected</span>';
        return;
    }

    selected.forEach((option) => {
        const item = document.createElement('span');
        item.className = 'rounded-full bg-slate-100 px-3 py-1 text-slate-700';
        item.textContent = option.textContent.trim();
        selectedFormsList.appendChild(item);
    });
}

sourceType.addEventListener('change', syncSourceUi);
assignmentMethod.addEventListener('change', syncAssignmentUi);
fbFormSelect?.addEventListener('change', syncSelectedForms);
syncSourceUi();
syncAssignmentUi();
syncSelectedForms();
</script>
@endpush
@endsection
