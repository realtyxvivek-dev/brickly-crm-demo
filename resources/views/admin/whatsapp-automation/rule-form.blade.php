@extends('layouts.app')

@section('title', $rule->exists ? 'Edit Rule' : 'Create Rule')
@section('page-title', $rule->exists ? 'Edit Rule' : 'Create Rule')

@section('content')
<div class="max-w-5xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
    <form method="POST" action="{{ $rule->exists ? route($routeBase . '.rules.update', $rule) : route($routeBase . '.rules.store') }}" class="space-y-6" id="wa-rule-form">
        @csrf
        @if($rule->exists)
            @method('PUT')
        @endif
        <div class="grid md:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Rule name</label><input type="text" name="name" value="{{ old('name', $rule->name) }}" class="w-full rounded-xl border-slate-300" required></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Journey</label><select name="journey_id" class="w-full rounded-xl border-slate-300" required>@foreach($journeys as $journey)<option value="{{ $journey->id }}" @selected((int) old('journey_id', request('journey_id', $rule->journey_id)) === $journey->id)>{{ $journey->name }}</option>@endforeach</select></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-2">When to send</label><select name="trigger" class="w-full rounded-xl border-slate-300" required>@foreach($availableTriggers as $triggerValue => $triggerLabel)<option value="{{ $triggerValue }}" @selected(old('trigger', $rule->trigger) === $triggerValue)>{{ $triggerLabel }}</option>@endforeach</select></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Which template to send</label><select name="template_id" class="w-full rounded-xl border-slate-300" id="template_id"><option value="">Use journey default</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected((int) old('template_id', $rule->template_id) === $template->id)>{{ $template->name }}</option>@endforeach</select></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Send from API number</label><select name="meta_waba_account_id" class="w-full rounded-xl border-slate-300"><option value="">Use routing/default</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((int) old('meta_waba_account_id', $rule->meta_waba_account_id) === $account->id)>{{ $account->display_phone_number ?: $account->name }}</option>@endforeach</select></div>
        </div>
        <div class="grid md:grid-cols-4 gap-4">
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Priority</label><input type="number" name="priority" value="{{ old('priority', $rule->priority ?: 100) }}" min="1" max="999" class="w-full rounded-xl border-slate-300"></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Status</label><select name="status" class="w-full rounded-xl border-slate-300">@foreach(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused'] as $statusValue => $statusLabel)<option value="{{ $statusValue }}" @selected(old('status', $rule->status ?: 'draft') === $statusValue)>{{ $statusLabel }}</option>@endforeach</select></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Resend cap</label><input type="number" name="resend_cap" value="{{ old('resend_cap', $rule->resend_cap ?: 1) }}" min="1" max="25" class="w-full rounded-xl border-slate-300"></div>
            <div class="flex items-end gap-4"><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->is_active))> Active</label><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="test_mode" value="1" @checked(old('test_mode', $rule->test_mode))> Test mode</label><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="once_per_lead" value="1" @checked(old('once_per_lead', $rule->once_per_lead ?? true))> Send only once</label></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Cooldown minutes</label><input type="number" name="cooldown_minutes" value="{{ old('cooldown_minutes', $rule->cooldown_minutes ?? 0) }}" min="0" max="10080" class="w-full rounded-xl border-slate-300"></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Daily send cap</label><input type="number" name="daily_send_cap" value="{{ old('daily_send_cap', $rule->daily_send_cap ?? 3) }}" min="1" max="50" class="w-full rounded-xl border-slate-300"></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-2">Fallback action</label><input type="text" name="fallback_action" value="{{ old('fallback_action', $rule->fallback_action) }}" class="w-full rounded-xl border-slate-300" placeholder="manual_review"></div>
            <div class="flex items-end"><label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="requires_session_window" value="1" @checked(old('requires_session_window', $rule->requires_session_window))> Requires 24h session</label></div>
        </div>
        <div class="grid md:grid-cols-2 gap-6">
            <div class="border border-slate-200 rounded-2xl p-4">
                <h3 class="text-base font-semibold text-slate-900 mb-3">Who should receive this</h3>
                @php($sourceMode = old('conditions.source_mode', data_get($rule->conditions, 'source_mode')))
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Source rule</label>
                        <select name="conditions[source_mode]" class="w-full rounded-xl border-slate-300"><option value="">Default fallback</option><option value="exact" @selected($sourceMode === 'exact')>Source is exactly</option><option value="in_list" @selected($sourceMode === 'in_list')>Source is in list</option><option value="except" @selected($sourceMode === 'except')>Source is anything except</option></select>
                        <div class="grid grid-cols-2 gap-2 mt-2">@foreach($availableSources as $source)<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="conditions[source_values][]" value="{{ $source }}" @checked(in_array($source, old('conditions.source_values', data_get($rule->conditions, 'source_values', []))))>{{ \App\Models\Lead::displaySourceLabel($source) }}</label>@endforeach</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Project filter</label>
                        <div class="max-h-36 overflow-auto border border-slate-200 rounded-xl p-3">@foreach($availableProjects as $project)<label class="flex items-center gap-2 text-sm text-slate-700 mb-2"><input type="checkbox" name="conditions[project_ids][]" value="{{ $project->id }}" @checked(in_array($project->id, old('conditions.project_ids', data_get($rule->conditions, 'project_ids', []))))>{{ $project->name }}</label>@endforeach</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Do not send if lead is</label>
                        <div class="grid grid-cols-2 gap-2">@foreach(['dead', 'closed', 'junk', 'not_interested', 'booked', 'invalid'] as $status)<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="stop_statuses[]" value="{{ $status }}" @checked(in_array($status, old('stop_statuses', $rule->stop_statuses ?? [])))>{{ ucwords(str_replace('_', ' ', $status)) }}</label>@endforeach</div>
                    </div>
                </div>
            </div>
            <div class="border border-slate-200 rounded-2xl p-4">
                <h3 class="text-base font-semibold text-slate-900 mb-3">Send immediately or later</h3>
                @php($timing = old('send_timing', $rule->send_timing ?? ['mode' => 'immediate']))
                @php($quiet = old('quiet_hour_policy', $rule->quiet_hour_policy ?? \App\Services\WhatsAppAutomationService::DEFAULT_QUIET_POLICY))
                <div class="space-y-4">
                    <div class="grid md:grid-cols-3 gap-3"><select name="send_timing[mode]" class="rounded-xl border-slate-300"><option value="immediate" @selected(($timing['mode'] ?? 'immediate') === 'immediate')>Immediate</option><option value="delay" @selected(($timing['mode'] ?? null) === 'delay')>Relative delay</option></select><input type="number" name="send_timing[value]" min="0" value="{{ $timing['value'] ?? 0 }}" class="rounded-xl border-slate-300" placeholder="Delay value"><select name="send_timing[unit]" class="rounded-xl border-slate-300">@foreach(['minutes' => 'Minutes', 'hours' => 'Hours', 'days' => 'Days'] as $unitValue => $unitLabel)<option value="{{ $unitValue }}" @selected(($timing['unit'] ?? 'minutes') === $unitValue)>{{ $unitLabel }}</option>@endforeach</select></div>
                    <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="quiet_hour_policy[enabled]" value="1" @checked((bool) ($quiet['enabled'] ?? false))> Quiet hours / allowed send window</label>
                    <div class="grid md:grid-cols-2 gap-3"><div><label class="block text-sm font-medium text-slate-700 mb-2">Start</label><input type="time" name="quiet_hour_policy[start]" value="{{ $quiet['start'] ?? '09:00' }}" class="w-full rounded-xl border-slate-300"></div><div><label class="block text-sm font-medium text-slate-700 mb-2">End</label><input type="time" name="quiet_hour_policy[end]" value="{{ $quiet['end'] ?? '19:00' }}" class="w-full rounded-xl border-slate-300"></div></div>
                </div>
            </div>
        </div>
        <div class="border border-slate-200 rounded-2xl p-4">
            <div class="flex items-center justify-between gap-4 mb-3"><div><h3 class="text-base font-semibold text-slate-900">Template field mapping</h3><p class="text-sm text-slate-500">Map template fields to CRM values.</p></div><button type="button" class="px-4 py-2 rounded-xl border border-slate-300 text-sm font-medium" id="preview-template-btn">Preview</button></div>
            <div class="grid md:grid-cols-4 gap-3">@for($slot = 1; $slot <= 6; $slot++)<div><label class="block text-sm font-medium text-slate-700 mb-2">Template field {{ $slot }}</label><select name="variable_map[{{ $slot }}]" class="w-full rounded-xl border-slate-300"><option value="">Not used</option>@foreach($variableCatalog as $fieldKey => $fieldLabel)<option value="{{ $fieldKey }}" @selected(old("variable_map.$slot", data_get($rule->variable_map, (string) $slot)) === $fieldKey)>{{ $fieldLabel }}</option>@endforeach</select></div>@endfor</div>
            <div class="mt-4 bg-slate-50 border border-slate-200 rounded-2xl p-4"><div class="text-sm font-semibold text-slate-900 mb-2">Preview</div><pre id="template-preview" class="whitespace-pre-wrap text-sm text-slate-700">Select a template and click Preview.</pre></div>
        </div>
        <div class="flex gap-3"><button type="submit" class="px-5 py-3 rounded-xl bg-teal-700 text-white font-semibold">Save rule</button><a href="{{ route($routeBase . '.index', ['tab' => 'rules']) }}" class="px-5 py-3 rounded-xl border border-slate-300 text-slate-700 font-semibold">Back</a></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('preview-template-btn')?.addEventListener('click', async function () {
    const form = document.getElementById('wa-rule-form');
    const data = new FormData(form);
    try {
        const response = await fetch('{{ route($routeBase . '.rules.preview') }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,'Accept': 'application/json'},
            body: data,
        });
        const payload = await response.json();
        const lines = [];
        if (payload.content) lines.push(payload.content);
        if (payload.errors && payload.errors.length) {
            lines.push('');
            lines.push('Missing values:');
            payload.errors.forEach((error) => lines.push('- ' + error));
        }
        document.getElementById('template-preview').textContent = lines.join("\n") || 'No preview available.';
    } catch (error) {
        document.getElementById('template-preview').textContent = 'Preview failed.';
    }
});
</script>
@endpush
